<?php
// /public_html/main/profile/API/active_devices.php
// Returns currently active devices merged from ARP, DHCP, WLAN, PPP
// Output shape: { ok: true, clients: [ {ip, mac, name, status, last_seen} ], meta: {...} }

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

function jexit($arr, $code = 200){ http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_SLASHES); exit; }

$APP_ROOT    = dirname(__DIR__, 3); // -> /public_html
$config_file = $APP_ROOT . '/config/router.php';
$client_file = $APP_ROOT . '/includes/routeros_client.php';
$lv_path     = $APP_ROOT . '/loginverification.php';

if (!file_exists($config_file)) jexit(['ok'=>false,'message'=>'config/router.php missing'], 500);
if (!file_exists($client_file)) jexit(['ok'=>false,'message'=>'includes/routeros_client.php missing'], 500);

$config = require $config_file;
require $client_file;

/* ---- Auth: API key OR login session (same style as other profile APIs) ---- */
$providedApiKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
if ($providedApiKey === '') $providedApiKey = (string)($config['api_key'] ?? '');
$configApiKey = trim((string)($config['api_key'] ?? getenv('BLOCKIT_API_KEY') ?? ''));
$authed = ($configApiKey !== '' && hash_equals($configApiKey, $providedApiKey));
if (!$authed && file_exists($lv_path)) {
  require_once $lv_path;
  if (function_exists('require_login')) { ob_start(); require_login(); ob_end_clean(); $authed = true; }
}
if (!$authed && session_status() === PHP_SESSION_NONE) session_start();
if (!$authed && !empty($_SESSION['user_id'])) $authed = true;
if (!$authed) jexit(['ok'=>false,'message'=>'Not authenticated'], 401);

/* ---- Inputs ---- */
$host    = $config['host']     ?? '10.10.20.10';
$port    = (int)($config['api_port'] ?? 8729);
$useTls  = (bool)($config['api_tls']  ?? true);
$user    = $config['user']     ?? 'api-dashboard';
$pass    = $config['pass']     ?? 'STRONG_PASSWORD';
$timeout = (int)($config['timeout']  ?? 8);

$limit = (int)($_GET['limit'] ?? 200);
$limit = max(1, min(1000, $limit));

try {
  $api = new RouterOSClient($host,$port,$user,$pass,$timeout,$useTls);

  // --- DHCP leases (prefer status=bound) ---
  $leases = [];
  $lease_err = null;
  try {
    $leases = $api->talk('/ip/dhcp-server/lease/print', ['?status=bound']);
  } catch (Throwable $e) {
    $lease_err = $e;
    $leases = $api->talk('/ip/dhcp-server/lease/print');
  }
  if (!is_array($leases)) $leases = [];
  $leases = array_values(array_filter($leases, function($l){
    if (!is_array($l)) return false;
    if (!isset($l['status'])) return true; // keep if missing
    return strtolower($l['status']) === 'bound';
  }));

  // --- ARP / PPP / WLAN ---
  $arp  = $api->talk('/ip/arp/print');                                   if (!is_array($arp))  $arp  = [];
  $ppp  = $api->talk('/ppp/active/print');                                if (!is_array($ppp))  $ppp  = [];
  $wlan = $api->talk('/interface/wireless/registration-table/print');     if (!is_array($wlan)) $wlan = [];

  // Merge by IP then MAC
  $byKey = []; // key = ip or mac
  $ensure = function($key) use (&$byKey){
    if (!isset($byKey[$key])) $byKey[$key] = ['ip'=>null,'mac'=>null,'name'=>null,'status'=>null,'last_seen'=>null];
  };

  foreach ($arp as $r){
    if (!is_array($r)) continue;
    $ip = $r['address'] ?? null;
    $mac = $r['mac-address'] ?? null;
    if (!$ip && !$mac) continue;
    $key = $ip ?: $mac;
    $ensure($key);
    $byKey[$key]['ip'] = $ip ?: $byKey[$key]['ip'];
    $byKey[$key]['mac'] = $mac ?: $byKey[$key]['mac'];
    if (empty($byKey[$key]['name']) && !empty($r['comment'])) $byKey[$key]['name'] = $r['comment'];
    if (empty($byKey[$key]['last_seen']) && !empty($r['published'])) $byKey[$key]['last_seen'] = $r['published'];
  }

  foreach ($leases as $l){
    if (!is_array($l)) continue;
    $ip = $l['address'] ?? null;
    $mac = $l['mac-address'] ?? null;
    if (!$ip && !$mac) continue;
    $key = $ip ?: $mac;
    $ensure($key);
    $byKey[$key]['ip'] = $ip ?: $byKey[$key]['ip'];
    $byKey[$key]['mac'] = $mac ?: $byKey[$key]['mac'];
    $name = $l['host-name'] ?? ($l['comment'] ?? null);
    if (empty($byKey[$key]['name']) && $name) $byKey[$key]['name'] = $name;
    if (empty($byKey[$key]['status']) && !empty($l['status'])) $byKey[$key]['status'] = $l['status'];
  }

  foreach ($wlan as $w){
    if (!is_array($w)) continue;
    $mac = $w['mac-address'] ?? null;
    if (!$mac) continue;
    $key = $mac;
    $ensure($key);
    $byKey[$key]['mac'] = $mac;
    if (empty($byKey[$key]['name']) && !empty($w['interface'])) $byKey[$key]['name'] = $w['interface'];
    if (empty($byKey[$key]['status'])) $byKey[$key]['status'] = 'wlan';
    if (empty($byKey[$key]['last_seen']) && !empty($w['uptime'])) $byKey[$key]['last_seen'] = $w['uptime'];
  }

  foreach ($ppp as $p){
    if (!is_array($p)) continue;
    $addr = $p['address'] ?? null;
    $nm   = $p['name'] ?? ($p['caller-id'] ?? null);
    if (!$addr && !$nm) continue;
    $key = $addr ?: $nm;
    $ensure($key);
    $byKey[$key]['ip'] = $addr ?: $byKey[$key]['ip'];
    if (empty($byKey[$key]['name']) && $nm) $byKey[$key]['name'] = $nm;
    if (empty($byKey[$key]['status'])) $byKey[$key]['status'] = $p['service'] ?? 'ppp';
    if (empty($byKey[$key]['last_seen']) && !empty($p['uptime'])) $byKey[$key]['last_seen'] = $p['uptime'];
  }

  // Flatten + sort
  $clients = array_values($byKey);
  usort($clients, function($a,$b){
    $ai = $a['ip'] ?? ''; $bi = $b['ip'] ?? '';
    if ($ai !== $bi) return strcmp((string)$ai,(string)$bi);
    return strcmp((string)($a['mac'] ?? ''),(string)($b['mac'] ?? ''));
  });

  if (count($clients) > $limit) $clients = array_slice($clients, 0, $limit);
  if (method_exists($api,'close')) $api->close();

  $meta = [];
  if ($lease_err) $meta['lease_filter_note'] = 'Server-side lease filter not supported; filtered in PHP.';

  jexit(['ok'=>true,'clients'=>$clients,'meta'=>$meta]);

} catch (Throwable $e) {
  jexit(['ok'=>false,'message'=>'Router connection failed: '.$e->getMessage()], 502);
}
