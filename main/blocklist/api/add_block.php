<?php
// /public_html/main/blocklist/api/add_block.php
// Adds IP/CIDR/hostname to Mikrotik block mechanisms:
// - /ip/firewall/address-list list=blocklist   (IPs & resolved A records)
// - /ip/dns/static sinkhole to 127.0.0.1       (hostnames incl. www)
// - /ip/firewall/filter drop with tls-host     (hostnames incl. www; protocol=tcp)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

function jexit(array $x, int $c = 200){ http_response_code($c); echo json_encode($x, JSON_UNESCAPED_SLASHES); exit; }
function logf(string $m){ @file_put_contents('/tmp/add_block_debug.log', date('c')." ".$m."\n", FILE_APPEND); }

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
    foreach ($recs as $r) {
      if (!empty($r['ip'])) $ips[] = $r['ip'];
    }
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
$added_ips        = [];
$added_sinkholes  = [];
$added_tls        = [];
$debug            = [];
$errors           = [];

/* ---- Fetch existing (for idempotency) ---- */
try {
  $fwAddr = $api->talk('/ip/firewall/address-list/print', ['.proplist'=>'.id,list,address,comment']);
  $dnsSta = $api->talk('/ip/dns/static/print', ['.proplist'=>'.id,name,address,comment']);
  $fwFlt  = $api->talk('/ip/firewall/filter/print', ['.proplist'=>'.id,chain,action,protocol,tls-host,comment']);
} catch (Throwable $e) {
  if (method_exists($api,'close')) $api->close();
  jexit(['ok'=>false,'message'=>'Router read failed: '.$e->getMessage()],500);
}

/* ---- Add logic ---- */
try {
  // A) IP or CIDR -> blocklist
  if (is_ip($input) || is_cidr($input)) {
    $addr = $input;

    $exists = false;
    foreach ($fwAddr as $r) {
      if (strtolower($r['list'] ?? '') === 'blocklist' &&
          strtolower($r['address'] ?? '') === strtolower($addr)) { $exists = true; break; }
    }

    if (!$exists) {
      $api->talk('/ip/firewall/address-list/add', [
        'list'    => 'blocklist',
        'address' => $addr,
        'comment' => $comment
      ]);
      $added_ips[] = $addr;
      $debug[] = "FW add blocklist address=$addr";
    } else {
      $debug[] = "FW already exists address=$addr";
    }
  }

  // B) Hostname/URL -> resolve -> add IPs, then sinkholes, then TLS
  if (is_hostname($input)) {
    $hn = normalize_hostname($input);

    // Hostname variants we act on
    $names = [$hn, "www.$hn"];
    if (str_starts_with($hn, 'www.')) {
      $root = substr($hn, 4);
      if ($root !== '') $names = array_unique([$hn, $root]);
    }

    // B1) Resolve both hostnames to IPv4s (before creating sinkholes!)
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

    // Add each resolved IP to blocklist if not present
    foreach ($resolved_ips as $ip) {
      $exists = false;
      foreach ($fwAddr as $r) {
        if (strtolower($r['list'] ?? '') === 'blocklist' &&
            strtolower($r['address'] ?? '') === strtolower($ip)) { $exists = true; break; }
      }
      if (!$exists) {
        $api->talk('/ip/firewall/address-list/add', [
          'list'    => 'blocklist',
          'address' => $ip,
          'comment' => "From: $hn"
        ]);
        $added_ips[] = $ip;
        $debug[] = "FW add blocklist address=$ip (From $hn)";
      } else {
        $debug[] = "FW already exists address=$ip";
      }
    }

    // B2) DNS sinkholes (127.0.0.1)
    foreach ($names as $name) {
      $exists = false;
      foreach ($dnsSta as $r) {
        if (strtolower($r['name'] ?? '') === strtolower($name) &&
            ($r['address'] ?? '') === '127.0.0.1') { $exists = true; break; }
      }
      if (!$exists) {
        $api->talk('/ip/dns/static/add', [
          'name'    => $name,
          'address' => '127.0.0.1',
          'comment' => "From: $hn"
        ]);
        $added_sinkholes[] = $name;
        $debug[] = "DNS sinkhole add name=$name -> 127.0.0.1";
      } else {
        $debug[] = "DNS sinkhole exists name=$name";
      }
    }

    // B3) TLS drop rules (protocol MUST be tcp)
    foreach ($names as $name) {
      $exists = false;
      foreach ($fwFlt as $r) {
        if (strtolower($r['chain'] ?? '') === 'forward' &&
            strtolower($r['action'] ?? '') === 'drop' &&
            strtolower($r['protocol'] ?? '') === 'tcp' &&
            strtolower($r['tls-host'] ?? '') === strtolower($name)) { $exists = true; break; }
      }
      if (!$exists) {
        $api->talk('/ip/firewall/filter/add', [
          'chain'    => 'forward',
          'action'   => 'drop',
          'protocol' => 'tcp',     // REQUIRED for tls-host matcher
          'tls-host' => $name,
          'comment'  => "From: $hn"
        ]);
        $added_tls[] = $name;
        $debug[] = "TLS filter add tls-host=$name (drop,tcp)";
      } else {
        $debug[] = "TLS filter exists tls-host=$name";
      }
    }
  }

  if (method_exists($api,'close')) $api->close();

  jexit([
    'ok'               => true,
    'message'          => 'Blocked',
    'input'            => $input,
    'added_ips'        => $added_ips,        // <- now includes resolved A-record IPs for hostnames
    'added_sinkholes'  => $added_sinkholes,  // domains added as DNS static 127.0.0.1
    'added_tls'        => $added_tls,        // tls-host rules added
    'debug'            => $debug,
    'errors'           => $errors
  ], 200);

} catch (Throwable $e) {
  if (method_exists($api,'close')) $api->close();
  jexit(['ok'=>false,'message'=>'Router operation failed: '.$e->getMessage()],500);
}
