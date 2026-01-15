<?php
// Return the configured bandwidth limit for a device from RouterOS (preferred) or DB (fallback)
header('Content-Type: application/json');

require_once '../connectMySql.php'; // provides $conn (mysqli)

// Optional: enable error reporting while developing
// error_reporting(E_ALL); ini_set('display_errors', 1);

try {
    if (!isset($_GET['mac']) || !$_GET['mac']) {
        throw new Exception('MAC address is required');
    }

    $mac = trim($_GET['mac']);
    $bandwidthLimit = null;

    // 1) Try RouterOS first
    try {
        require_once __DIR__ . '/connectMikrotik_safe.php'; // sets $client or null

        if (isset($client) && $client) {
            // Fetch all simple queues once
            $queues = $client->query(new \RouterOS\Query('/queue/simple/print'))->read();

            // Normalize MAC and queue naming variants we use
            $macClean = strtolower(str_replace(':', '', $mac));
            $possibleNames = [
                'limit_' . $macClean,
                'BW_' . strtoupper($macClean),
            ];

            foreach ($queues as $q) {
                $qName = $q['name'] ?? '';
                $qTarget = strtolower($q['target'] ?? '');
                $qComment = strtolower($q['comment'] ?? '');

                $nameMatch = in_array($qName, $possibleNames, true);
                $targetMatch = ($qTarget === strtolower($mac) || $qTarget === $macClean || strpos($qTarget, $macClean) !== false);
                $commentMatch = strpos($qComment, 'blockit bandwidth limit') !== false;

                if ($nameMatch || $targetMatch || $commentMatch) {
                    if (!empty($q['max-limit'])) {
                        // MikroTik format "upload/download" with units like 10M/5M
                        [$uploadStr, $downloadStr] = array_pad(explode('/', $q['max-limit']), 2, '0');
                        $uploadBps = parseRouterLimitToBytes($uploadStr);
                        $downloadBps = parseRouterLimitToBytes($downloadStr);

                        $bandwidthLimit = [
                            'upload' => formatBytes($uploadBps),
                            'download' => formatBytes($downloadBps),
                            'upload_raw' => $uploadBps,
                            'download_raw' => $downloadBps,
                            'status' => 'active'
                        ];
                        break;
                    }
                }
            }
        }
    } catch (Throwable $te) {
        error_log('get_bandwidth_limits: RouterOS query failed: ' . $te->getMessage());
    }

    // 2) Fallback to DB (supports both legacy bytes columns and new *_mbps columns)
    if (!$bandwidthLimit) {
        $dbLimit = getDbLimitForMac($conn, $mac);
        if ($dbLimit) {
            $bandwidthLimit = $dbLimit;
        }
    }

    echo json_encode([
        'success' => true,
        'limit' => $bandwidthLimit,
        'mac' => $mac
    ]);
    exit;

} catch (Throwable $e) {
    error_log('Bandwidth limit API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

// Helpers
function formatBytes($bytes) {
    $bytes = (float)$bytes;
    if ($bytes <= 0) return '0 B/s';
    $units = ['B/s', 'KB/s', 'MB/s', 'GB/s', 'TB/s'];
    $pow = (int)floor(log($bytes, 1024));
    $pow = max(0, min($pow, count($units) - 1));
    $val = $bytes / (1024 ** $pow);
    return round($val, 2) . ' ' . $units[$pow];
}

function parseRouterLimitToBytes($val) {
    // Accept values like "0", "512k", "10M", "1G"
    $val = trim((string)$val);
    if ($val === '' || $val === '0') return 0;
    $num = (float)$val;
    $unit = strtolower(substr($val, -1));
    switch ($unit) {
        case 'k': return (int)round($num * 1024);
        case 'm': return (int)round($num * 1024 * 1024);
        case 'g': return (int)round($num * 1024 * 1024 * 1024);
        default:  return (int)$num; // already in bytes per second
    }
}

function getDbLimitForMac(mysqli $conn, string $mac) {
    // Try new schema (Mbps columns)
    $sql = "SELECT download_limit_mbps, upload_limit_mbps, created_at, updated_at
            FROM bandwidth_limits WHERE mac_address = ?
            ORDER BY updated_at DESC, created_at DESC LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('s', $mac);
        if ($stmt->execute() && ($res = $stmt->get_result()) && $row = $res->fetch_assoc()) {
            if (isset($row['download_limit_mbps']) && isset($row['upload_limit_mbps'])) {
                $downBps = (float)$row['download_limit_mbps'] * 1024 * 1024;
                $upBps = (float)$row['upload_limit_mbps'] * 1024 * 1024;
                return [
                    'upload' => formatBytes($upBps),
                    'download' => formatBytes($downBps),
                    'upload_raw' => (int)$upBps,
                    'download_raw' => (int)$downBps,
                    'status' => 'stored'
                ];
            }
        }
        $stmt->close();
    }

    // Fallback to legacy schema (bytes columns)
    $sqlLegacy = "SELECT upload_limit, download_limit, created_at, updated_at
                  FROM bandwidth_limits WHERE mac_address = ?
                  ORDER BY updated_at DESC, created_at DESC LIMIT 1";
    if ($stmt2 = $conn->prepare($sqlLegacy)) {
        $stmt2->bind_param('s', $mac);
        if ($stmt2->execute() && ($res2 = $stmt2->get_result()) && $row2 = $res2->fetch_assoc()) {
            if (isset($row2['download_limit']) && isset($row2['upload_limit'])) {
                return [
                    'upload' => formatBytes((int)$row2['upload_limit']),
                    'download' => formatBytes((int)$row2['download_limit']),
                    'upload_raw' => (int)$row2['upload_limit'],
                    'download_raw' => (int)$row2['download_limit'],
                    'status' => 'stored'
                ];
            }
        }
        $stmt2->close();
    }
    return null;
}
?>
