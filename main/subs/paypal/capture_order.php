<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/../../../connectMySql.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$orderID = $input['orderID'] ?? null;
if (!$orderID) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing orderID']);
    exit;
}

try { $token = paypal_get_access_token(); }
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'OAuth failed']);
    exit;
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => PAYPAL_API_BASE . "/v2/checkout/orders/{$orderID}/capture",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]
]);
$response = curl_exec($ch);
if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Capture error', 'detail' => curl_error($ch)]);
    exit;
}
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
$completed = ($data['status'] ?? $data['result']['status'] ?? null) === 'COMPLETED';

if ($status >= 200 && $status < 300 && $completed) {
    $userId = (int)$_SESSION['user_id'];

    // If user already has active premium in future, extend from that date; else from now (UTC)
    $currExp = null;
    if ($stmt = $conn->prepare("
        SELECT expires_at
          FROM subscriptions
         WHERE user_id = ?
           AND status = 'active'
           AND plan = 'premium'
           AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
         ORDER BY started_at DESC
         LIMIT 1
    ")) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($currExp);
        $stmt->fetch();
        $stmt->close();
    }

    $base = $currExp ? new DateTime($currExp, new DateTimeZone('UTC'))
                     : new DateTime('now', new DateTimeZone('UTC'));
    $base->modify('+1 month');                       // or '+30 days'
    $expiresAt = $base->format('Y-m-d H:i:s');       // UTC

    // Mark older rows inactive (optional)
    $conn->query("UPDATE subscriptions SET status='inactive' WHERE user_id={$userId}");

    // Upsert active premium with new expiry
    if ($stmt = $conn->prepare("
        INSERT INTO subscriptions (user_id, plan, status, order_id, started_at, expires_at, updated_at)
        VALUES (?, 'premium', 'active', ?, UTC_TIMESTAMP(), ?, UTC_TIMESTAMP())
        ON DUPLICATE KEY UPDATE
          plan='premium',
          status='active',
          order_id=VALUES(order_id),
          started_at=UTC_TIMESTAMP(),
          expires_at=VALUES(expires_at),
          updated_at=UTC_TIMESTAMP()
    ")) {
        $stmt->bind_param('iss', $userId, $orderID, $expiresAt);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['status' => 'COMPLETED', 'orderID' => $orderID, 'expires_at' => $expiresAt, 'data' => $data]);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'NOT_COMPLETED', 'data' => $data]);
}
