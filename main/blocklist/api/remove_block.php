<?php
// /public_html/main/blocklist/api/remove_block.php
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
if ($configApiKey !== '' && hash_equals($configApiKey, $providedApiKey)) $authed = true;
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
  if (json_last_error() === JSON_ERROR_NONE && is_array($j)) $_POST = $j + $_POST;
}

$id      = trim((string)($_POST['id'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));

if ($id === '' && $address === '') {
  jexit(['ok'=>false,'message'=>'Address or id required','debug'=>['raw'=>$raw,'post'=>$_POST]]);
}

// -------- Helpers
function normalize_hostname(string $s): string {
  $s = trim(strtolower($s));
  if ($s === '') return '';
  if (!preg_match('~^[a-z][a-z0-9+\-.]*://~i', $s)) $s = 'http://' . $s;
  $host = parse_url($s, PHP_URL_HOST) ?? '';
  return rtrim($host, '.');
}
function base_hostname(string $s): string {
  $hn = normalize_hostname($s);
  return preg_replace('/^www\./', '', $hn);
}
function is_ip(string $s): bool {
  return (bool)filter_var($s, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
}
function is_hostname(string $s): bool {
  return (bool)preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', strtolower($s));
}
function row_id(array $r){
  return $r['.id'] ?? $r['id'] ?? null; // prefer .id
}
function extract_from_comment(?string $cmt): string {
  $c = strtolower(trim((string)$cmt));
  if (strpos($c, 'from:') !== 0) return '';
  $rest = trim(substr($c, 5));
  return base_hostname($rest); // base host only
}
function push_unique(array &$arr, string $val): void {
  if ($val !== '' && !in_array($val, $arr, true)) $arr[] = $val;
}

// remove TLS rules whose tls-host (or comment) matches any in $hostSet
function remove_tls_rules_for_hosts($api, array $rows, array $hostSet, array &$removed_tls, array &$debug, string $whereTag){
  $hostSet = array_map('strtolower', $hostSet);
  $hostSetFlip = array_flip($hostSet);

  foreach ((array)$rows as $r) {
    $rid  = row_id($r);
    if (!$rid) continue;

    $tls  = strtolower($r['tls-host'] ?? '');
    $cmt  = strtolower($r['comment'] ?? '');
    $nameHit = ($tls !== '' && (isset($hostSetFlip[$tls]) || isset($hostSetFlip[preg_replace('/^www\./','',$tls)]) || isset($hostSetFlip['www.'.$tls])));
    $cmtHit  = (strpos($cmt, 'from:') === 0) && (array_reduce(array_keys($hostSetFlip), fn($carry,$h)=>$carry || str_contains($cmt,$h), false));

    if ($nameHit || $cmtHit) {
      try {
        $api->talk($whereTag.'/remove', ['.id' => $rid]);
        $label = ($tls !== '' ? $tls : "rule_id={$rid}");
        push_unique($removed_tls, $label);
        $debug[] = "Removed TLS ($whereTag) tls-host={$tls} id={$rid}".($cmtHit?' (by comment)':'');
      } catch (Throwable $e) {
        // swallow; we keep going
        $debug[] = "Failed TLS remove ($whereTag) id={$rid}: ".$e->getMessage();
      }
    }
  }
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
$removed_tls = [];
$removed_sinkholes = [];
$debug = [['which_file' => __FILE__, 'address' => $address]];
$errors = [];

try {
  // 0) Remove by explicit ID (try both FW + DNS)
  if ($id !== '') {
    try {
      $api->talk('/ip/firewall/address-list/remove', ['.id' => $id]);
      $removed_ips[] = "id=$id";
      $debug[] = "Removed FW id=$id";
    } catch (Throwable $e) { $errors[] = "FW remove by id: ".$e->getMessage(); }
    try {
      $api->talk('/ip/dns/static/remove', ['.id' => $id]);
      $removed_dns[] = "id=$id";
      $debug[] = "Removed DNS id=$id";
    } catch (Throwable $e) { $errors[] = "DNS remove by id: ".$e->getMessage(); }
    // try both TLS tables by id as well (best effort)
    try { $api->talk('/ip/firewall/filter/remove', ['.id' => $id]); $removed_tls[] = "id=$id"; } catch (Throwable $e) {}
    try { $api->talk('/ip/firewall/raw/remove',    ['.id' => $id]); $removed_tls[] = "id=$id"; } catch (Throwable $e) {}
  }

  // Preload rows once (avoid RouterOS predicate quirks)
  $fwRows   = [];
  $dnsRows  = [];
  $fltRows  = []; // /ip/firewall/filter
  $rawRows  = []; // /ip/firewall/raw
  try { $fwRows  = (array)$api->talk('/ip/firewall/address-list/print'); } catch (Throwable $e) { $errors[] = "FW list: ".$e->getMessage(); }
  try { $dnsRows = (array)$api->talk('/ip/dns/static/print'); }          catch (Throwable $e) { $errors[] = "DNS list: ".$e->getMessage(); }
  try { $fltRows = (array)$api->talk('/ip/firewall/filter/print'); }     catch (Throwable $e) { $errors[] = "Filter list: ".$e->getMessage(); }
  try { $rawRows = (array)$api->talk('/ip/firewall/raw/print'); }        catch (Throwable $e) { $errors[] = "Raw list: ".$e->getMessage(); }

  $relatedHosts = []; // accumulate hosts tied to this removal (base + www)

  if ($address !== '') {

    if (is_ip($address)) {
      // 1) Remove FW rows (blocklist) for this IP + collect hosts from comments
      foreach ($fwRows as $r) {
        if (($r['list'] ?? '') === 'blocklist' && ($r['address'] ?? '') === $address) {
          $rid = row_id($r);
          if ($rid) {
            $api->talk('/ip/firewall/address-list/remove', ['.id' => $rid]);
            $removed_ips[] = $address;
            $debug[] = "Removed FW ip $address id={$rid}";
          }
          $base = extract_from_comment($r['comment'] ?? '');
          if ($base !== '') {
            push_unique($relatedHosts, $base);
            push_unique($relatedHosts, "www.$base");
          }
        }
      }

      // 2) Remove DNS rows pointing to the IP / comment mentioning IP / name in related hosts
      foreach ($dnsRows as $r) {
        $rid   = row_id($r);
        $addr  = $r['address'] ?? '';
        $name  = strtolower($r['name'] ?? '');
        $cmt   = strtolower($r['comment'] ?? '');
        if (!$rid) continue;

        $match = ($addr === $address)
              || (strpos($cmt, 'from:') === 0 && str_contains($cmt, $address))
              || ($name !== '' && in_array($name, $relatedHosts, true));

        if ($match) {
          $api->talk('/ip/dns/static/remove', ['.id' => $rid]);
          $label = ($name !== '' ? $name : "dns_id={$rid}");
          push_unique($removed_dns, $label);
          push_unique($removed_sinkholes, $label);
          $debug[] = "Removed DNS name=$name addr=$addr id={$rid}";
        }
      }

      // 3) Remove TLS rules in filter/raw tables for the related hosts
      if (!empty($relatedHosts)) {
        remove_tls_rules_for_hosts($api, $fltRows, $relatedHosts, $removed_tls, $debug, '/ip/firewall/filter');
        remove_tls_rules_for_hosts($api, $rawRows, $relatedHosts, $removed_tls, $debug, '/ip/firewall/raw');
      }
    }

    elseif (is_hostname($address)) {
      // base + www pair
      $base  = base_hostname($address);
      $hosts = [$base, "www.$base"];

      // 1) Remove DNS rows by name or comment references
      foreach ($dnsRows as $r) {
        $rid  = row_id($r);
        $name = strtolower($r['name'] ?? '');
        $cmt  = strtolower($r['comment'] ?? '');
        if (!$rid) continue;

        $byName = ($name !== '' && in_array($name, $hosts, true));
        $byCmt  = (strpos($cmt, 'from:') === 0) && (str_contains($cmt, $base) || str_contains($cmt, "www.$base"));

        if ($byName || $byCmt) {
          $api->talk('/ip/dns/static/remove', ['.id' => $rid]);
          $label = ($name !== '' ? $name : "dns_id={$rid}");
          push_unique($removed_dns, $label);
          push_unique($removed_sinkholes, $label);
          $debug[] = "Removed DNS name=$name id={$rid}".($byCmt ? " (by comment)" : "");
        }
      }

      // 2) Remove FW rows whose comment references the host(s)
      foreach ($fwRows as $r) {
        if (($r['list'] ?? '') !== 'blocklist') continue;
        $rid = row_id($r);
        if (!$rid) continue;
        $cmt = strtolower($r['comment'] ?? '');
        if (strpos($cmt, 'from:') === 0 && (str_contains($cmt, $base) || str_contains($cmt, "www.$base"))) {
          $api->talk('/ip/firewall/address-list/remove', ['.id' => $rid]);
          $removed_ips[] = (string)($r['address'] ?? 'fw_id='.$rid);
          $debug[] = "Removed FW by comment id={$rid}";
        }
      }

      // 3) Remove TLS rules in filter/raw tables for base + www
      remove_tls_rules_for_hosts($api, $fltRows, $hosts, $removed_tls, $debug, '/ip/firewall/filter');
      remove_tls_rules_for_hosts($api, $rawRows,  $hosts, $removed_tls, $debug, '/ip/firewall/raw');
    }
  }

  if (method_exists($api, 'close')) $api->close();
  jexit([
    'ok' => true,
    'removed_ips' => $removed_ips,
    'removed_dns' => $removed_dns,
    'removed_tls' => $removed_tls,
    'removed_sinkholes' => $removed_sinkholes,
    'debug' => $debug,
    'errors' => $errors
  ]);

} catch (Throwable $e) {
  if (method_exists($api, 'close')) $api->close();
  http_response_code(500);
  jexit(['ok'=>false,'message'=>'Router operation failed: '.$e->getMessage()]);
}
