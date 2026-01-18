<?php
// /main/reports/api/get_top_blocked_sites.php
// Returns aggregated blocked-site attempts from MySQL logs table.

declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json');

function jexit(array $arr): void { echo json_encode($arr); exit; }

function normalize_site_host(string $value): string {
    $value = trim($value);
    if ($value === '') return '';

    // If it's a URL, extract host
    if (str_contains($value, '://')) {
        $host = parse_url($value, PHP_URL_HOST);
        if (is_string($host) && $host !== '') $value = $host;
    } elseif (str_contains($value, '/')) {
        // Might be host/path without scheme
        $host = parse_url('http://' . $value, PHP_URL_HOST);
        if (is_string($host) && $host !== '') $value = $host;
    }

    // Strip port
    $value = preg_replace('/:\d+$/', '', $value);
    // Strip IPv6 brackets
    $value = preg_replace('/^\[(.*)\]$/', '$1', $value);
    // Keep only hostname-ish chars
    $value = strtolower(preg_replace('/[^a-z0-9.\-:]/i', '', $value));
    return $value;
}

function is_ip_literal(string $value): bool {
    return filter_var($value, FILTER_VALIDATE_IP) !== false;
}

function load_reverse_dns_cache(string $path): array {
    if (!is_file($path)) return [];
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function save_reverse_dns_cache(string $path, array $cache): void {
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);

    // Avoid unbounded growth
    if (count($cache) > 5000) {
        // Keep newest 2500
        uasort($cache, fn($a, $b) => (int)($b['t'] ?? 0) <=> (int)($a['t'] ?? 0));
        $cache = array_slice($cache, 0, 2500, true);
    }

    $tmp = $path . '.tmp';
    @file_put_contents($tmp, json_encode($cache, JSON_UNESCAPED_SLASHES), LOCK_EX);
    @rename($tmp, $path);
}

function fetch_router_dns_cache_map(string $appRoot, int $timeoutSeconds = 3): array {
    $config_file = $appRoot . '/config/router.php';
    $client_file = $appRoot . '/includes/routeros_client.php';
    if (!file_exists($config_file) || !file_exists($client_file)) return [];

    try {
        $config = require $config_file;
        require_once $client_file;
        if (!class_exists('RouterOSClient')) return [];

        $host    = $config['host']     ?? '10.10.20.10';
        $port    = (int)($config['api_port'] ?? 8729);
        $useTls  = (bool)($config['api_tls'] ?? true);
        $user    = $config['user']     ?? 'api-dashboard';
        $pass    = $config['pass']     ?? '';

        @set_time_limit($timeoutSeconds + 1);
        @ini_set('default_socket_timeout', (string)$timeoutSeconds);

        $api = new RouterOSClient($host, $port, $user, $pass, $timeoutSeconds, $useTls);
        $dnsc = $api->talk('/ip/dns/cache/print');
        $api->close();

        if (!is_array($dnsc)) return [];

        $map = [];
        foreach ($dnsc as $row) {
            if (!is_array($row)) continue;
            $name = strtolower((string)($row['name'] ?? ''));
            $addr = (string)($row['address'] ?? ($row['data'] ?? ''));
            if ($name === '' || $addr === '') continue;
            if (!is_ip_literal($addr)) continue;
            if (!isset($map[$addr])) $map[$addr] = $name;
        }
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

$APP_ROOT = dirname(__DIR__, 3);

require_once $APP_ROOT . '/loginverification.php';
if (function_exists('logged_in') && !logged_in()) {
    jexit(['ok' => false, 'message' => 'Not authenticated']);
}

require_once $APP_ROOT . '/connectMySql.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    jexit(['ok' => false, 'message' => 'DB connection unavailable']);
}

// Read JSON body (preferred) + fall back to POST
$raw = file_get_contents('php://input');
$body = [];
if (is_string($raw) && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $body = $decoded;
}

$device = trim((string)($body['device'] ?? ($_POST['device'] ?? '')));
$since  = trim((string)($body['since']  ?? ($_POST['since']  ?? '')));
$until  = trim((string)($body['until']  ?? ($_POST['until']  ?? '')));
$limit  = (int)($body['limit'] ?? ($_POST['limit'] ?? 20));
$sort   = strtolower(trim((string)($body['sort'] ?? ($_POST['sort'] ?? 'attempts'))));

$limit = max(1, min(200, $limit));
$since = $since ? str_replace('T', ' ', $since) : '';
$until = $until ? str_replace('T', ' ', $until) : '';

// Ensure logs table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'logs'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    jexit(['ok' => true, 'rows' => []]);
}

// Resolve device_id if a numeric id is provided; otherwise allow matching by name/ip
$deviceId = null;
if ($device !== '' && ctype_digit($device)) {
    $deviceId = (int)$device;
}

// Determine device label column (name/device_name/device)
$deviceColsRes = $conn->query("SHOW COLUMNS FROM `device`");
$deviceCols = [];
if ($deviceColsRes) {
    while ($c = $deviceColsRes->fetch_assoc()) $deviceCols[$c['Field']] = true;
}
$deviceHasIpCol = isset($deviceCols['ip_address']);
$deviceNameCol = null;
foreach (['device_name','name','device'] as $cand) {
    if (isset($deviceCols[$cand])) { $deviceNameCol = $cand; break; }
}

$where = [];
$params = [];
$types = '';

// Only blocked actions
$where[] = "(l.`action`='blocked' OR l.`type`='blocked')";
$where[] = "l.`domain` IS NOT NULL AND l.`domain` <> ''";

if ($since !== '') { $where[] = "l.`date` >= ?"; $types .= 's'; $params[] = $since; }
if ($until !== '') { $where[] = "l.`date` <= ?"; $types .= 's'; $params[] = $until; }

if ($deviceId !== null) {
    $where[] = "l.`device_id` = ?";
    $types .= 'i';
    $params[] = $deviceId;
} elseif ($device !== '') {
    // Best-effort match by device name or IP
    if ($deviceNameCol) {
        $where[] = "(l.`ip_address` LIKE CONCAT('%',?,'%') OR d.`$deviceNameCol` LIKE CONCAT('%',?,'%'))";
        $types .= 'ss';
        $params[] = $device;
        $params[] = $device;
    } else {
        $where[] = "(l.`ip_address` LIKE CONCAT('%',?,'%'))";
        $types .= 's';
        $params[] = $device;
    }
}

$orderBy = ($sort === 'last') ? 'lastAttempt DESC' : 'attempts DESC';

$deviceSelect = $deviceNameCol ? "COALESCE(d.`$deviceNameCol`, '')" : "''";
$deviceLabel = $deviceNameCol
    ? "COALESCE(NULLIF(d.`$deviceNameCol`, ''), 'Unknown Device')"
    : "'Unknown Device'";

$deviceJoin = $deviceHasIpCol
    ? "LEFT JOIN `device` d ON (l.`device_id` = d.`id` OR ((l.`device_id` IS NULL OR l.`device_id`=0) AND l.`ip_address` IS NOT NULL AND l.`ip_address` <> '' AND d.`ip_address` = l.`ip_address`))"
    : "LEFT JOIN `device` d ON l.`device_id` = d.`id`";

$sql = "SELECT
            l.`domain` AS site,
            $deviceLabel AS device,
            COALESCE(l.`ip_address`, '') AS ip,
            COUNT(*) AS attempts,
            MAX(l.`date`) AS lastAttempt,
            $deviceSelect AS deviceName
        FROM `logs` l
        $deviceJoin
        WHERE " . implode(' AND ', $where) . "
        GROUP BY l.`domain`, l.`device_id`, l.`ip_address`, device
        ORDER BY $orderBy
        LIMIT $limit";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    jexit(['ok' => false, 'message' => 'Query prepare failed']);
}

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    jexit(['ok' => false, 'message' => 'Query execute failed']);
}

$res = $stmt->get_result();
$rows = [];

$cachePath = $APP_ROOT . '/data/reverse_dns_cache.json';
$cacheTtlSeconds = 60 * 60 * 24 * 7; // 7 days
$rdnsCache = load_reverse_dns_cache($cachePath);
$rdnsDirty = false;
$routerDnsMap = [];
$routerDnsLoaded = false;
$rdnsLookups = 0;
$rdnsLookupCap = 30; // avoid slow requests

while ($row = $res->fetch_assoc()) {
    $rawSite = normalize_site_host((string)($row['site'] ?? ''));
    $displaySite = $rawSite;

    if ($rawSite !== '' && is_ip_literal($rawSite)) {
        if (!$routerDnsLoaded) {
            $routerDnsMap = fetch_router_dns_cache_map($APP_ROOT, 3);
            $routerDnsLoaded = true;
        }

        if (!empty($routerDnsMap[$rawSite])) {
            $displaySite = (string)$routerDnsMap[$rawSite];
        } else {
            $now = time();
            $cached = $rdnsCache[$rawSite] ?? null;
            $cachedHost = is_array($cached) ? (string)($cached['h'] ?? '') : '';
            $cachedAt = is_array($cached) ? (int)($cached['t'] ?? 0) : 0;

            if ($cachedHost !== '' && $cachedAt > 0 && ($now - $cachedAt) < $cacheTtlSeconds) {
                $displaySite = $cachedHost;
            } elseif ($rdnsLookups < $rdnsLookupCap) {
                $rdnsLookups++;
                $host = @gethostbyaddr($rawSite);
                if (is_string($host) && $host !== '' && $host !== $rawSite) {
                    $host = normalize_site_host($host);
                    if ($host !== '' && $host !== $rawSite) {
                        $displaySite = $host;
                        $rdnsCache[$rawSite] = ['h' => $host, 't' => $now];
                        $rdnsDirty = true;
                    }
                }
            }
        }
    }

    $rows[] = [
        'site' => $displaySite,
        'siteRaw' => $rawSite,
        'device' => (string)($row['device'] ?? ''),
        'attempts' => (int)($row['attempts'] ?? 0),
        'lastAttempt' => (string)($row['lastAttempt'] ?? ''),
        'deviceName' => (string)($row['deviceName'] ?? ''),
    ];
}

$stmt->close();

if ($rdnsDirty) {
    save_reverse_dns_cache($cachePath, $rdnsCache);
}

jexit(['ok' => true, 'rows' => $rows]);
