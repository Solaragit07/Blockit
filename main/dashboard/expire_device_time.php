<?php
/**
 * Expire Device Time and Block
 * This file handles automatically blocking devices when their time limit is reached
 */

header('Content-Type: application/json');

// Include database connection and MikroTik API
include_once '../../API/connectMikrotik.php';

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
$device_name = $_POST['device_name'] ?? '';
$timestamp = $_POST['timestamp'] ?? '';

// Validate required fields
if (empty($mac_address)) {
    echo json_encode(['status' => 'error', 'message' => 'MAC address is required']);
    exit;
}

try {
    // Create device_time_expiry table if it doesn't exist
    $createTableSQL = "CREATE TABLE IF NOT EXISTS device_time_expiry (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mac_address VARCHAR(17) NOT NULL,
        device_name VARCHAR(255),
        expired_at DATETIME NOT NULL,
        blocked_successfully BOOLEAN DEFAULT FALSE,
        block_method VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mac_expired (mac_address, expired_at)
    )";
    $conn->query($createTableSQL);

    // Deactivate the time limit
    $deactivateStmt = $conn->prepare("UPDATE device_time_limits SET is_active = FALSE WHERE mac_address = ? AND is_active = TRUE");
    $deactivateStmt->bind_param("s", $mac_address);
    $deactivateStmt->execute();

    $blocked_successfully = false;
    $block_method = 'none';

    // Try to block the device using MikroTik API
    if (isset($client) && $client !== null) {
        try {
            // Method 1: Add to address list for blocking
            $blockQuery = new \RouterOS\Query('/ip/firewall/address-list/add');
            $blockQuery->equal('list', 'blocked_devices');
            $blockQuery->equal('address', getMacIPAddress($mac_address, $client));
            $blockQuery->equal('comment', "Auto-blocked: Time limit reached for $device_name");
            
            $client->query($blockQuery)->read();
            $blocked_successfully = true;
            $block_method = 'firewall_address_list';
            
            error_log("Device blocked via firewall address list: $device_name ($mac_address)");
            
        } catch (Exception $e) {
            error_log("Failed to block device via address list: " . $e->getMessage());
            
            try {
                // Method 2: Try to disable DHCP lease
                $dhcpQuery = new \RouterOS\Query('/ip/dhcp-server/lease/print');
                $dhcpQuery->where('mac-address', $mac_address);
                $leases = $client->query($dhcpQuery)->read();
                
                if (!empty($leases)) {
                    $lease = $leases[0];
                    $leaseId = $lease['.id'];
                    
                    $disableQuery = new \RouterOS\Query('/ip/dhcp-server/lease/set');
                    $disableQuery->equal('.id', $leaseId);
                    $disableQuery->equal('disabled', 'yes');
                    
                    $client->query($disableQuery)->read();
                    $blocked_successfully = true;
                    $block_method = 'dhcp_lease_disable';
                    
                    error_log("Device blocked via DHCP lease disable: $device_name ($mac_address)");
                }
                
            } catch (Exception $e2) {
                error_log("Failed to block device via DHCP lease: " . $e2->getMessage());
            }
        }
    }

    // Insert expiry record
    $insertStmt = $conn->prepare("INSERT INTO device_time_expiry 
        (mac_address, device_name, expired_at, blocked_successfully, block_method) 
        VALUES (?, ?, ?, ?, ?)");
    
    $insertStmt->bind_param("sssis", $mac_address, $device_name, $timestamp, $blocked_successfully, $block_method);
    
    if ($insertStmt->execute()) {
        $expiry_id = $conn->insert_id;
        
        // Also add to blocked devices table if it exists
        try {
            $blockedStmt = $conn->prepare("INSERT IGNORE INTO blocked_devices 
                (mac_address, device_name, blocked_at, block_reason) 
                VALUES (?, ?, ?, 'Time limit reached')");
            $blockedStmt->bind_param("sss", $mac_address, $device_name, $timestamp);
            $blockedStmt->execute();
        } catch (Exception $e) {
            // Table might not exist, ignore this error
            error_log("Could not insert into blocked_devices table: " . $e->getMessage());
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Device time expired and handled successfully',
            'expiry_id' => $expiry_id,
            'blocked_successfully' => $blocked_successfully,
            'block_method' => $block_method,
            'device_name' => $device_name,
            'mac_address' => $mac_address
        ]);
        
    } else {
        throw new Exception("Failed to record device expiry: " . $conn->error);
    }

} catch (Exception $e) {
    error_log("Error in expire_device_time.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

/**
 * Get IP address for a MAC address from MikroTik
 */
function getMacIPAddress($mac_address, $client) {
    try {
        $dhcpQuery = new \RouterOS\Query('/ip/dhcp-server/lease/print');
        $dhcpQuery->where('mac-address', $mac_address);
        $leases = $client->query($dhcpQuery)->read();
        
        if (!empty($leases)) {
            return $leases[0]['address'] ?? '';
        }
        
        // Try ARP table as fallback
        $arpQuery = new \RouterOS\Query('/ip/arp/print');
        $arpQuery->where('mac-address', $mac_address);
        $arpEntries = $client->query($arpQuery)->read();
        
        if (!empty($arpEntries)) {
            return $arpEntries[0]['address'] ?? '';
        }
        
    } catch (Exception $e) {
        error_log("Error getting IP for MAC $mac_address: " . $e->getMessage());
    }
    
    return '';
}

$conn->close();
?>
