<?php
require_once __DIR__ . '/../connectMySql.php';
require_once __DIR__ . '/../API/connectMikrotik_safe.php'; // provides $client or null

header('Content-Type: application/json');

function json_response($ok, $data = [], $msg = '') {
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $msg]);
    exit;
}

// Normalize input URL to bare domain (lowercase, no scheme/path, drop leading www.)
function normalize_domain($input) {
    $str = trim(strtolower($input));
    if ($str === '') return '';
    // Prepend scheme if missing so parse_url works
    if (!preg_match('~^https?://~', $str)) {
        $str = 'http://' . $str;
    }
    $parts = parse_url($str);
    $host = $parts['host'] ?? '';
    // IDN to ASCII if available
    if (function_exists('idn_to_ascii')) {
        $hostConv = @idn_to_ascii($host, 0, INTL_IDNA_VARIANT_UTS46);
        if ($hostConv) $host = $hostConv;
    }
    // Drop leading www.
    $host = preg_replace('/^www\./', '', $host);
    // Basic validation: letters, digits, hyphen and dots
    if (!preg_match('/^[a-z0-9.-]+$/', $host)) {
        return '';
    }
    return $host;
}

function get_known_ecommerce_domains() {
    return [
        'amazon.com','ebay.com','aliexpress.com','etsy.com','walmart.com','target.com','bestbuy.com','shopee.ph','shopee.sg','lazada.com','lazada.com.ph','shein.com','temu.com','zalora.com','zalora.com.ph','noon.com','flipkart.com','rakuten.co.jp','mercadolibre.com','carousell.ph','shopify.com'
    ];
}

function get_payment_domains() {
    return [
        'paypal.com','api.paypal.com','pay.google.com','payments.google.com','checkout.stripe.com','stripe.com','pay.amazon.com','paymaya.com','gcash.com','adyen.com','shop.app','checkout.shopify.com','alipay.com','skrill.com','2checkout.com','paysafe.com','authorize.net'
    ];
}

function load_current_state(mysqli $conn) {
    $settings = [
        'blockAccess' => false,
        'blockPurchases' => false,
        'notifications' => false,
        'notificationMethods' => []
    ];
    $res = $conn->query("SELECT block_access, block_purchases, notifications, notification_methods FROM ecommerce_settings WHERE id=1");
    if ($res && $row = $res->fetch_assoc()) {
        $settings['blockAccess'] = (int)$row['block_access'] === 1;
        $settings['blockPurchases'] = (int)$row['block_purchases'] === 1;
        $settings['notifications'] = (int)$row['notifications'] === 1;
        $methods = $row['notification_methods'];
        if ($methods) {
            $decoded = json_decode($methods, true);
            if (is_array($decoded)) $settings['notificationMethods'] = $decoded;
        }
    }
    $platforms = [];
    $resP = $conn->query("SELECT id, name, url, access, reason FROM ecommerce_platforms");
    if ($resP) {
        while ($p = $resP->fetch_assoc()) { $platforms[] = $p; }
    }
    return [$settings, $platforms];
}

// Compute desired blocked domain set given settings and platforms
function compute_block_domains(array $settings, array $platforms) {
    $known = array_unique(get_known_ecommerce_domains());
    $allow = [];
    $explicitBlocked = [];
    foreach ($platforms as $p) {
        $dom = normalize_domain($p['url'] ?? '');
        if ($dom === '') continue;
        if (($p['access'] ?? 'browsing') === 'blocked') $explicitBlocked[] = $dom;
        else $allow[] = $dom;
    }
    $allow = array_unique($allow);
    $explicitBlocked = array_unique($explicitBlocked);

    $blocked = [];
    if (!empty($settings['blockAccess'])) {
        foreach ($known as $d) {
            if (!in_array($d, $allow, true)) $blocked[] = $d;
        }
    }
    // Always include explicitly blocked platforms
    foreach ($explicitBlocked as $d) { $blocked[] = $d; }

    // If only purchases are blocked, include payment domains
    if (empty($settings['blockAccess']) && !empty($settings['blockPurchases'])) {
        $blocked = array_merge($blocked, get_payment_domains());
    }

    // Unique and sanitize
    $blocked = array_values(array_unique(array_filter(array_map('normalize_domain', $blocked))));
    return $blocked;
}

// Apply RouterOS DNS static rules for desired domain blocks, tagged by comment "blockit:ecommerce"
function apply_router_ecommerce_rules($client, array $settings, array $platforms) {
    if (!$client) return; // Router not available
    try {
        $desired = compute_block_domains($settings, $platforms);
        // Expand with www. variant
        $final = [];
        foreach ($desired as $d) {
            $final[$d] = true;
            $final['www.' . $d] = true;
        }
        $desiredSet = array_keys($final);

        // Read existing entries for our tag using Query object
        $qPrint = new \RouterOS\Query('/ip/dns/static/print');
        $qPrint->where('comment', 'blockit:ecommerce');
        $existing = $client->query($qPrint)->read();
        $existingByName = [];
        foreach ($existing as $e) {
            if (isset($e['name'])) $existingByName[$e['name']] = $e;
        }

        // Remove entries that are no longer desired
        foreach ($existingByName as $name => $row) {
            if (!in_array($name, $desiredSet, true) && isset($row['.id'])) {
                $qRem = (new \RouterOS\Query('/ip/dns/static/remove'))->equal('.id', $row['.id']);
                $client->query($qRem)->read();
            }
        }

        // Ensure desired entries exist (address 0.0.0.0)
        foreach ($desiredSet as $name) {
            if (!isset($existingByName[$name])) {
                $qAdd = (new \RouterOS\Query('/ip/dns/static/add'))
                    ->equal('name', $name)
                    ->equal('address', '0.0.0.0')
                    ->equal('comment', 'blockit:ecommerce');
                $client->query($qAdd)->read();
            }
        }
    } catch (\Throwable $e) {
        error_log('apply_router_ecommerce_rules error: ' . $e->getMessage());
        // Do not break API on router errors
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

try {
    if ($method === 'GET' && $action === 'get') {
        // Load settings
        $settings = [
            'blockAccess' => 0,
            'blockPurchases' => 0,
            'notifications' => 0,
            'notificationMethods' => [],
            'platforms' => []
        ];
        $res = $conn->query("SELECT block_access, block_purchases, notifications, notification_methods FROM ecommerce_settings WHERE id=1");
        if ($res && $row = $res->fetch_assoc()) {
            $settings['blockAccess'] = (int)$row['block_access'] === 1;
            $settings['blockPurchases'] = (int)$row['block_purchases'] === 1;
            $settings['notifications'] = (int)$row['notifications'] === 1;
            $methods = $row['notification_methods'];
            if ($methods) {
                $decoded = json_decode($methods, true);
                if (is_array($decoded)) $settings['notificationMethods'] = $decoded;
            }
        }
        $resP = $conn->query("SELECT id, name, url, access, reason FROM ecommerce_platforms ORDER BY id DESC");
        if ($resP) {
            while ($p = $resP->fetch_assoc()) {
                $settings['platforms'][] = $p;
            }
        }
        json_response(true, $settings);
    }

    if ($method === 'POST' && $action === 'save') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $blockAccess = !empty($input['blockAccess']);
        $blockPurchases = !empty($input['blockPurchases']);
        $notifications = !empty($input['notifications']);
        $notificationMethods = isset($input['notificationMethods']) && is_array($input['notificationMethods']) ? $input['notificationMethods'] : [];

        $stmt = $conn->prepare("REPLACE INTO ecommerce_settings (id, block_access, block_purchases, notifications, notification_methods) VALUES (1, ?, ?, ?, ?)");
        $methodsJson = json_encode($notificationMethods);
        $ba = $blockAccess ? 1 : 0; $bp = $blockPurchases ? 1 : 0; $no = $notifications ? 1 : 0;
        $stmt->bind_param('iiis', $ba, $bp, $no, $methodsJson);
        $stmt->execute();
        $stmt->close();

    // Apply router rules (DNS-based blocking with tag)
    [$curSettings, $curPlatforms] = load_current_state($conn);
    // Overwrite current with the just-saved values
    $curSettings['blockAccess'] = $blockAccess;
    $curSettings['blockPurchases'] = $blockPurchases;
    $curSettings['notifications'] = $notifications;
    apply_router_ecommerce_rules($client, $curSettings, $curPlatforms);

        json_response(true, [], 'Settings saved');
    }

    if ($method === 'POST' && $action === 'addPlatform') {
    $name = trim($_POST['name'] ?? '');
    $url = normalize_domain($_POST['url'] ?? '');
        $access = $_POST['access'] ?? 'browsing';
        $reason = trim($_POST['reason'] ?? '');
        if ($name === '' || $url === '') json_response(false, [], 'Name and URL required');
    if (!in_array($access, ['browsing','full','blocked'], true)) $access = 'browsing';
        $stmt = $conn->prepare("INSERT INTO ecommerce_platforms (name, url, access, reason) VALUES (?,?,?,?)");
        $stmt->bind_param('ssss', $name, $url, $access, $reason);
        $stmt->execute();
        $id = $stmt->insert_id; $stmt->close();
    // Enforce router rules with updated platforms
    [$curSettings, $curPlatforms] = load_current_state($conn);
    apply_router_ecommerce_rules($client, $curSettings, $curPlatforms);
        json_response(true, ['id' => $id]);
    }

    if ($method === 'POST' && $action === 'updatePlatform') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $access = $_POST['access'] ?? 'browsing';
        if ($id <= 0) json_response(false, [], 'Invalid id');
    if (!in_array($access, ['browsing','full','blocked'], true)) $access = 'browsing';
        $stmt = $conn->prepare("UPDATE ecommerce_platforms SET name=?, reason=?, access=? WHERE id=?");
        $stmt->bind_param('sssi', $name, $reason, $access, $id);
        $stmt->execute();
        $stmt->close();
    // Re-apply router rules
    [$curSettings, $curPlatforms] = load_current_state($conn);
    apply_router_ecommerce_rules($client, $curSettings, $curPlatforms);
        json_response(true);
    }

    if ($method === 'POST' && $action === 'removePlatform') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) json_response(false, [], 'Invalid id');
        $stmt = $conn->prepare("DELETE FROM ecommerce_platforms WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    // Re-apply router rules
    [$curSettings, $curPlatforms] = load_current_state($conn);
    apply_router_ecommerce_rules($client, $curSettings, $curPlatforms);
        json_response(true);
    }

    json_response(false, [], 'Unknown action');
} catch (Throwable $e) {
    json_response(false, [], $e->getMessage());
}
