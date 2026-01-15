<?php
/**
 * Real Device Connection Test
 * This script tests if we can connect to your MikroTik and see real devices
 */

// Load RouterOS classes at the top
require_once 'vendor/autoload.php';
use RouterOS\Client;

echo "<!DOCTYPE html><html><head><title>Real Device Test</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;}";
echo ".success{color:green;} .error{color:red;} .warning{color:orange;}";
echo ".info{background:#e7f3ff;padding:10px;border-radius:5px;margin:10px 0;}";
echo "</style></head><body>";

echo "<h1>🧪 Real Device Connection Test</h1>";
echo "<p>Testing connection to your MikroTik router to show real devices...</p>";

// Step 1: Test basic connectivity
echo "<h2>Step 1: Testing Router Connectivity</h2>";
$router_ip = '192.168.10.1';
$api_port = 8728;

$socket_test = @fsockopen($router_ip, $api_port, $errno, $errstr, 5);
if ($socket_test) {
    echo "<div class='success'>✅ Router is reachable at $router_ip:$api_port</div>";
    fclose($socket_test);
} else {
    echo "<div class='error'>❌ Cannot reach router at $router_ip:$api_port</div>";
    echo "<div class='info'><strong>Fix:</strong> Check your router IP address. Common IPs: 192.168.1.1, 192.168.0.1, 192.168.10.1</div>";
    echo "</body></html>";
    exit;
}

// Step 2: Test API connection
echo "<h2>Step 2: Testing API Connection</h2>";
try {
    
    $client = new Client([
        'host' => $router_ip,
        'user' => 'user1',
        'pass' => 'admin',
        'port' => $api_port,
        'timeout' => 5
    ]);
    
    $identity = $client->query('/system/identity/print')->read();
    echo "<div class='success'>✅ API connection successful!</div>";
    
    if (!empty($identity)) {
        $routerName = $identity[0]['name'] ?? 'Unknown';
        echo "<div class='success'>🖥️ Router Name: <strong>$routerName</strong></div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ API connection failed: " . $e->getMessage() . "</div>";
    echo "<div class='info'>";
    echo "<strong>Possible fixes:</strong><br>";
    echo "1. Enable API service on your MikroTik router<br>";
    echo "2. Check username/password (current: user1/admin)<br>";
    echo "3. Verify API port is 8728<br>";
    echo "4. Check firewall rules<br>";
    echo "</div>";
    echo "</body></html>";
    exit;
}

// Step 3: Get real devices
echo "<h2>Step 3: Getting Real Connected Devices</h2>";
try {
    $dhcpLeases = $client->query('/ip/dhcp-server/lease/print')->read();
    
    if (empty($dhcpLeases)) {
        echo "<div class='warning'>⚠️ No devices found in DHCP leases</div>";
        echo "<div class='info'>This might mean:<br>";
        echo "• No devices are currently connected<br>";
        echo "• DHCP server is not configured<br>";
        echo "• Devices are using static IPs<br>";
        echo "</div>";
    } else {
        echo "<div class='success'>✅ Found " . count($dhcpLeases) . " real devices!</div>";
        echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse;margin:10px 0;'>";
        echo "<tr style='background:#f0f0f0;'><th>Device Name</th><th>MAC Address</th><th>IP Address</th><th>Status</th></tr>";
        
        foreach ($dhcpLeases as $lease) {
            $hostName = $lease['host-name'] ?? 'Unknown Device';
            $macAddress = $lease['mac-address'] ?? 'Unknown';
            $ipAddress = $lease['address'] ?? 'Unknown';
            $status = $lease['status'] ?? 'Unknown';
            
            $statusColor = ($status === 'bound') ? 'success' : 'warning';
            echo "<tr>";
            echo "<td><strong>$hostName</strong></td>";
            echo "<td><code>$macAddress</code></td>";
            echo "<td><strong>$ipAddress</strong></td>";
            echo "<td><span class='$statusColor'>$status</span></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Failed to get device list: " . $e->getMessage() . "</div>";
}

// Step 4: Test dashboard connection
echo "<h2>Step 4: Testing Dashboard Integration</h2>";
echo "<div class='info'>";
echo "<strong>Next Steps:</strong><br>";
echo "1. If devices were found above, they should now appear in your dashboard<br>";
echo "2. Go to your <a href='main/dashboard/'>Dashboard</a> to see real devices<br>";
echo "3. Real devices will replace the mock 'iPhone-Test' and 'MacBook-Pro-Test' data<br>";
echo "</div>";

echo "<div style='margin:20px 0;'>";
echo "<a href='main/dashboard/' style='background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>🏠 Go to Dashboard</a> ";
echo "<a href='mikrotik_verify_config.php' style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>🔧 Full Router Config</a>";
echo "</div>";

echo "</body></html>";
?>
