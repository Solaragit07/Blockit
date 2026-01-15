<?php
/**
 * /public_html/main/blocklist/api/set_up_group_policies.php
 *
 * One-time (idempotent) installer for BlockIT age-group policies on MikroTik.
 * Creates per-group base rules in the forward chain:
 *  - ACCEPT (IP path): src-address-list=profile:<group> AND dst-address-list=whitelist:<group>
 *  - DROP   (IP path): src-address-list=profile:<group> AND dst-address-list=blocklist:<group>
 *
 * Domain-based rules (tls-host + src-address-list) are created by your group_add_* endpoints.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

function jexit($x, $c = 200){ http_response_code($c); echo json_encode($x, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT); exit; }

$APP_ROOT = dirname(__DIR__, 3);
$cfg_path = $APP_ROOT . '/config/router.php';
$cli_path = $APP_ROOT . '/includes/routeros_client.php';
$lv_path  = $APP_ROOT . '/loginverification.php';

if (!file_exists($cfg_path)) jexit(['ok'=>false,'message'=>'Config file missing'], 500);
if (!file_exists($cli_path)) jexit(['ok'=>false,'message'=>'RouterOS client missing'], 500);

require_once $cli_path;
$config = require $cfg_path;

/* ---- Auth (API key or require_login/session) ---- */
$providedApiKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
$configApiKey   = trim((string)($config['api_key'] ?? getenv('BLOCKIT_API_KEY') ?? ''));
$authed = ($configApiKey !== '' && hash_equals($configApiKey, $providedApiKey));
if (!$authed && file_exists($lv_path)) {
  require_once $lv_path;
  if (function_exists('require_login')) { ob_start(); require_login(); ob_end_clean(); $authed = true; }
}
if (!$authed && session_status() === PHP_SESSION_NONE) session_start();
if (!$authed && !empty($_SESSION['user_id'])) $authed = true;
if (!$authed) jexit(['ok'=>false,'message'=>'Not authenticated'], 401);

/* ---- Router connect ---- */
$routerHost = (string)($config['host'] ?? '');
$apiPort    = (int)   ($config['api_port'] ?? 8729);
$useTls     = (bool)  ($config['api_tls'] ?? true);
$user       = (string)($config['user'] ?? 'api-dashboard');
$pass       = (string)($config['pass'] ?? '');
$timeout    = (int)   ($config['timeout'] ?? 8);

try { $api = new RouterOSClient($routerHost, $apiPort, $user, $pass, $timeout, $useTls); }
catch (Throwable $e) {
  try { $api = new RouterOSClient($routerHost, 8728, $user, $pass, $timeout, false); }
  catch (Throwable $e2) { jexit(['ok'=>false,'message'=>'Router connection failed'], 502); }
}

/* ---- Helpers ---- */
function load_filter_rules($api){
  // Get entire forward chain for inspection (proplist must include the fields we match on)
  return (array)$api->talk('/ip/firewall/filter/print', ['.proplist'=>'.id,chain,action,src-address-list,dst-address-list,protocol,tls-host,comment,disabled']);
}

/**
 * Find an existing rule by exact match of key fields we care about.
 * Matching keys: chain, action, src-address-list, dst-address-list (tls-host not used here).
 */
function rule_exists(array $rules, array $needle): ?string {
  foreach ($rules as $r){
    if (($r['chain'] ?? '') !== ($needle['chain'] ?? '')) continue;
    if (strtolower($r['action'] ?? '') !== strtolower($needle['action'] ?? '')) continue;
    if (($r['src-address-list'] ?? '') !== ($needle['src-address-list'] ?? '')) continue;
    if (($r['dst-address-list'] ?? '') !== ($needle['dst-address-list'] ?? '')) continue;
    // protocol is not set for IP path rules on purpose
    return $r['.id'] ?? null;
  }
  return null;
}

/**
 * Add rule near the top of forward chain.
 * If rules exist, we try to place-before the first rule id; else add simply.
 */
function add_rule_top($api, array $fields): string {
  // Try to fetch first rule's id to place before
  $all = (array)$api->talk('/ip/firewall/filter/print', ['.proplist'=>'.id,chain', '?chain=forward']);
  $placeBefore = null;
  if (!empty($all) && is_array($all[0]) && ($all[0]['.id'] ?? null)) {
    $placeBefore = $all[0]['.id'];
  }
  if ($placeBefore){
    $fields['place-before'] = $placeBefore;
  }
  $resp = (array)$api->talk('/ip/firewall/filter/add', $fields);
  // RouterOS returns nothing useful on success; return a label
  return 'added';
}

/* ---- Desired base rules per group ---- */
$groups = ['over18','under18'];
$planned   = [];
$ensured   = [];
$skipped   = [];
$errors    = [];

try {
  $rules = load_filter_rules($api);

  foreach ($groups as $g){
    $profileList  = "profile:$g";
    $whiteListIP  = "whitelist:$g";
    $blockListIP  = "blocklist:$g";

    // 1) ACCEPT if src in profile:<g> AND dst in whitelist:<g>
    $acceptIpRule = [
      'chain'            => 'forward',
      'action'           => 'accept',
      'src-address-list' => $profileList,
      'dst-address-list' => $whiteListIP,
      // comment helps idempotence/readability
      'comment'          => "BlockIT group policy: $g accept ip whitelist",
    ];

    // 2) DROP if src in profile:<g> AND dst in blocklist:<g>
    $dropIpRule = [
      'chain'            => 'forward',
      'action'           => 'drop',
      'src-address-list' => $profileList,
      'dst-address-list' => $blockListIP,
      'comment'          => "BlockIT group policy: $g drop ip blocklist",
    ];

    foreach ([ $acceptIpRule, $dropIpRule ] as $want){
      $needle = $want; unset($needle['comment']); // comments can change; don't use for matching
      $haveId = rule_exists($rules, $needle);
      $planned[] = $want['comment'];

      if ($haveId){
        $skipped[] = $want['comment'].' (exists)';
      } else {
        // ensure add near top
        add_rule_top($api, $want);
        $ensured[] = $want['comment'];
        // refresh rules cache so subsequent place-before uses updated list
        $rules = load_filter_rules($api);
      }
    }
  }

  if (method_exists($api,'close')) $api->close();

  jexit([
    'ok'      => true,
    'message' => 'Group policies ensured',
    'ensured' => $ensured,
    'skipped' => $skipped,
    'planned' => $planned
  ]);
} catch (Throwable $e){
  if (method_exists($api,'close')) $api->close();
  jexit(['ok'=>false,'message'=>'Router operation failed: '.$e->getMessage(), 'errors'=>$errors], 500);
}
