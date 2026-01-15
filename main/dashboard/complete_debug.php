<?php
session_start();
include '../../connectMySql.php';
include '../../loginverification.php';

// Check if user is logged in
if(!logged_in()) {
    die("Not logged in");
}

// RouterOS API imports
require_once '../../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Complete MikroTik Device Discovery</h2>";
echo "<p>This script shows ALL devices MikroTik knows about, without any filtering.</p>";

try {
    include '../../API/connectMikrotik.php';
    if (!isset($client) || $client === null) {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "❌ <strong>MikroTik client not available</strong><br>";
        echo "Please check your MikroTik connection settings in connectMikrotik.php";
        echo "</div>";
        exit;
    }
    
    echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
    echo "✅ <strong>MikroTik client connected successfully</strong>";
    echo "</div>";
    
    // Get ALL DHCP leases (no filtering)
    echo "<h3>📋 ALL DHCP Server Leases (Raw Data)</h3>";
    $dhcpLeases = $client->query((new Query('/ip/dhcp-server/lease/print')))->read();
    
    if(empty($dhcpLeases)) {
        echo "<div style='color: orange; padding: 10px; border: 1px solid orange;'>";
        echo "⚠️ No DHCP leases found. This could mean:";
        echo "<ul>";
        echo "<li>No devices have requested DHCP</li>";
        echo "<li>DHCP server is not configured</li>";
        echo "<li>Devices are using static IPs</li>";
        echo "</ul>";
        echo "</div>";
    } else {
        echo "<div style='color: blue; padding: 10px; border: 1px solid blue; margin-bottom: 10px;'>";
        echo "📊 Found <strong>" . count($dhcpLeases) . "</strong> DHCP lease(s)";
        echo "</div>";
        
        echo "<div style='overflow-x: auto;'>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>Index</th><th>MAC Address</th><th>IP Address</th><th>Status</th><th>Host Name</th>";
        echo "<th>Server</th><th>Expires After</th><th>Active Time</th><th>Disabled</th><th>Blocked</th>";
        echo "<th>Rate Limit</th><th>Comment</th>";
        echo "</tr>";
        
        foreach($dhcpLeases as $index => $lease) {
            $mac = $lease['mac-address'] ?? '<em>None</em>';
            $ip = $lease['address'] ?? '<em>None</em>';
            $status = $lease['status'] ?? '<em>None</em>';
            $hostname = $lease['host-name'] ?? '<em>None</em>';
            $server = $lease['server'] ?? '<em>None</em>';
            $expires = $lease['expires-after'] ?? '<em>None</em>';
            $activeTime = $lease['active-time'] ?? '<em>None</em>';
            $disabled = isset($lease['disabled']) ? 'Yes' : 'No';
            $blocked = isset($lease['blocked']) ? 'Yes' : 'No';
            $rateLimit = $lease['rate-limit'] ?? '<em>None</em>';
            $comment = $lease['comment'] ?? '<em>None</em>';
            
            // Color code based on status
            $rowColor = '';
            if($status === 'bound') {
                $rowColor = 'background: #e8f5e8;'; // Light green
            } elseif($status === 'waiting') {
                $rowColor = 'background: #fff3cd;'; // Light yellow
            } elseif(!empty($ip) && $ip !== 'None') {
                $rowColor = 'background: #d1ecf1;'; // Light blue
            }
            
            echo "<tr style='$rowColor'>";
            echo "<td>" . ($index + 1) . "</td>";
            echo "<td style='font-family: monospace;'><strong>$mac</strong></td>";
            echo "<td style='font-family: monospace;'>$ip</td>";
            echo "<td><strong>$status</strong></td>";
            echo "<td>$hostname</td>";
            echo "<td>$server</td>";
            echo "<td>$expires</td>";
            echo "<td>$activeTime</td>";
            echo "<td>$disabled</td>";
            echo "<td>$blocked</td>";
            echo "<td>$rateLimit</td>";
            echo "<td>$comment</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    }
    
    // Get ALL ARP entries
    echo "<h3>🌐 ALL ARP Table Entries</h3>";
    try {
        $arpEntries = $client->query((new Query('/ip/arp/print')))->read();
        
        if(empty($arpEntries)) {
            echo "<div style='color: orange; padding: 10px; border: 1px solid orange;'>";
            echo "⚠️ No ARP entries found";
            echo "</div>";
        } else {
            echo "<div style='color: blue; padding: 10px; border: 1px solid blue; margin-bottom: 10px;'>";
            echo "📊 Found <strong>" . count($arpEntries) . "</strong> ARP entry(ies)";
            echo "</div>";
            
            echo "<div style='overflow-x: auto;'>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
            echo "<tr style='background: #f0f0f0;'>";
            echo "<th>Index</th><th>MAC Address</th><th>IP Address</th><th>Interface</th>";
            echo "<th>Published</th><th>Invalid</th><th>DHCP</th><th>Dynamic</th><th>Complete</th><th>Disabled</th>";
            echo "</tr>";
            
            foreach($arpEntries as $index => $arp) {
                $mac = $arp['mac-address'] ?? '<em>None</em>';
                $ip = $arp['address'] ?? '<em>None</em>';
                $interface = $arp['interface'] ?? '<em>None</em>';
                $published = isset($arp['published']) ? 'Yes' : 'No';
                $invalid = isset($arp['invalid']) ? 'Yes' : 'No';
                $dhcp = isset($arp['DHCP']) ? 'Yes' : 'No';
                $dynamic = isset($arp['dynamic']) ? 'Yes' : 'No';
                $complete = isset($arp['complete']) ? 'Yes' : 'No';
                $disabled = isset($arp['disabled']) ? 'Yes' : 'No';
                
                // Color code active entries
                $rowColor = '';
                if($complete === 'Yes' && $invalid === 'No') {
                    $rowColor = 'background: #e8f5e8;'; // Light green
                } elseif($invalid === 'Yes') {
                    $rowColor = 'background: #f8d7da;'; // Light red
                }
                
                echo "<tr style='$rowColor'>";
                echo "<td>" . ($index + 1) . "</td>";
                echo "<td style='font-family: monospace;'><strong>$mac</strong></td>";
                echo "<td style='font-family: monospace;'>$ip</td>";
                echo "<td>$interface</td>";
                echo "<td>$published</td>";
                echo "<td>$invalid</td>";
                echo "<td>$dhcp</td>";
                echo "<td>$dynamic</td>";
                echo "<td>$complete</td>";
                echo "<td>$disabled</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";
        }
    } catch (Exception $arpError) {
        echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
        echo "❌ ARP query failed: " . $arpError->getMessage();
        echo "</div>";
    }
    
    // Show what our current logic would detect
    echo "<h3>🎯 What Our Detection Logic Currently Finds</h3>";
    $detectedDevices = [];
    
    if(!empty($dhcpLeases)) {
        foreach($dhcpLeases as $lease) {
            $isActive = false;
            $reason = '';
            
            if(isset($lease['mac-address']) && !empty($lease['mac-address'])) {
                if(isset($lease['address']) && !empty($lease['address']) && $lease['address'] !== '0.0.0.0') {
                    $isActive = true;
                    $reason = 'Has MAC and valid IP';
                }
                if(isset($lease['status']) && ($lease['status'] === 'bound' || $lease['status'] === 'waiting')) {
                    $isActive = true;
                    $reason = 'Status is bound/waiting';
                }
                if(isset($lease['active-time']) && !empty($lease['active-time'])) {
                    $isActive = true;
                    $reason = 'Has active time';
                }
            }
            
            if($isActive) {
                $detectedDevices[] = [
                    'mac' => $lease['mac-address'],
                    'ip' => $lease['address'] ?? 'Unknown',
                    'hostname' => $lease['host-name'] ?? 'Unknown',
                    'status' => $lease['status'] ?? 'Unknown',
                    'reason' => $reason
                ];
            }
        }
    }
    
    if(empty($detectedDevices)) {
        echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
        echo "❌ Our detection logic found <strong>0 devices</strong>";
        echo "<p>This means none of the DHCP leases meet our criteria for being 'active'.</p>";
        echo "</div>";
    } else {
        echo "<div style='color: green; padding: 10px; border: 1px solid green;'>";
        echo "✅ Our detection logic found <strong>" . count($detectedDevices) . "</strong> device(s)";
        echo "</div>";
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-top: 10px;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>MAC Address</th><th>IP Address</th><th>Hostname</th><th>Status</th><th>Detection Reason</th>";
        echo "</tr>";
        
        foreach($detectedDevices as $device) {
            echo "<tr style='background: #e8f5e8;'>";
            echo "<td style='font-family: monospace;'>" . $device['mac'] . "</td>";
            echo "<td style='font-family: monospace;'>" . $device['ip'] . "</td>";
            echo "<td>" . $device['hostname'] . "</td>";
            echo "<td>" . $device['status'] . "</td>";
            echo "<td>" . $device['reason'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
    echo "❌ <strong>Connection failed:</strong> " . $e->getMessage();
    echo "<br><br><strong>Possible causes:</strong>";
    echo "<ul>";
    echo "<li>MikroTik router is not accessible</li>";
    echo "<li>Wrong IP address or credentials</li>";
    echo "<li>API service is disabled on router</li>";
    echo "<li>Firewall blocking connection</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ol>";
echo "<li>Check if your device appears in the DHCP leases table above</li>";
echo "<li>Look for your device's MAC address in the ARP table</li>";
echo "<li>If your device appears but isn't detected by our logic, we need to adjust the detection criteria</li>";
echo "<li>If your device doesn't appear at all, check your MikroTik DHCP server configuration</li>";
echo "</ol>";
?>
