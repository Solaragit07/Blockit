<?php
// Diagnose internet connectivity issue for devices
require_once 'vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

echo "<h2>🌐 Internet Connectivity Diagnosis</h2>";
echo "<style>
body{font-family:Arial;margin:20px;} 
.debug{background:#f8f9fa;padding:10px;margin:5px 0;font-family:monospace;border-radius:4px;}
.error{background:#ffebee;color:#c62828;padding:10px;border-radius:4px;margin:10px 0;}
.success{background:#e8f5e8;color:#2e7d2e;padding:10px;border-radius:4px;margin:10px 0;}
.warning{background:#fff3e0;color:#f57c00;padding:10px;border-radius:4px;margin:10px 0;}
table{border-collapse:collapse;width:100%;margin:10px 0;}
th,td{border:1px solid #ddd;padding:8px;text-align:left;}
th{background:#f8f9fa;}
</style>";

try {
    include 'API/connectMikrotik.php';
    if (!isset($client) || $client === null) {
        throw new Exception("Could not connect to MikroTik router");
    }
    
    echo "<div class='success'>✅ Connected to MikroTik successfully</div>";
    
    // 1. Check NAT rules (essential for internet access)
    echo "<h3>🔄 NAT Rules Analysis</h3>";
    try {
        $natRules = $client->query((new Query('/ip/firewall/nat/print')))->read();
        echo "<div class='debug'>Found " . count($natRules) . " NAT rules</div>";
        
        $hasMasquerade = false;
        $hasProblematicRules = false;
        
        echo "<table>";
        echo "<tr><th>Chain</th><th>Action</th><th>Src Address</th><th>Out Interface</th><th>Comment</th><th>Status</th></tr>";
        
        foreach($natRules as $rule) {
            $chain = $rule['chain'] ?? 'N/A';
            $action = $rule['action'] ?? 'N/A';
            $srcAddress = $rule['src-address'] ?? 'any';
            $outInterface = $rule['out-interface'] ?? 'N/A';
            $comment = $rule['comment'] ?? '';
            $disabled = isset($rule['disabled']) && $rule['disabled'] === 'true' ? 'DISABLED' : 'ACTIVE';
            
            // Check for masquerade rule
            if($action === 'masquerade' && $chain === 'srcnat') {
                $hasMasquerade = true;
            }
            
            // Check for problematic redirect rules
            if($action === 'dst-nat' && strpos($comment, 'BlockIT') !== false) {
                $hasProblematicRules = true;
            }
            
            echo "<tr>";
            echo "<td>$chain</td>";
            echo "<td>$action</td>";
            echo "<td>$srcAddress</td>";
            echo "<td>$outInterface</td>";
            echo "<td>$comment</td>";
            echo "<td>$disabled</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        if(!$hasMasquerade) {
            echo "<div class='error'>❌ CRITICAL: No masquerade rule found! This is why devices have no internet.</div>";
            echo "<div class='warning'>🔧 Fix: Add masquerade rule in MikroTik:<br>";
            echo "<code>/ip firewall nat add chain=srcnat action=masquerade out-interface=ether1</code></div>";
        } else {
            echo "<div class='success'>✅ Masquerade rule exists - basic NAT should work</div>";
        }
        
        if($hasProblematicRules) {
            echo "<div class='warning'>⚠️ Found BlockIT redirect rules that might interfere with internet access</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error reading NAT rules: " . $e->getMessage() . "</div>";
    }
    
    // 2. Check firewall filter rules
    echo "<h3>🛡️ Firewall Filter Rules</h3>";
    try {
        $filterRules = $client->query((new Query('/ip/firewall/filter/print')))->read();
        echo "<div class='debug'>Found " . count($filterRules) . " filter rules</div>";
        
        $blockingRules = [];
        $allowInternetRules = [];
        
        foreach($filterRules as $rule) {
            $chain = $rule['chain'] ?? 'N/A';
            $action = $rule['action'] ?? 'N/A';
            $comment = $rule['comment'] ?? '';
            $disabled = isset($rule['disabled']) && $rule['disabled'] === 'true';
            
            if(!$disabled && $action === 'drop' && $chain === 'forward') {
                $blockingRules[] = $rule;
            }
            
            if(!$disabled && $action === 'accept' && $chain === 'forward') {
                $allowInternetRules[] = $rule;
            }
        }
        
        echo "<div class='debug'>Active blocking rules: " . count($blockingRules) . "</div>";
        echo "<div class='debug'>Active allow rules: " . count($allowInternetRules) . "</div>";
        
        if(count($blockingRules) > 0) {
            echo "<div class='warning'>⚠️ Found active blocking rules in forward chain:</div>";
            echo "<table>";
            echo "<tr><th>Chain</th><th>Action</th><th>Src Address</th><th>Comment</th></tr>";
            foreach($blockingRules as $rule) {
                echo "<tr>";
                echo "<td>" . ($rule['chain'] ?? 'N/A') . "</td>";
                echo "<td>" . ($rule['action'] ?? 'N/A') . "</td>";
                echo "<td>" . ($rule['src-address'] ?? 'any') . "</td>";
                echo "<td>" . ($rule['comment'] ?? '') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error reading filter rules: " . $e->getMessage() . "</div>";
    }
    
    // 3. Check routes
    echo "<h3>🗺️ Routing Table</h3>";
    try {
        $routes = $client->query((new Query('/ip/route/print')))->read();
        echo "<div class='debug'>Found " . count($routes) . " routes</div>";
        
        $hasDefaultRoute = false;
        foreach($routes as $route) {
            $dst = $route['dst-address'] ?? 'N/A';
            $gateway = $route['gateway'] ?? 'N/A';
            $distance = $route['distance'] ?? 'N/A';
            $active = isset($route['active']) && $route['active'] === 'true';
            
            if($dst === '0.0.0.0/0' && $active) {
                $hasDefaultRoute = true;
                echo "<div class='success'>✅ Default route found: Gateway $gateway</div>";
            }
        }
        
        if(!$hasDefaultRoute) {
            echo "<div class='error'>❌ CRITICAL: No active default route found!</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error reading routes: " . $e->getMessage() . "</div>";
    }
    
    // 4. Check DNS settings
    echo "<h3>🌐 DNS Configuration</h3>";
    try {
        $dns = $client->query((new Query('/ip/dns/print')))->read();
        if(count($dns) > 0) {
            $servers = $dns[0]['servers'] ?? 'None';
            $allowRemote = isset($dns[0]['allow-remote-requests']) && $dns[0]['allow-remote-requests'] === 'true';
            
            echo "<div class='debug'>DNS Servers: $servers</div>";
            echo "<div class='debug'>Allow Remote Requests: " . ($allowRemote ? 'YES' : 'NO') . "</div>";
            
            if($servers === 'None' || empty($servers)) {
                echo "<div class='error'>❌ No DNS servers configured!</div>";
            } else {
                echo "<div class='success'>✅ DNS servers configured</div>";
            }
            
            if(!$allowRemote) {
                echo "<div class='warning'>⚠️ Remote DNS requests not allowed - might affect client DNS resolution</div>";
            }
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error reading DNS config: " . $e->getMessage() . "</div>";
    }
    
    // 5. Interface status
    echo "<h3>🔌 Interface Status</h3>";
    try {
        $interfaces = $client->query((new Query('/interface/print')))->read();
        
        echo "<table>";
        echo "<tr><th>Name</th><th>Type</th><th>Running</th><th>Disabled</th><th>Comment</th></tr>";
        
        foreach($interfaces as $iface) {
            $name = $iface['name'] ?? 'N/A';
            $type = $iface['type'] ?? 'N/A';
            $running = isset($iface['running']) && $iface['running'] === 'true' ? 'YES' : 'NO';
            $disabled = isset($iface['disabled']) && $iface['disabled'] === 'true' ? 'YES' : 'NO';
            $comment = $iface['comment'] ?? '';
            
            echo "<tr>";
            echo "<td>$name</td>";
            echo "<td>$type</td>";
            echo "<td>$running</td>";
            echo "<td>$disabled</td>";
            echo "<td>$comment</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error reading interfaces: " . $e->getMessage() . "</div>";
    }
    
    // 6. Summary and recommendations
    echo "<h3>📋 Summary & Recommendations</h3>";
    echo "<div class='warning'>";
    echo "<strong>Most Common Causes of 'No Internet' Issues:</strong><br>";
    echo "1. Missing masquerade rule in NAT<br>";
    echo "2. Incorrect default route<br>";
    echo "3. Blocking firewall rules<br>";
    echo "4. DNS configuration issues<br>";
    echo "5. WAN interface problems<br><br>";
    
    echo "<strong>Quick Fixes to Try:</strong><br>";
    echo "• Reset firewall to default: <code>/system reset-configuration no-defaults=yes skip-backup=yes</code><br>";
    echo "• Add masquerade: <code>/ip firewall nat add chain=srcnat action=masquerade out-interface=ether1</code><br>";
    echo "• Set DNS: <code>/ip dns set servers=8.8.8.8,8.8.4.4 allow-remote-requests=yes</code><br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Connection Error: " . $e->getMessage() . "</div>";
}
?>
