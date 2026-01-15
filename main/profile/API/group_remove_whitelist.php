<?php
// /public_html/main/profile/API/group_remove_whitelist.php
// Remove group-based whitelist artifacts:
// - /ip/firewall/address-list list=whitelist_<group>_ip  (IPs/CIDRs)
// - /ip/firewall/filter ACCEPT rules with tls-host + src-address-list=profile:<group>
// No DNS static is ever touched for whitelist (by design).

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

function jexit(array $x, int $c=200){ http_response_code($c); echo json_encode($x, JSON_UNESCAPED_SLASHES); exit; }

/* ---- Includes ---- */
$APP_ROOT = dirname(__DIR__, 3);
$cli_path = $APP_ROOT . '/includes/routeros_client.php';
$cfg_path = $APP_ROOT . '/config/router.php';
$lv_path  = $APP_ROOT . '/loginverification.php';

if (!file_exists($cli_path)) jexit(['ok'=>false,'message'=>'RouterOS client missing'],500);
if (!file_exists($cfg_path)) jexit(['ok'=>false,'message'=>'Config file missing'],500);

require_once $cli_path;
$config = require $cfg_path;

/* ---- Auth ---- */
$providedApiKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
if ($providedApiKey === '') $providedApiKey = (string)($config['api_key'] ?? '');
$configApiKey   = trim((string)($config['api_key'] ?? getenv('BLOCKIT_API_KEY') ?? ''));

$authed = ($configApiKey !== '' && hash_equals($configApiKey, $providedApiKey));
if (!$authed && file_exists($lv_path)) {
  require_once $lv_path;
  if (function_exists('require_login')) { ob_start(); require_login(); ob_end_clean(); $authed = true; }
}
if (!$authed && session_status() === PHP_SESSION_NONE) session_start();
if (!$authed && !empty($_SESSION['user_id'])) $authed = true;
if (!$authed) jexit(['ok'=>false,'message'=>'Not authenticated'],401);

/* ---- Input ---- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jexit(['ok'=>false,'message'=>'Invalid method; use POST'],405);

$raw = file_get_contents('php://input') ?: '';
if ($raw) { $j = json_decode($raw,true); if (json_last_error()===JSON_ERROR_NONE && is_array($j)) $_POST = $j + $_POST; }

$group   = strtolower(trim((string)($_POST['group'] ?? '')));
$id      = trim((string)($_POST['id'] ?? ''));         // optional
$address = trim((string)($_POST['address'] ?? ''));    // optional

if (!in_array($group,['over18','under18'],true)) jexit(['ok'=>false,'message'=>'group must be over18 or under18'],400);
if ($id==='' && $address==='') jexit(['ok'=>false,'message'=>'Provide id or address'],400);

/* ---- Helpers ---- */
function normalize_hostname($s): string {
  $s = strtolower(trim($s));
  // strip scheme
  $s = preg_replace('~^[a-z][a-z0-9+.\-]*://~i','',$s);
  // keep only host
  $s = preg_replace('~[/\?#].*$~','',$s);
  return rtrim($s,'.');
}
function is_ip(string $s): bool { return (bool)filter_var($s,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4); }
function is_cidr(string $s): bool {
  if (!preg_match('~^(\d{1,3}\.){3}\d{1,3}/\d{1,2}$~',$s)) return false;
  [$ip,$mask] = explode('/',$s,2);
  return filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4) && (int)$mask>=0 && (int)$mask<=32;
}
function is_hostname_or_url(string $s): bool {
  $h = normalize_hostname($s);
  return $h !== '' && (bool)preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/',$h);
}
function resolve_ipv4s(string $host): array {
  $ips=[];
  $recs=@dns_get_record($host,DNS_A);
  if(is_array($recs)) foreach($recs as $r) if(!empty($r['ip'])) $ips[]=$r['ip'];
  if(!$ips){ $alt=@gethostbynamel($host); if(is_array($alt)) $ips=array_merge($ips,$alt); }
  return array_values(array_unique(array_filter($ips,function($ip){
    return filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4);
  })));
}
function names_for(string $dom): array {
  $dom = normalize_hostname($dom);
  $names = [$dom,"www.$dom"];
  if (strpos($dom,'www.')===0) {
    $root = substr($dom,4);
    if ($root!=='') $names = array_values(array_unique([$dom,$root]));
  }
  return $names;
}

/* ---- Router ---- */
$routerHost=(string)($config['host']??'');
$apiPort   =(int)($config['api_port']??8729);
$useTls    =(bool)($config['api_tls']??true);
$user      =(string)($config['user']??'api-dashboard');
$pass      =(string)($config['pass']??'');
$timeout   =(int)($config['timeout']??8);

if($routerHost===''||$user==='') jexit(['ok'=>false,'message'=>'Router config incomplete'],500);

try{ $api=new RouterOSClient($routerHost,$apiPort,$user,$pass,$timeout,$useTls); }
catch(Throwable $e){
  try{ $api=new RouterOSClient($routerHost,8728,$user,$pass,$timeout,false); }
  catch(Throwable $e2){ jexit(['ok'=>false,'message'=>'Router connection failed'],502); }
}

/* ---- Prefetch ---- */
try{
  $fwAddr=$api->talk('/ip/firewall/address-list/print',['.proplist'=>'.id,list,address,comment']);
  $fwFlt =$api->talk('/ip/firewall/filter/print',['.proplist'=>'.id,chain,action,protocol,tls-host,src-address-list,comment']);
}catch(Throwable $e){
  if(method_exists($api,'close')) $api->close();
  jexit(['ok'=>false,'message'=>'Router read failed: '.$e->getMessage()],500);
}

/* ---- Work vars ---- */
$listName = "whitelist_{$group}_ip";
$srcList  = "profile:$group";
$removed_ips = [];
$removed_tls_accept = [];
$debug = [];

/* ---- Internal removal helpers ---- */
$removeAddrById = function($rowId) use ($api,&$removed_ips,&$debug,$fwAddr,$listName){
  foreach ($fwAddr as $r) {
    // READ using 'id' (normalized by your client), not '.id'
    if (($r['id'] ?? '') === $rowId && strtolower($r['list'] ?? '') === strtolower($listName)) {
      $val = $r['address'] ?? '';
      // SEND '.id' back to RouterOS (correct)
      $api->talk('/ip/firewall/address-list/remove', ['.id' => $rowId]);
      if ($val !== '') $removed_ips[] = $val;
      $debug[] = "AddrList removed .id=$rowId ($val)";
      return $r; // return removed row for possible comment parsing
    }
  }
  return null;
};

$removeAddrByValue = function($value) use ($api,&$removed_ips,&$debug,$fwAddr,$listName){
  $removedAny = false;
  foreach ($fwAddr as $r) {
    if (strtolower($r['list'] ?? '') === strtolower($listName) &&
        strtolower($r['address'] ?? '') === strtolower($value)) {
      // READ 'id', SEND '.id'
      $api->talk('/ip/firewall/address-list/remove', ['.id' => $r['id']]);
      $removed_ips[] = $r['address'];
      $removedAny = true;
      $debug[] = "AddrList removed value=" . $r['address'];
    }
  }
  return $removedAny;
};

$removeTlsForNames = function(array $names, string $srcList) use ($api,&$removed_tls_accept,&$debug,$fwFlt){
  foreach ($fwFlt as $r) {
    if (strtolower($r['chain'] ?? '') === 'forward' &&
        strtolower($r['action'] ?? '') === 'accept' &&
        strtolower($r['protocol'] ?? '') === 'tcp' &&
        strtolower($r['src-address-list'] ?? '') === strtolower($srcList)) {
      $tls = strtolower($r['tls-host'] ?? '');
      if ($tls !== '' && in_array($tls, array_map('strtolower',$names), true)) {
        // READ 'id', SEND '.id'
        $api->talk('/ip/firewall/filter/remove', ['.id' => $r['id']]);
        $removed_tls_accept[] = $r['tls-host'];
        $debug[] = "TLS accept removed tls-host=".$r['tls-host']." src=".$srcList;
      }
    }
  }
};

/* ---- Main logic ---- */
try{
  $domainToPurge = null;

  // 1) If id is present: remove that addrlist row first and try to infer domain from comment.
  if ($id !== '') {
    $row = $removeAddrById($id);
    if ($row && !empty($row['comment'])) {
      if (preg_match('~From:\s*([^\s]+)~i', (string)$row['comment'], $m)) {
        $domainToPurge = normalize_hostname($m[1]);
        $debug[] = "Inferred domain from comment: ".$domainToPurge;
      }
    }
  }

  // 2) If address is provided, figure what it is and remove accordingly.
  if ($address !== '') {
    if (is_ip($address) || is_cidr($address)) {
      $removeAddrByValue($address);
    } elseif (is_hostname_or_url($address)) {
      $domainToPurge = normalize_hostname($address);
    }
  }

  // 3) If we have a domain to purge, remove TLS accept rules for this group and related IPs.
  if ($domainToPurge) {
    $names = names_for($domainToPurge);

    // Remove TLS accept rules for those names + group
    $removeTlsForNames($names, $srcList);

    // Remove IP entries that were previously added for this domain:
    //  a) entries whose comment mentions "From: <domain>"
    //  b) entries matching current A records for the domain variants
    $possibleIPs = [];
    foreach ($names as $nm) {
      $possibleIPs = array_merge($possibleIPs, resolve_ipv4s($nm));
    }
    $possibleIPs = array_values(array_unique($possibleIPs));

    foreach ($fwAddr as $r) {
      if (strtolower($r['list']??'')===strtolower($listName)) {
        $addr = $r['address'] ?? '';
        $cmt  = (string)($r['comment'] ?? '');
        $hasFrom = stripos($cmt, 'From:') !== false && stripos($cmt, $domainToPurge) !== false;

        if ($hasFrom || ($addr!=='' && in_array($addr, $possibleIPs, true))) {
          $api->talk('/ip/firewall/address-list/remove', ['.id'=>$r['.id']]);
          $removed_ips[] = $addr;
          $debug[] = "AddrList removed (domain purge) ".$addr;
        }
      }
    }
  }

  if (method_exists($api,'close')) $api->close();

  jexit([
    'ok'=>true,
    'message'=>'Whitelist rules removed',
    'group'=>$group,
    'removed_ips'=>$removed_ips,
    'removed_tls_accept'=>$removed_tls_accept,
    'debug'=>$debug
  ]);

}catch(Throwable $e){
  if (method_exists($api,'close')) $api->close();
  jexit(['ok'=>false,'message'=>'Router op failed: '.$e->getMessage()],500);
}
