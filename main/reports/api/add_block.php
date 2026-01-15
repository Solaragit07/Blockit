<?php
// /public_html/main/dashboard/api/add_block.php
// Adds IPs or hostnames to firewall address-list (blocklist) and sinkholes domains in /ip/dns/static.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

function jexit(array $x){ echo json_encode($x, JSON_UNESCAPED_SLASHES); exit; }

$APP_ROOT = dirname(__DIR__, 3);
$cli_path = $APP_ROOT . '/includes/routeros_client.php';
$cfg_path = $APP_ROOT . '/config/router.php';
$lv_path  = $APP_ROOT . '/loginverification.php';

if (!file_exists($cli_path) || !file_exists($cfg_path)) {
  jexit(['ok'=>false,'message'=>'Server config missing']);
}

require_once $cli_path;
$config = require $cfg_path;

// -------- Auth
$providedApiKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
if ($providedApiKey === '') $providedApiKey = (string)($config['api_key'] ?? '');
$configApiKey = trim((string)($config['api_key'] ?? getenv('BLOCKIT_API_KEY') ?? ''));

$authed = $configApiKey !== '' && hash_equals($configApiKey, $providedApiKey);
if (!$authed && file_exists($lv_path)) {
  require_once $lv_path;
  if (function_exists('require_login')) {
    ob_start(); require_login(); ob_end_clean();
    $authed = true;
  }
}
if (!$authed && session_status() === PHP_SESSION_NONE) session_start();
if (!$authed && !empty($_SESSION['user_id'])) $authed = true;
if (!$authed) {
  http_response_code(401);
  jexit(['ok'=>false,'message'=>'Not authenticated']);
}

// -------- Method & JSON body
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  $qs = $_GET ?? [];
  if (!empty($qs['address']) || !empty($qs['ip'])) $_POST = $qs + $_POST;
  else { http_response_code(405); jexit(['ok'=>false,'message'=>'Invalid method; use POST']); }
}
$ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') === 0) {
  $raw = file_get_contents('php://input');
  $j = json_decode($raw, true);
  if (is_array($j)) $_POST = $j + $_POST;
}

// -------- Helpers
function is_ip_or_cidr(string $s): bool {
  if (filter_var($s, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return true;
  if (strpos($s, '/') !== false) {
    [$ip,$mask] = explode('/', $s, 2);
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && ctype_digit($mask) && $mask >= 0 && $mask <= 32;
  }
  return false;
}
function normalize_hostname($s): string {
  if (!is_string($s)) return '';
  $s = strtolower(trim($s));
  $s = preg_replace('~^[a-z0-9]+://~i', '', $s); // strip scheme
  $s = preg_replace('~[/\?#].*$~', '', $s);      // strip path/query
  return rtrim($s, '.');
}
function is_hostname(string $s): bool {
  $s = normalize_hostname($s);
  if ($s === '') return false;
  return (bool)preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $s);
}
function resolve_host_ipv4s(string $host): array {
  $host = normalize_hostname($host);
  $ips = [];
  if ($host !== '') {
    $records = @dns_get_record($host, DNS_A);
    if ($records && is_array($records)) {
      foreach ($records as $r) {
        if (!empty($r['ip']) && filter_var($r['ip'], FILTER_VALIDATE_IP)) {
          $ips[] = $r['ip'];
        }
      }
    }
    $one = @gethostbyname($host);
    if ($one && $one !== $host && filter_var($one, FILTER_VALIDATE_IP)) {
      $ips[] = $one;
    }
  }
  return array_values(array_unique($ips));
}

// -------- Inputs
$address = strtolower(trim((string)($_POST['address'] ?? '')));
$comment = trim((string)($_POST['comment'] ?? ''));

if ($address === '') jexit(['ok'=>false,'message'=>'Address required']);
if (!is_ip_or_cidr($address) && !is_hostname($address)) {
  jexit(['ok'=>false,'message'=>'Invalid address. Use IPv4, IPv4/CIDR, or hostname.']);
}

// -------- Router settings
$routerHost = (string)($config['host'] ?? '10.10.20.10');
$apiPort    = (int)($config['api_port'] ?? 8729);
$useTls     = (bool)($config['api_tls'] ?? true);
$user       = (string)($config['user'] ?? 'api-dashboard');
$pass       = (string)($config['pass'] ?? 'STRONG_PASSWORD');
$timeout    = (int)($config['timeout'] ?? 8);

try {
  $api = new RouterOSClient($routerHost, $apiPort, $user, $pass, $timeout, $useTls);
} catch (Throwable $e) {
  try { $api = new RouterOSClient($routerHost, 8728, $user, $pass, $timeout, false); }
  catch (Throwable $e2) { http_response_code(502); jexit(['ok'=>false,'message'=>'Router connect failed']); }
}

// -------- Main add logic
$originalInput = $address;
$isHost = is_hostname($address);
$targets = $isHost ? resolve_host_ipv4s($address) : [$address];

// Fallback: if no IPs resolved, send the hostname itself to RouterOS
if ($isHost && !$targets) {
  $targets[] = $address;
}

$added = [];
$errors = [];

// existing entries
$have = [];
foreach ($api->talk('/ip/firewall/address-list/print') as $row) {
  if (!is_array($row)) continue;
  if (strtolower($row['list'] ?? '') === 'blocklist') {
    $have[strtolower($row['address'] ?? '')] = true;
  }
}

// add to firewall
foreach ($targets as $ip) {
  try {
    if (!isset($have[$ip])) {
      $api->talk('/ip/firewall/address-list/add', [
        'list'    => 'blocklist',
        'address' => $ip,
        'comment' => $comment !== '' ? $comment : "From: {$originalInput}"
      ]);
      $added[] = $ip;
      $have[$ip] = true;
    }
  } catch (Throwable $e) {
    $errors[] = "firewall add {$ip}: ".$e->getMessage();
  }
}

// DNS sinkhole (best effort)
if ($isHost) {
  try {
    $dn = normalize_hostname($originalInput);
    if ($dn) {
      $sinkIp = '127.0.0.1';
      $dnsHave = [];
      foreach ($api->talk('/ip/dns/static/print') as $r) {
        $dnsHave[strtolower((string)($r['name'] ?? ''))] = true;
      }
      foreach ([$dn, "www.$dn"] as $name) {
        if (!isset($dnsHave[$name])) {
          try {
            $reply = $api->talk('/ip/dns/static/add', [
              'name'    => $name,
              'type'    => 'A',
              'address' => $sinkIp,
              'comment' => "From: {$originalInput}"
            ]);
            @file_put_contents('/tmp/block_debug.log',
              date('c')." DNS ADD {$name} REPLY=".json_encode($reply)."\n",
              FILE_APPEND
            );
          } catch (Throwable $ex) {
            $errors[] = "dns add {$name}: ".$ex->getMessage();
            @file_put_contents('/tmp/block_debug.log',
              date('c')." DNS ADD {$name} ERROR=".$ex->getMessage()."\n",
              FILE_APPEND
            );
          }
        }
      }
    }
  } catch (Throwable $e) {
    $errors[] = "dns sinkhole: ".$e->getMessage();
    @file_put_contents('/tmp/block_debug.log',
      date('c')." DNS SINKHOLE ERROR=".$e->getMessage()."\n",
      FILE_APPEND
    );
  }
}


if (method_exists($api,'close')) $api->close();

// Debug log
@file_put_contents('/tmp/block_debug.log',
  date('c')." SUMMARY INPUT={$originalInput} TARGETS=".json_encode($targets)." ADDED=".json_encode($added)." ERRORS=".json_encode($errors)."\n",
  FILE_APPEND
);

// Success if either firewall entries OR DNS sinkhole were added
$success = (count($added) > 0) || ($isHost && empty($errors));

jexit([
  'ok' => $success,
  'input' => $originalInput,
  'added_ips' => $added,
  'dns_sinkhole' => $isHost ? 'attempted' : 'n/a',
  'errors' => $errors
]);

