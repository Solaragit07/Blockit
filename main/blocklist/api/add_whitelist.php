<?php
// /public_html/main/blocklist/api/add_whitelist.php
// Allows IP/CIDR/hostname on Mikrotik by:
// - /ip/firewall/address-list list=whitelist   (IPs & resolved A records)
// - /ip/firewall/filter ACCEPT with tls-host    (hostnames incl. www; protocol=tcp)
// Also removes conflicting block artifacts to ensure access works:
// - /ip/firewall/address-list list=blocklist (matching IPs)
// - /ip/dns/static sinkholes to 127.0.0.1 (matching hostnames)
// - /ip/firewall/filter DROP tls-host rules (matching hostnames)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

function jexit(array $x, int $c = 200){
  http_response_code($c);
  // Ensure absolutely no stray output (BOM, warnings, echoes)
  while (ob_get_level() > 0) { @ob_end_clean(); }
  echo json_encode($x, JSON_UNESCAPED_SLASHES);
  exit;
}
function logf(string $m){ @file_put_contents('/tmp/add_whitelist_debug.log', date('c')." ".$m."\n", FILE_APPEND); }

/* ---- Paths / Includes ---- */
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

/* ---- Method & Body ---- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jexit(['ok'=>false,'message'=>'Invalid method; use POST'],405);

$raw = file_get_contents('php://input') ?: '';
if ($raw) {
  $j = json_decode($raw, true);
  if (json_last_error() === JSON_ERROR_NONE && is_array($j)) $_POST = $j + $_POST;
}

$input   = trim((string)($_POST['address'] ?? ''));
$comment = trim((string)($_POST['comment'] ?? ''));
if ($input === '') jexit(['ok'=>false,'message'=>'address required'],400);
if ($comment === '') $comment = 'From: '.$input;

/* ---- Helpers ---- */
function normalize_hostname($s): string {
  if (!is_string($s)) return '';
  $s = strtolower(trim($s));
  $s = preg_replace('~^[a-z][a-z0-9+.\-]*://~i','',$s); // strip scheme
  $s = preg_replace('~[/\?#].*$~','',$s);               // strip path/query
  return rtrim($s,'.');
}
function is_ip(string $s): bool {
  return (bool)filter_var($s, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
}
function is_cidr(string $s): bool {
  if (!preg_match('~^(\d{1,3}\.){3}\d{1,3}/\d{1,2}$~', $s)) return false;
  [$ip,$mask] = explode('/', $s, 2);
  if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
  $m = (int)$mask; return $m>=0 && $m<=32;
}
function is_hostname(string $s): bool {
  $s = normalize_hostname($s);
  return $s !== '' && (bool)preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $s);
}

/**
 * Resolve a hostname to IPv4 addresses (A records), following at most a couple of CNAMEs.
 * Returns unique IPv4s excluding 127.0.0.1 and 0.0.0.0.
 */
function resolve_ipv4s(string $host): array {
  $host = normalize_hostname($host);
  $ips  = [];

  // Try dns_get_record first (A)
  $recs = @dns_get_record($host, DNS_A);
  if (is_array($recs)) {
    foreach ($recs as $r) { if (!empty($r['ip'])) $ips[] = $r['ip']; }
  }

  // Fallback to gethostbynamel
  if (!$ips) {
    $alt = @gethostbynamel($host);
    if (is_array($alt)) $ips = array_merge($ips, $alt);
  }

  // De-dup + filter out sinkhole IPs
  $ips = array_values(array_unique(array_filter($ips, function($ip){
    return $ip !== '127.0.0.1' && $ip !== '0.0.0.0' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
  })));

  return $ips;
}

/* ---- Router ---- */
$routerHost = (string)($config['host']     ?? '');
$apiPort    = (int)   ($config['api_port'] ?? 8729);
$useTls     = (bool)  ($config['api_tls']  ?? true);
$user       = (string)($config['user']     ?? 'api-dashboard');
$pass       = (string)($config['pass']     ?? '');
$timeout    = (int)   ($config['timeout']  ?? 8);

if ($routerHost === '' || $user === '') jexit(['ok'=>false,'message'=>'Router config incomplete'],500);

try {
  $api = new RouterOSClient($routerHost, $apiPort, $user, $pass, $timeout, $useTls);
} catch (Throwable $e) {
  try { $api = new RouterOSClient($routerHost, 8728, $user, $pass, $timeout, false); }
  catch (Throwable $e2) { jexit(['ok'=>false,'message'=>'Router connection failed'],502); }
}

/* ---- Work vars ---- */
$added_ips          = [];
$added_tls_accept   = [];
$removed_block_ips  = [];
$removed_sinkholes  = [];
$removed_tls_drops  = [];
$debug              = [];
$errors             = [];

/* ---- Fetch existing (for idempotency and cleanup) ---- */
try {
  $fwAddr = $api->talk('/ip/firewall/address-list/print', ['.proplist'=>'.id,list,address,comment']);
  $dnsSta = $api->talk('/ip/dns/static/print', ['.proplist'=>'.id,name,address,comment']);
  $fwFlt  = $api->talk('/ip/firewall/filter/print', ['.proplist'=>'.id,chain,action,protocol,tls-host,comment']);
} catch (Throwable $e) {
  if (method_exists($api,'close')) $api->close();
  jexit(['ok'=>false,'message'=>'Router read failed: '.$e->getMessage()],500);
}

/* ---- Add & Cleanup logic ---- */
try {
  // A) IP or CIDR -> whitelist (and remove from blocklist if present)
  if (is_ip($input) || is_cidr($input)) {
    $addr = $input;

    // Remove from blocklist if found
    foreach ($fwAddr as $r) {
      if (strtolower($r['list'] ?? '') === 'blocklist' &&
          strtolower($r['address'] ?? '') === strtolower($addr)) {
        try {
          $api->talk('/ip/firewall/address-list/remove', ['.id' => $r['.id']]);
          $removed_block_ips[] = $addr;
          $debug[] = "Removed from blocklist address=$addr";
        } catch (Throwable $e) {
          $errors[] = "Failed remove blocklist $addr: ".$e->getMessage();
        }
      }
    }

    // Ensure present in whitelist
    $exists = false;
    foreach ($fwAddr as $r) {
      if (strtolower($r['list'] ?? '') === 'whitelist' &&
          strtolower($r['address'] ?? '') === strtolower($addr)) { $exists = true; break; }
    }
    if (!$exists) {
      $api->talk('/ip/firewall/address-list/add', [
        'list'    => 'whitelist',
        'address' => $addr,
        'comment' => $comment
      ]);
      $added_ips[] = $addr;
      $debug[] = "FW add whitelist address=$addr";
    } else {
      $debug[] = "FW whitelist exists address=$addr";
    }
  }

  // B) Hostname/URL -> resolve -> add IPs to whitelist, add TLS ACCEPT, cleanup blockers
  if (is_hostname($input)) {
    $hn = normalize_hostname($input);

    // Hostname variants we act on
    $names = [$hn, "www.$hn"];
    if (str_starts_with($hn, 'www.')) {
      $root = substr($hn, 4);
      if ($root !== '') $names = array_unique([$hn, $root]);
    }

    // B1) Resolve to IPv4s first
    $resolved_ips = [];
    foreach ($names as $name) {
      $ips = resolve_ipv4s($name);
      if ($ips) {
        $debug[] = "Resolved $name => ".implode(', ', $ips);
        $resolved_ips = array_merge($resolved_ips, $ips);
      } else {
        $debug[] = "Resolved $name => (no A records)";
      }
    }
    $resolved_ips = array_values(array_unique($resolved_ips));

    // Cleanup: remove matching blocklist IPs
    foreach ($resolved_ips as $ip) {
      foreach ($fwAddr as $r) {
        if (strtolower($r['list'] ?? '') === 'blocklist' &&
            strtolower($r['address'] ?? '') === strtolower($ip)) {
          try {
            $api->talk('/ip/firewall/address-list/remove', ['.id' => $r['.id']]);
            $removed_block_ips[] = $ip;
            $debug[] = "Removed from blocklist ip=$ip (From $hn)";
          } catch (Throwable $e) {
            $errors[] = "Failed remove blocklist $ip: ".$e->getMessage();
          }
        }
      }
    }

    // Ensure each resolved IP is in whitelist
    foreach ($resolved_ips as $ip) {
      $exists = false;
      foreach ($fwAddr as $r) {
        if (strtolower($r['list'] ?? '') === 'whitelist' &&
            strtolower($r['address'] ?? '') === strtolower($ip)) { $exists = true; break; }
      }
      if (!$exists) {
        $api->talk('/ip/firewall/address-list/add', [
          'list'    => 'whitelist',
          'address' => $ip,
          'comment' => "From: $hn"
        ]);
        $added_ips[] = $ip;
        $debug[] = "FW add whitelist address=$ip (From $hn)";
      } else {
        $debug[] = "FW whitelist exists address=$ip";
      }
    }

    // Cleanup: remove DNS sinkholes (127.0.0.1) for host variants
    foreach ($names as $name) {
      foreach ($dnsSta as $r) {
        if (strtolower($r['name'] ?? '') === strtolower($name) &&
            ($r['address'] ?? '') === '127.0.0.1') {
          try {
            $api->talk('/ip/dns/static/remove', ['.id' => $r['.id']]);
            $removed_sinkholes[] = $name;
            $debug[] = "DNS sinkhole remove name=$name";
          } catch (Throwable $e) {
            $errors[] = "Failed remove DNS sinkhole $name: ".$e->getMessage();
          }
        }
      }
    }

    // Cleanup: remove DROP TLS rules and ensure ACCEPT TLS rule
    foreach ($names as $name) {
      // Remove DROP rules
      foreach ($fwFlt as $r) {
        if (strtolower($r['chain'] ?? '') === 'forward' &&
            strtolower($r['action'] ?? '') === 'drop' &&
            strtolower($r['protocol'] ?? '') === 'tcp' &&
            strtolower($r['tls-host'] ?? '') === strtolower($name)) {
          try {
            $api->talk('/ip/firewall/filter/remove', ['.id' => $r['.id']]);
            $removed_tls_drops[] = $name;
            $debug[] = "TLS filter DROP removed tls-host=$name";
          } catch (Throwable $e) {
            $errors[] = "Failed remove TLS DROP $name: ".$e->getMessage();
          }
        }
      }

      // Ensure ACCEPT rule exists
      $acceptExists = false;
      foreach ($fwFlt as $r) {
        if (strtolower($r['chain'] ?? '') === 'forward' &&
            strtolower($r['action'] ?? '') === 'accept' &&
            strtolower($r['protocol'] ?? '') === 'tcp' &&
            strtolower($r['tls-host'] ?? '') === strtolower($name)) { $acceptExists = true; break; }
      }
      if (!$acceptExists) {
        $api->talk('/ip/firewall/filter/add', [
          'chain'    => 'forward',
          'action'   => 'accept',
          'protocol' => 'tcp',     // REQUIRED for tls-host matcher
          'tls-host' => $name,
          'comment'  => "From: $hn"
        ]);
        $added_tls_accept[] = $name;
        $debug[] = "TLS filter ACCEPT add tls-host=$name (accept,tcp)";
      } else {
        $debug[] = "TLS filter ACCEPT exists tls-host=$name";
      }
    }
  }

  if (method_exists($api,'close')) $api->close();

  jexit([
    'ok'                 => true,
    'message'            => 'Whitelisted',
    'input'              => $input,
    'added_ips'          => $added_ips,          // includes resolved A-record IPs for hostnames
    'added_tls_accept'   => $added_tls_accept,   // tls-host ACCEPT rules added
    'removed_block_ips'  => $removed_block_ips,  // IPs removed from blocklist
    'removed_sinkholes'  => $removed_sinkholes,  // DNS sinkholes (127.0.0.1) removed
    'removed_tls_drops'  => $removed_tls_drops,  // DROP tls-host rules removed
    'debug'              => $debug,
    'errors'             => $errors
  ], 200);

} catch (Throwable $e) {
  if (method_exists($api,'close')) $api->close();
  jexit(['ok'=>false,'message'=>'Router operation failed: '.$e->getMessage()],500);
}
