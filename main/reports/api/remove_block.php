<?php
// /public_html/main/dashboard/api/remove_block.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

function jexit(array $x){
  echo json_encode($x, JSON_UNESCAPED_SLASHES);
  exit;
}

$APP_ROOT = dirname(__DIR__, 3);
$cli_path = $APP_ROOT . '/includes/routeros_client.php';
$cfg_path = $APP_ROOT . '/config/router.php';
$lv_path  = $APP_ROOT . '/loginverification.php';

if (!file_exists($cli_path)) jexit(['ok'=>false,'message'=>'RouterOS client missing']);
if (!file_exists($cfg_path)) jexit(['ok'=>false,'message'=>'Config file missing']);

require_once $cli_path;
$config = require $cfg_path;

// -------- Auth
$providedApiKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
if ($providedApiKey === '') $providedApiKey = (string)($config['api_key'] ?? '');
$configApiKey   = trim((string)($config['api_key'] ?? getenv('BLOCKIT_API_KEY') ?? ''));

$authed = false;
if ($configApiKey !== '' && hash_equals($configApiKey, $providedApiKey)) {
  $authed = true;
}
if (!$authed && file_exists($lv_path)) {
  require_once $lv_path;
  if (function_exists('require_login')) {
    ob_start();
    require_login();
    ob_end_clean();
    $authed = true;
  }
}
if (!$authed) {
  if (session_status() === PHP_SESSION_NONE) session_start();
  if (!empty($_SESSION['user_id'])) $authed = true;
}
if (!$authed) {
  http_response_code(401);
  jexit(['ok'=>false,'message'=>'Not authenticated']);
}

// -------- Method & body
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  jexit(['ok'=>false,'message'=>'Invalid method; use POST']);
}
$raw = file_get_contents('php://input');
if ($raw) {
  $j = json_decode($raw, true);
  if (json_last_error() === JSON_ERROR_NONE && is_array($j)) {
    $_POST = $j + $_POST;
  }
}

$id      = trim((string)($_POST['id'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));

if ($id === '' && $address === '') {
  jexit(['ok'=>false,'message'=>'Address or id required','debug'=>['raw'=>$raw,'post'=>$_POST]]);
}

// -------- Helpers
function normalize_hostname(string $s): string {
  $s = trim(strtolower($s));
  $s = preg_replace('#^[a-z]+://#','',$s);
  $s = preg_replace('#[/\?#].*$#','',$s);
  return rtrim($s,'.');
}
function is_ip(string $s): bool {
  return (bool)filter_var($s, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
}
function is_hostname(string $s): bool {
  return (bool)preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', strtolower($s));
}

// -------- Router settings
$routerHost = (string)($config['host']     ?? '10.10.20.10');
$apiPort    = (int)   ($config['api_port'] ?? 8729);
$useTls     = (bool)  ($config['api_tls']  ?? true);
$user       = (string)($config['user']     ?? 'api-dashboard');
$pass       = (string)($config['pass']     ?? 'STRONG_PASSWORD');
$timeout    = (int)   ($config['timeout']  ?? 8);

try {
  $api = new RouterOSClient($routerHost, $apiPort, $user, $pass, $timeout, $useTls);
} catch (Throwable $e) {
  try {
    $api = new RouterOSClient($routerHost, 8728, $user, $pass, $timeout, false);
  } catch (Throwable $e2) {
    http_response_code(502);
    jexit(['ok'=>false,'message'=>'Router connection failed']);
  }
}

// -------- Remove logic
$removed_ips = [];
$removed_dns = [];
$debug = [];
$errors = [];

try {
  // Remove by id if given — try BOTH firewall address-list and DNS static with the same .id
  if ($id !== '') {
    // Try firewall
    try {
      $api->talk('/ip/firewall/address-list/remove', ['.id' => $id]);
      $removed_ips[] = "id=$id";
      $debug[] = "Removed FW id=$id";
    } catch (Throwable $e) {
      $errors[] = "FW remove by id: " . $e->getMessage();
    }
    // Try DNS static
    try {
      $api->talk('/ip/dns/static/remove', ['.id' => $id]);
      $removed_dns[] = "id=$id";
      $debug[] = "Removed DNS id=$id";
    } catch (Throwable $e) {
      $errors[] = "DNS remove by id: " . $e->getMessage();
    }
  }

  if ($address !== '') {
    if (is_ip($address)) {
      // Remove firewall entries in 'blocklist' for this IP
      try {
        $rows = $api->talk('/ip/firewall/address-list/print');
        foreach ($rows as $r) {
          if (($r['list'] ?? '') === 'blocklist' && ($r['address'] ?? '') === $address) {
            if (!empty($r['id'])) {
              $api->talk('/ip/firewall/address-list/remove', ['.id' => $r['id']]);
              $removed_ips[] = $address;
              $debug[] = "Removed FW ip $address id=" . $r['id'];
            }
          }
        }
      } catch (Throwable $e) {
        $errors[] = "firewall remove address $address: " . $e->getMessage();
      }

      // Also remove DNS-static entries that point to this IP when they look like sinkhole blocks (comment starts with "From:")
      try {
        $dnsRows = $api->talk('/ip/dns/static/print');
        foreach ($dnsRows as $r) {
          $addr = $r['address'] ?? '';
          $name = strtolower($r['name'] ?? '');
          $cmt  = strtolower($r['comment'] ?? '');
          if ($addr === $address && strpos($cmt, 'from:') === 0 && !empty($r['id'])) {
            $api->talk('/ip/dns/static/remove', ['.id' => $r['id']]);
            $removed_dns[] = ($name !== '' ? $name : "dns_id=" . $r['id']);
            $debug[] = "Removed DNS (addr=$address) id=" . $r['id'];
          }
        }
      } catch (Throwable $e) {
        $errors[] = "dns remove by address $address: " . $e->getMessage();
      }
    }
    elseif (is_hostname($address)) {
      // Remove DNS entries for hostname and www.hostname
      $hn = normalize_hostname($address);
      try {
        $dnsRows = $api->talk('/ip/dns/static/print');
        foreach ($dnsRows as $r) {
          $n = strtolower($r['name'] ?? '');
          if ($n === $hn || $n === "www.$hn") {
            if (!empty($r['id'])) {
              $api->talk('/ip/dns/static/remove', ['.id' => $r['id']]);
              $removed_dns[] = $n;
              $debug[] = "Removed DNS $n id=" . $r['id'];
            }
          }
        }
      } catch (Throwable $e) {
        $errors[] = "dns remove host $hn: " . $e->getMessage();
      }
    }
  }

  if (method_exists($api, 'close')) $api->close();
  jexit([
    'ok' => true,
    'removed_ips' => $removed_ips,
    'removed_dns' => $removed_dns,
    'debug' => $debug,
    'errors' => $errors
  ]);

} catch (Throwable $e) {
  if (method_exists($api, 'close')) $api->close();
  http_response_code(500);
  jexit(['ok'=>false,'message'=>'Router operation failed: '.$e->getMessage()]);
}
