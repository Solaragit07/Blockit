<?php
// Simple MikroTik Internet Troubleshooting Guide
session_start();
$_SESSION['user_id'] = 1;

echo "<h1>🔧 MikroTik Internet Access Troubleshooting</h1>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h2>⚠️ Fix for the 'remove-dynamic' Error</h2>";
echo "<p>The command you tried doesn't exist in your MikroTik version. Here are the correct commands:</p>";
echo "</div>";

echo "<h2>📋 Step-by-Step Troubleshooting</h2>";

echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>Step 1: Check Current Configuration</h3>";
echo "<p>Run these commands in your MikroTik terminal:</p>";
echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
echo "/ip firewall nat print\n";
echo "/ip dhcp-server network print\n";
echo "/ip dhcp-server print\n";
echo "/ip dhcp-server lease print\n";
echo "</pre>";
echo "</div>";

echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>Step 2: Force Devices to Get New IP Addresses</h3>";
echo "<p><strong>Method 1 - Restart DHCP Server:</strong></p>";
echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
echo "# First check your DHCP server name\n";
echo "/ip dhcp-server print\n\n";
echo "# Then restart it (replace 'dhcp1' with your server name)\n";
echo "/ip dhcp-server set dhcp1 disabled=yes\n";
echo "/ip dhcp-server set dhcp1 disabled=no\n";
echo "</pre>";

echo "<p><strong>Method 2 - Clear Specific Leases:</strong></p>";
echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
echo "# Remove all dynamic leases\n";
echo "/ip dhcp-server lease remove [find dynamic=yes]\n\n";
echo "# Or remove specific device lease\n";
echo "/ip dhcp-server lease remove [find mac-address=XX:XX:XX:XX:XX:XX]\n";
echo "</pre>";
echo "</div>";

echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>Step 3: Test Device Connectivity</h3>";
echo "<ol>";
echo "<li><strong>On the device (phone/tablet):</strong>";
echo "<ul>";
echo "<li>Go to WiFi settings</li>";
echo "<li>Forget the WiFi network completely</li>";
echo "<li>Reconnect and enter password again</li>";
echo "<li>Check if it gets IP address in 192.168.1.x range</li>";
echo "</ul></li>";
echo "<li><strong>Test internet access:</strong>";
echo "<ul>";
echo "<li>Open web browser</li>";
echo "<li>Try google.com</li>";
echo "<li>Try youtube.com</li>";
echo "<li>Try 8.8.8.8 (Google DNS)</li>";
echo "</ul></li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>Step 4: Advanced Diagnostics</h3>";
echo "<p>If devices still can't access internet, run these diagnostic commands:</p>";
echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
echo "# Test internet from router itself\n";
echo "/ping 8.8.8.8\n\n";
echo "# Check default route\n";
echo "/ip route print where dst-address=0.0.0.0/0\n\n";
echo "# Check if WAN interface is up\n";
echo "/interface print where name=ether2\n\n";
echo "# Monitor traffic\n";
echo "/tool torch interface=ether2\n";
echo "</pre>";
echo "</div>";

echo "<h2>🚨 Common Issues & Solutions</h2>";

echo "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
echo "<tr style='background: #f8f9fa;'>";
echo "<th>Problem</th><th>Symptoms</th><th>Solution</th>";
echo "</tr>";

echo "<tr>";
echo "<td><strong>No Gateway</strong></td>";
echo "<td>Devices get IP but no internet</td>";
echo "<td><code>/ip dhcp-server network set [find] gateway=192.168.1.1</code></td>";
echo "</tr>";

echo "<tr>";
echo "<td><strong>No DNS</strong></td>";
echo "<td>Can ping IPs but can't browse websites</td>";
echo "<td><code>/ip dhcp-server network set [find] dns-server=8.8.8.8,8.8.4.4</code></td>";
echo "</tr>";

echo "<tr>";
echo "<td><strong>No NAT Rule</strong></td>";
echo "<td>Local network works, internet doesn't</td>";
echo "<td><code>/ip firewall nat add chain=srcnat action=masquerade out-interface=ether2</code></td>";
echo "</tr>";

echo "<tr>";
echo "<td><strong>WAN Interface Down</strong></td>";
echo "<td>Router itself has no internet</td>";
echo "<td>Check cable connection to ether2, contact ISP</td>";
echo "</tr>";

echo "</table>";

try {
    require_once 'vendor/autoload.php';
    use RouterOS\Client;
    include 'API/connectMikrotik.php';
    
    if (isset($client) && $client !== null) {
        echo "<h2>🔍 Current Status Check</h2>";
        
        // Quick status check
        echo "<div style='background: #e7f3ff; padding: 10px; border-radius: 5px;'>";
        
        // Check NAT rules
        $natRules = $client->query('/ip/firewall/nat/print')->read();
        $hasMasquerade = false;
        foreach ($natRules as $rule) {
            if (($rule['action'] ?? '') === 'masquerade') {
                $hasMasquerade = true;
                break;
            }
        }
        echo $hasMasquerade ? "✅ NAT masquerade rule: FOUND<br>" : "❌ NAT masquerade rule: MISSING<br>";
        
        // Check DHCP networks
        $dhcpNetworks = $client->query('/ip/dhcp-server/network/print')->read();
        $hasGateway = false;
        foreach ($dhcpNetworks as $network) {
            if (isset($network['gateway'])) {
                $hasGateway = true;
                break;
            }
        }
        echo $hasGateway ? "✅ DHCP gateway: CONFIGURED<br>" : "❌ DHCP gateway: MISSING<br>";
        
        // Check DHCP server
        $dhcpServers = $client->query('/ip/dhcp-server/print')->read();
        $dhcpEnabled = false;
        foreach ($dhcpServers as $server) {
            if (!isset($server['disabled']) || $server['disabled'] !== 'true') {
                $dhcpEnabled = true;
                break;
            }
        }
        echo $dhcpEnabled ? "✅ DHCP server: RUNNING<br>" : "❌ DHCP server: NOT RUNNING<br>";
        
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>⚠️ Could not connect to MikroTik to check status</div>";
}

echo "<br><p><a href='main/dashboard/'>🏠 Back to Dashboard</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1, h2, h3 { color: #2c3e50; }
pre { overflow-x: auto; }
code { background: #f8f9fa; padding: 2px 4px; border-radius: 3px; font-family: 'Courier New', monospace; }
table { border-collapse: collapse; }
th, td { text-align: left; vertical-align: top; }
</style>
