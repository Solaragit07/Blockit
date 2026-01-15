<?php
require_once('API/connectMikrotik.php');
require_once('API/routeros_api.php');

use RouterOS\Query;

echo "<h1>MikroTik Raw Data Diagnostic</h1>";
echo "<style>body { font-family: Arial; margin: 20px; } pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; white-space: pre-wrap; }</style>";

try {
    // Connect to MikroTik
    $client = connectMikrotik();
    
    if ($client === null) {
        echo "<h2 style='color: red;'>ERROR: Cannot connect to MikroTik</h2>";
        exit;
    }
    
    echo "<h2 style='color: green;'>✓ Successfully connected to MikroTik</h2>";
    
    // Get DHCP Server Leases
    echo "<h3>DHCP Server Leases (Raw Data):</h3>";
    try {
        $dhcpLeases = $client->query((new Query('/ip/dhcp-server/lease/print')))->read();
        echo "<p><strong>Total DHCP leases found:</strong> " . count($dhcpLeases) . "</p>";
        echo "<pre>" . print_r($dhcpLeases, true) . "</pre>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>DHCP Query Error: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
    
    // Get ARP Table
    echo "<h3>ARP Table (Raw Data):</h3>";
    try {
        $arpEntries = $client->query((new Query('/ip/arp/print')))->read();
        echo "<p><strong>Total ARP entries found:</strong> " . count($arpEntries) . "</p>";
        echo "<pre>" . print_r($arpEntries, true) . "</pre>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>ARP Query Error: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
    
    // Get Interface Status
    echo "<h3>Interface Status (Raw Data):</h3>";
    try {
        $interfaces = $client->query((new Query('/interface/print')))->read();
        echo "<p><strong>Total interfaces found:</strong> " . count($interfaces) . "</p>";
        echo "<pre>" . print_r($interfaces, true) . "</pre>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Interface Query Error: " . $e->getMessage() . "</p>";
    }
    
    $client->query((new Query('/quit')));
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Connection Error: " . $e->getMessage() . "</h2>";
}
?>
