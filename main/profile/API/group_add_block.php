<?php
// Group-scoped BLOCK add:
// - address-list: block_<group>_ip  (IPv4 / resolved A records)
// - filter: DROP tcp with tls-host + src-address-list=profile:<group>
// No DNS static here (to avoid global effects).

ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json');

function jexit(array $x, int $c=200){ http_response_code($c); echo json_encode($x, JSON_UNESCAPED_SLASHES); exit; }

/* ---- Includes ---- */
$APP_ROOT = dirname(__DIR__, 3);
$cli_path = $APP_ROOT.'/includes/routeros_client.php';
$cfg_path = $APP_ROOT.'/config/router.php';
$lv_path  = $APP_ROOT.'/loginverification.php';

if (!file_exists($cli_path)) jexit(['ok'=>false,'message'=>'RouterOS client missing'],500);
if (!file_exists($cfg_path)) jexit(['ok'=>false,'message'=>'Config file missing'],500);

require_once $cli_path;
$config = require $cfg_path;

/* ---- Auth ---- */
$providedApiKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
if ($providedApiKey === '') $providedApiKey = (string)($config['api_key'] ?? '');
$configApiKey   = trim((string)($config['api_key'] ?? getenv('BLOCKIT_API_KEY') ?? ''));

$authed = ($configApiKey !== '' && hash_equals($configApiKey, $providedApiKey));
if (!$authed && file_exists($lv_path)) { require_once $lv_path; if (function_exists('require_login')) { ob_start(); require_login(); ob_end_clean(); $authed = true; } }
if (!$authed && session_status()===PHP_SESSION_NONE) session_start();
if (!$authed && !empty($_SESSION['user_id'])) $authed = true;
if (!$authed) jexit(['ok'=>false,'message'=>'Not authenticated'],401);

/* ---- Input ---- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jexit(['ok'=>false,'message'=>'Invalid method; use POST'],405);
$raw = file_get_contents('php://input') ?: '';
if ($raw) { $j = json_decode($raw, true); if (json_last_error()===JSON_ERROR_NONE && is_array($j)) $_POST = $j + $_POST; }

$group   = strtolower(trim((string)($_POST['group'] ?? '')));
$input   = trim((string)($_POST['address'] ?? ''));
$comment = trim((string)($_POST['comment'] ?? ''));
if (!in_array($group,['over18','under18'],true)) jexit(['ok'=>false,'message'=>'group must be over18 or under18'],400);
if ($input==='') jexit(['ok'=>false,'message'=>'address required'],400);
if ($comment==='') $comment = 'From: '.$input;

/* ---- Helpers ---- */
function normalize_hostname($s): string {
  $s = strtolower(trim($s));
  $s = preg_replace('~^[a-z][a-z0-9+.\-]*://~i','',$s); // strip scheme
  $s = preg_replace('~[/\?#].*$~','',$s);               // strip path/query/frag
  return rtrim($s,'.');
}
function is_ip(string $s): bool { return (bool)filter_var($s, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4); }
function is_cidr(string $s): bool {
  if (!preg_match('~^(\d{1,3}\.){3}\d{1,3}/\d{1,2}$~', $s)) return false;
  [$ip,$mask] = explode('/', $s, 2);
  return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && (int)$mask>=0 && (int)$mask<=32;
}
function is_hostname_or_url(string $s): bool {
  $h = normalize_hostname($s);
  return $h !== '' && (bool)preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/',$h);
}
function resolve_ipv4s(string $host): array {
  $ips=[]; $recs = @dns_get_record($host, DNS_A);
  if (is_array($recs)) foreach ($recs as $r) if (!empty($r['ip'])) $ips[]=$r['ip'];
  if (!$ips) { $alt=@gethostbynamel($host); if (is_array($alt)) $ips=array_merge($ips,$alt); }
  $ips = array_values(array_unique($ips));
  return array_values(array_filter($ips, fn($ip)=>filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)));
}
function names_for(string $dom): array {
  $dom = normalize_hostname($dom);
  $names = [$dom, "www.$dom"];
  if (strpos($dom,'www.')===0){ $root=substr($dom,4); if ($root!=='') $names=array_values(array_unique([$dom,$root])); }
  return $names;
}

/* ---- Router ---- */
$routerHost=(string)($config['host']??'');
$apiPort   =(int)($config['api_port']??8729);
$useTls    =(bool)($config['api_tls']??true);
$user      =(string)($config['user']??'api-dashboard');
$pass      =(string)($config['pass']??'');
$timeout   =(int)($config['timeout']??8);
if ($routerHost==='' || $user==='') jexit(['ok'=>false,'message'=>'Router config incomplete'],500);

try { $api = new RouterOSClient($routerHost,$apiPort,$user,$pass,$timeout,$useTls); }
catch(Throwable $e){
  try { $api = new RouterOSClient($routerHost,8728,$user,$pass,$timeout,false); }
  catch(Throwable $e2){ jexit(['ok'=>false,'message'=>'Router connection failed'],502); }
}

/* ---- Prefetch snapshot for idempotency ---- */
try{
  $fwAddr = $api->talk('/ip/firewall/address-list/print', ['.proplist'=>'.id,list,address,comment']);
  $fwFlt  = $api->talk('/ip/firewall/filter/print', ['.proplist'=>'.id,chain,action,protocol,tls-host,src-address-list,comment']);
}catch(Throwable $e){
  if (method_exists($api,'close')) $api->close();
  jexit(['ok'=>false,'message'=>'Router read failed: '.$e->getMessage()],500);
}

/* ---- Small search helpers over snapshots ---- */
$listBlock = "block_{$group}_ip";
$srcList   = "profile:$group";

$addrExists = function(string $list, string $addr) use ($fwAddr): bool {
  foreach ($fwAddr as $r){
    if (strtolower($r['list']??'')===strtolower($list) && strtolower($r['address']??'')===strtolower($addr)) return true;
  }
  return false;
};
$dropExists = function(string $name, string $srcList) use ($fwFlt): bool {
  foreach ($fwFlt as $r){
    if (
      strtolower($r['chain']??'')==='forward' &&
      strtolower($r['action']??'')==='drop' &&
      strtolower($r['protocol']??'')==='tcp' &&
      strtolower($r['src-address-list']??'')===strtolower($srcList) &&
      strtolower($r['tls-host']??'')===strtolower($name)
    ) return true;
  }
  return false;
};

/* ---- Work ---- */
$added_ips = []; $skipped_ips = [];
$added_tls = []; $skipped_tls = [];
$debug = [];

try{
  // IP / CIDR direct -> address-list
  if (is_ip($input) || is_cidr($input)) {
    if (!$addrExists($listBlock, $input)) {
      $api->talk('/ip/firewall/address-list/add', [
        'list'    => $listBlock,
        'address' => $input,
        'comment' => "group:$group ".($comment ?: '')
      ]);
      $added_ips[] = $input; $debug[] = "addrlist add $listBlock $input";
    } else { $skipped_ips[] = $input; $debug[] = "addrlist exists $listBlock $input"; }
  }

  // Hostname / URL
  if (is_hostname_or_url($input)) {
    $dom   = normalize_hostname($input);
    $names = names_for($dom);

    // Resolve both names, add every A to block_<group>_ip (idempotent)
    $ips = [];
    foreach ($names as $n) { $ips = array_merge($ips, resolve_ipv4s($n)); }
    $ips = array_values(array_unique($ips));
    foreach ($ips as $ip) {
      if (!$addrExists($listBlock, $ip)) {
        $api->talk('/ip/firewall/address-list/add', [
          'list'    => $listBlock,
          'address' => $ip,
          'comment' => "group:$group From: $dom"
        ]);
        $added_ips[] = $ip; $debug[] = "addrlist add $listBlock $ip (From $dom)";
      } else { $skipped_ips[]=$ip; $debug[] = "addrlist exists $listBlock $ip"; }
    }

    // TLS drop per name for this group (idempotent)
    foreach ($names as $n) {
      if (!$dropExists($n,$srcList)) {
        $api->talk('/ip/firewall/filter/add', [
          'chain'            => 'forward',
          'action'           => 'drop',
          'protocol'         => 'tcp',
          'src-address-list' => $srcList,
          'tls-host'         => $n,
          'comment'          => "group:$group From: $dom"
        ]);
        $added_tls[] = $n; $debug[] = "tls drop add name=$n src=$srcList";
      } else { $skipped_tls[]=$n; $debug[]="tls drop exists name=$n src=$srcList"; }
    }
  }

  if (method_exists($api,'close')) $api->close();

  jexit([
    'ok' => true,
    'message' => 'Blocked (group-scoped)',
    'group' => $group,
    'input' => $input,
    'added_ips'   => $added_ips,
    'skipped_ips' => $skipped_ips,
    'added_tls'   => $added_tls,
    'skipped_tls' => $skipped_tls,
    'debug'       => $debug
  ]);

}catch(Throwable $e){
  if (method_exists($api,'close')) $api->close();
  jexit(['ok'=>false,'message'=>'Router op failed: '.$e->getMessage()],500);
}
