<?php
// /main/profile/API/address_list.php
// Returns { ok, blocklist:[], whitelist:[] } with fields { .id, address, comment }.
// Optional ?group=over18|under18 to scope by group-specific lists if present,
// while remaining backward-compatible with global "blocklist"/"whitelist".

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

function jexit(array $x, int $code = 200){
  http_response_code($code);
  // Ensure no stray output
  while (ob_get_level() > 0) { @ob_end_clean(); }
  echo json_encode($x, JSON_UNESCAPED_SLASHES);
  exit;
}

$APP_ROOT   = dirname(__DIR__, 3); // /public_html
$configFile = $APP_ROOT . '/config/router.php';
$clientFile = $APP_ROOT . '/includes/routeros_client.php';

if (!file_exists($configFile)) jexit(['ok'=>false,'message'=>'config/router.php missing'], 500);
if (!file_exists($clientFile)) jexit(['ok'=>false,'message'=>'includes/routeros_client.php missing'], 500);

$config = require $configFile;
require $clientFile;

// ---- Router cfg ----
$host    = (string)($config['host']      ?? '127.0.0.1');
$port    = (int)   ($config['api_port']  ?? 8729);
$useTls  = (bool)  ($config['api_tls']   ?? true);
$user    = (string)($config['user']      ?? 'api-dashboard');
$pass    = (string)($config['pass']      ?? '');
$timeout = (int)   ($config['timeout']   ?? 8);

// ---- Group param (optional) ----
$group = strtolower(trim((string)($_GET['group'] ?? '')));
$group = in_array($group, ['over18','under18'], true) ? $group : '';

// Candidate RouterOS list names we will recognize, in priority order
$blockCandidates = ['blocklist'];
$whiteCandidates = ['whitelist'];
if ($group !== '') {
  // Accept several naming styles so you don't have to refactor the router immediately
  $blockCandidates = array_merge(
    ["blocklist_{$group}", "{$group}_blocklist", "block_{$group}"],
    $blockCandidates
  );
  $whiteCandidates = array_merge(
    ["whitelist_{$group}", "{$group}_whitelist", "white_{$group}"],
    $whiteCandidates
  );
}

// Helper: check if comment suggests a group (e.g., "group: over18")
function comment_matches_group(?string $comment, string $group): bool {
  if ($group === '' || $comment === null || $comment === '') return false;
  $c = strtolower($comment);
  // accept "group: over18" OR "grp=over18" anywhere in the comment
  return (strpos($c, "group: {$group}") !== false) || (strpos($c, "grp={$group}") !== false);
}

try {
  try {
    $api = new RouterOSClient($host,$port,$user,$pass,$timeout,$useTls);
  } catch (Throwable $e) {
    // fallback to :8728 non-TLS if TLS is off/unavailable
    $api = new RouterOSClient($host, 8728, $user, $pass, $timeout, false);
  }

  $rows = (array)$api->talk('/ip/firewall/address-list/print');
  if (method_exists($api,'close')) $api->close();

  $block = [];
  $white = [];

  foreach ($rows as $r) {
    if (!is_array($r)) continue;
    $list = strtolower((string)($r['list'] ?? ''));
    $addr = (string)($r['address'] ?? '');
    $cmt  = (string)($r['comment'] ?? '');
    $id   = $r['.id'] ?? null;

    if ($addr === '' || !$id) continue;

    $entry = [ '.id' => $id, 'address' => $addr, 'comment' => $cmt ];

    // Decide bucket
    $isBlockList = in_array($list, $blockCandidates, true);
    $isWhiteList = in_array($list, $whiteCandidates, true);

    // If a group is requested, prefer explicit list-name match.
    // Otherwise, accept global list with group in comment as a fallback.
    if ($group !== '') {
      if ($isBlockList || (!$isWhiteList && !$isBlockList && in_array('blocklist', $blockCandidates, true) && $list === 'blocklist' && comment_matches_group($cmt, $group))) {
        $block[] = $entry;
        continue;
      }
      if ($isWhiteList || (!$isWhiteList && !$isBlockList && in_array('whitelist', $whiteCandidates, true) && $list === 'whitelist' && comment_matches_group($cmt, $group))) {
        $white[] = $entry;
        continue;
      }
      // If list doesn't match this group, skip.
      continue;
    }

    // No group requested -> classic behavior
    if ($isBlockList) { $block[] = $entry; continue; }
    if ($isWhiteList) { $white[] = $entry; continue; }
  }

  jexit(['ok'=>true,'blocklist'=>$block,'whitelist'=>$white], 200);

} catch (Throwable $e) {
  jexit(['ok'=>false,'message'=>'Router connection failed: '.$e->getMessage()], 502);
}
