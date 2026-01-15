<?php
session_start();
include '../../connectMySql.php';
include '../../loginverification.php';

// Check if user is logged in
if(!logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// RouterOS API imports
require_once '../../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

header('Content-Type: application/json');

try {
    echo "<h2>MikroTik Device Detection Debug</h2>";
    
    include '../../API/connectMikrotik.php';
    if (!isset($client) || $client === null) {
        echo "<p style='color: red;'>❌ MikroTik client not available</p>";
        exit;
    }
    
    echo "<p style='color: green;'>✅ MikroTik client connected successfully</p>";
    
    // Get DHCP leases
    echo "<h3>DHCP Server Leases:</h3>";
    $dhcpLeases = $client->query((new Query('/ip/dhcp-server/lease/print')))->read();
    
    if(empty($dhcpLeases)) {
        echo "<p style='color: orange;'>⚠️ No DHCP leases found</p>";
    } else {
        echo "<p style='color: blue;'>📊 Found " . count($dhcpLeases) . " DHCP lease(s)</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>MAC Address</th><th>IP Address</th><th>Status</th><th>Host Name</th><th>Server</th><th>Expires After</th><th>Active Time</th><th>Will Show?</th></tr>";
        
        foreach($dhcpLeases as $index => $lease) {
            $mac = $lease['mac-address'] ?? 'N/A';
            $ip = $lease['address'] ?? 'N/A';
            $status = $lease['status'] ?? 'N/A';
            $hostname = $lease['host-name'] ?? 'N/A';
            $server = $lease['server'] ?? 'N/A';
            $expires = $lease['expires-after'] ?? 'N/A';
            $activeTime = $lease['active-time'] ?? 'N/A';
            
            // Check if this device would be considered active
            $willShow = false;
            if(isset($lease['mac-address']) && !empty($lease['mac-address'])) {
                if(isset($lease['status']) && $lease['status'] === 'bound' && isset($lease['address']) && !empty($lease['address'])) {
                    $willShow = true;
                }
                elseif(!isset($lease['status']) && isset($lease['address']) && !empty($lease['address'])) {
                    if(!isset($lease['expires-after']) || $lease['expires-after'] !== '0s') {
                        $willShow = true;
                    }
                }
                elseif(isset($lease['address']) && !empty($lease['address'])) {
                    $willShow = true;
                }
            }
            
            $showStatus = $willShow ? "<span style='color: green;'>✅ YES</span>" : "<span style='color: red;'>❌ NO</span>";
            
            echo "<tr>";
            echo "<td>$mac</td>";
            echo "<td>$ip</td>";
            echo "<td>$status</td>";
            echo "<td>$hostname</td>";
            echo "<td>$server</td>";
            echo "<td>$expires</td>";
            echo "<td>$activeTime</td>";
            echo "<td>$showStatus</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Get ARP table
    echo "<h3>ARP Table:</h3>";
    try {
        $arpEntries = $client->query((new Query('/ip/arp/print')))->read();
        
        if(empty($arpEntries)) {
            echo "<p style='color: orange;'>⚠️ No ARP entries found</p>";
        } else {
            echo "<p style='color: blue;'>📊 Found " . count($arpEntries) . " ARP entry(ies)</p>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>MAC Address</th><th>IP Address</th><th>Interface</th><th>Published</th><th>Invalid</th><th>DHCP</th><th>Dynamic</th><th>Complete</th></tr>";
            
            foreach($arpEntries as $arp) {
                $mac = $arp['mac-address'] ?? 'N/A';
                $ip = $arp['address'] ?? 'N/A';
                $interface = $arp['interface'] ?? 'N/A';
                $published = isset($arp['published']) ? '✅' : '❌';
                $invalid = isset($arp['invalid']) ? '✅' : '❌';
                $dhcp = isset($arp['DHCP']) ? '✅' : '❌';
                $dynamic = isset($arp['dynamic']) ? '✅' : '❌';
                $complete = isset($arp['complete']) ? '✅' : '❌';
                
                echo "<tr>";
                echo "<td>$mac</td>";
                echo "<td>$ip</td>";
                echo "<td>$interface</td>";
                echo "<td>$published</td>";
                echo "<td>$invalid</td>";
                echo "<td>$dhcp</td>";
                echo "<td>$dynamic</td>";
                echo "<td>$complete</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $arpError) {
        echo "<p style='color: red;'>❌ ARP query failed: " . $arpError->getMessage() . "</p>";
    }
    
    // Test interface monitoring
    echo "<h3>Network Interfaces:</h3>";
    try {
        $interfaces = $client->query((new Query('/interface/print')))->read();
        
        if(empty($interfaces)) {
            echo "<p style='color: orange;'>⚠️ No interfaces found</p>";
        } else {
            echo "<p style='color: blue;'>📊 Found " . count($interfaces) . " interface(s)</p>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Name</th><th>Type</th><th>Running</th><th>Disabled</th><th>Comment</th></tr>";
            
            foreach($interfaces as $interface) {
                $name = $interface['name'] ?? 'N/A';
                $type = $interface['type'] ?? 'N/A';
                $running = isset($interface['running']) ? '✅' : '❌';
                $disabled = isset($interface['disabled']) ? '✅' : '❌';
                $comment = $interface['comment'] ?? 'N/A';
                
                echo "<tr>";
                echo "<td>$name</td>";
                echo "<td>$type</td>";
                echo "<td>$running</td>";
                echo "<td>$disabled</td>";
                echo "<td>$comment</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $interfaceError) {
        echo "<p style='color: red;'>❌ Interface query failed: " . $interfaceError->getMessage() . "</p>";
    }
    
    // Show current system info
    echo "<h3>Router System Info:</h3>";
    try {
        $system = $client->query((new Query('/system/resource/print')))->read();
        if(!empty($system)) {
            echo "<pre>";
            print_r($system[0]);
            echo "</pre>";
        }
    } catch (Exception $systemError) {
        echo "<p style='color: red;'>❌ System query failed: " . $systemError->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Connection failed: " . $e->getMessage() . "</p>";
    echo "<p>Debug info: " . $e->getTraceAsString() . "</p>";
}
?>
