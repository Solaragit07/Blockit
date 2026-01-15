<?php
// /public_html/main/blocklist/api/profiles_delete.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

function jexit($x,$c=200){ http_response_code($c); echo json_encode($x, JSON_UNESCAPED_SLASHES); exit; }

$APP_ROOT = dirname(__DIR__, 3);
$cfg_path = $APP_ROOT.'/config/router.php';
$cli_path = $APP_ROOT.'/includes/routeros_client.php';
$lv_path  = $APP_ROOT.'/loginverification.php';
$data_dir = $APP_ROOT.'/data';
$file     = $data_dir.'/profiles.json';

if (!is_dir($data_dir)) @mkdir($data_dir,0775,true);
if (!file_exists($file)) file_put_contents($file,'[]');

if (!file_exists($cfg_path)) jexit(['ok'=>false,'message'=>'Config missing'],500);
if (!file_exists($cli_path)) jexit(['ok'=>false,'message'=>'RouterOS client missing'],500);
require_once $cli_path;
$config = require $cfg_path;

/* ---------- Auth ---------- */
$providedApiKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
$configApiKey   = trim((string)($config['api_key'] ?? getenv('BLOCKIT_API_KEY') ?? ''));
$authed = ($configApiKey !== '' && hash_equals($configApiKey, $providedApiKey));
if (!$authed && file_exists($lv_path)) { require_once $lv_path; if (function_exists('require_login')) { ob_start(); require_login(); ob_end_clean(); $authed = true; } }
if (!$authed && session_status()===PHP_SESSION_NONE) session_start();
if (!$authed && !empty($_SESSION['user_id'])) $authed = true;
if (!$authed) jexit(['ok'=>false,'message'=>'Not authenticated'],401);

/* ---------- Method & body ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jexit(['ok'=>false,'message'=>'Invalid method; use POST'],405);
$raw = file_get_contents('php://input') ?: '';
if ($raw) { $j = json_decode($raw, true); if (json_last_error()===JSON_ERROR_NONE && is_array($j)) $_POST = $j + $_POST; }

$name = trim((string)($_POST['name'] ?? ''));
if ($name === '') jexit(['ok'=>false,'message'=>'name required'],400);

/* ---------- Load profiles ---------- */
$profiles = json_decode((string)@file_get_contents($file), true);
if (!is_array($profiles)) $profiles = [];

/* ---------- Delete by name (case-insensitive) ---------- */
$before = count($profiles);
$profiles = array_values(array_filter($profiles, function($p) use ($name){
  return strcasecmp((string)($p['name'] ?? ''), $name) !== 0;
}));
$after = count($profiles);
if ($before === $after){
  // Not found, still ok to return success (idempotent)
  jexit(['ok'=>true,'message'=>'Profile not found (nothing to delete)','profiles'=>$profiles]);
}

/* ---------- Save updated list ---------- */
file_put_contents($file, json_encode($profiles, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);

/* ---------- Recompute desired address-lists from remaining profiles ---------- */
function normalize_mac($m){
  $m = trim((string)$m);
  $hex = preg_replace('/[^0-9a-fA-F]/','', $m);
  if ($hex === null || strlen($hex)!==12) return '';
  $hex = strtoupper($hex);
  return implode(':', str_split($hex,2));
}

$routerHost = (string)($config['host'] ?? '');
$apiPort    = (int)   ($config['api_port'] ?? 8729);
$useTls     = (bool)  ($config['api_tls'] ?? true);
$user       = (string)($config['user'] ?? '');
$pass       = (string)($config['pass'] ?? '');
$timeout    = (int)   ($config['timeout'] ?? 8);

try { $api = new RouterOSClient($routerHost,$apiPort,$user,$pass,$timeout,$useTls); }
catch (Throwable $e) {
  try { $api = new RouterOSClient($routerHost,8728,$user,$pass,$timeout,false); }
  catch (Throwable $e2) {
    jexit(['ok'=>true,'message'=>'Profile deleted locally; router unreachable (will re-sync later)','profiles'=>$profiles]);
  }
}

/* Build group→MAC set from remaining profiles */
$groupMacs = ['over18'=>[], 'under18'=>[]];
foreach ($profiles as $p){
  $g = strtolower($p['group'] ?? '');
  if (!isset($groupMacs[$g])) continue;
  foreach ((array)$p['macs'] as $m) {
    $nm = normalize_mac($m);
    if ($nm !== '') $groupMacs[$g][$nm]=true;
  }
}

/* Map current MAC→IP (ARP & DHCP) */
$arp    = (array)$api->talk('/ip/arp/print');
$leases = (array)$api->talk('/ip/dhcp-server/lease/print');
$macToIp = [];
foreach ($arp as $r){
  $ip  = $r['address'] ?? '';
  $mac = isset($r['mac-address']) ? normalize_mac($r['mac-address']) : '';
  if ($ip && $mac) $macToIp[$mac] = $ip;
}
foreach ($leases as $l){
  $ip  = $l['address'] ?? '';
  $mac = isset($l['mac-address']) ? normalize_mac($l['mac-address']) : '';
  if ($ip && $mac && empty($macToIp[$mac])) $macToIp[$mac] = $ip;
}

/* Desired IPs per group (remaining profiles only) */
$want = ['over18'=>[], 'under18'=>[]];
foreach (['over18','under18'] as $g){
  foreach (array_keys($groupMacs[$g]) as $mac){
    if (!empty($macToIp[$mac])) $want[$g][strtolower($macToIp[$mac])] = true;
  }
}

/* Current address-lists snapshot */
$fwAddr = (array)$api->talk('/ip/firewall/address-list/print', ['.proplist'=>'.id,list,address,comment']);

/* Sync: remove extras automatically */
function sync_list($api, $fwAddr, $listName, $wantedIps, $commentTag){
  $have = [];
  foreach ($fwAddr as $r){
    if (strtolower($r['list']??'') === strtolower($listName)){
      $addr = strtolower($r['address'] ?? '');
      $have[$addr] = $r['id'] ?? null;
    }
  }
  // Add missing
  foreach (array_keys($wantedIps) as $ip){
    if ($ip === '') continue;
    if (!isset($have[$ip])){
      $api->talk('/ip/firewall/address-list/add', [
        'list'=>$listName, 'address'=>$ip, 'comment'=>$commentTag
      ]);
    }
  }
  // Remove extras
  foreach ($have as $ip=>$id){
    if (!isset($wantedIps[$ip]) && $id){
      $api->talk('/ip/firewall/address-list/remove', ['.id'=>$id]);
    }
  }
}

sync_list($api,$fwAddr,'profile:over18',$want['over18'], 'group:over18');
sync_list($api,$fwAddr,'profile:under18',$want['under18'],'group:under18');

if (method_exists($api,'close')) $api->close();

jexit([
  'ok'=>true,
  'message'=>'Profile deleted & group IPs re-synced',
  'profiles'=>$profiles,
  'synced'=>[
    'over18'=>array_keys($want['over18']),
    'under18'=>array_keys($want['under18'])
  ]
]);
