<?php
/**
 * Safe MikroTik Connection - ENABLED for Real Device Detection
 * This version connects to MikroTik to get real device data
 */

require_once __DIR__ . '/../vendor/autoload.php';
use RouterOS\Client;

// Set reasonable timeouts
set_time_limit(8);
ini_set('default_socket_timeout', 3);

$router_ip = '192.168.10.1';
$api_port = 8728;

$client = null;
$connection_start = microtime(true);

try {
    // Quick connectivity test first
    $socket_test = @fsockopen($router_ip, $api_port, $errno, $errstr, 2);
    if (!$socket_test) {
        throw new Exception("Router not reachable: $errstr ($errno)");
    }
    fclose($socket_test);
    
    // Router is reachable, attempt API connection
    error_log("Safe MikroTik: Router reachable, attempting API connection...");
    
    $client = new Client([
        'host' => $router_ip,
        'port' => $api_port,
        'user' => 'user1',
        'pass' => 'admin',
        'timeout' => 3,
        'attempts' => 1,
        'delay' => 1
    ]);
    
    // Test the connection with a simple query
    $identity = $client->query('/system/identity/print')->read();
    $connection_time = round(microtime(true) - $connection_start, 2);
    
    error_log("Safe MikroTik: Successfully connected in {$connection_time}s");
    
} catch (Exception $e) {
    $connection_time = round(microtime(true) - $connection_start, 2);
    error_log("Safe MikroTik connection failed after {$connection_time}s: " . $e->getMessage());
    $client = null;
}

?>
