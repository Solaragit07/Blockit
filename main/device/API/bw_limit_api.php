<?php
// /main/usage/API/bw_limit_api.php — NO DATABASE
const DEBUG = true; // set to false after testing

$APP_ROOT = dirname(__DIR__, 3);
$config   = require $APP_ROOT . '/config/router.php';

function strip_invisible(string $s): string {
  $s = preg_replace('/^\xEF\xBB\xBF/', '', $s); // BOM
  $s = preg_replace('/[^\P{C}\t\r\n]/u', '', $s); // control chars
  return trim($s);
}
function read_api_key_header(): string {
  if (!empty($_SERVER['HTTP_X_API_KEY'])) return $_SERVER['HTTP_X_API_KEY'];
  if (function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) { if (strcasecmp($k,'X-API-Key')===0) return $v; }
  }
  if (function_exists('apache_request_headers')) {
    foreach (apache_request_headers() as $k => $v) { if (strcasecmp($k,'X-API-Key')===0) return $v; }
  }
  return '';
}

// --- API key check (with query fallback for proxies that strip headers) ---
$EXPECTED_RAW = (string)($config['api_key'] ?? '');
$RECEIVED_RAW = (string)read_api_key_header();
if (DEBUG && !$RECEIVED_RAW && isset($_GET['api_key'])) { $RECEIVED_RAW = (string)$_GET['api_key']; }

$EXPECTED = strip_invisible($EXPECTED_RAW);
$RECEIVED = strip_invisible($RECEIVED_RAW);

if ($EXPECTED !== '' && !hash_equals($EXPECTED, $RECEIVED)) {
  http_response_code(403);
  header('Content-Type: application/json');
  echo json_encode([
    'ok' => false, 'message' => 'Forbidden',
    'hint' => DEBUG ? 'X-API-Key mismatch. Check router.php or proxy header pass-through.' : null,
    'diag' => DEBUG ? [
      'received_header_present' => ($RECEIVED_RAW!=='')?'yes':'no',
      'expected_len' => strlen($EXPECTED),
      'received_len' => strlen($RECEIVED),
      'expected_sha256_prefix' => substr(hash('sha256', $EXPECTED), 0, 16),
      'received_sha256_prefix' => substr(hash('sha256', $RECEIVED), 0, 16),
      'expected_tail' => substr($EXPECTED, -6),
      'received_tail' => substr($RECEIVED, -6),
    ] : null,
  ]);
  exit;
}

header('Content-Type: application/json');
require_once $APP_ROOT . '/includes/routeros_client.php';

function jerr($m,$c=400){ http_response_code($c); echo json_encode(['ok'=>false,'message'=>$m]); exit; }
function ok($arr=[]){ echo json_encode(['ok'=>true]+$arr); exit; }

// Normalize action so "set-limit", "SetLimit", etc. all map to "setlimit"
$raw_action = $_GET['action'] ?? '';
$action = strtolower(preg_replace('/[^a-z]/i','',$raw_action)); // keep letters only, lowercase
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input  = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];

// RouterOS connection (TLS-aware per router.php)
$ROS_HOST    = $config['host']    ?? '127.0.0.1';
$ROS_PORT    = (int)($config['api_port'] ?? ($config['port'] ?? 8728));
$ROS_USER    = $config['user']    ?? 'admin';
$ROS_PASS    = $config['pass']    ?? '';
$ROS_TIMEOUT = (int)($config['timeout'] ?? 8);
$ROS_TLS     = !empty($config['api_tls']);

try {
  $ros = new RouterOSClient($ROS_HOST, $ROS_PORT, $ROS_USER, $ROS_PASS, $ROS_TIMEOUT, $ROS_TLS);
} catch (Throwable $e) {
  jerr('RouterOS client error: '.$e->getMessage(), 500);
}

// ---- helpers ----
function qname($mac){ return 'DEV-'.preg_replace('/[^A-F0-9]/i','', strtoupper($mac)); }

function ros_list_devices($ros){
  $arr = [];
  foreach ($ros->talk('/ip/dhcp-server/lease/print') as $l){
    $mac = $l['mac-address'] ?? null; if(!$mac) continue;
    $arr[$mac] = [
      'mac' => $mac,
      'ip'  => $l['address'] ?? null,
      'name'=> $l['host-name'] ?? ($l['comment'] ?? null),
    ];
  }
  foreach ($ros->talk('/ip/arp/print') as $a){
    $mac = $a['mac-address'] ?? null; if(!$mac) continue;
    if (!isset($arr[$mac])) $arr[$mac]=['mac'=>$mac,'ip'=>$a['address']??null,'name'=>null];
    $arr[$mac]['ip'] = $arr[$mac]['ip'] ?: ($a['address'] ?? null);
  }
  return array_values($arr);
}

function parse_maxlimit($s){
  $down=0;$up=0;
  if (strpos($s,'/')!==false){ [$d,$u]=explode('/',$s,2); } else { $d=$s; $u='0'; }
  $conv=function($v){
    $v=trim($v);
    if ($v===''||$v==='0') return 0;
    if (preg_match('/^(\d+)([kKmMgG])?$/',$v,$m)){
      $n=(int)$m[1]; $u=strtolower($m[2]??'k');
      if($u==='m') return $n*1000;
      if($u==='g') return $n*1000*1000;
      return $n; // default k
    }
    return 0;
  };
  return [$conv($d), $conv($u)];
}

// Convert strings like "12.3kbps/180.9kbps" to [tx_kbps, rx_kbps]
function parse_rate_kbps_pair(string $s): array {
  $s = trim($s);
  if ($s === '' || strpos($s, '/') === false) return [0, 0];
  [$a, $b] = explode('/', $s, 2);

  $toKbps = function(string $one): int {
    $one = strtolower(trim($one));
    // normalize spaces, e.g. "12.3 kbps"
    $one = preg_replace('/\s+/', '', $one);

    if (preg_match('/^([0-9]*\.?[0-9]+)([kmg]?bps)$/i', $one, $m)) {
      $val = (float)$m[1];
      $unit = strtolower($m[2]);  // bps, kbps, mbps, gbps

      if ($unit === 'bps')  return (int)round($val / 1000.0);
      if ($unit === 'kbps') return (int)round($val);
      if ($unit === 'mbps') return (int)round($val * 1000.0);
      if ($unit === 'gbps') return (int)round($val * 1000_000.0);
    } elseif (preg_match('/^([0-9]+)$/', $one)) {
      // sometimes RouterOS may return plain number (bps)
      return (int)round(((float)$one) / 1000.0);
    }
    return 0;
  };

  // IMPORTANT: RouterOS prints "rate=TX/RX" for simple queues.
  $tx_kbps = $toKbps($a);
  $rx_kbps = $toKbps($b);
  return [$tx_kbps, $rx_kbps];
}

function ros_queue_snapshot($ros){
  // Request live counters for queues.
  // With this RouterOS client wrapper, the most reliable way is passing a raw word '=stats='.
  $rows = $ros->talk('/queue/simple/print', [
    '=stats=' => '',
    '.proplist' => 'name,max-limit,priority,rate,bytes'
  ]);

  $byName = [];
  foreach ($rows as $r){
    $name = $r['name'] ?? '';
    if (strpos($name, 'DEV-') !== 0) continue;

    $ml = $r['max-limit'] ?? '0/0';
    [$down, $up] = parse_maxlimit($ml);

    // Prefer 'rate' (TX/RX) if present; fall back to 0/0
    $rate = $r['rate'] ?? '0/0';
    [$tx_kbps, $rx_kbps] = parse_rate_kbps_pair($rate);

    $byName[$name] = [
      'max_down_kbps' => $down,
      'max_up_kbps'   => $up,
      'priority'      => (int)($r['priority'] ?? 8),
      'rx_rate_kbps'  => $rx_kbps,   // Rx from device perspective = second number
      'tx_rate_kbps'  => $tx_kbps,   // Tx = first number
    ];
  }
  return $byName;
}



function ensure_queue($ros,$mac,$ip,$down_kbps,$up_kbps,$priority){
  $name = qname($mac);

  // OLD (buggy): $rows = $ros->talk('/queue/simple/print', ['?name'=>$name]);
  // NEW (correct): pass the query as a raw word
  $rows = $ros->talk('/queue/simple/print', ['?name='.$name]);

  $maxlimit = (($down_kbps?:0).'k/'.($up_kbps?:0).'k'); // 0k → unlimited
  if ($rows && !empty($rows[0]['id'])) {
    $id = $rows[0]['id'];              // READ as 'id' (normalized by your client)
    $ros->talk('/queue/simple/set', [  // WRITE using '.id'
      '.id'       => $id,
      'max-limit' => $maxlimit,
      'priority'  => $priority
    ]);
    if ($ip) {
      $ros->talk('/queue/simple/set', ['.id'=>$id, 'target'=>"$ip/32"]);
    }
  } else {
    $ros->talk('/queue/simple/add', [
      'name'      => $name,
      'target'    => $ip ? "$ip/32" : '',
      'max-limit' => $maxlimit,
      'priority'  => $priority,
      'comment'   => "BlockIT bandwidth limit for $mac"
    ]);
  }
}


function clear_limit($ros,$mac){
  $name = qname($mac);

  // OLD: $rows = $ros->talk('/queue/simple/print', ['?name'=>$name]);
  // NEW:
  $rows = $ros->talk('/queue/simple/print', ['?name='.$name]);

  if ($rows && !empty($rows[0]['id'])) {
    $ros->talk('/queue/simple/remove', ['.id' => $rows[0]['id']]);
  }
}


// ---- actions ----
if ($action === 'ping') {
  ok([
    'note' => 'pong',
    'received_header' => ($RECEIVED_RAW!=='')?'present':'missing',
    'expected_key'    => ($EXPECTED!=='')?'present':'missing'
  ]);
}

switch ($action){
  case 'getdevices': {
    $devices = ros_list_devices($ros);
    $q = ros_queue_snapshot($ros);
    $out = [];
    foreach ($devices as $d){
      $name = qname($d['mac']);
      $qq = $q[$name] ?? [];
      $out[] = [
        'mac'=>$d['mac'], 'ip'=>$d['ip'], 'name'=>$d['name'],
        'max_down_kbps'=>$qq['max_down_kbps']??0,
        'max_up_kbps'=>$qq['max_up_kbps']??0,
        'is_priority_device'=>isset($qq['priority']) ? (($qq['priority']??8) <= 2) : false,
        'rx_rate_kbps'=>array_key_exists('rx_rate_kbps',$qq) ? ($qq['rx_rate_kbps'] ?? 0) : null,
        'tx_rate_kbps'=>array_key_exists('tx_rate_kbps',$qq) ? ($qq['tx_rate_kbps'] ?? 0) : null,
      ];
    }
    ok(['devices'=>$out]);
  }

  case 'setlimit': {
    if ($method!=='POST') jerr('POST required',405);
    $mac = $input['mac'] ?? '';
    $down = max(0, (int)($input['down_kbps'] ?? 0));
    $up   = max(0, (int)($input['up_kbps'] ?? 0));
    $flag = !empty($input['is_priority_device']);
    if (!$mac) jerr('mac required');

    $devs = ros_list_devices($ros);
    $d = null; foreach ($devs as $x){ if (strcasecmp($x['mac'],$mac)===0){ $d=$x; break; } }
    if (!$d) jerr('Device not found on router',404);

    ensure_queue($ros, $d['mac'], $d['ip'], $down, $up, $flag?2:8);
    ok(['message'=>'Bandwidth limit saved']);
  }

  case 'clearlimit': {
    if ($method!=='POST') jerr('POST required',405);
    $mac = $input['mac'] ?? '';
    if (!$mac) jerr('mac required');
    clear_limit($ros,$mac); ok(['message'=>'Cleared']);
  }

  case 'getrealtime': {
    $q = ros_queue_snapshot($ros);
    $lines = [];
    foreach ($q as $name=>$r){
      $lines[] = sprintf("%s  Rx:%s kbps  Tx:%s kbps\n", $name, $r['rx_rate_kbps']??0, $r['tx_rate_kbps']??0);
    }
    ok(['lines'=>$lines]);
  }

  default: jerr('Unknown action',404);
}
