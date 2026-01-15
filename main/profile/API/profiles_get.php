<?php
// /public_html/main/blocklist/api/profiles_get.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

function jexit($x,$c=200){ http_response_code($c); echo json_encode($x, JSON_UNESCAPED_SLASHES); exit; }

$APP_ROOT = dirname(__DIR__, 3);
$cfg_path = $APP_ROOT.'/config/router.php';
$lv_path  = $APP_ROOT.'/loginverification.php';
$data_dir = $APP_ROOT.'/data';
$file     = $data_dir.'/profiles.json';

if (!is_dir($data_dir)) @mkdir($data_dir,0775,true);
if (!file_exists($file)) file_put_contents($file,'[]');

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
