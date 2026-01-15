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

try {
  $api = new RouterOSClient($host,$port,$user,$pass,$timeout,$useTls);
  $rows = $api->talk('/ip/firewall/address-list/print');
  $api->close();
  $block=[]; $white=[];
  if (is_array($rows)) {
    foreach($rows as $r){
      $entry = [
        '.id' => $r['.id'] ?? null,
        'address' => $r['address'] ?? '',
        'comment' => $r['comment'] ?? ''
      ];
      if (($r['list'] ?? '') === 'blocklist') $block[] = $entry;
      if (($r['list'] ?? '') === 'whitelist') $white[] = $entry;
    }
  }
  jexit(['ok'=>true,'blocklist'=>$block,'whitelist'=>$white]);
} catch (Throwable $e) {
  jexit(['ok'=>false,'message'=>'Router connection failed: '.$e->getMessage()]);
}
