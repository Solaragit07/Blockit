<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    require_once '../../connectMySql.php';
    
    // Check if connection is successful
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Check if table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'device_time_limits'");
    if (!$tableExists || $tableExists->num_rows === 0) {
        // Create the table if it doesn't exist
        $createSQL = "CREATE TABLE IF NOT EXISTS device_time_limits (
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
        
        if (!$conn->query($createSQL)) {
            throw new Exception('Failed to create table: ' . $conn->error);
        }
    }
    // Get active time limits that haven't expired
    $stmt = $conn->prepare("
        SELECT 
            mac_address, 
            hostname, 
            time_limit_minutes, 
            start_time, 
            end_time,
            TIMESTAMPDIFF(MINUTE, start_time, NOW()) as used_minutes,
            GREATEST(0, TIMESTAMPDIFF(MINUTE, NOW(), end_time)) as remaining_minutes
        FROM device_time_limits 
        WHERE is_active = TRUE 
        AND end_time > NOW()
        ORDER BY start_time DESC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $active_limits = [];
    while ($row = $result->fetch_assoc()) {
        $active_limits[] = [
            'mac_address' => $row['mac_address'],
            'hostname' => $row['hostname'],
            'time_limit_minutes' => (int)$row['time_limit_minutes'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'used_minutes' => (int)$row['used_minutes'],
            'remaining_minutes' => (int)$row['remaining_minutes']
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'active_limits' => $active_limits
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_active_time_limits.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
?>
