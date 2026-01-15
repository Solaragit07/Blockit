<?php
header('Content-Type: application/json');
require_once '../../connectMySql.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

try {
    // Create device_profiles table if it doesn't exist
    $createTable = "CREATE TABLE IF NOT EXISTS device_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mac_address VARCHAR(17) UNIQUE NOT NULL,
        device_name VARCHAR(255) NOT NULL,
        ip_address VARCHAR(15),
        status ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->query($createTable);
    
    $mac_address = $_POST['mac_address'] ?? '';
    $device_name = $_POST['device_name'] ?? 'Unknown Device';
    $ip_address = $_POST['ip_address'] ?? '';
    
    if (empty($mac_address)) {
        echo json_encode(['status' => 'error', 'message' => 'MAC address is required']);
        exit;
    }
    
    // Check if device already exists in profile
    $checkStmt = $conn->prepare("SELECT id FROM device_profiles WHERE mac_address = ?");
    $checkStmt->bind_param("s", $mac_address);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Device already exists in profiles']);
        exit;
    }
    
    // Insert new device profile
    $stmt = $conn->prepare("INSERT INTO device_profiles (mac_address, device_name, ip_address, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
    $stmt->bind_param("sss", $mac_address, $device_name, $ip_address);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Device successfully added to profile',
            'device_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add device to profile: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    error_log("Error in add_device_profile.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Internal server error: ' . $e->getMessage()]);
}
?>
