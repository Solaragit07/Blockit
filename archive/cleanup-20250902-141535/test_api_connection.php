<?php
// Test API connection and device detection
header('Content-Type: application/json');

echo "<h2>Testing BlockIt API Connection</h2>";

// Test 1: Database connection
echo "<h3>1. Database Connection</h3>";
try {
    include 'connectMySql.php';
    echo "✅ Database connection successful<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

// Test 2: MikroTik API connection
echo "<h3>2. MikroTik API Connection</h3>";
try {
    include 'API/connectMikrotik.php';
    if (isset($client) && $client !== null) {
        echo "✅ MikroTik API connection successful<br>";
        
        // Test device detection
        echo "<h3>3. Device Detection Service</h3>";
        include 'includes/DeviceDetectionService.php';
        $deviceService = new DeviceDetectionService($client, $conn);
        $deviceData = $deviceService->getInternetConnectedDevices();
        
        echo "✅ Device detection service working<br>";
        echo "Found " . count($deviceData['devices']) . " devices<br>";
        
        if (!empty($deviceData['devices'])) {
            echo "<h4>Device List:</h4>";
            echo "<pre>";
            foreach ($deviceData['devices'] as $i => $device) {
                echo "Device " . ($i + 1) . ":\n";
                echo "  Hostname: " . ($device['host-name'] ?? $device['hostname'] ?? 'Unknown') . "\n";
                echo "  MAC: " . ($device['mac-address'] ?? $device['mac'] ?? 'Unknown') . "\n";
                echo "  IP: " . ($device['address'] ?? $device['ip'] ?? 'N/A') . "\n";
                echo "  Status: " . ($device['status'] ?? 'Unknown') . "\n";
                echo "\n";
            }
            echo "</pre>";
        } else {
            echo "ℹ️ No devices found - this might be normal if no devices are currently connected<br>";
        }
        
    } else {
        echo "❌ MikroTik API client is null<br>";
    }
} catch (Exception $e) {
    echo "❌ MikroTik API connection failed: " . $e->getMessage() . "<br>";
}

echo "<br><hr>";
echo "<p><a href='main/dashboard/'>Back to Dashboard</a></p>";
?>
