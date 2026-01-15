<?php

include 'connectMikrotik.php';

require_once __DIR__ . '/../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$mac_address = $input['mac_address'] ?? null;

try {
    $query = new Query('/ip/dhcp-server/lease/print');
    $leases = $client->query($query)->read();

    $firewallQuery = new Query('/ip/firewall/filter/print');
    $firewallRules = $client->query($firewallQuery)->read();

    $result = [];
    foreach ($leases as $lease) {
        if (($lease['status'] ?? '') !== 'bound') {
            continue;
        }

        if (!empty($mac_address) && strtolower($lease['mac-address']) !== strtolower($mac_address)) {
            continue;
        }

        $mac = $lease['mac-address'] ?? null;
        $ip = $lease['active-address'] ?? $lease['address'] ?? null;

        $is_blocked = false;
        $block_comment = null;
        
        foreach ($firewallRules as $rule) {
            if (!isset($rule['action']) || !isset($rule['chain'])) {
                continue;
            }

            if ($rule['chain'] === 'forward' && $rule['action'] === 'drop') {
                if (isset($rule['comment']) && $rule['comment'] === "Internet block for $mac") {
                    $is_blocked = true;
                    $block_comment = $rule['comment'];
                    break;
                }
            }
        }

        $result[] = [
            'mac_address'   => $mac,
            'ip_address'    => $ip,
            'host_name'     => $lease['host-name'] ?? null,
            'status'        => $lease['status'] ?? null,
            'last_seen'     => $lease['last-seen'] ?? null,
            'expires_after' => $lease['expires-after'] ?? null,
            'server'        => $lease['server'] ?? null,
            'comment'       => $lease['comment'] ?? null,
            'block_status'  => [
                'is_blocked' => $is_blocked,
                'block_rule_comment' => $block_comment,
                'block_type' => $is_blocked ? 'IP/MAC blocked' : 'Not blocked',
                'block_pattern_match' => $is_blocked ? 'Exact match' : 'No match'
            ]
        ];
    }

    echo json_encode([
    'status' => 'success',
    'data' => $result
], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => "❌ Failed to retrieve DHCP leases: " . $e->getMessage()
    ]);
}