<?php
header('Content-Type: application/json');
require_once '../../connectMySql.php';
require_once '../../includes/MikroTikBandwidthMonitor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

try {
    $mac_address = $_POST['mac_address'] ?? '';
    $download_limit = $_POST['download_limit'] ?? '';
    $upload_limit = $_POST['upload_limit'] ?? '';
    $device_name = $_POST['device_name'] ?? 'Unknown Device';
    
    if (empty($mac_address) || empty($download_limit) || empty($upload_limit)) {
        echo json_encode(['status' => 'error', 'message' => 'MAC address and bandwidth limits are required']);
        exit;
    }
    
    // Validate bandwidth limits
    $download_limit = floatval($download_limit);
    $upload_limit = floatval($upload_limit);
    
    if ($download_limit <= 0 || $upload_limit <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Bandwidth limits must be greater than 0']);
        exit;
    }
    
    // Convert Mbps to bytes per second (for MikroTik)
    $download_limit_bps = $download_limit * 1024 * 1024;
    $upload_limit_bps = $upload_limit * 1024 * 1024;
    
    try {
        // Initialize MikroTik connection
        $monitor = new MikroTikBandwidthMonitor('192.168.10.1', 'admin', 'admin123');
        
        // Apply bandwidth limitation via MikroTik
        $result = $monitor->setBandwidthLimit($mac_address, $download_limit_bps, $upload_limit_bps);
        
        if ($result) {
            // Save bandwidth limit to database
            $stmt = $conn->prepare("
                INSERT INTO bandwidth_limits (mac_address, device_name, download_limit_mbps, upload_limit_mbps, created_at) 
                VALUES (?, ?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                device_name = VALUES(device_name),
                download_limit_mbps = VALUES(download_limit_mbps), 
                upload_limit_mbps = VALUES(upload_limit_mbps),
                updated_at = NOW()
            ");
            $stmt->bind_param("ssdd", $mac_address, $device_name, $download_limit, $upload_limit);
            $stmt->execute();
            
            echo json_encode([
                'status' => 'success', 
                'message' => "Bandwidth limited to {$download_limit}Mbps down / {$upload_limit}Mbps up",
                'download_limit' => $download_limit,
                'upload_limit' => $upload_limit
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to apply bandwidth limit on router']);
        }
        
    } catch (Exception $e) {
        error_log("MikroTik error in limit_bandwidth.php: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Router connection failed: ' . $e->getMessage()]);
    }
    
} catch (Exception $e) {
    error_log("Error in limit_bandwidth.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
?>
