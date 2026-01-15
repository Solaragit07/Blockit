<?php
include '../../connectMySql.php';
include '../../loginverification.php';

// RouterOS API imports
require_once '../../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

header('Content-Type: application/json');

if(!logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

if($_POST['action'] !== 'block' || !isset($_POST['mac_address'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$macAddress = $_POST['mac_address'];

try {
    // Connect to RouterOS
    include '../../API/connectMikrotik.php';
    
    // Add the MAC address to a blocked devices address list
    $client->query((new Query('/ip/firewall/address-list/add'))
        ->equal('list', 'blocked_devices')
        ->equal('address', $macAddress)
        ->equal('comment', 'Unknown device blocked from dashboard - ' . date('Y-m-d H:i:s'))
    )->read();
    
    // Create firewall rule to block this device if it doesn't exist
    $existingRules = $client->query((new Query('/ip/firewall/filter/print'))
        ->where('src-mac-address', $macAddress)
        ->where('action', 'drop')
    )->read();
    
    if(empty($existingRules)) {
        $client->query((new Query('/ip/firewall/filter/add'))
            ->equal('chain', 'forward')
            ->equal('src-mac-address', $macAddress)
            ->equal('action', 'drop')
            ->equal('comment', 'Block unknown device - ' . $macAddress)
        )->read();
    }
    
    // Log the action
    $logQuery = "INSERT INTO api_log (action, device_mac, status, timestamp) VALUES 
                 ('block_unknown_device', '$macAddress', 'success', NOW())";
    $conn->query($logQuery);
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Unknown device blocked successfully',
        'mac_address' => $macAddress
    ]);
    
} catch (Exception $e) {
    // Log the error
    $errorMsg = $e->getMessage();
    $logQuery = "INSERT INTO api_log (action, device_mac, status, error_message, timestamp) VALUES 
                 ('block_unknown_device', '$macAddress', 'error', '$errorMsg', NOW())";
    $conn->query($logQuery);
    
    echo json_encode([
        'status' => 'error', 
        'message' => 'Failed to block device: ' . $errorMsg
    ]);
}
?>
