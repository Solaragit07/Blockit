<?php
// MikroTik Internet Access Diagnostic Tool
session_start();
$_SESSION['user_id'] = 1; // Temporary for testing

require_once 'vendor/autoload.php';
use RouterOS\Client;

echo "<h1>🔧 MikroTik Internet Access Diagnostics</h1>";

try {
    include 'API/connectMikrotik.php';
    
    if (!isset($client) || $client === null) {
        throw new Exception("Cannot connect to MikroTik router");
    }
    
    echo "<div style='color: green;'>✅ Connected to MikroTik router</div><br>";
    
    // 1. Check DHCP Server Configuration
    echo "<h2>1. 🌐 DHCP Server Configuration</h2>";
    $dhcpServers = $client->query('/ip/dhcp-server/print')->read();
    
    if (empty($dhcpServers)) {
        echo "<div style='color: red;'>❌ No DHCP server configured!</div>";
        echo "<p><strong>Solution:</strong> You need to configure a DHCP server to assign IP addresses to devices.</p>";
    } else {
        foreach ($dhcpServers as $server) {
            $name = $server['name'] ?? 'unnamed';
            $interface = $server['interface'] ?? 'unknown';
            $disabled = isset($server['disabled']) && $server['disabled'] === 'true' ? 'DISABLED' : 'ENABLED';
            
            echo "<div>📡 DHCP Server: <strong>$name</strong> on interface <strong>$interface</strong> - Status: <strong>$disabled</strong></div>";
        }
    }
    
    // 2. Check DHCP Network Configuration
    echo "<h2>2. 🌍 DHCP Network Configuration</h2>";
    $dhcpNetworks = $client->query('/ip/dhcp-server/network/print')->read();
    
    if (empty($dhcpNetworks)) {
        echo "<div style='color: red;'>❌ No DHCP network configured!</div>";
        echo "<p><strong>Solution:</strong> You need to configure DHCP network with gateway and DNS.</p>";
    } else {
        foreach ($dhcpNetworks as $network) {
            $address = $network['address'] ?? 'unknown';
            $gateway = $network['gateway'] ?? 'NOT SET';
            $dnsServer = $network['dns-server'] ?? 'NOT SET';
            
            echo "<div>🌐 Network: <strong>$address</strong></div>";
            echo "<div>🚪 Gateway: <strong>$gateway</strong></div>";
            echo "<div>🔍 DNS Server: <strong>$dnsServer</strong></div>";
            
            if ($gateway === 'NOT SET') {
                echo "<div style='color: red;'>❌ No gateway configured - devices won't have internet!</div>";
            }
            if ($dnsServer === 'NOT SET') {
                echo "<div style='color: orange;'>⚠️ No DNS configured - devices may have trouble resolving websites!</div>";
            }
            echo "<br>";
        }
    }
    
    // 3. Check Firewall NAT Rules
    echo "<h2>3. 🔥 Firewall NAT Rules</h2>";
    $natRules = $client->query('/ip/firewall/nat/print')->read();
    
    $hasMasqueradeRule = false;
    if (empty($natRules)) {
        echo "<div style='color: red;'>❌ No NAT rules configured!</div>";
    } else {
        foreach ($natRules as $rule) {
            $chain = $rule['chain'] ?? '';
            $action = $rule['action'] ?? '';
            $outInterface = $rule['out-interface'] ?? '';
            $srcAddress = $rule['src-address'] ?? '';
            $disabled = isset($rule['disabled']) && $rule['disabled'] === 'true' ? 'DISABLED' : 'ENABLED';
            
            echo "<div>🔗 Chain: <strong>$chain</strong>, Action: <strong>$action</strong>, Out-Interface: <strong>$outInterface</strong> - Status: <strong>$disabled</strong></div>";
            
            if ($action === 'masquerade' && $chain === 'srcnat') {
                $hasMasqueradeRule = true;
            }
        }
    }
    
    if (!$hasMasqueradeRule) {
        echo "<div style='color: red;'>❌ No masquerade rule found - devices won't have internet access!</div>";
        echo "<p><strong>Critical Issue:</strong> You need a masquerade rule to allow internet access.</p>";
    }
    
    // 4. Check Firewall Filter Rules
    echo "<h2>4. 🛡️ Firewall Filter Rules</h2>";
    $filterRules = $client->query('/ip/firewall/filter/print')->read();
    
    $hasBlockingRules = false;
    if (!empty($filterRules)) {
        foreach ($filterRules as $rule) {
            $chain = $rule['chain'] ?? '';
            $action = $rule['action'] ?? '';
            $srcAddress = $rule['src-address'] ?? '';
            $dstAddress = $rule['dst-address'] ?? '';
            $disabled = isset($rule['disabled']) && $rule['disabled'] === 'true' ? 'DISABLED' : 'ENABLED';
            
            if ($action === 'drop' || $action === 'reject') {
                echo "<div style='color: orange;'>⚠️ Blocking rule: Chain: <strong>$chain</strong>, Action: <strong>$action</strong>, Src: <strong>$srcAddress</strong>, Dst: <strong>$dstAddress</strong> - Status: <strong>$disabled</strong></div>";
                if ($disabled === 'ENABLED') {
                    $hasBlockingRules = true;
                }
            }
        }
    }
    
    // 5. Check Default Route
    echo "<h2>5. 🛣️ Default Route</h2>";
    $routes = $client->query('/ip/route/print')->read();
    
    $hasDefaultRoute = false;
    foreach ($routes as $route) {
        $dstAddress = $route['dst-address'] ?? '';
        $gateway = $route['gateway'] ?? '';
        $distance = $route['distance'] ?? '';
        $active = isset($route['active']) ? 'ACTIVE' : 'INACTIVE';
        
        if ($dstAddress === '0.0.0.0/0') {
            $hasDefaultRoute = true;
            echo "<div>🌐 Default Route: Gateway <strong>$gateway</strong>, Distance: <strong>$distance</strong> - Status: <strong>$active</strong></div>";
        }
    }
    
    if (!$hasDefaultRoute) {
        echo "<div style='color: red;'>❌ No default route configured!</div>";
        echo "<p><strong>Issue:</strong> Router doesn't know how to reach the internet.</p>";
    }
    
    // 6. Summary and Recommendations
    echo "<h2>6. 📋 Summary & Solutions</h2>";
    
    $issues = [];
    if (empty($dhcpServers)) $issues[] = "No DHCP server";
    if (empty($dhcpNetworks)) $issues[] = "No DHCP network";
    if (!$hasMasqueradeRule) $issues[] = "No NAT masquerade rule";
    if (!$hasDefaultRoute) $issues[] = "No default route";
    
    if (empty($issues)) {
        echo "<div style='color: green;'>✅ Configuration looks good! Let me check device-specific settings...</div>";
        
        // Additional check - look at current DHCP leases
        echo "<h3>📱 Current Device Analysis</h3>";
        $dhcpLeases = $client->query('/ip/dhcp-server/lease/print')->read();
        
        foreach ($dhcpLeases as $lease) {
            $address = $lease['address'] ?? 'unknown';
            $macAddress = $lease['mac-address'] ?? 'unknown';
            $hostName = $lease['host-name'] ?? 'unknown';
            $status = $lease['status'] ?? 'unknown';
            
            echo "<div>📱 Device: <strong>$hostName</strong> ($macAddress) - IP: <strong>$address</strong> - Status: <strong>$status</strong></div>";
        }
        
    } else {
        echo "<div style='color: red;'>❌ Found " . count($issues) . " critical issues:</div>";
        foreach ($issues as $issue) {
            echo "<div>• $issue</div>";
        }
        
        echo "<h3>🔧 Quick Fix Commands</h3>";
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
        echo "<p><strong>Run these commands in MikroTik terminal:</strong></p>";
        
        if (!$hasMasqueradeRule) {
            echo "<code>/ip firewall nat add chain=srcnat action=masquerade out-interface=ether1</code><br>";
            echo "<small>^ Replace 'ether1' with your WAN interface</small><br><br>";
        }
        
        if (empty($dhcpNetworks)) {
            echo "<code>/ip dhcp-server network add address=192.168.1.0/24 gateway=192.168.1.1 dns-server=8.8.8.8,8.8.4.4</code><br>";
            echo "<small>^ Adjust network range as needed</small><br><br>";
        }
        
        if (empty($dhcpServers)) {
            echo "<code>/ip dhcp-server add name=dhcp1 interface=bridge disabled=no</code><br>";
            echo "<small>^ Replace 'bridge' with your LAN interface</small><br><br>";
        }
        
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . $e->getMessage() . "</div>";
    echo "<p>Make sure:</p>";
    echo "<ul>";
    echo "<li>MikroTik router is accessible at 192.168.10.1</li>";
    echo "<li>API service is enabled on the router</li>";
    echo "<li>Username/password are correct</li>";
    echo "</ul>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2, h3 { color: #2c3e50; }
div { margin: 5px 0; }
code { background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
</style>
