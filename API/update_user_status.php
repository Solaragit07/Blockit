<?php

include 'connectMikrotik.php';

require_once __DIR__ . '/../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

$mac_address = $_GET['mac_address'] ?? null;
$block_action = $_GET['block_action'] ?? 'block';

if (!$mac_address) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required parameter: mac_address.'
    ]);
    exit;
}

if (!in_array(strtolower($block_action), ['block', 'unblock'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid block_action. Must be either "block" or "unblock".'
    ]);
    exit;
}

try {
    $query = (new Query('/ip/dhcp-server/lease/print'))
        ->where('mac-address', $mac_address);
    $leases = $client->query($query)->read();

    if (count($leases) === 0) {
        throw new Exception("MAC address not found in DHCP leases.");
    }

    $lease = $leases[0];
    $ip_address = $lease['address'] ?? null;
    $leaseId = $lease['.id'];

    if (isset($lease['dynamic']) && $lease['dynamic'] === 'true') {
        $makeStatic = (new Query('/ip/dhcp-server/lease/make-static'))
            ->equal('.id', $leaseId);
        $client->query($makeStatic)->read();
        $response['messages'][] = "Made DHCP lease static for MAC $mac_address.";
    }

    $query = (new Query('/ip/firewall/filter/print'))
        ->where('comment', "Internet block for $mac_address");
    $existingRules = $client->query($query)->read();

    if (strtolower($block_action) === 'block') {
        foreach ($existingRules as $rule) {
            $client->query((new Query('/ip/firewall/filter/remove'))->equal('.id', $rule['.id']))->read();
        }

        $blockRule = (new Query('/ip/firewall/filter/add'))
            ->equal('chain', 'forward')
            ->equal('src-address', $ip_address)
            ->equal('action', 'drop')
            ->equal('disabled', 'no')
            ->equal('comment', "Internet block for $mac_address");
        $result = $client->query($blockRule)->read();

        $response = [
            'status' => 'success',
            'action' => 'blocked',
            'message' => "Successfully blocked internet access for MAC $mac_address.",
            'ip_address' => $ip_address,
            'details' => $result
        ];
    } else {
        $rulesRemoved = 0;
        foreach ($existingRules as $rule) {
            $client->query((new Query('/ip/firewall/filter/remove'))->equal('.id', $rule['.id']))->read();
            $rulesRemoved++;
        }

        if ($rulesRemoved > 0) {
            $response = [
                'status' => 'success',
                'action' => 'unblocked',
                'message' => "Successfully unblocked internet access for MAC $mac_address.",
                'ip_address' => $ip_address,
                'rules_removed' => $rulesRemoved
            ];
        } else {
            $response = [
                'status' => 'info',
                'action' => 'unblocked',
                'message' => "No blocking rules found for MAC $mac_address. No action taken.",
                'ip_address' => $ip_address
            ];
        }
    }

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

header('Content-Type: application/json');
echo json_encode($response);