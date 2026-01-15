<?php
/**
 * Device Detection Debug - Check what devices are being processed
 */

set_time_limit(30);
require_once 'vendor/autoload.php';
include 'connectMySql.php';
include 'includes/DeviceDetectionService.php';

echo "=== Device Detection Debug Analysis ===\n\n";

// Test MikroTik connection
try {
    $client = new RouterOS\Client([
        'host' => '192.168.10.1',
        'port' => 8728,
        'user' => 'user1',
        'pass' => 'admin',
        'timeout' => 5,
        'attempts' => 1
    ]);
    
    echo "✅ MikroTik connected\n\n";
    
    // Get device service data
    $deviceService = new DeviceDetectionService($client, $conn);
    $deviceData = $deviceService->getInternetConnectedDevices();
    $connectedDevices = $deviceData['devices'];
    
    echo "Raw device data from DeviceDetectionService:\n";
    echo "Device count: " . count($connectedDevices) . "\n\n";
    
    foreach ($connectedDevices as $i => $device) {
        echo "=== Device $i ===\n";
        foreach ($device as $key => $value) {
            echo "  $key: " . ($value ?? 'null') . "\n";
        }
        echo "\n";
    }
    
    // Now simulate the dashboard deduplication logic
    echo "=== Dashboard Deduplication Simulation ===\n";
    $finalUniqueDevices = [];
    $seenMACsDisplay = [];
    
    foreach ($connectedDevices as $device) {
        $mac = $device['mac-address'] ?? $device['mac'] ?? '';
        $hostname = $device['host-name'] ?? $device['hostname'] ?? 'Unknown';
        $ip = $device['address'] ?? 'Unknown';
        
        echo "Processing: MAC=$mac, hostname=$hostname, IP=$ip\n";
        
        if (!empty($mac) && !in_array($mac, $seenMACsDisplay)) {
            $finalUniqueDevices[] = $device;
            $seenMACsDisplay[] = $mac;
            echo "  ✅ ADDED to final display\n";
        } else {
            echo "  ❌ SKIPPED (empty=" . (empty($mac) ? 'yes' : 'no') . ", duplicate=" . (in_array($mac, $seenMACsDisplay) ? 'yes' : 'no') . ")\n";
        }
        echo "\n";
    }
    
    echo "Final unique devices for display: " . count($finalUniqueDevices) . "\n\n";
    
    echo "=== MAC Address Analysis ===\n";
    $allMACs = [];
    foreach ($connectedDevices as $device) {
        $mac = $device['mac-address'] ?? $device['mac'] ?? '';
        if (!empty($mac)) {
            $allMACs[] = $mac;
        }
    }
    
    $uniqueMACs = array_unique($allMACs);
    echo "Total MAC addresses found: " . count($allMACs) . "\n";
    echo "Unique MAC addresses: " . count($uniqueMACs) . "\n";
    
    if (count($allMACs) !== count($uniqueMACs)) {
        echo "⚠️ DUPLICATE MAC ADDRESSES DETECTED!\n";
        $macCounts = array_count_values($allMACs);
        foreach ($macCounts as $mac => $count) {
            if ($count > 1) {
                echo "  MAC $mac appears $count times\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
