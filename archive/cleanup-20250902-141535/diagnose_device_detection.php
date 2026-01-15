<?php
/**
 * Device Detection Diagnostic Script
 * This script will help diagnose why devices are not showing up
 */

set_time_limit(30);
ini_set('default_socket_timeout', 5);

echo "=== BlockIT Device Detection Diagnostic ===\n\n";

// Test 1: Check if router is reachable
echo "1. Testing router connectivity...\n";
$router_ip = '192.168.10.1';
$api_port = 8728;

$socket_test = @fsockopen($router_ip, $api_port, $errno, $errstr, 3);
if ($socket_test) {
    fclose($socket_test);
    echo "✅ Router is reachable at $router_ip:$api_port\n\n";
} else {
    echo "❌ Router not reachable: $errstr ($errno)\n";
    echo "This is why devices are not showing up - no router connection!\n\n";
    
    // Check if it's a different IP
    echo "2. Trying common router IPs...\n";
    $common_ips = ['192.168.1.1', '192.168.0.1', '10.0.0.1', '192.168.100.1'];
    
    foreach ($common_ips as $test_ip) {
        $test_socket = @fsockopen($test_ip, $api_port, $errno, $errstr, 2);
        if ($test_socket) {
            fclose($test_socket);
            echo "✅ Found MikroTik at $test_ip:$api_port\n";
            echo "Update your router IP in the configuration!\n";
            break;
        } else {
            echo "❌ No MikroTik at $test_ip\n";
        }
    }
    exit;
}

// Test 2: Try MikroTik API connection
echo "2. Testing MikroTik API connection...\n";
try {
    require_once 'vendor/autoload.php';
    
    $client = new RouterOS\Client([
        'host' => $router_ip,
        'port' => $api_port,
        'user' => 'user1',
        'pass' => 'admin',
        'timeout' => 5,
        'attempts' => 1
    ]);
    
    $identity = $client->query('/system/identity/print')->read();
    echo "✅ API connection successful\n";
    echo "Router identity: " . ($identity[0]['name'] ?? 'Unknown') . "\n\n";
    
} catch (Exception $e) {
    echo "❌ API connection failed: " . $e->getMessage() . "\n";
    echo "Check your credentials (user1/admin)\n\n";
    exit;
}

// Test 3: Check DHCP leases
echo "3. Getting DHCP leases...\n";
try {
    $dhcpLeases = $client->query((new RouterOS\Query('/ip/dhcp-server/lease/print')))->read();
    echo "✅ Found " . count($dhcpLeases) . " DHCP leases\n";
    
    foreach ($dhcpLeases as $i => $lease) {
        $mac = $lease['mac-address'] ?? 'unknown';
        $ip = $lease['address'] ?? 'unknown';
        $hostname = $lease['host-name'] ?? 'unknown';
        $status = $lease['status'] ?? 'unknown';
        $disabled = $lease['disabled'] ?? 'false';
        
        echo "  Device $i: $hostname ($mac) - IP: $ip, Status: $status, Disabled: $disabled\n";
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ Failed to get DHCP leases: " . $e->getMessage() . "\n\n";
}

// Test 4: Check ARP table
echo "4. Getting ARP table...\n";
try {
    $arpEntries = $client->query((new RouterOS\Query('/ip/arp/print')))->read();
    echo "✅ Found " . count($arpEntries) . " ARP entries\n";
    
    foreach ($arpEntries as $i => $arp) {
        $mac = $arp['mac-address'] ?? 'unknown';
        $ip = $arp['address'] ?? 'unknown';
        $interface = $arp['interface'] ?? 'unknown';
        $complete = $arp['complete'] ?? 'false';
        $invalid = $arp['invalid'] ?? 'false';
        
        echo "  ARP $i: $ip ($mac) - Interface: $interface, Complete: $complete, Invalid: $invalid\n";
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ Failed to get ARP table: " . $e->getMessage() . "\n\n";
}

// Test 5: Use DeviceDetectionService
echo "5. Testing DeviceDetectionService...\n";
try {
    include 'connectMySql.php';
    include 'includes/DeviceDetectionService.php';
    
    $deviceService = new DeviceDetectionService($client, $conn);
    $deviceData = $deviceService->getConnectedDevicesOnly();
    
    echo "✅ DeviceDetectionService found " . count($deviceData['devices']) . " connected devices\n";
    
    foreach ($deviceData['devices'] as $i => $device) {
        $mac = $device['mac-address'] ?? 'unknown';
        $ip = $device['address'] ?? 'unknown';
        $hostname = $device['host-name'] ?? 'unknown';
        $status = $device['status'] ?? 'unknown';
        
        echo "  Connected Device $i: $hostname ($mac) - IP: $ip, Status: $status\n";
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ DeviceDetectionService failed: " . $e->getMessage() . "\n\n";
}

echo "=== Diagnostic Complete ===\n";
echo "If devices are not showing up, check:\n";
echo "1. Router IP address (currently: $router_ip)\n";
echo "2. API credentials (currently: user1/admin)\n";
echo "3. Device status (must be 'bound' or have valid ARP entry)\n";
echo "4. Device must not be disabled\n";
?>
