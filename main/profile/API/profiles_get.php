<?php
// /public_html/main/blocklist/api/profiles_get.php
ini_set('display_errors',0); ini_set('display_startup_errors',0); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

function jexit($x,$c=200){ http_response_code($c); echo json_encode($x, JSON_UNESCAPED_SLASHES); exit; }

function resolve_data_dir(string $appRoot): array {
	$candidates = [];
	$env = getenv('BLOCKIT_DATA_DIR');
	if ($env) $candidates[] = rtrim($env, '/\\');
	$candidates[] = $appRoot . '/data';
	$candidates[] = rtrim(sys_get_temp_dir(), '/\\') . '/blockit';

	foreach ($candidates as $dir) {
		if (!is_dir($dir)) @mkdir($dir, 0775, true);
		if (is_dir($dir) && is_writable($dir)) {
			return [$dir, $dir . '/profiles.json'];
		}
	}
	return [null, null];
}

$APP_ROOT = dirname(__DIR__, 3);
$cfg_path = $APP_ROOT.'/config/router.php';
$lv_path  = $APP_ROOT.'/loginverification.php';
[$data_dir, $file] = resolve_data_dir($APP_ROOT);
if (!$data_dir || !$file) jexit(['ok'=>false,'message'=>'Profiles storage not writable'],500);
if (!file_exists($file)) {
	if (@file_put_contents($file,'[]', LOCK_EX) === false) {
		jexit(['ok'=>false,'message'=>'Unable to initialize profiles storage'],500);
	}
}

$config = file_exists($cfg_path) ? require $cfg_path : [];

// Auth (API key or session)
$providedApiKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
$configApiKey   = trim((string)($config['api_key'] ?? getenv('BLOCKIT_API_KEY') ?? ''));
$authed = ($configApiKey !== '' && hash_equals($configApiKey, $providedApiKey));
if (!$authed && file_exists($lv_path)) { require_once $lv_path; if (function_exists('require_login')) { ob_start(); require_login(); ob_end_clean(); $authed = true; } }
if (!$authed && session_status()===PHP_SESSION_NONE) session_start();
if (!$authed && !empty($_SESSION['user_id'])) $authed = true;
if (!$authed) jexit(['ok'=>false,'message'=>'Not authenticated'],401);

$profiles = json_decode((string)@file_get_contents($file), true);
if (!is_array($profiles)) $profiles = [];
jexit(['ok'=>true,'profiles'=>$profiles]);
