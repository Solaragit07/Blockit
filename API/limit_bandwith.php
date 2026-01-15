<?php
include 'connectMikrotik.php';
require_once __DIR__ . '/../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

header('Content-Type: application/json');

$mac_address = $_GET['mac'] ?? null;
$download = $_GET['download'] ?? null;
$upload = $_GET['upload'] ?? null;
$action = $_GET['action'] ?? 'set';

if (!$mac_address) {
    echo json_encode(['status' => 'error', 'message' => 'MAC address required']);
    exit;
}

try {
    $query = (new Query('/ip/dhcp-server/lease/print'))
        ->where('mac-address', $mac_address);
    $leases = $client->query($query)->read();

    if (empty($leases)) {
        throw new Exception("MAC address not found in DHCP leases");
    }

    $ip = $leases[0]['address'] ?? $leases[0]['active-address'] ?? null;
    if (!$ip) {
        throw new Exception("Could not determine IP address");
    }

    $ip_target = $ip . '/32';
    $queue_name = "BW_$mac_address";

    $queueQuery = (new Query('/queue/simple/print'))
        ->where('name', $queue_name);
    $existingQueues = $client->query($queueQuery)->read();

    if ($action === 'remove') {
        if (!empty($existingQueues)) {
            $removeQuery = (new Query('/queue/simple/remove'))
                ->equal('.id', $existingQueues[0]['.id']);
            $client->query($removeQuery)->read();

            echo json_encode([
                'status' => 'success',
                'message' => "Removed bandwidth limits for $mac_address ($ip)",
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'message' => "No existing queue to remove for $mac_address",
            ]);
        }
        exit;
    }

    if (!$download || !$upload) {
        throw new Exception("Both download and upload parameters are required");
    }

    $max_limit = "$download/$upload";

    if (!empty($existingQueues)) {
        $updateQuery = (new Query('/queue/simple/set'))
            ->equal('.id', $existingQueues[0]['.id'])
            ->equal('max-limit', $max_limit)
            ->equal('target', $ip_target)
            ->equal('disabled', 'no');
        $client->query($updateQuery)->read();

        echo json_encode([
            'status' => 'success',
            'message' => "Bandwidth limit updated for $mac_address ($ip)",
            'limit' => $max_limit,
            'updated_existing' => true
        ]);
    } else {
        $addQuery = (new Query('/queue/simple/add'))
            ->equal('name', $queue_name)
            ->equal('target', $ip_target)
            ->equal('max-limit', $max_limit)
            ->equal('disabled', 'no');
        $client->query($addQuery)->read();

        echo json_encode([
            'status' => 'success',
            'message' => "Bandwidth limit set for $mac_address ($ip)",
            'limit' => $max_limit,
            'created_new' => true
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
