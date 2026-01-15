<?php
/**
 * Set Time Limit for Device
 * This file handles setting time limits for devices and stores them in the database
 */

// Disable error output to prevent JSON corruption
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

// Include database connection and login verification
require_once '../../connectMySql.php';
require_once '../../loginverification.php';

// Check if user is logged in
if (!logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

if (!isset($conn)) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests allowed']);
    exit;
}

// Get POST data
$mac_address = $_POST['mac_address'] ?? '';
$hostname = $_POST['hostname'] ?? '';
$time_limit_minutes = (int)($_POST['time_limit_minutes'] ?? 0);
$start_time = $_POST['start_time'] ?? '';
$end_time = $_POST['end_time'] ?? '';

// Debug logging
error_log("DEBUG - set_time_limit.php received data: " . json_encode([
    'mac_address' => $mac_address,
    'hostname' => $hostname,
    'time_limit_minutes' => $time_limit_minutes,
    'start_time' => $start_time,
    'end_time' => $end_time,
    'raw_post' => $_POST
]));

// Validate required fields
if (empty($mac_address)) {
    error_log("ERROR - MAC address is empty");
    echo json_encode(['status' => 'error', 'message' => 'MAC address is required']);
    exit;
}

if ($time_limit_minutes < 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid time limit']);
    exit;
}

try {
    // Create device_time_limits table if it doesn't exist
    $createTableSQL = "CREATE TABLE IF NOT EXISTS device_time_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mac_address VARCHAR(17) NOT NULL,
        hostname VARCHAR(255),
        time_limit_minutes INT NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_mac_active (mac_address, is_active),
        INDEX idx_end_time (end_time)
    )";
    $conn->query($createTableSQL);

    // IMPORTANT: First, mark ALL existing active time limits for this device as inactive
    $deactivateStmt = $conn->prepare("UPDATE device_time_limits SET is_active = FALSE WHERE mac_address = ? AND is_active = TRUE");
    $deactivateStmt->bind_param("s", $mac_address);
    $deactivateStmt->execute();
    
    error_log("Deactivated all existing time limits for device: $mac_address");

    // Now insert the new time limit as the only active one
    $insertStmt = $conn->prepare("INSERT INTO device_time_limits 
        (mac_address, hostname, time_limit_minutes, start_time, end_time, is_active) 
        VALUES (?, ?, ?, ?, ?, TRUE)");
    
    $insertStmt->bind_param("ssiss", $mac_address, $hostname, $time_limit_minutes, $start_time, $end_time);
    
    if ($insertStmt->execute()) {
        // Log the new time limit
        error_log("Time limit set for device: $hostname ($mac_address) - $time_limit_minutes minutes (deactivated previous limits)");
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Time limit set successfully',
            'mac_address' => $mac_address,
            'hostname' => $hostname,
            'time_limit_minutes' => $time_limit_minutes
        ]);
    } else {
        throw new Exception("Failed to set time limit: " . $conn->error);
    }

} catch (Exception $e) {
    error_log("Error in set_time_limit.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
