<?php
require_once __DIR__ . '/../vendor/autoload.php';
use RouterOS\Client;

// Only set header if no output has been sent and this is called directly
if (!headers_sent() && basename($_SERVER['PHP_SELF']) == 'connectMikrotik.php') {
    header('Content-Type: application/json');
}

$router_ip = '192.168.10.1';  // Updated to correct router IP
$api_port = 8728;
$credentials = [
    'user' => 'user1',
    'pass' => 'admin'
];

// Check if this file is being called directly vs included
$is_direct_call = (basename($_SERVER['PHP_SELF']) == 'connectMikrotik.php');

if (!function_exists('socket_create')) {
    if ($is_direct_call) {
        die(json_encode([
            'status' => 'error',
            'message' => 'PHP sockets extension not enabled'
        ]));
    } else {
        throw new Exception('PHP sockets extension not enabled');
    }
}

exec("ping -n 2 -w 3000 $router_ip", $output, $result);
if ($result !== 0) {
    // Only fail if socket connection also fails
    $socket_test = @fsockopen($router_ip, $api_port, $errno, $errstr, 5);
    if (!$socket_test) {
        if ($is_direct_call) {
            die(json_encode([
                'status' => 'error',
                'message' => "Router $router_ip is unreachable from this server"
            ]));
        } else {
            throw new Exception("Router $router_ip is unreachable from this server");
        }
    } else {
        fclose($socket_test);
        // Ping failed but socket works, continue
    }
}

// Socket connection test is now integrated above with ping test

try {
    // Set a very short timeout for this connection attempt
    set_time_limit(10);
    
    $client = new Client([
        'host' => $router_ip,
        'user' => $credentials['user'],
        'pass' => $credentials['pass'],
        'port' => $api_port,
        'timeout' => 2,  // Very short timeout
        'attempts' => 1,  // Single attempt only
        'delay' => 1    // Must be integer, not float
    ]);

    // Quick test query with timeout
    $start_time = time();
    $client->query('/system/identity/print')->read();
    $connection_time = time() - $start_time;
    
    if ($connection_time > 5) {
        throw new Exception("Connection too slow ($connection_time seconds)");
    }

  

} catch (Exception $e) {
    $client = null; // Ensure client is set to null on failure
    if ($is_direct_call) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Connection failed: ' . $e->getMessage(),
            'solution' => [
                '1. Verify router IP is correct',
                '2. Check API service is enabled on router',
                '3. Confirm username/password are correct',
                '4. Check firewall rules on both server and router'
            ]
        ]);
    } else {
        // When included, just set client to null and let calling script handle it
        error_log("MikroTik connection failed: " . $e->getMessage());
    }
}