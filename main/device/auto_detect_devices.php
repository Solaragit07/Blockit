<?php
include '../../connectMySql.php';
include '../../loginverification.php';
include '../../includes/api_helper.php';

header('Content-Type: application/json');

if (!logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    autoDetectDevices();
}

function autoDetectDevices() {
    global $conn;
    
    include_once('../../API/connectMikrotik.php');
    
    if (!$client->connect()) {
        echo json_encode(['success' => false, 'message' => 'Failed to connect to router']);
        return;
    }
    
    try {
        // Get DHCP leases
        $dhcpLeases = $client->query('/ip/dhcp-server/lease/print', [
            '?status' => 'bound'
        ]);
        
        $newDevicesCount = 0;
        
        foreach ($dhcpLeases as $lease) {
            $mac = $lease['mac-address'] ?? '';
            $ip = $lease['address'] ?? '';
            $hostname = $lease['host-name'] ?? 'Unknown Device';
            $server = $lease['server'] ?? '';
            
            if (empty($mac) || empty($ip)) {
                continue;
            }
            
            // Check if device already exists
            $checkQuery = "SELECT id FROM device WHERE mac_address = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("s", $mac);
            $checkStmt->execute();
            
            if ($checkStmt->get_result()->num_rows == 0) {
                // Add new device with enhanced info
                $deviceName = !empty($hostname) && $hostname !== 'Unknown Device' ? $hostname : generateDeviceName($mac);
                
                // Insert with IP address stored in a comment/note field if available
                $insertQuery = "INSERT INTO device (name, mac_address, timelimit, internet, bandwidth, device_type) VALUES (?, ?, 8, 'No', 3, 'Auto-detected')";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("ss", $deviceName, $mac);
                
                if ($insertStmt->execute()) {
                    $newDevicesCount++;
                    
                    // Log the detection for debugging
                    error_log("Auto-detected new device: $deviceName ($mac) with IP: $ip");
                }
            }
        }
        
        $client->disconnect();
        
        if ($newDevicesCount > 0) {
            echo json_encode([
                'success' => true, 
                'message' => "Found and added {$newDevicesCount} new device(s)",
                'devices_added' => $newDevicesCount
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'No new devices found',
                'devices_added' => 0
            ]);
        }
        
    } catch (Exception $e) {
        $client->disconnect();
        echo json_encode(['success' => false, 'message' => 'Error scanning for devices: ' . $e->getMessage()]);
    }
}

function generateDeviceName($mac) {
    // Generate a friendly name based on MAC address
    $macParts = explode(':', $mac);
    $lastPart = end($macParts);
    return "Device-" . strtoupper($lastPart);
}
?>
