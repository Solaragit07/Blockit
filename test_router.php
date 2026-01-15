<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = require __DIR__ . '/config/router.php';
require __DIR__ . '/includes/routeros_client.php';

header('Content-Type: text/plain; charset=utf-8');

// ---- ENV CHECKS ----
$checks = [
    'php_version' => PHP_VERSION,
    'openssl_loaded' => extension_loaded('openssl'),
    'default_socket_timeout' => ini_get('default_socket_timeout'),
    'router_host' => $config['host'],
    'router_api_port' => $config['api_port'],
    'api_tls' => $config['api_tls'] ? 'yes' : 'no',
    'timeout' => $config['timeout'],
];
print_r($checks);
// ---- PROBE (TLS aware, no verify) ----
function probe_tls($host, $port, $timeout) {
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
            'SNI_enabled'       => false,
            'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                                 | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                                 | STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT,
        ]
    ]);
    $errno = 0; $err = '';
    $fp = @stream_socket_client("tls://{$host}:{$port}", $errno, $err, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if ($fp) { fclose($fp); return "OPEN"; }
    return "FAIL $err ($errno)";
}

function probe_plain($host, $port, $timeout) {
    $errno = 0; $err = '';
    $fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $err, $timeout);
    if ($fp) { fclose($fp); return "OPEN"; }
    return "FAIL $err ($errno)";
}

echo "\nPROBE API-SSL {$config['host']}:{$config['api_port']}...\n";
echo probe_tls($config['host'], (int)$config['api_port'], (int)$config['timeout']), "\n";

// Temporary plain probe on 8728 (helps isolate TLS vs reachability)
echo "\n(Temporary) PROBE plain API {$config['host']}:8728...\n";
echo probe_plain($config['host'], 8728, (int)$config['timeout']), "\n";

// ---- LOGIN (RouterOSClient) ----
echo "\nCLIENT LOGIN TEST (TLS 8729)...\n";
try {
    $client = new RouterOSClient(
        $config['host'],
        8729,
        $config['user'],
        $config['pass'],
        (int)$config['timeout'],
        true   // TLS
    );
    echo "Login OK.\n";
    print_r($client->talk('/system/identity/print'));
    $client->close();
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// ---- PROBE SOCKET ----
echo "\nPROBE API {$config['host']}:{$config['api_port']} (TLS=" . ($config['api_tls'] ? 'on' : 'off') . "): ";
$errno = 0; $errstr = '';
$fp = @fsockopen(($config['api_tls'] ? "tls://" : "tcp://").$config['host'], $config['api_port'], $errno, $errstr, $config['timeout']);
if ($fp) {
    echo "OPEN\n";
    fclose($fp);
} else {
    echo "FAIL $errstr ($errno)\n";
}

// ---- LOGIN TEST ----
echo "\nCLIENT LOGIN TEST...\n";
try {
    $client = new RouterOSClient(
        $config['host'],
        $config['api_port'],
        $config['user'],
        $config['pass'],
        $config['timeout'],
        $config['api_tls']
    );
    echo "Login OK\n";
    $rows = $client->talk('/system/identity/print');
    print_r($rows);
    $client->close();
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

