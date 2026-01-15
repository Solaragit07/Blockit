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
$plan = $input['plan'] ?? 'premium';

$amount_value = "499.00";
$currency     = "PHP";
$description  = "BlockIt Premium Subscription (Monthly)";

try {
    $token = paypal_get_access_token();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'OAuth failed']);
    exit;
}

$payload = [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
        'amount' => ['currency_code' => $currency, 'value' => $amount_value],
        'description' => $description,
        'custom_id' => 'user_' . $_SESSION['user_id'] . '_plan_' . $plan
    ]]
];

try {
    [$status, $data] = paypal_post('/v2/checkout/orders', $payload, $token);
    if ($status >= 200 && $status < 300 && !empty($data['id'])) {

        // 🔐 persist a PENDING subscription row (no expiry yet)
        $userId  = (int)$_SESSION['user_id'];
        $orderId = $data['id'];

        $sql = "
            INSERT INTO subscriptions (user_id, plan, status, order_id, started_at, expires_at, updated_at)
            VALUES (?, ?, 'pending', ?, UTC_TIMESTAMP(), NULL, UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE
                plan       = VALUES(plan),
                status     = 'pending',
                order_id   = VALUES(order_id),
                started_at = UTC_TIMESTAMP(),
                expires_at = NULL,
                updated_at = UTC_TIMESTAMP()
        ";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('iss', $userId, $plan, $orderId);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode(['id' => $orderId]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Create order failed', 'details' => $data]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Create order exception']);
}
