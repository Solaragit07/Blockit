<?php
// /main/reports/api/get_block_attempts_by_device.php
// Returns blocked attempt counts grouped by device from MySQL `logs` table.

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

$raw = file_get_contents('php://input');
$body = [];
if (is_string($raw) && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $body = $decoded;
}

$device = trim((string)($body['device'] ?? ($_POST['device'] ?? '')));
$since  = trim((string)($body['since']  ?? ($_POST['since']  ?? '')));
$until  = trim((string)($body['until']  ?? ($_POST['until']  ?? '')));
$limit  = (int)($body['limit'] ?? ($_POST['limit'] ?? 50));
$sort   = strtolower(trim((string)($body['sort'] ?? ($_POST['sort'] ?? 'attempts'))));

$limit = max(1, min(200, $limit));
$since = $since ? str_replace('T', ' ', $since) : '';
$until = $until ? str_replace('T', ' ', $until) : '';

$tableCheck = $conn->query("SHOW TABLES LIKE 'logs'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    jexit(['ok' => true, 'rows' => []]);
}

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

$where[] = "(l.`action`='blocked' OR l.`type`='blocked')";
$where[] = "l.`domain` IS NOT NULL AND l.`domain` <> ''";

if ($since !== '') { $where[] = "l.`date` >= ?"; $types .= 's'; $params[] = $since; }
if ($until !== '') { $where[] = "l.`date` <= ?"; $types .= 's'; $params[] = $until; }

if ($device !== '') {
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

$deviceLabel = $deviceNameCol
    ? "COALESCE(NULLIF(d.`$deviceNameCol`, ''), 'Unknown Device')"
    : "'Unknown Device'";

$deviceJoin = $deviceHasIpCol
    ? "LEFT JOIN `device` d ON (l.`device_id` = d.`id` OR ((l.`device_id` IS NULL OR l.`device_id`=0) AND l.`ip_address` IS NOT NULL AND l.`ip_address` <> '' AND d.`ip_address` = l.`ip_address`))"
    : "LEFT JOIN `device` d ON l.`device_id` = d.`id`";

$sql = "SELECT
            $deviceLabel AS `device`,
            COALESCE(l.`ip_address`, '') AS `ip`,
            COUNT(*) AS `attempts`,
            MAX(l.`date`) AS `lastAttempt`
        FROM `logs` l
        $deviceJoin
        WHERE " . implode(' AND ', $where) . "
        GROUP BY l.`device_id`, l.`ip_address`, device
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
        'device' => (string)($row['device'] ?? ''),
        'ip' => (string)($row['ip'] ?? ''),
        'attempts' => (int)($row['attempts'] ?? 0),
        'lastAttempt' => (string)($row['lastAttempt'] ?? ''),
    ];
}

$stmt->close();

jexit(['ok' => true, 'rows' => $rows]);
