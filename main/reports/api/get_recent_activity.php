<?php
// ./api/get_recent_activity.php — Device/IP/Resource feed from RouterOS logs (noise filtered, no Action)
// Shows only LAN device flows; Resource = hostname/app (from log or DNS cache).
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

function jexit($arr){ echo json_encode($arr); exit; }

$APP_ROOT = dirname(__DIR__, 3);

// Load config & client
$config_file = $APP_ROOT . '/config/router.php';
$client_file = $APP_ROOT . '/includes/routeros_client.php';
if (!file_exists($config_file)) jexit(['ok'=>false,'message'=>'config/router.php missing']);
if (!file_exists($client_file)) jexit(['ok'=>false,'message'=>'includes/routeros_client.php missing']);

$config = require $config_file;
require $client_file;

// Connection settings
$host    = $config['host']     ?? '10.10.20.10';
$port    = (int)($config['api_port'] ?? 8729);
$useTls  = (bool)($config['api_tls'] ?? true);
$user    = $config['user']     ?? 'api-dashboard';
$pass    = $config['pass']     ?? '';
$timeout = (int)($config['timeout'] ?? 8);
// Hard stop for slow RouterOS calls
@set_time_limit($timeout + 3);
@ini_set('default_socket_timeout', (string)$timeout);

// === Inputs from dashboard (JSON POST preferred; fallback to form POST) ===
$rawBody = file_get_contents('php://input');
$jsonBody = [];
if (is_string($rawBody) && trim($rawBody) !== '') {
  $decoded = json_decode($rawBody, true);
  if (is_array($decoded)) $jsonBody = $decoded;
}

$device = trim((string)($jsonBody['device'] ?? ($_POST['device'] ?? '')));
$action = trim((string)($jsonBody['action'] ?? ($_POST['action'] ?? '')));
$since  = trim((string)($jsonBody['since']  ?? ($_POST['since']  ?? ''))); // "YYYY-MM-DDTHH:MM"
$until  = trim((string)($jsonBody['until']  ?? ($_POST['until']  ?? '')));
$limit  = (int)($jsonBody['limit'] ?? ($_POST['limit'] ?? 200));
$limit  = max(1, min(500, $limit));

// Normalize "datetime-local" → "YYYY-MM-DD HH:MM"
$since = $since ? str_replace('T',' ',$since) : '';
$until = $until ? str_replace('T',' ',$until) : '';

// ----------------- helpers -----------------

function cidr_match($ip, $cidr) {
  if (!$ip || !$cidr) return false;
  [$subnet, $mask] = array_pad(explode('/', $cidr, 2), 2, 32);
  $mask = (int)$mask;
  $ip_long = ip2long($ip);
  $sub_long = ip2long($subnet);
  if ($ip_long === false || $sub_long === false) return false;
  $mask_long = $mask === 0 ? 0 : (~0 << (32 - $mask)) & 0xFFFFFFFF;
  return (($ip_long & $mask_long) === ($sub_long & $mask_long));
}
function is_private_ip($ip) {
  return cidr_match($ip,'10.0.0.0/8')
      || cidr_match($ip,'172.16.0.0/12')
      || cidr_match($ip,'192.168.0.0/16')
      || cidr_match($ip,'100.64.0.0/10'); // CGNAT
}

// return [src_ip, dst_ip]
function extract_ips_from_log($text) {
  $src = ''; $dst = '';
  if (preg_match('/\bsrc-?address=([0-9.]+)/i', $text, $m)) $src = $m[1];
  if (preg_match('/\bdst-?address=([0-9.]+)/i', $text, $m)) $dst = $m[1];

  // Fallback: first two IPv4s
  if (!$src || !$dst) {
    preg_match_all('/\b(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)\b/', $text, $mm);
    $ips = $mm[0] ?? [];
    if (!$src && isset($ips[0])) $src = $ips[0];
    if (!$dst && isset($ips[1])) $dst = $ips[1] ?? '';
  }

  // Prefer private src
  if ($dst && is_private_ip($dst) && $src && !is_private_ip($src)) {
    [$src, $dst] = [$dst, $src];
  }
  return [$src, $dst];
}

function first_mac($text){
  if (preg_match('/\b([0-9A-Fa-f]{2}(?::[0-9A-Fa-f]{2}){5})\b/', $text, $m)) return strtolower($m[0]);
  return '';
}

function extract_domain($text){
  if (preg_match('/\b([a-z0-9][a-z0-9\-]{0,62}\.)+[a-z]{2,}\b/i', $text, $m)) return strtolower($m[0]);
  return '';
}

function ts_pass($since,$until,$time_string){
  if (!$since && !$until) return true;
  $ts = $time_string ? strtotime(str_replace('T',' ', $time_string)) : null;
  if (!$ts) return true; // if router time unparsable, do not exclude
  if ($since && $ts < strtotime($since)) return false;
  if ($until && $ts > strtotime($until)) return false;
  return true;
}

function includes_ci($haystack, $needle){
  return $needle === '' || stripos($haystack, $needle) !== false;
}

function detect_action($topics, $message, $extra) {
  $blob = strtolower(trim(($topics ?? '') . ' ' . ($message ?? '') . ' ' . ($extra ?? '')));
  if (preg_match('/\b(action=drop|action=reject|drop\b|reject\b|blocked\b|deny\b)\b/', $blob)) {
    return 'Blocked';
  }
  if (preg_match('/\b(action=accept|accept\b|allowed\b|permit\b)\b/', $blob)) {
    return 'Allowed';
  }
  return 'Accessible';
}

// Only keep log lines that are likely device traffic (not wireless/info, not VPN handshakes, etc.)
function is_device_flow_line($topics, $message, $extra) {
  $t = strtolower($topics ?? '');
  $blob = strtolower(trim(($message ?? '') . ' ' . ($extra ?? '')));

  // Drop noisy/non-traffic topics
  $drop_prefixes = [
    'wireless,', 'ipsec,', 'ike,', 'l2tp,', 'pptp,', 'pppoe,', 'ppp,', 'dhcp,', 'system,'
  ];
  foreach ($drop_prefixes as $p) {
    if (str_starts_with($t, $p)) return false;
  }

  // Keep if it looks like a forward/flow/firewall record or has src/dst markers
  if (str_contains($t, 'firewall') || str_contains($t, 'filter')) return true;
  if (str_contains($blob, ' connection-state=')) return true;
  if (str_contains($blob, ' flow ') || str_contains($blob, ' log-prefix=flow') || str_contains($blob, 'flow forward')) return true;
  if (preg_match('/\bsrc-?address=|dst-?address=/i', $blob)) return true;

  // As a last resort, keep if we can see two IPs (likely a flow)
  preg_match_all('/\b(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)\b/', $blob, $mm);
  return count($mm[0] ?? []) >= 2;
}

// ----------------- main -----------------

try {
  $api = new RouterOSClient($host,$port,$user,$pass,$timeout,$useTls);

  // Lookup tables
  $leases = $api->talk('/ip/dhcp-server/lease/print'); // address, mac-address, host-name, comment
  $arp    = $api->talk('/ip/arp/print');               // address, mac-address, comment
  $ppp    = $api->talk('/ppp/active/print');           // name, address, caller-id, service
  $dnsc   = $api->talk('/ip/dns/cache/print');         // name, address|data
  $logs   = $api->talk('/log/print');

  $api->close();

  if (!is_array($logs))   $logs = [];
  if (!is_array($leases)) $leases = [];
  if (!is_array($arp))    $arp = [];
  if (!is_array($ppp))    $ppp = [];
  if (!is_array($dnsc))   $dnsc = [];

  // Build maps
  $ip2name    = []; // ip → device name
  $mac2name   = []; // mac → device name
  $pppaddr2nm = []; // PPP addr → PPP username
  $ip2host    = []; // dst public ip → hostname

  foreach ($leases as $L) {
    if (!is_array($L)) continue;
    $ip   = $L['address']     ?? '';
    $mac  = $L['mac-address'] ?? '';
    $name = $L['host-name'] ?? ($L['comment'] ?? '');
    if ($ip)  $ip2name[$ip]   = $name ?: ($ip2name[$ip] ?? '');
    if ($mac) $mac2name[$mac] = $name ?: ($mac2name[$mac] ?? '');
  }
  foreach ($arp as $A) {
    if (!is_array($A)) continue;
    $ip   = $A['address']     ?? '';
    $mac  = $A['mac-address'] ?? '';
    $name = $A['comment']     ?? '';
    if ($ip && $name && empty($ip2name[$ip]))   $ip2name[$ip] = $name;
    if ($mac && $name && empty($mac2name[$mac]))$mac2name[$mac] = $name;
  }
  foreach ($ppp as $P) {
    if (!is_array($P)) continue;
    $addr = $P['address']   ?? '';
    $nm   = $P['name']      ?? ($P['caller-id'] ?? '');
    if ($addr && $nm && empty($pppaddr2nm[$addr])) $pppaddr2nm[$addr] = $nm;
  }
  // DNS cache: handle both 'address' and 'data' fields (RouterOS variants)
  foreach ($dnsc as $D) {
    if (!is_array($D)) continue;
    $name = strtolower($D['name'] ?? '');
    $addr = $D['address'] ?? ($D['data'] ?? '');
    if ($name && $addr) {
      // Only map if $addr looks like an IP (v4 or v6)
      if (filter_var($addr, FILTER_VALIDATE_IP)) {
        if (empty($ip2host[$addr])) $ip2host[$addr] = $name;
      }
    }
  }

  // Newest N logs only
  if (count($logs) > $limit) $logs = array_slice($logs, -$limit);

  $rows = [];
  foreach ($logs as $r) {
    if (!is_array($r)) continue;

    $time    = $r['time']       ?? '';
    $topics  = $r['topics']     ?? '';
    $message = $r['message']    ?? '';
    $extra   = $r['extra-info'] ?? '';
    $blob    = trim($message . ' ' . $extra);

    if (!ts_pass($since,$until,$time)) continue;

    // Keep only device traffic lines
    if (!is_device_flow_line($topics, $message, $extra)) continue;

    // Extract IPs
    [$src_ip, $dst_ip] = extract_ips_from_log($blob);

    // Require a private "device IP" (drops wireless info & other non-device lines)
    $device_ip = $src_ip && is_private_ip($src_ip)
      ? $src_ip
      : ($dst_ip && is_private_ip($dst_ip) ? $dst_ip : '');

    if ($device_ip === '') continue; // drop rows that don't identify a LAN device

    // Resolve device name: DHCP/ARP → PPP username → MAC comment
    $device_name = '';
    if (!empty($ip2name[$device_ip])) {
      $device_name = $ip2name[$device_ip];
    } elseif (!empty($pppaddr2nm[$device_ip])) {
      $device_name = $pppaddr2nm[$device_ip];
    } else {
      $mac = first_mac($blob);
      if ($mac && !empty($mac2name[$mac])) $device_name = $mac2name[$mac];
    }

    // Resource (hostname/app only):
    // 1) domain inside the log line; else
    // 2) DNS cache by dst public IP; else blank.
    $resource = extract_domain($blob);
    if ($resource === '' && $dst_ip && !is_private_ip($dst_ip) && !empty($ip2host[$dst_ip])) {
      $resource = $ip2host[$dst_ip];
    }
    // If still blank and you prefer to show the public IP, uncomment:
    // if ($resource === '' && $dst_ip && !is_private_ip($dst_ip)) $resource = $dst_ip;

    // Action detection + filter (UI)
    $row_action = detect_action($topics, $message, $extra);
    if ($action !== '' && strcasecmp($row_action, $action) !== 0) continue;

    // Device filter (UI)
    $hay = $device_name.' '.$device_ip.' '.$resource;
    if ($device !== '' && !includes_ci($hay, $device)) continue;

    $rows[] = [
      'time'        => $time,
      'device_name' => $device_name !== '' ? $device_name : $device_ip, // fallback to IP if unnamed
      'device_ip'   => $device_ip,
      'resource'    => $resource, // hostname/app only (blank if unknown)
      'action'      => $row_action,
    ];
  }

  jexit(['ok'=>true,'rows'=>$rows]);

} catch (Throwable $e) {
  jexit(['ok'=>false,'message'=>'Router connection failed: '.$e->getMessage()]);
}
