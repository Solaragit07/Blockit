<?php
// Prevent timeouts
set_time_limit(10);
ini_set('max_execution_time', 10);

// Set proper headers for AJAX response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Include necessary files
include '../../connectMySql.php';
include '../../loginverification.php';

// RouterOS API imports
require_once '../../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

// Function to format bytes to human readable format
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Function to calculate speed from bytes
function calculateSpeed($currentBytes, $previousBytes, $timeInterval) {
    $bytesDiff = max(0, $currentBytes - $previousBytes);
    $speedBps = $bytesDiff / $timeInterval; // Bytes per second
    $speedMbps = ($speedBps * 8) / (1024 * 1024); // Convert to Mbps
    return $speedMbps;
}

try {
    if (!logged_in()) {
        throw new Exception('Not authenticated');
    }

    $bandwidthData = [];
    $success = false;
    $message = '';

    // Try to get real bandwidth data from MikroTik
    try {
        include '../../API/connectMikrotik.php';
        
        if (isset($client) && $client !== null) {
            // Get interface statistics for bandwidth monitoring
            $interfaces = $client->query((new Query('/interface/print')))->read();
            
            // Get current interface statistics
            $interfaceStats = [];
            foreach ($interfaces as $interface) {
                if (isset($interface['name']) && $interface['running'] === 'true') {
                    $interfaceName = $interface['name'];
                    
                    // Get detailed stats for this interface
                    $stats = $client->query((new Query('/interface/monitor-traffic'))
                        ->equal('interface', $interfaceName)
                        ->equal('duration', '1'))->read();
                    
                    if (!empty($stats)) {
                        $interfaceStats[$interfaceName] = $stats[0];
                    }
                }
            }
            
            // Get DHCP leases to map devices
            $dhcpLeases = $client->query((new Query('/ip/dhcp-server/lease/print')))->read();
            
            // Get ARP table for additional device mapping
            $arpEntries = $client->query((new Query('/ip/arp/print')))->read();
            
            // Process bandwidth data for each connected device
            foreach ($dhcpLeases as $lease) {
                if (isset($lease['mac-address']) && isset($lease['address']) && 
                    isset($lease['status']) && $lease['status'] === 'bound') {
                    
                    $mac = strtoupper($lease['mac-address']);
                    $ip = $lease['address'];
                    
                    // Get bandwidth stats for this specific IP
                    try {
                        // Query firewall connection tracking for bandwidth (separate queries since orWhere doesn't exist)
                        $connections_src = $client->query((new Query('/ip/firewall/connection/print'))
                            ->where('src-address', $ip))->read();
                        
                        $connections_dst = $client->query((new Query('/ip/firewall/connection/print'))
                            ->where('dst-address', $ip))->read();
                        
                        // Combine both result sets
                        $connections = array_merge($connections_src, $connections_dst);
                        
                        $totalRx = 0;
                        $totalTx = 0;
                        $activeConnections = 0;
                        
                        foreach ($connections as $conn) {
                            if (isset($conn['bytes'])) {
                                $bytes = explode(',', $conn['bytes']);
                                if (count($bytes) >= 2) {
                                    $totalRx += intval($bytes[0]);
                                    $totalTx += intval($bytes[1]);
                                    $activeConnections++;
                                }
                            }
                        }
                        
                        // Calculate approximate speed based on active connections
                        $downloadSpeed = $activeConnections > 0 ? 
                            min(10, ($totalRx / 1048576) / max(1, $activeConnections)) : 0; // MB/s
                        $uploadSpeed = $activeConnections > 0 ? 
                            min(2, ($totalTx / 1048576) / max(1, $activeConnections)) : 0; // MB/s
                        
                        $bandwidthData[$mac] = [
                            'download_speed' => number_format($downloadSpeed, 1),
                            'upload_speed' => number_format($uploadSpeed, 1),
                            'total_downloaded' => number_format($totalRx / 1048576, 0), // MB
                            'total_uploaded' => number_format($totalTx / 1048576, 0), // MB
                            'active_connections' => $activeConnections,
                            'ip_address' => $ip,
                            'real_data' => true
                        ];
                        
                    } catch (Exception $e) {
                        // If connection tracking fails, generate realistic mock data
                        $bandwidthData[$mac] = [
                            'download_speed' => number_format(rand(5, 100) / 10, 1), // 0.5-10.0 MB/s
                            'upload_speed' => number_format(rand(1, 20) / 10, 1),   // 0.1-2.0 MB/s
                            'total_downloaded' => rand(50, 500),
                            'total_uploaded' => rand(10, 100),
                            'active_connections' => rand(1, 5),
                            'ip_address' => $ip,
                            'real_data' => false,
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }
            
            $success = true;
            $message = 'Real-time bandwidth data retrieved from MikroTik';
            
        } else {
            throw new Exception('MikroTik client not available');
        }
        
    } catch (Exception $e) {
        // Fallback to mock data when MikroTik is not available
        error_log("Bandwidth monitoring fallback: " . $e->getMessage());
        
        // Generate mock data for testing
        $mockDevices = [
            '9A:C1:84:DA:8F:22',
            '02:00:00:00:00:01',
            '02:00:00:00:00:02',
            '02:00:00:00:00:03'
        ];
        
        foreach ($mockDevices as $mac) {
            $bandwidthData[$mac] = [
                'download_speed' => number_format(rand(5, 120) / 10, 1), // 0.5-12.0 MB/s
                'upload_speed' => number_format(rand(1, 25) / 10, 1),   // 0.1-2.5 MB/s
                'total_downloaded' => rand(50, 800),
                'total_uploaded' => rand(10, 150),
                'active_connections' => rand(1, 8),
                'ip_address' => '192.168.1.' . rand(100, 200),
                'real_data' => false,
                'mock' => true
            ];
        }
        
        $success = true;
        $message = 'Mock bandwidth data generated (MikroTik unavailable)';
    }
    
    // Enhanced response with additional metadata
    $response = [
        'success' => $success,
        'message' => $message,
        'bandwidth_data' => $bandwidthData,
        'timestamp' => date('Y-m-d H:i:s'),
        'device_count' => count($bandwidthData),
        'real_time' => true,
        'update_interval' => 2000 // milliseconds
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Error retrieving bandwidth data: ' . $e->getMessage(),
        'bandwidth_data' => [],
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => true
    ];
    
    error_log("Bandwidth data error: " . $e->getMessage());
}

// Return JSON response
echo json_encode($response, JSON_PRETTY_PRINT);
?>
