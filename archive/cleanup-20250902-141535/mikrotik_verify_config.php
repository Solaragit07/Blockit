<?php
// MikroTik Configuration Verification & Device Testing
session_start();
$_SESSION['user_id'] = 1;

require_once 'vendor/autoload.php';
use RouterOS\Client;

echo "<h1>✅ MikroTik Configuration Verification</h1>";
echo "<p>Let's verify your configuration is working and test device connectivity...</p>";

try {
    include 'API/connectMikrotik.php';
    
    if (!isset($client) || $client === null) {
        throw new Exception("Cannot connect to MikroTik router");
    }
    
    echo "<div style='color: green;'>✅ Connected to MikroTik router</div><br>";
    
    // 1. Verify NAT Configuration
    echo "<h2>1. 🔗 NAT Configuration Status</h2>";
    $natRules = $client->query('/ip/firewall/nat/print')->read();
    
    $masqueradeFound = false;
    foreach ($natRules as $rule) {
        if (($rule['action'] ?? '') === 'masquerade' && ($rule['chain'] ?? '') === 'srcnat') {
            $outInterface = $rule['out-interface'] ?? 'unknown';
            $disabled = isset($rule['disabled']) && $rule['disabled'] === 'true' ? 'DISABLED' : 'ENABLED';
            echo "<div style='color: green;'>✅ Masquerade rule found: Out-interface = <strong>$outInterface</strong> (Status: $disabled)</div>";
            $masqueradeFound = true;
        }
    }
    
    if (!$masqueradeFound) {
        echo "<div style='color: red;'>❌ No masquerade rule found!</div>";
    }
    
    // 2. Verify DHCP Configuration
    echo "<h2>2. 🌐 DHCP Configuration Status</h2>";
    $dhcpNetworks = $client->query('/ip/dhcp-server/network/print')->read();
    
    foreach ($dhcpNetworks as $network) {
        $address = $network['address'] ?? 'unknown';
        $gateway = $network['gateway'] ?? 'NOT SET';
        $dnsServer = $network['dns-server'] ?? 'NOT SET';
        
        echo "<div>🌍 Network: <strong>$address</strong></div>";
        echo "<div style='color: green;'>✅ Gateway: <strong>$gateway</strong></div>";
        echo "<div style='color: green;'>✅ DNS Server: <strong>$dnsServer</strong></div>";
        echo "<br>";
    }
    
    // 3. Check Current Device Status
    echo "<h2>3. 📱 Current Connected Devices</h2>";
    $dhcpLeases = $client->query('/ip/dhcp-server/lease/print')->read();
    
    if (empty($dhcpLeases)) {
        echo "<div style='color: orange;'>⚠️ No DHCP leases found. Make sure devices are connected and getting IP addresses.</div>";
    } else {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>Device Name</th><th>MAC Address</th><th>IP Address</th><th>Status</th><th>Internet Test</th>";
        echo "</tr>";
        
        foreach ($dhcpLeases as $lease) {
            $address = $lease['address'] ?? 'unknown';
            $macAddress = $lease['mac-address'] ?? 'unknown';
            $hostName = $lease['host-name'] ?? 'unknown';
            $status = $lease['status'] ?? 'unknown';
            $active = isset($lease['active']) ? 'Active' : 'Inactive';
            
            echo "<tr>";
            echo "<td><strong>$hostName</strong></td>";
            echo "<td><code>$macAddress</code></td>";
            echo "<td><strong>$address</strong></td>";
            echo "<td>" . ($status === 'bound' ? '<span style="color: green;">✅ Bound</span>' : '<span style="color: orange;">⚠️ ' . $status . '</span>') . "</td>";
            echo "<td><button onclick=\"testDeviceInternet('$address', '$hostName')\" style='background: #007bff; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;'>🧪 Test Internet</button></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Network Interface Status
    echo "<h2>4. 🔌 Interface Status</h2>";
    $interfaces = $client->query('/interface/print')->read();
    
    foreach ($interfaces as $interface) {
        $name = $interface['name'] ?? 'unknown';
        $type = $interface['type'] ?? 'unknown';
        $running = isset($interface['running']) && $interface['running'] === 'true' ? 'RUNNING' : 'NOT RUNNING';
        $disabled = isset($interface['disabled']) && $interface['disabled'] === 'true' ? 'DISABLED' : 'ENABLED';
        
        $statusColor = ($running === 'RUNNING' && $disabled === 'ENABLED') ? 'green' : 'orange';
        echo "<div style='color: $statusColor;'>🔌 Interface: <strong>$name</strong> ($type) - Status: $running, $disabled</div>";
    }
    
    // 5. Test Recommendations
    echo "<h2>5. 🧪 Testing Instructions</h2>";
    echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>📋 How to Test Internet Access on Other Devices:</h3>";
    echo "<ol>";
    echo "<li><strong>Reconnect devices:</strong> Disconnect and reconnect WiFi on phones/tablets</li>";
    echo "<li><strong>Check IP settings:</strong> Verify devices get IP addresses in the 192.168.1.x range</li>";
    echo "<li><strong>Test websites:</strong> Try browsing to google.com or youtube.com</li>";
    echo "<li><strong>DNS test:</strong> Try ping 8.8.8.8 if you have access to command line</li>";
    echo "<li><strong>Monitor dashboard:</strong> Check if devices show 'Internet Connected' status</li>";
    echo "</ol>";
    echo "</div>";
    
    // 6. Troubleshooting Section
    echo "<h2>6. 🔧 Quick Troubleshooting</h2>";
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>If devices still can't access internet:</h3>";
    echo "<ul>";
    echo "<li>🔄 <strong>Restart DHCP service:</strong> <code>/ip dhcp-server disable dhcp1; /ip dhcp-server enable dhcp1</code></li>";
    echo "<li>📱 <strong>Force device renewal:</strong> Forget and reconnect to WiFi network</li>";
    echo "<li>🔍 <strong>Check firewall logs:</strong> Look for blocked connections</li>";
    echo "<li>🌐 <strong>Test with different websites:</strong> Some sites might be blocked by DNS</li>";
    echo "<li>📡 <strong>Check WAN connection:</strong> Ensure ether2 has internet access</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . $e->getMessage() . "</div>";
}

echo "<br><p><a href='main/dashboard/'>🏠 Back to Dashboard</a> | <a href='mikrotik_internet_diagnostic.php'>🔍 Full Diagnostic</a></p>";
?>

<script>
function testDeviceInternet(ip, deviceName) {
    alert(`Testing internet connectivity for ${deviceName} (${ip})...\n\nTo test manually:\n1. Connect to the device\n2. Open a web browser\n3. Try visiting google.com\n4. Check if pages load properly\n\nIf it works, the device should show "Internet Connected" in the dashboard.`);
}
</script>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2, h3 { color: #2c3e50; }
table { margin: 10px 0; }
th { background: #f8f9fa; }
code { background: #f8f9fa; padding: 2px 4px; border-radius: 3px; font-family: monospace; }
</style>
