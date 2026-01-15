<?php
session_start();
include 'connectMySql.php';
include 'loginverification.php';
include 'includes/DeviceDetectionService.php';

// Simple diagnostic to test internet detection
echo "<!DOCTYPE html>
<html>
<head>
    <title>Internet Detection Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .warning { color: orange; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🔍 Internet Detection Diagnostic</h1>";

try {
    include 'API/connectMikrotik.php';
    
    if (isset($client) && $client !== null) {
        echo "<p class='success'>✅ MikroTik connection established</p>";
        
        $deviceService = new DeviceDetectionService($client, $conn);
        
        // Test 1: Get basic connected devices
        echo "<h2>📱 Connected Devices</h2>";
        $basicData = $deviceService->getConnectedDevicesOnly();
        echo "<p>Found " . count($basicData['devices']) . " connected devices:</p>";
        
        foreach($basicData['devices'] as $device) {
            $mac = $device['mac-address'] ?? 'Unknown';
            $ip = $device['address'] ?? 'Unknown';
            $host = $device['host-name'] ?? 'Unknown';
            $lastSeen = $device['last-seen'] ?? 'Unknown';
            
            echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ddd;'>
                    <strong>Device:</strong> $host<br>
                    <strong>MAC:</strong> $mac<br>
                    <strong>IP:</strong> $ip<br>
                    <strong>Last Seen:</strong> $lastSeen
                  </div>";
        }
        
        // Test 2: Get internet-detected devices
        echo "<h2>🌐 Internet Detection Test</h2>";
        $internetData = $deviceService->getInternetConnectedDevices();
        echo "<p>Devices with internet detection:</p>";
        
        foreach($internetData['devices'] as $device) {
            $mac = $device['mac-address'] ?? 'Unknown';
            $ip = $device['address'] ?? 'Unknown';
            $host = $device['host-name'] ?? 'Unknown';
            $hasInternet = $device['hasInternet'] ?? false;
            $status = $hasInternet ? '<span class="success">🌐 HAS INTERNET</span>' : '<span class="warning">🏠 LOCAL ONLY</span>';
            
            echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ddd;'>
                    <strong>Device:</strong> $host<br>
                    <strong>MAC:</strong> $mac<br>
                    <strong>IP:</strong> $ip<br>
                    <strong>Status:</strong> $status
                  </div>";
        }
        
        // Test 3: Check firewall connections
        echo "<h2>🔥 Firewall Connections Test</h2>";
        try {
            $connectionsQuery = new \RouterOS\Query('/ip/firewall/connection/print');
            $connectionsQuery->add('?connection-state=established');
            $connections = $client->query($connectionsQuery)->read();
            
            echo "<p>Found " . count($connections) . " established connections</p>";
            
            $internetConnections = 0;
            foreach($connections as $conn) {
                $srcAddress = $conn['src-address'] ?? '';
                $dstAddress = $conn['dst-address'] ?? '';
                
                if (!empty($srcAddress) && !empty($dstAddress)) {
                    $srcIP = explode(':', $srcAddress)[0];
                    $dstIP = explode(':', $dstAddress)[0];
                    
                    // Check for local to internet connections
                    if (strpos($srcIP, '192.168.') === 0 && strpos($dstIP, '192.168.') !== 0) {
                        $internetConnections++;
                        echo "<div style='margin: 5px 0; padding: 5px; background: #e8f5e8;'>
                                🌐 $srcIP → $dstIP (Internet connection)
                              </div>";
                    }
                }
            }
            
            echo "<p class='info'>Total internet connections found: $internetConnections</p>";
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Failed to check firewall connections: " . $e->getMessage() . "</p>";
        }
        
    } else {
        echo "<p class='error'>❌ Failed to connect to MikroTik router</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<br><br><p><a href='main/dashboard/'>← Back to Dashboard</a></p>";
echo "</body></html>";
?>
