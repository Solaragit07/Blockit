<?php
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

function safeTalk($api, $cmd, $args=[]) {
  try { return $api->talk($cmd, $args); }
  catch (Throwable $e) { return null; }
}

function hasAnyRows($arr){
  if (!is_array($arr)) return false;
  foreach ($arr as $k=>$v) { if (is_array($v)) return true; }
  return false;
}

try {
  $api = new RouterOSClient($host,$port,$user,$pass,$timeout,$useTls);

  // 1) plain print (max compat)
  $ifs = safeTalk($api, '/interface/print');
  // 2) if empty, try without stats but with proplist (newer ROS)
  if (!hasAnyRows($ifs)) $ifs = safeTalk($api, '/interface/print', ['=.proplist=name,running,disabled,rx-byte,tx-byte,rx-bytes,tx-bytes']);
  // 3) try "print stats" variants (some ROS)
  if (!hasAnyRows($ifs)) {
    $ifs = safeTalk($api, '/interface/print', ['stats']);
    if (!hasAnyRows($ifs)) $ifs = safeTalk($api, '/interface/print', ['=stats=']);
  }
  // 4) some counters live under ethernet only
  if (!hasAnyRows($ifs)) {
    $ifs = safeTalk($api, '/interface/ethernet/print');
    if (!hasAnyRows($ifs)) $ifs = safeTalk($api, '/interface/ethernet/print', ['stats']);
  }

  // Normalize rows
  $out = [];
  if (is_array($ifs)) {
    foreach ($ifs as $row) {
      if (!is_array($row)) continue;
      // Skip non-record items if your client mixes in !done/etc
      if (isset($row['.tag']) && $row['.tag']==='done') continue;

      $name = $row['name'] ?? ($row['default-name'] ?? '');
      $running  = isset($row['running'])  ? ($row['running']==='true')   : null;
      $disabled = isset($row['disabled']) ? ($row['disabled']==='true')  : null;

      $rx = null; $tx = null;
      if (isset($row['rx-byte']))  $rx = (int)$row['rx-byte'];
      if (isset($row['tx-byte']))  $tx = (int)$row['tx-byte'];
      if ($rx===null && isset($row['rx-bytes'])) $rx = (int)$row['rx-bytes'];
      if ($tx===null && isset($row['tx-bytes'])) $tx = (int)$row['tx-bytes'];

      // Only emit rows that look like interfaces (have a name)
      if ($name !== '') {
        $out[] = [
          'name'     => $name,
          'running'  => (bool)$running,
          'disabled' => (bool)$disabled,
          'rx'       => $rx,
          'tx'       => $tx,
        ];
      }
    }
  }

  $api->close();
  jexit(['ok'=>true,'interfaces'=>$out]);
} catch (Throwable $e) {
  jexit(['ok'=>false,'message'=>'Router connection failed: '.$e->getMessage()]);
}
