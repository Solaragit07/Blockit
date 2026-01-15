<?php
// Set proper headers for AJAX response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Include necessary files
include '../../connectMySql.php';
include '../../loginverification.php';

// RouterOS API imports
require_once '../../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

try {
    if (!logged_in()) {
        throw new Exception('Not authenticated');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $macAddress = $_POST['mac_address'] ?? '';
    $reason = $_POST['reason'] ?? 'manual_block';
    
    if (empty($macAddress)) {
        throw new Exception('MAC address is required');
    }

    $success = false;
    $message = '';
    $blockActions = [];

    // 1. Block device in database
    try {
        // Check if device exists in database
        $checkQuery = "SELECT id, device_name FROM device WHERE mac_address = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("s", $macAddress);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing device
            $device = $result->fetch_assoc();
            $updateQuery = "UPDATE device SET internet = 'Yes', blocked_reason = ?, blocked_at = NOW() WHERE mac_address = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("ss", $reason, $macAddress);
            $updateStmt->execute();
            
            $blockActions[] = "Updated device '{$device['device_name']}' in database";
        } else {
            // Insert new device entry as blocked
            $insertQuery = "INSERT INTO device (mac_address, device_name, internet, blocked_reason, blocked_at) VALUES (?, ?, 'Yes', ?, NOW())";
            $insertStmt = $conn->prepare($insertQuery);
            $deviceName = "Auto-blocked Device";
            $insertStmt->bind_param("sss", $macAddress, $deviceName, $reason);
            $insertStmt->execute();
            
            $blockActions[] = "Added new blocked device to database";
        }
        
    } catch (Exception $e) {
        $blockActions[] = "Database error: " . $e->getMessage();
    }

    // 2. Block device on MikroTik router
    try {
        include '../../API/connectMikrotik.php';
        
        if (isset($client) && $client !== null) {
            // Add firewall filter rule to block this MAC address
            $blockRule = new Query('/ip/firewall/filter/add');
            $blockRule->equal('chain', 'forward');
            $blockRule->equal('src-mac-address', $macAddress);
            $blockRule->equal('action', 'drop');
            $blockRule->equal('comment', "Auto-blocked: {$reason} - " . date('Y-m-d H:i:s'));
            
            $client->query($blockRule)->read();
            $blockActions[] = "Added firewall rule to block device on router";
            
            // Also try to remove from DHCP lease to force disconnect
            try {
                $dhcpQuery = new Query('/ip/dhcp-server/lease/print');
                $dhcpQuery->where('mac-address', $macAddress);
                $leases = $client->query($dhcpQuery)->read();
                
                foreach ($leases as $lease) {
                    if (isset($lease['.id'])) {
                        $removeQuery = new Query('/ip/dhcp-server/lease/remove');
                        $removeQuery->equal('.id', $lease['.id']);
                        $client->query($removeQuery)->read();
                        $blockActions[] = "Removed DHCP lease for device";
                    }
                }
            } catch (Exception $e) {
                $blockActions[] = "DHCP removal warning: " . $e->getMessage();
            }
            
        } else {
            $blockActions[] = "MikroTik router not available - device blocked in database only";
        }
        
    } catch (Exception $e) {
        $blockActions[] = "Router error: " . $e->getMessage();
    }

    // 3. End device session in session tracking
    try {
        $sessionQuery = "UPDATE device_sessions SET session_end = NOW() WHERE mac_address = ? AND session_end IS NULL";
        $sessionStmt = $conn->prepare($sessionQuery);
        $sessionStmt->bind_param("s", $macAddress);
        $sessionStmt->execute();
        
        if ($sessionStmt->affected_rows > 0) {
            $blockActions[] = "Ended active device session";
        }
        
    } catch (Exception $e) {
        $blockActions[] = "Session update error: " . $e->getMessage();
    }

    // 4. Log the blocking action
    try {
        $logQuery = "INSERT INTO device_block_log (mac_address, reason, blocked_at, actions_taken) VALUES (?, ?, NOW(), ?)";
        $logStmt = $conn->prepare($logQuery);
        $actionsJson = json_encode($blockActions);
        $logStmt->bind_param("sss", $macAddress, $reason, $actionsJson);
        $logStmt->execute();
        
        $blockActions[] = "Logged blocking action";
        
    } catch (Exception $e) {
        // Create log table if it doesn't exist
        $createLogTable = "CREATE TABLE IF NOT EXISTS device_block_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mac_address VARCHAR(17) NOT NULL,
            reason VARCHAR(255),
            blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            actions_taken TEXT,
            INDEX idx_mac (mac_address),
            INDEX idx_blocked_at (blocked_at)
        )";
        
        if ($conn->query($createLogTable)) {
            // Retry logging
            $logQuery = "INSERT INTO device_block_log (mac_address, reason, blocked_at, actions_taken) VALUES (?, ?, NOW(), ?)";
            $logStmt = $conn->prepare($logQuery);
            $actionsJson = json_encode($blockActions);
            $logStmt->bind_param("sss", $macAddress, $reason, $actionsJson);
            $logStmt->execute();
            $blockActions[] = "Created log table and logged action";
        } else {
            $blockActions[] = "Logging error: " . $e->getMessage();
        }
    }

    $success = true;
    $message = "Device {$macAddress} has been blocked successfully";

    $response = [
        'success' => $success,
        'message' => $message,
        'mac_address' => $macAddress,
        'reason' => $reason,
        'actions_taken' => $blockActions,
        'timestamp' => date('Y-m-d H:i:s')
    ];

} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Error blocking device: ' . $e->getMessage(),
        'mac_address' => $macAddress ?? 'unknown',
        'reason' => $reason ?? 'unknown',
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => true
    ];
    
    error_log("Auto-block device error: " . $e->getMessage());
}

// Return JSON response
echo json_encode($response, JSON_PRETTY_PRINT);
?>
