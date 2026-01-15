<?php
// Test API endpoint to check if everything is working
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connectMySql.php';
include '../../loginverification.php';

header('Content-Type: application/json');

if(!logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'BlockList API is working!',
    'timestamp' => date('Y-m-d H:i:s'),
    'connected_to_db' => isset($conn) && $conn ? true : false,
    'tables_exist' => [
        'application_blocks' => $conn->query('SHOW TABLES LIKE "application_blocks"')->num_rows > 0,
        'application_categories' => $conn->query('SHOW TABLES LIKE "application_categories"')->num_rows > 0,
        'blocklist' => $conn->query('SHOW TABLES LIKE "blocklist"')->num_rows > 0,
        'whitelist' => $conn->query('SHOW TABLES LIKE "whitelist"')->num_rows > 0
    ]
]);
?>
