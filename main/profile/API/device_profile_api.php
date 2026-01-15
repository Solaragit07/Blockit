<?php
// /public_html/device_profile.php
$APP_ROOT = dirname(__DIR__, 3);
$config_file = $APP_ROOT . '/config/router.php';
$client_file = $APP_ROOT . '/includes/routeros_client.php';

if (!file_exists($config_file)) die(json_encode(['ok' => false, 'message' => 'config/router.php missing']));
if (!file_exists($client_file)) die(json_encode(['ok' => false, 'message' => 'includes/routeros_client.php missing']));

$config = require $config_file;
require_once $client_file;

// Create Profile and Sync with MikroTik
function createProfileAndSyncWithMikrotik($name, $ageGroup, $deviceName) {
    try {
        // Fetch MAC Address from MikroTik
        $macAddress = getMikrotikDeviceMacAddress();

        // Assign to MikroTik based on age group
        syncWithMikrotik($macAddress, $ageGroup);

        echo json_encode(["ok" => true, "message" => "Profile created and synced successfully"]);
    } catch (Exception $e) {
        echo json_encode(["ok" => false, "message" => "Error: " . $e->getMessage()]);
    }
}

// Get MAC Address from MikroTik
function getMikrotikDeviceMacAddress() {
    $config = require 'config/router.php';
    $api = new RouterOSClient($config['host'], $config['api_port'], $config['user'], $config['pass'], $config['timeout'], $config['api_tls']);
    
    // Fetch connected devices' MAC addresses from MikroTik
    $devices = $api->talk('/ip/dhcp-server/lease/print', ['?status=bound']);
    
    // Assuming we fetch the first device's MAC address for simplicity
    $macAddress = $devices[0]['mac-address'] ?? null;
    $api->close();
    
    return $macAddress;
}

// Sync Profile with MikroTik based on Age Group
function syncWithMikrotik($macAddress, $ageGroup) {
    try {
        $config = require 'config/router.php';
        $api = new RouterOSClient($config['host'], $config['api_port'], $config['user'], $config['pass'], $config['timeout'], $config['api_tls']);
        
        // Apply rules based on age group
        if ($ageGroup === 'under_18') {
            // Block for under 18
            $api->talk('/ip/firewall/address-list/add', [
                'list' => 'blocklist',
                'address' => $macAddress,
                'comment' => 'Under 18 device'
            ]);
        } else {
            // Whitelist for over 18
            $api->talk('/ip/firewall/address-list/add', [
                'list' => 'whitelist',
                'address' => $macAddress,
                'comment' => 'Over 18 device'
            ]);
        }
        
        $api->close();
    } catch (Throwable $e) {
        echo json_encode(["ok" => false, "message" => "MikroTik Error: " . $e->getMessage()]);
    }
}

// Example Usage (Create Profile)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $ageGroup = $_POST['age_group'];
    $deviceName = $_POST['device_name'];

    createProfileAndSyncWithMikrotik($name, $ageGroup, $deviceName);
}

