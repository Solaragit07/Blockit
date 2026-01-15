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
$pass    = $config['pass']     ?? '';
$timeout = (int)($config['timeout'] ?? 8);

// ---------- helpers ----------
function is_truthy($v){
  $s = strtolower((string)$v);
  return $s === 'true' || $s === 'yes' || $s === 'enabled' || $s === 'on' || $s === '1';
}
function first_ipv4_from_addr($addr){
  // RouterOS returns "A.B.C.D/nn"
  if (!$addr) return null;
  $parts = explode('/', $addr, 2);
  return filter_var($parts[0] ?? '', FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $parts[0] : null;
}

// ---------- main ----------
try {
  $api = new RouterOSClient($host,$port,$user,$pass,$timeout,$useTls);

  // Fetch; we'll filter locally to avoid RouterOS filter word issues
  $routes  = $api->talk('/ip/route/print');         if (!is_array($routes))  $routes = [];
  $ifaces  = $api->talk('/interface/print');        if (!is_array($ifaces))  $ifaces = [];
  $addrv4  = $api->talk('/ip/address/print');       if (!is_array($addrv4))  $addrv4 = [];

  // Map interface names for quick lookup
  $iface_names = [];
  foreach ($ifaces as $i) {
    if (!is_array($i)) continue;
    $n = $i['name'] ?? null;
    if ($n) $iface_names[$n] = true;
  }

  // 1) Find active default IPv4 route (0.0.0.0/0)
  $candidates = array_values(array_filter($routes, function($r){
    if (!is_array($r)) return false;
    if (($r['dst-address'] ?? '') !== '0.0.0.0/0') return false;
    // Active if not disabled/inactive; some builds add 'active'='true'
    if (isset($r['disabled']) && is_truthy($r['disabled'])) return false;
    if (isset($r['inactive']) && is_truthy($r['inactive'])) return false;
    return true;
  }));

  // Prefer ones explicitly marked active=true
  usort($candidates, function($a,$b){
    $aa = is_truthy($a['active'] ?? '');
    $bb = is_truthy($b['active'] ?? '');
    if ($aa !== $bb) return $aa ? -1 : 1;
    // lower distance first if present
    $da = isset($a['distance']) ? (int)$a['distance'] : 255;
    $db = isset($b['distance']) ? (int)$b['distance'] : 255;
    return $da <=> $db;
  });

  $wan_iface = null;

  if (!empty($candidates)) {
    $def = $candidates[0];

    // 2) Derive interface from gateway-status when available:
    // formats often contain "... reachable via <iface> ..." or "... via <iface> ..."
    $gs = $def['gateway-status'] ?? '';
    if ($gs) {
      if (preg_match('/\bvia\s+([A-Za-z0-9_.\-:]+)/', $gs, $m)) {
        $cand = $m[1];
        if (isset($iface_names[$cand])) $wan_iface = $cand;
      }
    }

    // 3) Some builds include 'interface' directly
    if (!$wan_iface && !empty($def['interface']) && isset($iface_names[$def['interface']])) {
      $wan_iface = $def['interface'];
    }

    // 4) Sometimes 'gateway' is an interface name (e.g., lte1); check
    if (!$wan_iface && !empty($def['gateway']) && isset($iface_names[$def['gateway']])) {
      $wan_iface = $def['gateway'];
    }
  }

  // 5) Resolve WAN IPv4 from the interface's addresses
  $wan_ip = null;
  if ($wan_iface) {
    foreach ($addrv4 as $a) {
      if (!is_array($a)) continue;
      if (($a['interface'] ?? null) !== $wan_iface) continue;
      $ip = first_ipv4_from_addr($a['address'] ?? null);
      if ($ip && $ip !== '127.0.0.1') { $wan_ip = $ip; break; }
    }
  }

  // 6) Fallback: any non-loopback IPv4 address
  if (!$wan_ip) {
    foreach ($addrv4 as $a) {
      if (!is_array($a)) continue;
      $ip = first_ipv4_from_addr($a['address'] ?? null);
      if ($ip && $ip !== '127.0.0.1') { $wan_ip = $ip; break; }
    }
  }

  $api->close();

  jexit([
    'ok'        => true,
    'wan_iface' => $wan_iface,
    'wan_ip'    => $wan_ip
  ]);

} catch (Throwable $e) {
  jexit(['ok'=>false,'message'=>'Router connection failed: '.$e->getMessage()]);
}
