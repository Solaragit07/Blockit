<?php
// /main/usage/API/time_limit_api.php
declare(strict_types=1);
header('Content-Type: application/json');

// Read session without locking (fast for many AJAX hits)
if (session_status() === PHP_SESSION_NONE) {
  @session_start(['read_and_close' => true]);
}

// ---------- Paths & includes ----------
$root     = dirname(__DIR__, 3);
$THIS_DIR = __DIR__;

$config = require $root . '/config/router.php';

// Try to load PDO (but work if it's missing)
$pdo = null;
$connectPath = $root . '/connectMySql.php';
if (is_file($connectPath)) {
  try { require_once $connectPath; } catch (\Throwable $e) {}
}
if (!($pdo ?? null) instanceof PDO) $pdo = null;

require_once $root . '/includes/routeros_client.php';

// ---------- Flat files ----------
$LOG_FILE   = $THIS_DIR . '/time_limit.log';
$STATE_FILE = $root . '/data/time_limits.json';
if (!is_dir($THIS_DIR)) @mkdir($THIS_DIR, 0755, true);
if (!is_dir(dirname($STATE_FILE))) @mkdir(dirname($STATE_FILE), 0755, true);

// ---------- Helpers ----------
function log_event(string $msg): void {
  global $LOG_FILE;
  @file_put_contents($LOG_FILE, '['.date('Y-m-d H:i:s')."] {$msg}\n", FILE_APPEND);
}
function now(): int { return time(); }
function load_state(): array {
  global $STATE_FILE;
  if (!is_file($STATE_FILE)) return [];
  $j = json_decode(@file_get_contents($STATE_FILE), true);
  return is_array($j) ? $j : [];
}
function save_state(array $s): void {
  global $STATE_FILE;
  @file_put_contents($STATE_FILE, json_encode($s, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}

/**
 * Resolve current user's email in this priority:
 *   1) X-User-Email header (JS sends this each call)
 *   2) Session keys (email/user_email/admin_email/nested)
 *   3) DB lookup by $_SESSION['user_id'] from admin table
 *   4) Fallback value (admin@blockit.site)
 */
function current_user_email($pdoOrNull, string $fallback='admin@blockit.site'): string {
  // 1) Header override
  $hdr = $_SERVER['HTTP_X_USER_EMAIL'] ?? '';
  if ($hdr && filter_var($hdr, FILTER_VALIDATE_EMAIL)) {
    log_event("current_user_email(): using header X-User-Email -> {$hdr}");
    return $hdr;
  }

  // 2) Session keys (check several)
  foreach ([
    $_SESSION['email']            ?? null,
    $_SESSION['user_email']       ?? null,
    $_SESSION['admin_email']      ?? null,
    $_SESSION['user']['email']    ?? null,
    $_SESSION['auth']['email']    ?? null,
  ] as $e) {
    if ($e && filter_var($e, FILTER_VALIDATE_EMAIL)) {
      log_event("current_user_email(): using session email -> {$e}");
      return $e;
    }
  }

  // 3) DB lookup
  if (!empty($_SESSION['user_id']) && $pdoOrNull instanceof PDO) {
    try {
      $stmt = $pdoOrNull->prepare('SELECT email FROM admin WHERE user_id = ? LIMIT 1');
      $stmt->execute([(int)$_SESSION['user_id']]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
        log_event("current_user_email(): using DB email -> {$row['email']}");
        return $row['email'];
      }
    } catch (\Throwable $e) {
      log_event('current_user_email(): DB lookup failed: '.$e->getMessage());
    }
  }

  // 4) Fallback
  log_event("current_user_email(): using fallback -> {$fallback}");
  return $fallback;
}

/** Send via PHP mail() (msmtp is your sendmail). */

// ---------- RouterOS ----------
function ros(): RouterOSClient {
  global $config;
  return new RouterOSClient(
    $config['host'], $config['api_port'],
    $config['user'], $config['pass'],
    (int)($config['timeout'] ?? 8),
    (bool)($config['api_tls'] ?? true)
  );
}

// ---------- MikroTik helpers ----------
const TL_COMMENT_PREFIX = 'TIME-LIMIT';

function find_lease_id_by_mac(RouterOSClient $api, string $mac): ?string {
  $mac = strtolower($mac);
  try {
    $rows = $api->talk('/ip/dhcp-server/lease/print', ['?mac-address='.$mac, '.proplist'=>'.id,mac-address']);
    foreach ((array)$rows as $r) {
      if (strtolower($r['mac-address'] ?? '') === $mac) return $r['id'] ?? null;
      if (isset($r['id'])) return $r['id'];
    }
  } catch (\Throwable $e) {}
  return null;
}
function find_lease_row_by_mac(RouterOSClient $api, string $mac): ?array {
  $mac = strtolower($mac);
  $rows = $api->talk('/ip/dhcp-server/lease/print', ['?mac-address='.$mac]);
  foreach ((array)$rows as $r) {
    if (strtolower(($r['mac-address'] ?? '')) === $mac) return $r;
  }
  return null;
}
function wifi_kick_if_wlan12(RouterOSClient $api, string $mac): void {
  $mac = strtolower($mac);
  try {
    $regs = $api->talk('/interface/wireless/registration-table/print', [
      '?mac-address='.$mac, '.proplist'=>'.id,interface,mac-address'
    ]);
    foreach ((array)$regs as $r) {
      $iface = $r['interface'] ?? '';
      if (($iface === 'wlan1' || $iface === 'wlan2') && !empty($r['id'])) {
        $api->talk('/interface/wireless/registration-table/remove', ['.id'=>$r['id']]);
        log_event("WiFi client $mac kicked from $iface for refresh");
      }
    }
  } catch (\Throwable $e) { log_event("WiFi kick error for $mac: ".$e->getMessage()); }
}
function ensure_block_rule(RouterOSClient $api, string $mac): void {
  $macLC = strtolower($mac);
  $macUC = strtoupper($mac);
  $rows = $api->talk('/ip/firewall/filter/print', ['.proplist'=>'.id,src-mac-address,comment,chain,action']) ?: [];
  foreach ((array)$rows as $r) {
    $ruleMac = $r['src-mac-address'] ?? '';
    $cmt     = $r['comment'] ?? '';
    $isTL    = stripos($cmt, 'TIME-LIMIT') !== false;
    $hit     = strcasecmp($ruleMac, $macLC) === 0 || strcasecmp($ruleMac, $macUC) === 0;
    if ($isTL && $hit) return;
  }
  $api->talk('/ip/firewall/filter/add', [
    'chain'=>'forward','action'=>'drop','src-mac-address'=>$macLC,
    'comment'=>TL_COMMENT_PREFIX." [$macLC]", 'disabled'=>'no',
  ]);
}
function debug_dump_time_limit_rules(RouterOSClient $api): void {
  try {
    $rows = $api->talk('/ip/firewall/filter/print', ['.proplist'=>'.id,chain,action,src-mac-address,comment']) ?: [];
    log_event('--- DEBUG: firewall filter snapshot start ---');
    foreach ((array)$rows as $r) {
      $id=$r['id']??''; $ch=$r['chain']??''; $ac=$r['action']??'';
      $mac=$r['src-mac-address']??''; $c=$r['comment']??'';
      if (stripos($c,'TIME-LIMIT')!==false || stripos($mac,':')!==false) {
        log_event("id=$id chain=$ch act=$ac mac=$mac cmt=\"$c\"");
      }
    }
    log_event('--- DEBUG: firewall filter snapshot end ---');
  } catch (\Throwable $e) { log_event('DEBUG dump error: '.$e->getMessage()); }
}
function remove_block_rule(RouterOSClient $api, string $mac): int {
  $macUC = strtoupper($mac);
  $macLC = strtolower($mac);
  $removed = 0; $found = [];
  debug_dump_time_limit_rules($api);
  $rows = $api->talk('/ip/firewall/filter/print', ['.proplist'=>'.id,src-mac-address,comment,chain,action']) ?: [];
  foreach ((array)$rows as $r) {
    $id = $r['id'] ?? ''; $cmt=(string)($r['comment']??''); $rm=(string)($r['src-mac-address']??'');
    $isTL = stripos($cmt,'TIME-LIMIT')!==false;
    $hit  = (strcasecmp($rm,$macUC)===0) || (strcasecmp($rm,$macLC)===0);
    if ($id !== '' && ($isTL || $hit)) { $found[]=['id'=>$id,'mac'=>$rm,'cmt'=>$cmt]; log_event("Matched TIME-LIMIT candidate: id=$id mac=$rm cmt=\"$cmt\""); }
  }
  if (!$found) { log_event("No TIME-LIMIT candidates found for $mac (by comment or mac)"); return 0; }
  foreach ($found as $f) {
    try { $api->talk('/ip/firewall/filter/remove', ['.id'=>$f['id']]); $removed++; log_event("Removed by .id successfully: id={$f['id']}"); }
    catch (\Throwable $e) { log_event("Remove by .id failed for id={$f['id']}: ".$e->getMessage()); }
  }
  $still = [];
  $rows2 = $api->talk('/ip/firewall/filter/print', ['.proplist'=>'.id,comment,src-mac-address']) ?: [];
  $existingIds = array_column((array)$rows2, 'id');
  foreach ($found as $f) { if (in_array($f['id'], (array)$existingIds, true)) $still[]=$f['id']; }
  foreach ($still as $n) {
    try { $api->talk('/ip/firewall/filter/remove', ['numbers'=>$n]); $removed++; log_event("Removed by numbers successfully: numbers=$n"); }
    catch (\Throwable $e) { log_event("Remove by numbers failed: numbers=$n error=".$e->getMessage()); }
  }
  try {
    $rows3 = $api->talk('/ip/firewall/filter/print', ['?comment~=TIME-LIMIT', '.proplist'=>'.id,src-mac-address,comment']) ?: [];
    if ($rows3) foreach ((array)$rows3 as $r) log_event("Still present after removal: id={$r['id']} mac={$r['src-mac-address']} cmt=\"{$r['comment']}\"");
    else log_event("No TIME-LIMIT rules remain after removal attempt.");
  } catch (\Throwable $e) { log_event("Final snapshot failed: ".$e->getMessage()); }
  return $removed;
}
function block_device(string $mac): void {
  $api = ros();
  try {
    if ($id = find_lease_id_by_mac($api, $mac)) {
      $api->talk('/ip/dhcp-server/lease/set', ['.id'=>$id, 'disabled'=>'yes']);
      log_event("Disabled DHCP lease for $mac (.id=$id)");
    }
    ensure_block_rule($api, $mac);
  } finally { $api->close(); }
}
function flush_arp_and_conn(RouterOSClient $api, ?string $ip, ?string $mac): void {
  try {
    if ($mac) {
      $arp = $api->talk('/ip/arp/print', ['?mac-address='.strtolower($mac), '.proplist'=>'.id']) ?: [];
      foreach ((array)$arp as $r) if (!empty($r['id'])) $api->talk('/ip/arp/remove', ['.id'=>$r['id']]);
    }
    if ($ip) {
      $arp = $api->talk('/ip/arp/print', ['?address='.$ip, '.proplist'=>'.id']) ?: [];
      foreach ((array)$arp as $r) if (!empty($r['id'])) $api->talk('/ip/arp/remove', ['.id'=>$r['id']]);
    }
  } catch (\Throwable $e) {}
  try {
    if ($ip) {
      $rows = $api->talk('/ip/firewall/connection/print', ['?src-address='.$ip, '.proplist'=>'.id']) ?: [];
      foreach ((array)$rows as $r) if (!empty($r['id'])) $api->talk('/ip/firewall/connection/remove', ['.id'=>$r['id']]);
      $rows = $api->talk('/ip/firewall/connection/print', ['?dst-address='.$ip, '.proplist'=>'.id']) ?: [];
      foreach ((array)$rows as $r) if (!empty($r['id'])) $api->talk('/ip/firewall/connection/remove', ['.id'=>$r['id']]);
    }
  } catch (\Throwable $e) {}
}
function renew_lease_if_possible(RouterOSClient $api, ?string $leaseId): void {
  if (!$leaseId) return;
  try { $api->talk('/ip/dhcp-server/lease/renew', ['.id'=>$leaseId]); } catch (\Throwable $e) {}
}
function unblock_device(string $mac): void {
  $api = ros();
  try {
    try {
      $idRow = $api->talk('/system/identity/print', ['.proplist'=>'name']);
      $idName = is_array($idRow) && isset($idRow[0]['name']) ? $idRow[0]['name'] : json_encode($idRow);
      log_event("Router identity: ".$idName);
    } catch (\Throwable $e) { log_event("Router identity query failed: ".$e->getMessage()); }

    $row     = find_lease_row_by_mac($api, $mac);
    $leaseId = $row['id'] ?? null;
    $ip      = $row['address'] ?? null;

    if ($leaseId) {
      $api->talk('/ip/dhcp-server/lease/set', ['.id'=>$leaseId, 'disabled'=>'no']);
      log_event("Re-enabled DHCP lease for $mac (.id=$leaseId)");
    }
    $removed = remove_block_rule($api, $mac);
    log_event("Removed $removed TIME-LIMIT firewall rule(s) for $mac");
    flush_arp_and_conn($api, $ip, $mac);
    wifi_kick_if_wlan12($api, $mac);
    renew_lease_if_possible($api, $leaseId);
  } finally { $api->close(); }
}

/** Accrue time and send notice when limit hit. */
function accrue_usage(array &$state, string $mac, $pdo): void {
  $mac = strtolower($mac);
  $rec = $state[$mac] ?? null;
  if (!$rec || ($rec['status'] ?? 'active') !== 'active') return;

  $last = (int)($rec['last_ts'] ?? 0);
  $now  = now();
  $deltaSec = max(0, $now - $last);
  if ($last === 0) { $state[$mac]['last_ts'] = $now; return; }
  $addMin = $deltaSec / 60.0;
  if ($addMin < 0.01) { $state[$mac]['last_ts'] = $now; return; }

  $state[$mac]['used']    = round(($rec['used'] ?? 0) + $addMin, 2);
  $state[$mac]['last_ts'] = $now;

  $remain = ($rec['minutes'] ?? 0) - ($state[$mac]['used'] ?? 0);
  if ($remain <= 5 && empty($rec['alerted'])) {
    $state[$mac]['alerted'] = true;
    log_event("Pre-alert for $mac (<=5 min left)");
  }
  if ($remain <= 0 && ($rec['status'] ?? 'active') === 'active') {
    try { block_device($mac); } catch (\Throwable $e) { log_event("Error blocking $mac: ".$e->getMessage()); }
    $state[$mac]['status'] = 'blocked';
    log_event("Device $mac blocked due to time limit");

    // Email to resolved user
    $to   = current_user_email($pdo, 'admin@blockit.site');
    $mins = $state[$mac]['minutes'] ?? 0;
    $body = "Heads up!\n\nDevice {$mac} reached its time limit of {$mins} minutes and was blocked.\n\n— BlockIT";
    $ok   = send_notice($to, 'BlockIT: device blocked (time limit)', $body);
    log_event($ok ? "Block email queued to {$to} for {$mac}" : "Block email FAILED to {$to} for {$mac}");
  }
}

// ---------- API key auth ----------
if (($_SERVER['HTTP_X_API_KEY'] ?? '') !== ($config['api_key'] ?? '')) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'message'=>'Unauthorized']); exit;
}

// ---------- Main ----------
$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$state  = load_state();

switch ($action) {
  case 'whoami': {
    $email = current_user_email($pdo, 'admin@blockit.site');
    echo json_encode(['ok'=>true,'email'=>$email,'sid'=>session_id(),'keys'=>array_keys($_SESSION ?? [])]); exit;
  }

  case 'getDevices': {
    try {
      $api  = ros();
      $wlan = $api->talk('/interface/wireless/registration-table/print');
      $dhcp = $api->talk('/ip/dhcp-server/lease/print', ['.proplist'=>'mac-address,host-name,address']);
      $api->close();

      $wifi = [];
      foreach ((array)$wlan as $w) {
        $mac = strtolower($w['mac-address'] ?? ''); if (!$mac) continue;
        $iface = $w['interface'] ?? '';
        if ($iface !== 'wlan1' && $iface !== 'wlan2') continue;
        $wifi[$mac] = ['mac'=>$mac, 'name'=>$w['interface'] ?? 'WiFi Client', 'ip'=>null, 'status'=>'active', 'used'=>0];
      }
      foreach ((array)$dhcp as $r) {
        $m = strtolower($r['mac-address'] ?? ''); if (!$m || !isset($wifi[$m])) continue;
        $wifi[$m]['ip']   = $r['address']   ?? $wifi[$m]['ip'];
        $wifi[$m]['name'] = $r['host-name'] ?? $wifi[$m]['name'];
        if (isset($state[$m])) {
          $wifi[$m]['type']    = $state[$m]['type']    ?? null;
          $wifi[$m]['minutes'] = $state[$m]['minutes'] ?? null;
          $wifi[$m]['used']    = round($state[$m]['used'] ?? 0, 2);
          $wifi[$m]['status']  = $state[$m]['status']  ?? 'active';
        }
      }
      echo json_encode(['ok'=>true,'devices'=>array_values($wifi)]); exit;
    } catch (\Throwable $e) {
      echo json_encode(['ok'=>false,'message'=>$e->getMessage()]); exit;
    }
  }

  case 'setLimit': {
    $mac     = strtolower(trim($input['mac'] ?? ''));
    $type    = $input['type'] ?? 'daily';
    $minutes = (int)($input['minutes'] ?? 0);
    if (!$mac || $minutes < 1) { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Invalid params']); exit; }
    $state[$mac] = [
      'type'=>$type, 'minutes'=>$minutes, 'used'=>0.0,
      'status'=>'active', 'alerted'=>false, 'last_ts'=>now(),
    ];
    save_state($state);
    log_event("Set {$type} limit {$minutes} min for {$mac}");
    echo json_encode(['ok'=>true,'message'=>"Limit set for $mac"]); exit;
  }

  case 'tickUsage': {
    foreach ($state as $m=>$rec) {
      if (($rec['status'] ?? 'active') !== 'active') continue;
      $state[$m]['used'] = round(($rec['used'] ?? 0) + 1, 2);
      $remain = ($rec['minutes'] ?? 0) - ($state[$m]['used'] ?? 0);
      if ($remain <= 5 && empty($rec['alerted'])) {
        $state[$m]['alerted'] = true;
        log_event("Pre-alert for $m (<=5 min left)");
      }
      if ($remain <= 0) {
        try { block_device($m); } catch (\Throwable $e) { log_event("Error blocking $m: ".$e->getMessage()); }
        $state[$m]['status'] = 'blocked';
        log_event("Device $m blocked via tickUsage");

        $to   = current_user_email($pdo, 'admin@blockit.site');
        $mins = $state[$m]['minutes'] ?? 0;
        $body = "Heads up!\n\nDevice {$m} reached its time limit of {$mins} minutes and was blocked.\n\n— BlockIT";
        $ok   = send_notice($to, 'BlockIT: device blocked (time limit)', $body);
        log_event($ok ? "Block email queued to {$to} for {$m}" : "Block email FAILED to {$to} for {$m}");
      }
    }
    save_state($state);
    echo json_encode(['ok'=>true,'message'=>'Tick updated']); exit;
  }

  case 'pulse': {
    foreach (array_keys($state) as $m) accrue_usage($state, $m, $pdo);
    save_state($state);
    $out = [];
    foreach ($state as $m=>$rec) {
      $out[] = [
        'mac'=>$m,
        'type'=>$rec['type']??null,
        'minutes'=>$rec['minutes']??null,
        'used'=>round($rec['used'] ?? 0, 2),
        'status'=>$rec['status'] ?? 'active',
      ];
    }
    echo json_encode(['ok'=>true,'state'=>$out]); exit;
  }

  case 'getState': {
    $out = [];
    foreach ($state as $m=>$rec) {
      $out[] = [
        'mac'=>$m,'type'=>$rec['type']??null,'minutes'=>$rec['minutes']??null,
        'used'=>round($rec['used'] ?? 0, 2),'status'=>$rec['status'] ?? 'active',
      ];
    }
    echo json_encode(['ok'=>true,'state'=>$out]); exit;
  }

  case 'unblock': {
    $mac = strtolower(trim($input['mac'] ?? ''));
    if (!$mac) { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'MAC required']); exit; }
    try { unblock_device($mac); } catch (\Throwable $e) { log_event("Error unblocking $mac: ".$e->getMessage()); }
    if (isset($state[$mac])) unset($state[$mac]);
    save_state($state);
    log_event("Device $mac unblocked (timer cleared)");
    echo json_encode(['ok'=>true,'message'=>'Device unblocked and timer cleared']); exit;
  }

  case 'block': {
    $mac = strtolower(trim($input['mac'] ?? ''));
    if (!$mac) { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'MAC required']); exit; }
    try { block_device($mac); } catch (\Throwable $e) { log_event("Error blocking $mac: ".$e->getMessage()); }
    $rec = $state[$mac] ?? [
      'type'=>null, 'minutes'=>null, 'used'=>0.0,
      'status'=>'active', 'alerted'=>false, 'last_ts'=>now(),
    ];
    $rec['status'] = 'blocked';
    $rec['last_ts'] = now();
    $state[$mac] = $rec;
    save_state($state);
    log_event("Device $mac blocked manually");
    echo json_encode(['ok'=>true,'message'=>'Device blocked']); exit;
  }

  case 'getLogs': {
    $lines = is_file($LOG_FILE) ? file($LOG_FILE) : [];
    echo json_encode(['ok'=>true,'logs'=>$lines]); exit;
  }
}

echo json_encode(['ok'=>false,'message'=>'Unknown action']);
