<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

function jexit($arr){ echo json_encode($arr); exit; }

$APP_ROOT = dirname(__DIR__, 3);
$config_file = $APP_ROOT . '/config/router.php';
$client_file = $APP_ROOT . '/includes/routeros_client.php';
if (!file_exists($config_file)) jexit(['ok'=>false,'message'=>'config/router.php missing']);
if (!file_exists($client_file)) jexit(['ok'=>false,'message'=>'includes/routeros_client.php missing']);

$config = require $config_file;
require $client_file;

$host    = $config['host']     ?? '10.10.20.10';
$port    = (int)($config['api_port'] ?? 8729);
$useTls  = (bool)($config['api_tls'] ?? true);
$user    = $config['user']     ?? 'api-dashboard';
$pass    = $config['pass']     ?? 'STRONG_PASSWORD';
$timeout = (int)($config['timeout'] ?? 8);

$limit = (int)($_GET['limit'] ?? 200);
$limit = max(1, min(1000, $limit));

try {
  $api = new RouterOSClient($host,$port,$user,$pass,$timeout,$useTls);

  // --- DHCP leases ---
  // Try server-side filter correctly (raw word): ['?status=bound'].
  // If RouterOS rejects it, fall back to unfiltered and filter in PHP.
  $leases = [];
  $lease_err = null;
  try {
    $leases = $api->talk('/ip/dhcp-server/lease/print', ['?status=bound']);
  } catch (Throwable $e) {
    $lease_err = $e;
    $leases = $api->talk('/ip/dhcp-server/lease/print');
  }
  if (!is_array($leases)) $leases = [];
  // If server-side filter failed, filter locally to status=bound when present.
  $leases = array_values(array_filter($leases, function($l){
    if (!is_array($l)) return false;
    if (!isset($l['status'])) return true; // some ROS builds omit status; keep them
    return strtolower($l['status']) === 'bound';
  }));

  // --- ARP / PPP / WLAN registration ---
  $arp  = $api->talk('/ip/arp/print');
  $ppp  = $api->talk('/ppp/active/print');
  $wlan = $api->talk('/interface/wireless/registration-table/print');

  if (!is_array($arp))  $arp = [];
  if (!is_array($ppp))  $ppp = [];
  if (!is_array($wlan)) $wlan = [];

  // Build a dictionary keyed by IP (preferred) or MAC
  $byKey = [];

  // ARP: good for IP<->MAC and sometimes comments (names)
  foreach ($arp as $r) {
    if (!is_array($r)) continue;
    $ip  = $r['address']     ?? null;
    $mac = $r['mac-address'] ?? null;
    if (!$ip && !$mac) continue;
    $key = $ip ?: $mac;
    if (!isset($byKey[$key])) $byKey[$key] = ['ip'=>null,'mac'=>null,'name'=>null,'status'=>null,'last_seen'=>null];
    $byKey[$key]['ip']        = $ip  ?: $byKey[$key]['ip'];
    $byKey[$key]['mac']       = $mac ?: $byKey[$key]['mac'];
    // Some setups put a friendly name in ARP comment
    if (empty($byKey[$key]['name']) && !empty($r['comment'])) $byKey[$key]['name'] = $r['comment'];
    // Not all ROS expose 'published'; keep if present
    if (empty($byKey[$key]['last_seen']) && !empty($r['published'])) $byKey[$key]['last_seen'] = $r['published'];
  }

  // DHCP leases: host-name/comment often contain usable names
  foreach ($leases as $l) {
    if (!is_array($l)) continue;
    $ip  = $l['address']     ?? null;
    $mac = $l['mac-address'] ?? null;
    if (!$ip && !$mac) continue;
    $key = $ip ?: $mac;
    if (!isset($byKey[$key])) $byKey[$key] = ['ip'=>null,'mac'=>null,'name'=>null,'status'=>null,'last_seen'=>null];
    $byKey[$key]['ip']    = $ip  ?: $byKey[$key]['ip'];
    $byKey[$key]['mac']   = $mac ?: $byKey[$key]['mac'];
    $name = $l['host-name'] ?? ($l['comment'] ?? null);
    if (empty($byKey[$key]['name']) && $name) $byKey[$key]['name'] = $name;
    if (empty($byKey[$key]['status']) && !empty($l['status'])) $byKey[$key]['status'] = $l['status']; // bound
  }

  // WLAN registration table: shows associated clients (by MAC), use interface name if no host-name
  foreach ($wlan as $w) {
    if (!is_array($w)) continue;
    $mac = $w['mac-address'] ?? null;
    if (!$mac) continue;
    $key = $mac;
    if (!isset($byKey[$key])) $byKey[$key] = ['ip'=>null,'mac'=>null,'name'=>null,'status'=>null,'last_seen'=>null];
    $byKey[$key]['mac'] = $mac;
    if (empty($byKey[$key]['name']) && !empty($w['interface'])) $byKey[$key]['name'] = $w['interface'];
    if (empty($byKey[$key]['status'])) $byKey[$key]['status'] = 'wlan';
    if (empty($byKey[$key]['last_seen']) && !empty($w['uptime'])) $byKey[$key]['last_seen'] = $w['uptime'];
  }

  // PPP active: map address->PPP username
  foreach ($ppp as $p) {
    if (!is_array($p)) continue;
    $addr = $p['address']   ?? null;
    $nm   = $p['name']      ?? ($p['caller-id'] ?? null);
    if (!$addr && !$nm) continue;
    $key = $addr ?: $nm;
    if (!isset($byKey[$key])) $byKey[$key] = ['ip'=>null,'mac'=>null,'name'=>null,'status'=>null,'last_seen'=>null];
    $byKey[$key]['ip']       = $addr ?: $byKey[$key]['ip'];
    if (empty($byKey[$key]['name']) && $nm) $byKey[$key]['name'] = $nm;
    if (empty($byKey[$key]['status'])) $byKey[$key]['status'] = $p['service'] ?? 'ppp';
    if (empty($byKey[$key]['last_seen']) && !empty($p['uptime'])) $byKey[$key]['last_seen'] = $p['uptime'];
  }

  // Build result list
  $clients = array_values($byKey);

  // Optional: sort by IP then MAC (stable output). Adjust as you prefer.
  usort($clients, function($a,$b){
    $ai = $a['ip'] ?? ''; $bi = $b['ip'] ?? '';
    if ($ai !== $bi) return strcmp((string)$ai,(string)$bi);
    return strcmp((string)($a['mac'] ?? ''),(string)($b['mac'] ?? ''));
  });

  // Enforce limit
  if (count($clients) > $limit) $clients = array_slice($clients, 0, $limit);

  $api->close();

  // If we hit a filter error earlier, include a hint (optional)
  $meta = [];
  if ($lease_err) $meta['lease_filter_note'] = 'Server-side lease filter not supported; filtered in PHP.';

  jexit(['ok'=>true,'clients'=>$clients,'meta'=>$meta]);

} catch (Throwable $e) {
  jexit(['ok'=>false,'message'=>'Router connection failed: '.$e->getMessage()]);
}

