<?php
// /main/ecommerce/API/ecom_checkout_api.php
// DNS-only checkout blocking (MikroTik RouterOS)
// Manages /ip/dns/static records with comment prefix "ECOM-CHECKOUT"
// Requires your RouterOS API client at /includes/routeros_client.php
// and config at /config/router.php

declare(strict_types=1);
header('Content-Type: application/json');

// ---------- Load config + client ----------
$ROOT = dirname(__DIR__, 3); // .../public_html
$config = require $ROOT . '/config/router.php';
require_once $ROOT . '/includes/routeros_client.php';

// ---------- API key auth ----------
$hdrKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
$cfgKey = trim((string)($config['api_key'] ?? getenv('BLOCKIT_API_KEY') ?? ''));
if ($cfgKey === '' || !hash_equals($cfgKey, $hdrKey)) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'message'=>'Unauthorized']);
  exit;
}
/**
 * Return ALL rules we manage so the UI can show what’s active.
 * For DNS-only mode this reads /ip/dns/static and filters by comment prefix.
 * For filter-chain (TLS) mode you can flip $SOURCE to 'filter'.
 */
function list_managed_rules(RouterOSClient $api): array {
  // choose where we read “our” rules from
  $SOURCE = 'dns'; // 'dns'  -> /ip/dns/static (sinkholes)
                   // 'filter' -> /ip/firewall/filter (tls-host)

  if ($SOURCE === 'dns') {
    // DNS sinkholes
    $rows = $api->talk('/ip/dns/static/print', [
      '.proplist' => '.id,name,address,comment,disabled'
    ]);
    $managed = [];
    foreach ($rows as $r) {
      $c = (string)($r['comment'] ?? '');
      if ($c !== '' && str_starts_with($c, RULE_PREFIX)) {
        $managed[] = $r;
      }
    }
    return $managed;
  } else {
    // Firewall filter (TLS SNI)
    $rows = $api->talk('/ip/firewall/filter/print', [
      '.proplist' => '.id,chain,action,protocol,tls-host,comment,disabled'
    ]);
    $managed = [];
    foreach ($rows as $r) {
      $c = (string)($r['comment'] ?? '');
      if ($c !== '' && str_starts_with($c, RULE_PREFIX)) {
        $managed[] = $r;
      }
    }
    return $managed;
  }
}

// ---------- Constants ----------
const RULE_PREFIX = 'ECOM-CHECKOUT'; // comment prefix

/**
 * Platforms
 * type: "subdomain" → add list of hostnames below
 *       "root"      → block the root host (DNS blocks the whole site)
 * hosts: DNS names to sinkhole (A 127.0.0.1). Keep this tight to checkout/payment.
 * root:  the main site; shown in UI (and blocked when type="root").
 *
 * NOTE: These are starter sets tuned for the PH market. Hostnames can evolve.
 */
$PLATFORMS = [
  'Shopee' => [ 'hosts' => ['shopee.ph.checkout','checkout.shopee.ph','pay.shopee.ph','payment.shopee.ph','shopeeapi.com','shopeemobile.com'] ],
  'Lazada' => [ 'hosts' => ['lazada.com.ph.checkout','checkout.lazada','pay.lazada','cart.lazada','alicdn.com','alipayobjects.com','alipay.com'] ],
  // 'TikTok Shop' => [ 'hosts' => ['tiktokglobalshop.com.shop','shopping.tiktok','shop.tiktok','mcs.tiktokv.com','tiktokcdn.com'] ],
  'Amazon' => [ 'hosts' => ['amazon.com.checkout','checkout.amazon','pay.amazon','payments.amazon.com','amazonpay.com'] ],
  // --- Add more popular PH sites ---
  'Temu' => [ 'hosts' => ['temu.com'] ],
  'Shein' => [ 'hosts' => ['shein.com'] ],
  'eBay' => [ 'hosts' => ['ebay.com.checkout','checkout.ebay.com','pay.ebay.com'] ],
  'Zalora' => [ 'hosts' => ['zalora.com.ph.checkout','checkout.zalora.com.ph','secure.zalora.com.ph'] ],
  'GCash' => [ 'hosts' => ['gcash.com.pay','pay.gcash.com'] ],
  'Maya' => [ 'hosts' => ['paymaya.com.checkout','maya.ph.checkout','checkout.maya.ph'] ],
  'Grab' => [ 'hosts' => ['grab.com.pay','pay.grab.com','grabpay.com'] ],
  'Foodpanda' => [ 'hosts' => ['foodpanda.ph.checkout','checkout.foodpanda.ph'] ],
];


// ---------- Helpers ----------
function ros(): RouterOSClient {
  global $config;
  return new RouterOSClient(
    (string)$config['host'],
    (int)($config['api_port'] ?? 8729),
    (string)$config['user'],
    (string)$config['pass'],
    (int)($config['timeout'] ?? 8),
    (bool)($config['api_tls'] ?? true)
  );
}

function sanitize_host(string $h): string {
  $h = strtolower(trim($h));
  // strip scheme
  $h = preg_replace('~^[a-z][a-z0-9+.\-]*://~i', '', $h);
  // strip user@, port, path, query, hash
  $h = preg_replace('~^([^/@]+@)?([^/:#?]+).*~', '$2', $h);
  // trim trailing dot
  $h = rtrim($h, '.');
  return $h;
}

function list_managed_dns(RouterOSClient $api): array {
  $rows = $api->talk('/ip/dns/static/print', ['.proplist'=>'.id,name,address,comment,disabled']);
  $out = [];
  foreach ($rows as $r) {
    $c = $r['comment'] ?? '';
    if ($c !== '' && str_starts_with($c, RULE_PREFIX)) $out[] = $r;
  }
  return $out;
}

function dns_exists(array $rows, string $name): bool {
  foreach ($rows as $r) {
    if (strcasecmp($r['name'] ?? '', $name) === 0) return true;
  }
  return false;
}

function add_dns_block(RouterOSClient $api, string $name, string $comment): void {
  $name = sanitize_host($name);
  if ($name === '') throw new RuntimeException('empty host');
  // get current list once to avoid duplicates
  static $cached = null;
  if ($cached === null) $cached = list_managed_dns($api);
  if (dns_exists($cached, $name)) return;

  $api->talk('/ip/dns/static/add', [
    'name'    => $name,
    'type'    => 'A',
    'address' => '127.0.0.1',
    'comment' => $comment,
    'disabled'=> 'no',
  ]);

  // mutate cache to avoid re-adding in same request
  $cached[] = ['name'=>$name, 'comment'=>$comment];
}

function ids_by_comment_prefix(RouterOSClient $api, string $prefix): array {
  $rows = $api->talk('/ip/dns/static/print', ['.proplist'=>'.id,comment']);
  $ids = [];
  foreach ($rows as $r) {
    $c = $r['comment'] ?? '';
    if ($c !== '' && str_starts_with($c, $prefix)) {
      if (!empty($r['id'])) $ids[] = $r['id'];
    }
  }
  return $ids;
}

function del_ids(RouterOSClient $api, array $ids): int {
  $n = 0;
  foreach ($ids as $id) {
    $api->talk('/ip/dns/static/remove', ['.id'=>$id]);
    $n++;
  }
  return $n;
}

function rule_comment(string $platform, string $host): string {
  return RULE_PREFIX . " [$platform] " . $host;
}

// ---------- Request ----------
$raw   = file_get_contents('php://input') ?: '';
$body  = json_decode($raw, true) ?: [];
$action = $_GET['action'] ?? ($body['action'] ?? '');

try {
  if ($action === 'get') {
    $api = ros();
    $managed = list_managed_rules($api);
    $api->close();

    // collect comments only
    $active_comments = array_values(array_unique(array_map(
        fn($r) => (string)($r['comment'] ?? ''),
        $managed
    )));

    // ensure it’s always an array
    if (!is_array($active_comments)) {
        $active_comments = [];
    }

    echo json_encode([
        'ok'        => true,
        'platforms' => array_keys($GLOBALS['PLATFORMS']),
        'active'    => $active_comments,  // frontend expects an array
    ]);
    exit;
}


  if ($action === 'togglePlatform') {
    $platform = trim((string)($body['platform'] ?? ''));
    $enable   = (bool)($body['enable'] ?? false);

    if ($platform === '' || !isset($PLATFORMS[$platform])) {
      http_response_code(400);
      echo json_encode(['ok'=>false,'message'=>'Unknown platform']); exit;
    }

    $p = $PLATFORMS[$platform];
    $api = ros();

    if ($enable) {
      $added = [];

      if ($p['type'] === 'root') {
        $host = sanitize_host((string)$p['root']);
        add_dns_block($api, $host, rule_comment($platform, $host));
        $added[] = $host;
      } else {
        foreach ((array)$p['hosts'] as $h) {
          $h = sanitize_host((string)$h);
          if ($h === '') continue;
          add_dns_block($api, $h, rule_comment($platform, $h));
          $added[] = $h;
        }
      }

      $api->close();
      echo json_encode(['ok'=>true,'message'=>"Enabled $platform DNS checkout blocking",'added'=>$added]); exit;
    } else {
      $ids = ids_by_comment_prefix($api, RULE_PREFIX . " [$platform]");
      $n = del_ids($api, $ids);
      $api->close();
      echo json_encode(['ok'=>true,'message'=>"Disabled $platform",'removed_count'=>$n]); exit;
    }
  }

  if ($action === 'addCustom') {
    $site   = sanitize_host((string)($body['site'] ?? ''));
    $enable = (bool)($body['enable'] ?? true);

    if ($site === '' || strlen($site) < 3) {
      http_response_code(400);
      echo json_encode(['ok'=>false,'message'=>'Provide a hostname (e.g., checkout.ebay.com or pay.shopee.ph)']); exit;
    }

    $api = ros();
    if ($enable) {
      add_dns_block($api, $site, rule_comment('Custom', $site));
      $api->close();
      echo json_encode(['ok'=>true,'message'=>"Custom DNS blocked: $site"]); exit;
    } else {
      $ids = ids_by_comment_prefix($api, RULE_PREFIX . ' [Custom] ' . $site);
      $n   = del_ids($api, $ids);
      $api->close();
      echo json_encode(['ok'=>true,'message'=>"Custom unblocked: $site",'removed_count'=>$n]); exit;
    }
  }

  if ($action === 'clearAll') {
    $api = ros();
    $ids = ids_by_comment_prefix($api, RULE_PREFIX);
    $n   = del_ids($api, $ids);
    $api->close();
    echo json_encode(['ok'=>true,'message'=>'Cleared all DNS checkout rules','removed_count'=>$n]); exit;
  }

  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Unknown action']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'RouterOS error: '.$e->getMessage()]);
}
