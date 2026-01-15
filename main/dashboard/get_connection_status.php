<?php
include '../../connectMySql.php';
include '../../loginverification.php';

// RouterOS API imports
require_once '../../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

header('Content-Type: application/json');

if(!logged_in()) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$devices = [];

try {
    // Get all devices from database for lookup
    $deviceMap = [];
    $query = "SELECT * FROM device";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $deviceMap[$row['mac_address']] = $row;
    }
    
    // Get connected devices from RouterOS
    include '../../API/connectMikrotik.php';
    $dhcpLeases = $client->query((new Query('/ip/dhcp-server/lease/print')))->read();
    
    foreach($dhcpLeases as $lease) {
        $macAddress = $lease['mac-address'];
        $ipAddress = isset($lease['address']) ? $lease['address'] : 'N/A';
        $hostName = isset($lease['host-name']) ? $lease['host-name'] : 'Unknown';
        
        // Check if this device is in our database
        if(isset($deviceMap[$macAddress])) {
            // Known device from database
            $device = $deviceMap[$macAddress];
            $devices[] = [
                'id' => $device['id'],
                'name' => $device['name'],
                'mac' => $macAddress,
                'ip' => $ipAddress,
                'connected' => true,
                'known' => true,
                'device_type' => $device['device'],
                'age' => $device['age'],
                'timelimit' => $device['timelimit']
            ];
        } else {
            // Unknown device
            $devices[] = [
                'id' => null,
                'name' => !empty($hostName) && $hostName != 'Unknown' ? $hostName : 'Unknown Device',
                'mac' => $macAddress,
                'ip' => $ipAddress,
                'connected' => true,
                'known' => false,
                'device_type' => 'Unknown',
                'age' => null,
                'timelimit' => null
            ];
        }
    }
    
} catch (Exception $e) {
    // If RouterOS connection fails, return empty array
    $devices = [];
}

echo json_encode($devices);
?>
