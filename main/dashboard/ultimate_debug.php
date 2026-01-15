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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ultimate MikroTik Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .warning { background-color: #fff3cd; border-color: #ffeaa7; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; font-size: 12px; }
        th { background-color: #f8f9fa; }
        .highlight { background-color: #fff3cd; font-weight: bold; }
        .mac { font-family: monospace; font-weight: bold; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>

<h1>🔍 Ultimate MikroTik Device Debug</h1>
<p><strong>Goal:</strong> Find out exactly why your connected device isn't showing up in the dashboard.</p>

<?php
echo "<div class='section info'>";
echo "<h3>📊 Debug Information</h3>";
echo "<p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>User ID:</strong> " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "</div>";

try {
    // Test MikroTik connection
    echo "<div class='section'>";
    echo "<h3>🔌 MikroTik Connection Test</h3>";
    
    include '../../API/connectMikrotik.php';
    if (!isset($client) || $client === null) {
        echo "<div class='error'>";
        echo "❌ <strong>MikroTik client not available</strong><br>";
        echo "Check your connectMikrotik.php file and router settings.";
        echo "</div>";
        exit;
    }
    
    echo "<div class='success'>";
    echo "✅ <strong>MikroTik client connected successfully</strong>";
    echo "</div>";
    echo "</div>";
    
    // Raw DHCP data
    echo "<div class='section'>";
    echo "<h3>📋 Raw DHCP Server Leases (Unfiltered)</h3>";
    
    $dhcpLeases = $client->query((new Query('/ip/dhcp-server/lease/print')))->read();
    
    if(empty($dhcpLeases)) {
        echo "<div class='warning'>";
        echo "⚠️ <strong>No DHCP leases found!</strong><br>";
        echo "This means:<br>";
        echo "• No devices have requested DHCP addresses<br>";
        echo "• DHCP server might not be configured<br>";
        echo "• Your device might be using a static IP<br>";
        echo "</div>";
    } else {
        echo "<div class='success'>";
        echo "✅ Found <strong>" . count($dhcpLeases) . "</strong> DHCP lease(s)";
        echo "</div>";
        
        echo "<h4>Complete DHCP Lease Data:</h4>";
        echo "<div class='code'>";
        echo "<pre>" . htmlspecialchars(print_r($dhcpLeases, true)) . "</pre>";
        echo "</div>";
        
        echo "<h4>DHCP Leases Table:</h4>";
        echo "<table>";
        echo "<tr>";
        echo "<th>#</th><th>MAC Address</th><th>IP Address</th><th>Status</th><th>Host Name</th>";
        echo "<th>Server</th><th>Expires After</th><th>Active Time</th><th>Disabled</th><th>Blocked</th>";
        echo "<th>All Properties</th>";
        echo "</tr>";
        
        foreach($dhcpLeases as $index => $lease) {
            $mac = $lease['mac-address'] ?? 'N/A';
            $ip = $lease['address'] ?? 'N/A';
            $status = $lease['status'] ?? 'N/A';
            $hostname = $lease['host-name'] ?? 'N/A';
            $server = $lease['server'] ?? 'N/A';
            $expires = $lease['expires-after'] ?? 'N/A';
            $activeTime = $lease['active-time'] ?? 'N/A';
            $disabled = isset($lease['disabled']) ? 'YES' : 'NO';
            $blocked = isset($lease['blocked']) ? 'YES' : 'NO';
            
            // Highlight rows with MAC addresses
            $rowClass = !empty($mac) && $mac !== 'N/A' ? 'highlight' : '';
            
            echo "<tr class='$rowClass'>";
            echo "<td>" . ($index + 1) . "</td>";
            echo "<td class='mac'>$mac</td>";
            echo "<td>$ip</td>";
            echo "<td><strong>$status</strong></td>";
            echo "<td>$hostname</td>";
            echo "<td>$server</td>";
            echo "<td>$expires</td>";
            echo "<td>$activeTime</td>";
            echo "<td>$disabled</td>";
            echo "<td>$blocked</td>";
            echo "<td style='font-size: 10px;'>" . htmlspecialchars(json_encode($lease)) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Raw ARP data
    echo "<div class='section'>";
    echo "<h3>🌐 Raw ARP Table Entries</h3>";
    
    try {
        $arpEntries = $client->query((new Query('/ip/arp/print')))->read();
        
        if(empty($arpEntries)) {
            echo "<div class='warning'>";
            echo "⚠️ No ARP entries found";
            echo "</div>";
        } else {
            echo "<div class='success'>";
            echo "✅ Found <strong>" . count($arpEntries) . "</strong> ARP entry(ies)";
            echo "</div>";
            
            echo "<h4>Complete ARP Data:</h4>";
            echo "<div class='code'>";
            echo "<pre>" . htmlspecialchars(print_r($arpEntries, true)) . "</pre>";
            echo "</div>";
            
            echo "<h4>ARP Table:</h4>";
            echo "<table>";
            echo "<tr>";
            echo "<th>#</th><th>MAC Address</th><th>IP Address</th><th>Interface</th>";
            echo "<th>Published</th><th>Invalid</th><th>DHCP</th><th>Dynamic</th><th>Complete</th><th>Disabled</th>";
            echo "<th>All Properties</th>";
            echo "</tr>";
            
            foreach($arpEntries as $index => $arp) {
                $mac = $arp['mac-address'] ?? 'N/A';
                $ip = $arp['address'] ?? 'N/A';
                $interface = $arp['interface'] ?? 'N/A';
                $published = isset($arp['published']) ? 'YES' : 'NO';
                $invalid = isset($arp['invalid']) ? 'YES' : 'NO';
                $dhcp = isset($arp['DHCP']) ? 'YES' : 'NO';
                $dynamic = isset($arp['dynamic']) ? 'YES' : 'NO';
                $complete = isset($arp['complete']) ? 'YES' : 'NO';
                $disabled = isset($arp['disabled']) ? 'YES' : 'NO';
                
                // Highlight active entries
                $rowClass = ($complete === 'YES' && $invalid === 'NO') ? 'highlight' : '';
                
                echo "<tr class='$rowClass'>";
                echo "<td>" . ($index + 1) . "</td>";
                echo "<td class='mac'>$mac</td>";
                echo "<td>$ip</td>";
                echo "<td>$interface</td>";
                echo "<td>$published</td>";
                echo "<td>$invalid</td>";
                echo "<td>$dhcp</td>";
                echo "<td>$dynamic</td>";
                echo "<td>$complete</td>";
                echo "<td>$disabled</td>";
                echo "<td style='font-size: 10px;'>" . htmlspecialchars(json_encode($arp)) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $arpError) {
        echo "<div class='error'>";
        echo "❌ ARP query failed: " . $arpError->getMessage();
        echo "</div>";
    }
    echo "</div>";
    
    // Test our detection logic
    echo "<div class='section'>";
    echo "<h3>🎯 Our Current Detection Logic Test</h3>";
    
    $detectedDevices = [];
    $allDevicesWithMAC = [];
    
    // Apply our current logic
    if(!empty($dhcpLeases)) {
        foreach($dhcpLeases as $lease) {
            // Track all devices with MAC
            if(isset($lease['mac-address']) && !empty($lease['mac-address'])) {
                $allDevicesWithMAC[] = $lease;
            }
            
            // Current detection logic
            if(isset($lease['mac-address']) && !empty($lease['mac-address'])) {
                // Skip only explicitly disabled devices
                if(isset($lease['disabled']) && $lease['disabled'] === 'true') {
                    continue;
                }
                
                $detectedDevices[] = $lease;
            }
        }
    }
    
    echo "<h4>All Devices with MAC Addresses:</h4>";
    echo "<div class='info'>";
    echo "Found <strong>" . count($allDevicesWithMAC) . "</strong> device(s) with MAC addresses";
    echo "</div>";
    
    if(!empty($allDevicesWithMAC)) {
        echo "<table>";
        echo "<tr><th>MAC</th><th>IP</th><th>Status</th><th>Hostname</th><th>Would Be Detected?</th></tr>";
        foreach($allDevicesWithMAC as $device) {
            $mac = $device['mac-address'];
            $ip = $device['address'] ?? 'N/A';
            $status = $device['status'] ?? 'N/A';
            $hostname = $device['host-name'] ?? 'N/A';
            
            $wouldDetect = 'YES';
            $reason = 'Has MAC address';
            
            if(isset($device['disabled']) && $device['disabled'] === 'true') {
                $wouldDetect = 'NO';
                $reason = 'Disabled device';
            }
            
            $detectedClass = $wouldDetect === 'YES' ? 'highlight' : '';
            
            echo "<tr class='$detectedClass'>";
            echo "<td class='mac'>$mac</td>";
            echo "<td>$ip</td>";
            echo "<td>$status</td>";
            echo "<td>$hostname</td>";
            echo "<td><strong>$wouldDetect</strong> ($reason)</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h4>Final Detection Result:</h4>";
    if(empty($detectedDevices)) {
        echo "<div class='error'>";
        echo "❌ Our logic detected <strong>0 devices</strong><br>";
        echo "This means no devices in DHCP leases have MAC addresses or all are disabled.";
        echo "</div>";
    } else {
        echo "<div class='success'>";
        echo "✅ Our logic detected <strong>" . count($detectedDevices) . "</strong> device(s)";
        echo "</div>";
        
        echo "<table>";
        echo "<tr><th>MAC</th><th>IP</th><th>Status</th><th>Hostname</th></tr>";
        foreach($detectedDevices as $device) {
            echo "<tr class='highlight'>";
            echo "<td class='mac'>" . ($device['mac-address'] ?? 'N/A') . "</td>";
            echo "<td>" . ($device['address'] ?? 'N/A') . "</td>";
            echo "<td>" . ($device['status'] ?? 'N/A') . "</td>";
            echo "<td>" . ($device['host-name'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Test the actual API endpoint
    echo "<div class='section'>";
    echo "<h3>🚀 Live API Test</h3>";
    echo "<p>Testing the actual get_real_time_devices.php endpoint:</p>";
    
    echo "<iframe src='get_real_time_devices.php' width='100%' height='400' style='border: 1px solid #ddd;'></iframe>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='section error'>";
    echo "<h3>❌ Connection Error</h3>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<div class='section info'>";
echo "<h3>🔍 Next Steps</h3>";
echo "<ol>";
echo "<li><strong>Look for your device's MAC address</strong> in the DHCP leases table above</li>";
echo "<li><strong>Check if it appears</strong> in the ARP table as well</li>";
echo "<li><strong>Verify our detection logic</strong> shows your device as 'Would Be Detected: YES'</li>";
echo "<li><strong>Check the Live API Test</strong> iframe to see if it returns your device</li>";
echo "<li><strong>If your device doesn't appear anywhere</strong>, check MikroTik DHCP server configuration</li>";
echo "</ol>";
echo "</div>";
?>

</body>
</html>
