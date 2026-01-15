<?php
header('Content-Type: application/json');
require_once '../../connectMySql.php';

try {
    // Create device_profiles table if it doesn't exist
    $sql1 = "CREATE TABLE IF NOT EXISTS device_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mac_address VARCHAR(17) UNIQUE NOT NULL,
        device_name VARCHAR(255) NOT NULL,
        ip_address VARCHAR(15),
        status ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    // Create bandwidth_limits table if it doesn't exist
    $sql2 = "CREATE TABLE IF NOT EXISTS bandwidth_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mac_address VARCHAR(17) UNIQUE NOT NULL,
        device_name VARCHAR(255) NOT NULL,
        download_limit_mbps DECIMAL(10,2) NOT NULL,
        upload_limit_mbps DECIMAL(10,2) NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql1) === TRUE && $conn->query($sql2) === TRUE) {
        echo json_encode(['status' => 'success', 'message' => 'Database tables created successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error creating tables: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    error_log("Error in create_tables.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
?>
