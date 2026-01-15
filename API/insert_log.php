<?php

include '../connectMySql.php';

$type = $_POST['type'] ?? null;
$domain = $_POST['domain'] ?? null;
$date = $_POST['date'] ?? null;
$mac_address = $_POST['mac_address'] ?? null;


if (!$type || !$domain || !$date || !$mac_address) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO logs (type, domain, date, mac_address) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $type, $domain, $date, $mac_address);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Log inserted']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
