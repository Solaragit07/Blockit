<?php
// /main/ecommerce/API/ping_router.php
// Simple RouterOS connectivity check (auth + socket)

declare(strict_types=1);
header('Content-Type: application/json');

// PHP < 8 compatibility
if (!function_exists('str_starts_with')) {
  function str_starts_with(string $haystack, string $needle): bool {
    return $needle === '' || strpos($haystack, $needle) === 0;
  }
}

$ROOT = dirname(__DIR__, 3); // .../public_html
$login = $ROOT . '/loginverification.php';
if (is_file($login)) {
  require_once $login;
}

$isLoggedIn = function_exists('logged_in') ? logged_in() : (isset($_SESSION['user_id']) && !empty($_SESSION['user_id']));
$config = require $ROOT . '/config/router.php';
require_once $ROOT . '/includes/routeros_client.php';

$hdrKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
$cfgKey = trim((string)($config['api_key'] ?? getenv('BLOCKIT_API_KEY') ?? ''));

$isKeyOk = ($cfgKey !== '' && hash_equals($cfgKey, $hdrKey));
if (!$isLoggedIn && !$isKeyOk) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'message'=>'Unauthorized']);
  exit;
}

try {
  $api = new RouterOSClient(
    (string)$config['host'],
    (int)($config['api_port'] ?? 8729),
    (string)$config['user'],
    (string)$config['pass'],
    (int)($config['timeout'] ?? 8),
    (bool)($config['api_tls'] ?? true)
  );

  // light-weight checks
  $identity = $api->talk('/system/identity/print');
  $resource = $api->talk('/system/resource/print');
  $api->close();

  $name = $identity[0]['name'] ?? 'unknown';
  $version = $resource[0]['version'] ?? 'unknown';
  $uptime = $resource[0]['uptime'] ?? 'unknown';

  echo json_encode([
    'ok' => true,
    'message' => 'Router reachable',
    'identity' => $name,
    'version' => $version,
    'uptime' => $uptime,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'message' => 'RouterOS error: ' . $e->getMessage(),
  ]);
}
