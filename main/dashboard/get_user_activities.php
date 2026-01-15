<?php
// Fast and router-sourced; filter to visible MACs from the table
set_time_limit(10);
ini_set('max_execution_time', 10);

include '../../connectMySql.php';
include '../../loginverification.php';
include '../../includes/DeviceDetectionService.php';
require_once '../../vendor/autoload.php';

header('Content-Type: application/json');

if (!logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }
if (!isset($conn) || !$conn) { echo json_encode(['error' => 'Database connection failed']); exit; }

// Accept POSTed JSON with visible MACs
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$filterMacs = [];
if (is_array($payload) && isset($payload['macs']) && is_array($payload['macs'])) {
    $filterMacs = array_map('strtoupper', $payload['macs']);
}

$activities = [];

try {
    include '../../API/connectMikrotik_safe.php';
    $routerConnected = (isset($client) && $client);
    if (!$routerConnected) {
        // Soft-fail: don't abort; we may still provide placeholders
        $client = null;
    }

    $service = $routerConnected ? new DeviceDetectionService($client, $conn) : null;
    $leases = [];
    if ($service) {
        $snapshot = $service->getConnectedDevicesOnly();
        $leases = $snapshot['devices'] ?? [];
    }

    // Helper: vendor guess by MAC OUI
    $guessVendorFromMac = function($mac) {
        $mac = strtoupper($mac ?? '');
        if (strlen($mac) < 8) return '';
        $oui = str_replace([':', '-'], '', substr($mac, 0, 8));
        $map = [
            'F01898'=>'Apple','D4619D'=>'Apple','40B395'=>'Apple','7C6DF8'=>'Apple','8C8590'=>'Apple','28CFE9'=>'Apple',
            '5C497D'=>'Samsung','DC4EDE'=>'Samsung','14F65A'=>'Samsung','C8D7B0'=>'Samsung','34B354'=>'Samsung',
            'D46E0E'=>'Huawei','001E10'=>'Huawei','00E0FC'=>'Huawei','18F1D8'=>'Huawei',
            'C8F2FA'=>'Xiaomi','64B473'=>'Xiaomi','7427EA'=>'Xiaomi',
            'F0D1A9'=>'OnePlus','44E08E'=>'OnePlus',
            '3C5AB4'=>'Google','F4F5E8'=>'Google',
            'C83A35'=>'Microsoft','B4AE2B'=>'Microsoft',
            'A88E24'=>'LG','001E75'=>'LG',
            '0013A9'=>'Sony','0019C5'=>'Sony',
            '0022AA'=>'Nintendo',
            '50C7BF'=>'TP-Link','F4F26D'=>'TP-Link',
            '14D64D'=>'D-Link',
            '3C4A92'=>'HP','18A99B'=>'HP',
            'B8CA3A'=>'Dell','F0B0E7'=>'Dell',
            'C0B6F9'=>'Lenovo','BCEC5D'=>'Lenovo',
        ];
        return $map[$oui] ?? '';
    };

    // Map current leases by MAC for fast lookup and IP alignment
    $leaseMap = [];
    foreach ($leases as $l) {
        $m = strtoupper($l['mac-address'] ?? '');
        $ip = $l['address'] ?? '';
        $host = $l['host-name'] ?? 'Unknown';
        if ($m && $ip) { $leaseMap[$m] = ['ip' => $ip, 'host' => $host]; }
    }

    $targetMacs = !empty($filterMacs) ? $filterMacs : array_keys($leaseMap);

    foreach ($targetMacs as $macU) {
    $ip = '';
    $hostname = 'Unknown';

        if (isset($leaseMap[$macU])) {
            // Use router lease mapping
            $ip = $leaseMap[$macU]['ip'];
            $hostname = $leaseMap[$macU]['host'];
        } else {
            // Fallback: lookup IP from database
            $stmt = $conn->prepare("SELECT ip_address, COALESCE(name, device_name, 'Unknown') as display_name FROM device WHERE UPPER(mac_address) = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $macU);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        $ip = $row['ip_address'] ?? '';
                        $hostname = $row['display_name'] ?? 'Unknown';
                    }
                }
                $stmt->close();
            }
            if (empty($ip)) { continue; } // nothing to do if we cannot resolve an IP
        }

    try {
            $act = $service ? $service->getDeviceActivity($ip) : [
                'activity' => 'IDLE',
                'details' => 'Router offline',
                'icon' => 'fas fa-wifi'
            ];
            $connCount = isset($act['connections']) && is_array($act['connections']) ? count($act['connections']) : 0;
            $usage = 'Low';
            if ($connCount >= 20) { $usage = 'Very High'; }
            elseif ($connCount >= 10) { $usage = 'High'; }
            elseif ($connCount >= 5) { $usage = 'Medium'; }

            // Top hosts (optional)
            $topHosts = [];
            if (!empty($act['connections'])) {
                $hostCounts = [];
                foreach ($act['connections'] as $c) {
                    $h = strtolower($c['hostname'] ?? '');
                    if (!$h || filter_var($h, FILTER_VALIDATE_IP)) continue;
                    $base = explode(':', $h)[0];
                    $hostCounts[$base] = ($hostCounts[$base] ?? 0) + 1;
                }
                arsort($hostCounts);
                $topHosts = array_slice(array_keys($hostCounts), 0, 3);
            }

            // Derive display name with vendor fallback
            $vendor = $guessVendorFromMac($macU);
            $displayName = (!empty($hostname) && stripos($hostname, 'unknown') === false) ? $hostname : ($vendor ? ($vendor . ' Device') : ('Device ' . substr(str_replace([':', '-'], '', $macU), -4)));

            $activities[$macU] = [
                'ip' => $ip,
                'hostname' => $displayName,
                'activity' => $act['activity'] ?? 'IDLE',
                'details' => $act['details'] ?? 'No active internet connections',
                'icon' => $act['icon'] ?? 'fas fa-moon',
                'connections' => isset($act['connections']) ? array_slice($act['connections'], 0, 10) : [],
                'top_hosts' => $topHosts,
                'data_usage' => $usage,
                'last_updated' => date('H:i:s')
            ];
        } catch (Exception $e) {
            $vendor = $guessVendorFromMac($macU);
            $displayName = (!empty($hostname) && stripos($hostname, 'unknown') === false) ? $hostname : ($vendor ? ($vendor . ' Device') : ('Device ' . substr(str_replace([':', '-'], '', $macU), -4)));
            $activities[$macU] = [
                'ip' => $ip,
                'hostname' => $displayName,
                'activity' => 'ERROR',
                'details' => 'Unable to detect activity',
                'icon' => 'fas fa-exclamation-triangle',
                'connections' => [],
                'top_hosts' => [],
                'data_usage' => 'Low',
                'last_updated' => date('H:i:s')
            ];
        }
    }

    echo json_encode(['success' => true, 'router_connected' => $routerConnected, 'activities' => $activities, 'timestamp' => time()]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to get activity data']);
}
?>
