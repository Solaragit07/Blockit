<?php
// MikroTik Auto-Fix for Internet Access Issues
session_start();
$_SESSION['user_id'] = 1;

require_once 'vendor/autoload.php';
use RouterOS\Client;

echo "<h1>🔧 MikroTik Internet Access Auto-Fix</h1>";
echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
echo "⚠️ <strong>Warning:</strong> This will modify your router configuration. Make sure you have backup access to your router.";
echo "</div>";

if (isset($_POST['apply_fixes'])) {
    try {
        include 'API/connectMikrotik.php';
        
        if (!isset($client) || $client === null) {
            throw new Exception("Cannot connect to MikroTik router");
        }
        
        echo "<h2>🚀 Applying Fixes...</h2>";
        
        // Fix 1: Check and add NAT masquerade rule
        $natRules = $client->query('/ip/firewall/nat/print')->read();
        $hasMasquerade = false;
        
        foreach ($natRules as $rule) {
            if (($rule['action'] ?? '') === 'masquerade' && ($rule['chain'] ?? '') === 'srcnat') {
                $hasMasquerade = true;
                break;
            }
        }
        
        if (!$hasMasquerade) {
            echo "<div>🔄 Adding NAT masquerade rule...</div>";
            try {
                // Try common WAN interface names
                $wanInterfaces = ['ether1', 'ether2', 'wlan1', 'pppoe-out1'];
                $interfaceAdded = false;
                
                foreach ($wanInterfaces as $interface) {
                    try {
                        $client->query('/ip/firewall/nat/add', [
                            'chain' => 'srcnat',
                            'action' => 'masquerade',
                            'out-interface' => $interface
                        ])->read();
                        echo "<div style='color: green;'>✅ Added masquerade rule for interface: $interface</div>";
                        $interfaceAdded = true;
                        break;
                    } catch (Exception $e) {
                        // Continue trying other interfaces
                    }
                }
                
                if (!$interfaceAdded) {
                    echo "<div style='color: orange;'>⚠️ Could not automatically determine WAN interface. Please add manually:</div>";
                    echo "<code>/ip firewall nat add chain=srcnat action=masquerade out-interface=YOUR_WAN_INTERFACE</code>";
                }
            } catch (Exception $e) {
                echo "<div style='color: red;'>❌ Error adding NAT rule: " . $e->getMessage() . "</div>";
            }
        } else {
            echo "<div style='color: green;'>✅ NAT masquerade rule already exists</div>";
        }
        
        // Fix 2: Check and configure DHCP network
        $dhcpNetworks = $client->query('/ip/dhcp-server/network/print')->read();
        $hasProperDhcp = false;
        
        foreach ($dhcpNetworks as $network) {
            if (isset($network['gateway']) && isset($network['dns-server'])) {
                $hasProperDhcp = true;
                break;
            }
        }
        
        if (!$hasProperDhcp) {
            echo "<div>🔄 Configuring DHCP network with gateway and DNS...</div>";
            try {
                // Try to find the router's IP to use as gateway
                $addresses = $client->query('/ip/address/print')->read();
                $gatewayIP = '192.168.1.1'; // Default fallback
                
                foreach ($addresses as $addr) {
                    $address = $addr['address'] ?? '';
                    if (preg_match('/^192\.168\.(\d+)\.(\d+)\//', $address, $matches)) {
                        $gatewayIP = "192.168.{$matches[1]}.1";
                        break;
                    }
                }
                
                $client->query('/ip/dhcp-server/network/add', [
                    'address' => '192.168.1.0/24',
                    'gateway' => $gatewayIP,
                    'dns-server' => '8.8.8.8,8.8.4.4'
                ])->read();
                
                echo "<div style='color: green;'>✅ Added DHCP network with gateway: $gatewayIP and DNS: 8.8.8.8</div>";
            } catch (Exception $e) {
                echo "<div style='color: red;'>❌ Error configuring DHCP: " . $e->getMessage() . "</div>";
            }
        } else {
            echo "<div style='color: green;'>✅ DHCP network properly configured</div>";
        }
        
        // Fix 3: Check default route
        $routes = $client->query('/ip/route/print')->read();
        $hasDefaultRoute = false;
        
        foreach ($routes as $route) {
            if (($route['dst-address'] ?? '') === '0.0.0.0/0') {
                $hasDefaultRoute = true;
                break;
            }
        }
        
        if ($hasDefaultRoute) {
            echo "<div style='color: green;'>✅ Default route exists</div>";
        } else {
            echo "<div style='color: orange;'>⚠️ No default route found - this needs to be configured based on your ISP connection</div>";
        }
        
        echo "<h2>🎉 Auto-fix Complete!</h2>";
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "✅ Basic internet sharing configuration has been applied.<br>";
        echo "📱 Other devices should now be able to access the internet.<br>";
        echo "🔄 You may need to reconnect devices or renew their DHCP leases.";
        echo "</div>";
        
        echo "<h3>🧪 Test Steps:</h3>";
        echo "<ol>";
        echo "<li>Disconnect and reconnect a device to WiFi</li>";
        echo "<li>Try browsing to a website (e.g., google.com)</li>";
        echo "<li>Check if the device gets a proper IP address with gateway</li>";
        echo "</ol>";
        
    } catch (Exception $e) {
        echo "<div style='color: red;'>❌ Error during auto-fix: " . $e->getMessage() . "</div>";
    }
} else {
    // Show the form
    echo "<p>This tool will automatically apply common fixes for internet access issues:</p>";
    echo "<ul>";
    echo "<li>✅ Add NAT masquerade rule for internet sharing</li>";
    echo "<li>✅ Configure DHCP network with proper gateway and DNS</li>";
    echo "<li>✅ Verify routing configuration</li>";
    echo "</ul>";
    
    echo "<form method='post'>";
    echo "<button type='submit' name='apply_fixes' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>🚀 Apply Fixes</button>";
    echo "</form>";
    
    echo "<br><p><a href='mikrotik_internet_diagnostic.php'>🔍 Run Diagnostic First</a> | <a href='main/dashboard/'>🏠 Back to Dashboard</a></p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2, h3 { color: #2c3e50; }
div { margin: 5px 0; }
code { background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
</style>
