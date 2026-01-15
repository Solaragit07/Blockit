<?php
// Return both IP-based and Domain-based rules for a given group
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
function jexit($a){ echo json_encode($a); exit; }

$APP_ROOT = dirname(__DIR__, 3);
$config_file = $APP_ROOT . '/config/router.php';
$client_file = $APP_ROOT . '/includes/routeros_client.php';
if (!file_exists($config_file)) jexit(['ok'=>false,'message'=>'config/router.php missing']);
if (!file_exists($client_file)) jexit(['ok'=>false,'message'=>'includes/routeros_client.php missing']);
$config = require $config_file;
require $client_file;

/* ------------ input ------------ */
$group = strtolower(trim((string)($_GET['group'] ?? $_POST['group'] ?? '')));
if ($group !== 'over18' && $group !== 'under18') {
  jexit(['ok'=>false,'message'=>'group must be over18 or under18']);
}

$LIST_BLOCK_IP = "block_{$group}_ip";
$LIST_WHITE_IP = "whitelist_{$group}_ip";
$SINKHOLE_IP   = "127.0.0.1";  // where we redirect blocked domains

$out = [
  'ok'     => true,
  'group'  => $group,
  'blocks' => ['ip'=>[], 'domain'=>[]],
  'whites' => ['ip'=>[], 'domain'=>[]]
];

try {
  $api = new RouterOSClient(
    $config['host'],$config['api_port'],$config['user'],$config['pass'],
    $config['timeout'] ?? 8, $config['api_tls'] ?? true
  );

  /* === BLOCK IPs === */
  $rows = $api->talk('/ip/firewall/address-list/print', ['?list='.$LIST_BLOCK_IP]);
  foreach ($rows as $r) {
    $out['blocks']['ip'][] = [
      'id'      => $r['.id'] ?? '',
      'address' => $r['address'] ?? '',
      'comment' => $r['comment'] ?? ''
    ];
  }

  /* === WHITELIST IPs === */
  $rows = $api->talk('/ip/firewall/address-list/print', ['?list='.$LIST_WHITE_IP]);
  foreach ($rows as $r) {
    $out['whites']['ip'][] = [
      'id'      => $r['.id'] ?? '',
      'address' => $r['address'] ?? '',
      'comment' => $r['comment'] ?? ''
    ];
  }

  /* === BLOCK DOMAINS (sinkholed to 127.0.0.1) === */
  $rows = $api->talk('/ip/dns/static/print', ['?address='.$SINKHOLE_IP]);
  foreach ($rows as $r) {
    $out['blocks']['domain'][] = [
      'id'   => $r['.id'] ?? '',
      'host' => $r['name'] ?? '',
      'type' => 'A',
      'to'   => $r['address'] ?? '',
      'comment' => $r['comment'] ?? ''
    ];
  }

  /* === OPTIONAL: WHITELIST DOMAINS === */
  // If you want explicit domain whitelists, you can store them in a db
  // or use a different RouterOS list. For now, we return empty.
  $out['whites']['domain'] = [];

  $api->close();
  jexit($out);

} catch (Throwable $e) {
  jexit(['ok'=>false,'message'=>'Router error: '.$e->getMessage()]);
}
