<?php
// /main/reports/api/get_top_blocked_sites.php
// Returns aggregated blocked-site attempts from MySQL logs table.

declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json');

function jexit(array $arr): void { echo json_encode($arr); exit; }

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

$sql = "SELECT
            l.`domain` AS site,
            COUNT(*) AS attempts,
            MAX(l.`date`) AS lastAttempt,
            $deviceSelect AS deviceName
        FROM `logs` l
        LEFT JOIN `device` d ON l.`device_id` = d.`id`
        WHERE " . implode(' AND ', $where) . "
        GROUP BY l.`domain`
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
while ($row = $res->fetch_assoc()) {
    $rows[] = [
        'site' => (string)($row['site'] ?? ''),
        'attempts' => (int)($row['attempts'] ?? 0),
        'lastAttempt' => (string)($row['lastAttempt'] ?? ''),
        'deviceName' => (string)($row['deviceName'] ?? ''),
    ];
}

$stmt->close();

jexit(['ok' => true, 'rows' => $rows]);
