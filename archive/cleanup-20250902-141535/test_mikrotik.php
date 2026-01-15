<?php
set_time_limit(10);

echo "<h3>MikroTik Connection Test</h3>";

// Test socket connection first
$router_ips = ['192.168.10.1', '192.168.1.1', '192.168.0.1'];
$found_router = false;

foreach ($router_ips as $test_ip) {
    echo "<p>Testing $test_ip:8728... ";
    $socket_test = @fsockopen($test_ip, 8728, $errno, $errstr, 3);
    if ($socket_test) {
        fclose($socket_test);
        echo "<span style='color: green;'>✓ Socket connection successful</span></p>";
        $found_router = $test_ip;
        break;
    } else {
        echo "<span style='color: red;'>✗ Failed ($errstr)</span></p>";
    }
}

if (!$found_router) {
    echo "<p style='color: red;'><strong>No MikroTik router found on any IP. Please check:</strong></p>";
    echo "<ul>";
    echo "<li>Is your MikroTik router running?</li>";
    echo "<li>Is the API service enabled? (/ip service enable api)</li>";
    echo "<li>Is the router on a different IP address?</li>";
    echo "<li>Are there firewall rules blocking port 8728?</li>";
    echo "</ul>";
    exit;
}

echo "<p style='color: blue;'>Router found at: <strong>$found_router</strong></p>";

// Test API connection
echo "<p>Testing API connection... ";
try {
    require_once '../vendor/autoload.php';
    use RouterOS\Client;
    
    $start_time = microtime(true);
    
    $client = new Client([
        'host' => $found_router,
        'user' => 'admin',
        'pass' => '',
        'port' => 8728,
        'timeout' => 5,
        'attempts' => 1
    ]);
    
    $identity = $client->query('/system/identity/print')->read();
    $connection_time = round((microtime(true) - $start_time) * 1000, 2);
    
    echo "<span style='color: green;'>✓ API connection successful ({$connection_time}ms)</span></p>";
    echo "<p>Router identity: <strong>" . ($identity[0]['name'] ?? 'Unknown') . "</strong></p>";
    
    // Test device detection
    echo "<p>Testing device detection... ";
    $dhcp_leases = $client->query('/ip/dhcp-server/lease/print')->read();
    echo "<span style='color: green;'>✓ Found " . count($dhcp_leases) . " DHCP leases</span></p>";
    
    echo "<h4>Connected Devices:</h4>";
    echo "<ul>";
    foreach ($dhcp_leases as $lease) {
        $mac = $lease['mac-address'] ?? 'Unknown';
        $ip = $lease['address'] ?? 'Unknown';
        $hostname = $lease['host-name'] ?? 'Unknown';
        $status = $lease['status'] ?? 'Unknown';
        echo "<li>$hostname ($ip) - $mac - Status: $status</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ API connection failed: " . $e->getMessage() . "</span></p>";
    echo "<p style='color: red;'><strong>Please check:</strong></p>";
    echo "<ul>";
    echo "<li>Username/password in connectMikrotik.php</li>";
    echo "<li>API user permissions</li>";
    echo "<li>Router API configuration</li>";
    echo "</ul>";
}
?>
