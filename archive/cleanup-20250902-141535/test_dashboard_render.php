<?php
/**
 * Check what the dashboard actually renders
 */

// Simulate visiting the dashboard and capture device data
ob_start();

// Include the dashboard logic with sane limits
set_time_limit(30);
ini_set('max_execution_time', 30);
ini_set('default_socket_timeout', 3);

include 'connectMySql.php';
include 'includes/DeviceDetectionService.php';

// RouterOS API imports
require_once 'vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

// Initialize centralized device detection service
$deviceService = null;
$connectedDevices = [];
$connectedMACs = [];

// Set a shorter timeout for this script to prevent hanging
set_time_limit(12);

try {
    // Only attempt MikroTik connection if router is reachable
    $router_ip = '192.168.10.1';
    $socket_test = @fsockopen($router_ip, 8728, $errno, $errstr, 1); // 1 second timeout
    
    if ($socket_test) {
        fclose($socket_test);
        
        // Set a timeout for the include
        $start_time = time();
        
        // Try MikroTik connection with new safe timeout protection
        error_log("Dashboard: Attempting safe MikroTik connection");
        
        try {
            // Use the new safe MikroTik connection
            include 'API/connectMikrotik_safe.php';
            
            $connection_time = time() - $start_time;
            if ($connection_time > 4) {
                error_log("Dashboard: Safe MikroTik connection took too long ($connection_time seconds)");
                $client = null;
            } elseif (isset($client) && $client !== null) {
                $deviceService = new DeviceDetectionService($client, $conn);
                // Get devices with internet connectivity detection
                $deviceData = $deviceService->getInternetConnectedDevices();
                $connectedDevices = $deviceData['devices'];
                $connectedMACs = $deviceData['macs'];
                error_log("Dashboard: Safe MikroTik connected successfully in {$connection_time}s - found " . count($connectedDevices) . " devices");
            } else {
                error_log("Dashboard: Safe MikroTik connection returned null client");
                $client = null;
            }
        } catch (Exception $e) {
            error_log("Dashboard: Safe MikroTik connection failed: " . $e->getMessage());
            $client = null;
        }
    } else {
        error_log("Dashboard: Router unreachable at $router_ip:8728, skipping MikroTik connection");
        $client = null;
    }
} catch (Exception $e) {
    error_log("Dashboard: MikroTik connection error - " . $e->getMessage());
    $client = null;
}

// Capture the output
$output = ob_get_clean();

echo "=== Dashboard Device Rendering Test ===\n\n";
echo "Devices found: " . count($connectedDevices) . "\n\n";

foreach ($connectedDevices as $i => $device) {
    echo "=== Device " . ($i + 1) . " ===\n";
    echo "MAC: " . ($device['mac-address'] ?? 'Unknown') . "\n";
    echo "IP: " . ($device['address'] ?? 'Unknown') . "\n";
    echo "Hostname: " . ($device['host-name'] ?? 'Unknown') . "\n";
    echo "Status: " . ($device['status'] ?? 'Unknown') . "\n";
    echo "\n";
}

if (count($connectedDevices) >= 2) {
    echo "✅ Both devices should be visible in the dashboard!\n";
} else {
    echo "❌ Only " . count($connectedDevices) . " device(s) detected\n";
}

echo "\n=== Check the dashboard at http://localhost/blockit/main/dashboard/ ===\n";
?>
