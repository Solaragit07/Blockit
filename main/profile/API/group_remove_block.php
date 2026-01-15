<?php
// Remove a group-scoped block entry (IP + associated DNS/TLS)
// Input (JSON or form): { group, id?, address? }
// - group: "over18" | "under18"  (required)
// - id: RouterOS .id of the address-list entry (optional)
// - address: IPv4 to remove (optional; used when id is not given)

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

function jexit(array $x, int $code = 200){ http_response_code($code); echo json_encode($x, JSON_UNESCAPED_SLASHES); exit; }

// ---- config/includes ----
$APP_ROOT = dirname(__DIR__, 3);
$cli_path = $APP_ROOT . '/includes/routeros_client.php';
$cfg_path = $APP_ROOT . '/config/router.php';
$lv_path  = $APP_ROOT . '/loginverification.php';
if (!file_exists($cli_path) || !file_exists($cfg_path)) jexit(['ok'=>false,'message'=>'Server not configured'],500);

require_once $cli_path;
$config = require $cfg_path;

// ---- auth (reuse your style) ----
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

// ---- method/body ----
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST')
  jexit(['ok'=>false,'message'=>'Invalid method; use POST'],405);

$raw = file_get_contents('php://input') ?: '';
if ($raw) {
  $j = json_decode($raw, true);
  if (json_last_error() === JSON_ERROR_NONE && is_array($j)) $_POST = $j + $_POST;
}

$group   = strtolower(trim((string)($_POST['group'] ?? $_GET['group'] ?? '')));
$id      = trim((string)($_POST['id'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));

if ($group !== 'over18' && $group !== 'under18')
  jexit(['ok'=>false,'message'=>'group must be over18 or under18'],400);

$listIp = "block_{$group}_ip";

// ---- connect router ----
$routerHost = (string)($config['host'] ?? '');
$apiPort    = (int)($config['api_port'] ?? 8729);
$useTls     = (bool)($config['api_tls'] ?? true);
$user       = (string)($config['user'] ?? 'api-dashboard');
$pass       = (string)($config['pass'] ?? '');
$timeout    = (int)($config['timeout'] ?? 8);

if ($routerHost === '' || $user === '') jexit(['ok'=>false,'message'=>'Router config incomplete'],500);

try {
  $api = new RouterOSClient($routerHost, $apiPort, $user, $pass, $timeout, $useTls);
} catch (Throwable $e) {
  try { $api = new RouterOSClient($routerHost, 8728, $user, $pass, $timeout, false); }
  catch (Throwable $e2) { jexit(['ok'=>false,'message'=>'Router connection failed'],502); }
}

// ---- pulls ----
try {
  $fwAddr = $api->talk('/ip/firewall/address-list/print', ['.proplist'=>'.id,list,address,comment']);
  $dnsSta = $api->talk('/ip/dns/static/print',           ['.proplist'=>'.id,name,address,comment']);
  $fwFlt  = $api->talk('/ip/firewall/filter/print',      ['.proplist'=>'.id,chain,action,protocol,tls-host,comment']);
} catch (Throwable $e) {
  if (method_exists($api,'close')) $api->close();
  jexit(['ok'=>false,'message'=>'Router read failed: '.$e->getMessage()],500);
}

// ---- helpers ----
$removed_ips   = [];
$removed_dns   = [];
$removed_tls   = [];
$debug         = [];

$extractDomain = function(string $comment) {
  // Accept "group:over18 From: domain" OR "From: domain"
  if (preg_match('~From:\s*([^\s]+)~i', $comment, $m)) return strtolower(rtrim($m[1],'.'));
  return '';
};

$domainVariants = function(string $host) {
  $host = strtolower($host);
  if ($host === '') return [];
  if (str_starts_with($host, 'www.')) {
    $root = substr($host, 4);
    return array_values(array_unique([$host, $root]));
  }
  return array_values(array_unique([$host, "www.$host"]));
};

// ---- find targets ----
// 1) Prefer id if provided (exact match in block_<group>_ip)
$targets = []; // each: ['id'=>..., 'address'=>..., 'domain'=>...]
if ($id !== '') {
  foreach ($fwAddr as $r) {
    if (($r['id'] ?? '') === $id && strtolower($r['list'] ?? '') === $listIp) {
      $targets[] = [
        'id'      => $r['id'],
        'address' => $r['address'] ?? '',
        'domain'  => $extractDomain($r['comment'] ?? ''),
      ];
      break;
    }
  }
}

// 2) If no id match and address provided, match by address in block_<group>_ip
if (!$targets && $address !== '') {
  foreach ($fwAddr as $r) {
    if (strtolower($r['list'] ?? '') === $listIp && strtolower($r['address'] ?? '') === strtolower($address)) {
      $targets[] = [
        'id'      => $r['id'] ?? '',
        'address' => $r['address'] ?? '',
        'domain'  => $extractDomain($r['comment'] ?? ''),
      ];
    }
  }
}

if (!$targets) {
  if (method_exists($api,'close')) $api->close();
  jexit(['ok'=>true,'group'=>$group,'input'=>($address ?: $id),'message'=>'Nothing to remove','removed_ips'=>[],'removed_dns'=>[],'removed_tls'=>[],'debug'=>[]]);
}

// ---- perform removals ----
try {
  foreach ($targets as $t) {
    // A) remove IP rows from block_<group>_ip
    foreach ($fwAddr as $r) {
      if (strtolower($r['list'] ?? '') === $listIp &&
          strtolower($r['address'] ?? '') === strtolower($t['address'])) {
        $api->talk('/ip/firewall/address-list/remove', ['.id' => $r['id']]);
        $removed_ips[] = $t['address'];
        $debug[] = "address-list remove {$r['id']} ({$t['address']} in $listIp)";
      }
    }

    // B) if we know the domain, remove group-scoped DNS+TLS for both host and www.host
    $dom = $t['domain'];
    if ($dom !== '') {
      $names = $domainVariants($dom);

      // DNS static (only those with 127.0.0.1 and comment containing "group:<group>")
      foreach ($dnsSta as $d) {
        $nm = strtolower($d['name'] ?? '');
        $cm = strtolower($d['comment'] ?? '');
        if (in_array($nm, $names, true) &&
            ($d['address'] ?? '') === '127.0.0.1' &&
            str_contains($cm, "group:$group")) {
          $api->talk('/ip/dns/static/remove', ['.id' => $d['id']]);
          $removed_dns[] = $nm;
          $debug[] = "dns static remove {$d['id']} ($nm)";
        }
      }

      // TLS drop rules (forward, action=drop, protocol=tcp) with comment containing "group:<group>"
      foreach ($fwFlt as $f) {
        $cm = strtolower($f['comment'] ?? '');
        if (strtolower($f['chain'] ?? '') === 'forward' &&
            strtolower($f['action'] ?? '') === 'drop' &&
            strtolower($f['protocol'] ?? '') === 'tcp' &&
            in_array(strtolower($f['tls-host'] ?? ''), $names, true) &&
            str_contains($cm, "group:$group")) {
          $api->talk('/ip/firewall/filter/remove', ['.id' => $f['id']]);
          $removed_tls[] = strtolower($f['tls-host']);
          $debug[] = "firewall filter remove {$f['id']} (tls-host={$f['tls-host']})";
        }
      }
    }
  }

  if (method_exists($api,'close')) $api->close();

  jexit([
    'ok'            => true,
    'group'         => $group,
    'input'         => ($address ?: $id),
    'message'       => 'Removed',
    'removed_ips'   => array_values(array_unique($removed_ips)),
    'removed_dns'   => array_values(array_unique($removed_dns)),
    'removed_tls'   => array_values(array_unique($removed_tls)),
    'debug'         => $debug,
  ]);

} catch (Throwable $e) {
  if (method_exists($api,'close')) $api->close();
  jexit(['ok'=>false,'message'=>'Router operation failed: '.$e->getMessage()],500);
}
