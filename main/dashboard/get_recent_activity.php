<?php
// /public_html/main/dashboard/get_recent_activity.php
// Robust version: safe dynamic bind, clear JSON errors, login fallback

// TEMP: enable error visibility while debugging (remove later)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$APP_ROOT = dirname(__DIR__, 2); // /public_html
require_once $APP_ROOT . '/connectMySql.php';
require_once $APP_ROOT . '/loginverification.php';

// ---- Auth (fallback if require_login() isn't defined) ----
if (function_exists('require_login')) {
    require_login();
} else {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['ok' => false, 'message' => 'Not authenticated']); exit;
    }
}

// ---- Safety checks ----
if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['ok'=>false,'message'=>'DB connection not initialized']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'message'=>'Invalid request method']); exit;
}

// ---- Inputs ----
$device = trim($_POST['device'] ?? '');
$action = trim($_POST['action'] ?? '');
$since  = trim($_POST['since']  ?? ''); // HTML datetime-local: YYYY-MM-DDTHH:MM
$until  = trim($_POST['until']  ?? '');
$limit  = (int)($_POST['limit'] ?? 200);
if ($limit < 1 || $limit > 1000) $limit = 200;

// ---- WHERE builder ----
$where = [];
$params = [];
$types  = '';

if ($device !== '') {
    $like = '%'.$device.'%';
    $where[] = '(device_name LIKE ? OR device_ip LIKE ?)';
    $params[] = $like; $params[] = $like; $types .= 'ss';
}
if ($action !== '') {
    $where[] = 'action = ?';
    $params[] = $action; $types .= 's';
}
if ($since !== '') {
    $since_ts = str_replace('T',' ', $since) . ':00';
    $where[] = 'time >= ?';
    $params[] = $since_ts; $types .= 's';
}
if ($until !== '') {
    $until_ts = str_replace('T',' ', $until) . ':59';
    $where[] = 'time <= ?';
    $params[] = $until_ts; $types .= 's';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ---- Query (adjust table/columns if your schema differs) ----
$sql = "SELECT time, device_name, device_ip, resource, action
        FROM activity_logs
        $whereSql
        ORDER BY time DESC
        LIMIT ?";

// Append limit
$params[] = $limit; $types .= 'i';

// ---- Helper: bind params dynamically with references (works on PHP 7+) ----
function stmt_bind_params(mysqli_stmt $stmt, string $types, array $params): bool {
    $refs = [];
    foreach ($params as $k => $v) { $refs[$k] = &$params[$k]; }
    array_unshift($refs, $types);
    return call_user_func_array([$stmt, 'bind_param'], $refs);
}

try {
    // If table is missing, this prepare will throw OR fail with error 1146
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // surface common “table doesn’t exist” error
        if ($conn->errno === 1146) {
            echo json_encode([
                'ok' => false,
                'message' => "Table 'activity_logs' not found. Create it or adjust the SELECT to your schema."
            ]);
            exit;
        }
        echo json_encode(['ok'=>false,'message'=>'Prepare failed: '.$conn->error]); exit;
    }

    if ($types !== '') {
        if (!stmt_bind_params($stmt, $types, $params)) {
            echo json_encode(['ok'=>false,'message'=>'bind_param failed']); exit;
        }
    }

    if (!$stmt->execute()) {
        echo json_encode(['ok'=>false,'message'=>'Execute failed: '.$stmt->error]); exit;
    }

    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'time'        => $r['time'],
            'device_name' => $r['device_name'] ?? '',
            'device_ip'   => $r['device_ip'] ?? '',
            'resource'    => $r['resource'] ?? '',
            'action'      => $r['action'] ?? '',
        ];
    }
    $stmt->close();

    echo json_encode(['ok'=>true,'rows'=>$rows]); exit;

} catch (Throwable $e) {
    // Return error as JSON instead of blank 500
    error_log('get_recent_activity fatal: '.$e->getMessage());
    echo json_encode(['ok'=>false,'message'=>'Server error: '.$e->getMessage()]); exit;
}
?>