<?php
session_start();
include '../../connectMySql.php';
include '../../loginverification.php';

// Check if user is logged in
if (!logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Release session lock to prevent blocking other concurrent requests
if (session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close();
}

header('Content-Type: application/json');

// Check if RouterOS library exists
$routeros_available = false;
if (file_exists('../../vendor/autoload.php')) {
    try {
        require_once '../../vendor/autoload.php';
        $routeros_available = class_exists('RouterOS\Client');
    } catch (Exception $e) {
        error_log("RouterOS library error: " . $e->getMessage());
    }
}

function runDeviceDiagnostic() {
    global $routeros_available;
    
    $diagnostic_data = [
        'connection' => null,
        'dhcp' => null,
        'arp' => null,
        'connections' => null,
        'database' => null
    ];
    
    try {
        if ($routeros_available) {
            // Test MikroTik Connection
            $diagnostic_data['connection'] = testMikroTikConnection();
            
            // If connection successful, analyze device detection
            if ($diagnostic_data['connection']['status'] === 'success') {
                $diagnostic_data['dhcp'] = analyzeDHCPLeases();
                $diagnostic_data['arp'] = analyzeARPTable();
                $diagnostic_data['connections'] = analyzeConnections();
            }
        } else {
            $diagnostic_data['connection'] = [
                'status' => 'error',
                'message' => 'RouterOS library not found',
                'details' => 'Please install the RouterOS library or check vendor/autoload.php'
            ];
        }
        
        // Always check database
        $diagnostic_data['database'] = analyzeDatabaseDevices();
        
        return [
            'success' => true,
            'data' => $diagnostic_data
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function testMikroTikConnection() {
    global $routeros_available;
    
    try {
        // Router configuration
        $router_ip = '192.168.10.1';
        $username = 'user1';
        $password = 'admin';
        
        // Test socket connection first
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            throw new Exception('Could not create socket');
        }
        
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 5, 'usec' => 0]);
        
        $result = @socket_connect($socket, $router_ip, 8728);
        socket_close($socket);
        
        if (!$result) {
            throw new Exception('Socket connection failed to ' . $router_ip . ':8728');
        }
        
        if (!$routeros_available) {
            return [
                'status' => 'warning',
                'message' => 'Socket connection successful, but RouterOS library unavailable',
                'details' => "Router IP: {$router_ip}\nPort: 8728\nSocket: Connected\nRouterOS API: Not available"
            ];
        }
        
        // Test RouterOS API connection
        $client = new RouterOS\Client([
            'host' => $router_ip,
            'user' => $username,
            'pass' => $password,
            'port' => 8728,
        ]);
        
        // Test with a simple query
        $query = new RouterOS\Query('/system/identity/print');
        $response = $client->query($query)->read();
        
        $identity = isset($response[0]['name']) ? $response[0]['name'] : 'Unknown';
        
        return [
            'status' => 'success',
            'message' => 'Successfully connected to MikroTik router',
            'details' => "Router Identity: {$identity}\nIP: {$router_ip}\nPort: 8728\nUser: {$username}"
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Connection failed: ' . $e->getMessage(),
            'details' => "Attempted connection to: {$router_ip}:8728\nUser: {$username}\nError: " . $e->getMessage()
        ];
    }
}

function analyzeDHCPLeases() {
    global $routeros_available;
    
    if (!$routeros_available) {
        return [
            'total_leases' => 0,
            'active_leases' => 0,
            'leases' => [],
            'error' => 'RouterOS library not available'
        ];
    }
    
    try {
        $client = new RouterOS\Client([
            'host' => '192.168.10.1',
            'user' => 'user1',
            'pass' => 'admin',
            'port' => 8728,
        ]);
        
        // Get DHCP leases
        $query = new RouterOS\Query('/ip/dhcp-server/lease/print');
        $dhcp_leases = $client->query($query)->read();
        
        $active_leases = 0;
        $processed_leases = [];
        
        foreach ($dhcp_leases as $lease) {
            $status = isset($lease['status']) ? $lease['status'] : 'unknown';
            if ($status === 'bound') {
                $active_leases++;
            }
            
            $processed_leases[] = [
                'mac' => isset($lease['mac-address']) ? $lease['mac-address'] : null,
                'ip' => isset($lease['address']) ? $lease['address'] : null,
                'hostname' => isset($lease['host-name']) ? $lease['host-name'] : null,
                'status' => $status,
                'expires' => isset($lease['expires-after']) ? $lease['expires-after'] : null
            ];
        }
        
        return [
            'total_leases' => count($dhcp_leases),
            'active_leases' => $active_leases,
            'leases' => $processed_leases
        ];
        
    } catch (Exception $e) {
        return [
            'total_leases' => 0,
            'active_leases' => 0,
            'leases' => [],
            'error' => $e->getMessage()
        ];
    }
}

function analyzeARPTable() {
    global $routeros_available;
    
    if (!$routeros_available) {
        return [
            'total_entries' => 0,
            'entries' => [],
            'error' => 'RouterOS library not available'
        ];
    }
    
    try {
        $client = new RouterOS\Client([
            'host' => '192.168.10.1',
            'user' => 'user1',
            'pass' => 'admin',
            'port' => 8728,
        ]);
        
        // Get ARP entries
        $query = new RouterOS\Query('/ip/arp/print');
        $arp_entries = $client->query($query)->read();
        
        $processed_entries = [];
        
        foreach ($arp_entries as $entry) {
            $processed_entries[] = [
                'mac' => isset($entry['mac-address']) ? $entry['mac-address'] : null,
                'ip' => isset($entry['address']) ? $entry['address'] : null,
                'interface' => isset($entry['interface']) ? $entry['interface'] : null,
                'status' => isset($entry['status']) ? $entry['status'] : 'reachable'
            ];
        }
        
        return [
            'total_entries' => count($arp_entries),
            'entries' => $processed_entries
        ];
        
    } catch (Exception $e) {
        return [
            'total_entries' => 0,
            'entries' => [],
            'error' => $e->getMessage()
        ];
    }
}

function analyzeConnections() {
    global $routeros_available;
    
    if (!$routeros_available) {
        return [
            'total_devices' => 0,
            'dashboard_visible' => 0,
            'detection_method' => 'RouterOS library not available',
            'issues' => ['RouterOS library not found'],
            'devices' => []
        ];
    }
    
    try {
        // Use the same logic as get_real_time_devices.php
        $client = new RouterOS\Client([
            'host' => '192.168.10.1',
            'user' => 'user1',
            'pass' => 'admin',
            'port' => 8728,
        ]);
        
        $detected_devices = [];
        $issues = [];
        $detection_method = '';
        
        // Try DHCP first
        try {
            $query = new RouterOS\Query('/ip/dhcp-server/lease/print');
            $dhcp_leases = $client->query($query)->read();
            
            foreach ($dhcp_leases as $lease) {
                if (isset($lease['status']) && $lease['status'] === 'bound') {
                    $mac = isset($lease['mac-address']) ? $lease['mac-address'] : '';
                    $ip = isset($lease['address']) ? $lease['address'] : '';
                    $hostname = isset($lease['host-name']) ? $lease['host-name'] : 'Unknown Device';
                    
                    if (!empty($mac) && !empty($ip)) {
                        $detected_devices[] = [
                            'mac' => $mac,
                            'ip' => $ip,
                            'hostname' => $hostname,
                            'method' => 'DHCP'
                        ];
                    }
                }
            }
            
            $detection_method = 'DHCP Leases';
            
        } catch (Exception $e) {
            $issues[] = 'DHCP query failed: ' . $e->getMessage();
        }
        
        // If no DHCP devices, try ARP
        if (empty($detected_devices)) {
            try {
                $query = new RouterOS\Query('/ip/arp/print');
                $arp_entries = $client->query($query)->read();
                
                foreach ($arp_entries as $entry) {
                    $mac = isset($entry['mac-address']) ? $entry['mac-address'] : '';
                    $ip = isset($entry['address']) ? $entry['address'] : '';
                    
                    if (!empty($mac) && !empty($ip) && 
                        !in_array($mac, ['00:00:00:00:00:00', 'FF:FF:FF:FF:FF:FF'])) {
                        $detected_devices[] = [
                            'mac' => $mac,
                            'ip' => $ip,
                            'hostname' => 'ARP Device',
                            'method' => 'ARP'
                        ];
                    }
                }
                
                $detection_method = empty($detection_method) ? 'ARP Table' : $detection_method . ' + ARP Table';
                
            } catch (Exception $e) {
                $issues[] = 'ARP query failed: ' . $e->getMessage();
            }
        }
        
        // Count how many would be visible in dashboard (simulate filtering)
        $dashboard_visible = 0;
        foreach ($detected_devices as $device) {
            // Simulate the filtering logic from get_real_time_devices.php
            if (!empty($device['mac']) && !empty($device['ip'])) {
                $dashboard_visible++;
            }
        }
        
        return [
            'total_devices' => count($detected_devices),
            'dashboard_visible' => $dashboard_visible,
            'detection_method' => $detection_method,
            'issues' => $issues,
            'devices' => array_slice($detected_devices, 0, 10) // Show first 10 for sample
        ];
        
    } catch (Exception $e) {
        return [
            'total_devices' => 0,
            'dashboard_visible' => 0,
            'detection_method' => 'Failed',
            'issues' => ['Connection error: ' . $e->getMessage()],
            'devices' => []
        ];
    }
}

function analyzeDatabaseDevices() {
    global $mysqli;
    
    try {
        $sql = "SELECT device_name, mac_address, device_type, blocking_status FROM devices ORDER BY device_name";
        $result = $mysqli->query($sql);
        
        $devices = [];
        while ($row = $result->fetch_assoc()) {
            $devices[] = [
                'name' => $row['device_name'],
                'mac' => $row['mac_address'],
                'type' => $row['device_type'],
                'blocked' => $row['blocking_status']
            ];
        }
        
        return [
            'total_devices' => count($devices),
            'devices' => $devices
        ];
        
    } catch (Exception $e) {
        return [
            'total_devices' => 0,
            'devices' => [],
            'error' => $e->getMessage()
        ];
    }
}

// Run diagnostic and return results
try {
    $result = runDeviceDiagnostic();
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Diagnostic script error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
