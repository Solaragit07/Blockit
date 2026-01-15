<?php
// Device Age-Based Filter Enforcement
// This integrates with the existing device management to apply age-based filters

include '../connectMySql.php';
include '../includes/AgeBasedFilterEngine.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$filter_engine = new AgeBasedFilterEngine($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'get_device_age_rules':
                $device_id = (int)$_POST['device_id'];
                
                // Get device age
                $device_query = "SELECT age, name FROM device WHERE id = $device_id";
                $device_result = $conn->query($device_query);
                
                if (!$device_result || $device_result->num_rows === 0) {
                    throw new Exception('Device not found');
                }
                
                $device = $device_result->fetch_assoc();
                $device_age = (int)$device['age'];
                
                if ($device_age <= 0) {
                    throw new Exception('Device age not set or invalid');
                }
                
                // Get age-based rules for this device
                $allowed_domains = $filter_engine->getAllowedDomainsForAge($device_age);
                $blocked_domains = $filter_engine->getBlockedDomainsForAge($device_age);
                
                $response['success'] = true;
                $response['device_name'] = $device['name'];
                $response['device_age'] = $device_age;
                $response['allowed_domains'] = $allowed_domains;
                $response['blocked_domains'] = $blocked_domains;
                $response['total_allowed'] = count($allowed_domains);
                $response['total_blocked'] = count($blocked_domains);
                break;
                
            case 'apply_age_filters_to_device':
                $device_id = (int)$_POST['device_id'];
                
                // Get device information
                $device_query = "SELECT age, name, mac_address FROM device WHERE id = $device_id";
                $device_result = $conn->query($device_query);
                
                if (!$device_result || $device_result->num_rows === 0) {
                    throw new Exception('Device not found');
                }
                
                $device = $device_result->fetch_assoc();
                $device_age = (int)$device['age'];
                
                if ($device_age <= 0) {
                    throw new Exception('Device age not set. Please set the device age first.');
                }
                
                // Export rules for this specific device age
                $rules = $filter_engine->exportForRouter($device_age);
                
                // Here you would integrate with the router API to apply these rules
                // For now, we'll simulate the process
                
                // Log the rule application
                $log_insert = "INSERT INTO age_filter_logs (device_id, device_age, applied_at, rules_count) 
                              VALUES ($device_id, $device_age, NOW(), " . 
                              (count($rules['blacklist']) + count($rules['whitelist'])) . ")";
                $conn->query($log_insert);
                
                $response['success'] = true;
                $response['message'] = "Age-based filters applied to {$device['name']} (Age: {$device_age})";
                $response['rules_applied'] = count($rules['blacklist']) + count($rules['whitelist']);
                break;
                
            case 'get_all_devices_age_status':
                // Get all devices with their age-based filter status
                $devices_query = "SELECT d.id, d.name, d.age, d.mac_address, d.device,
                                        COUNT(afl.id) as filter_applications
                                 FROM device d
                                 LEFT JOIN age_filter_logs afl ON d.id = afl.device_id 
                                 AND DATE(afl.applied_at) = CURDATE()
                                 GROUP BY d.id
                                 ORDER BY d.name";
                
                $devices_result = $conn->query($devices_query);
                $devices = [];
                
                while ($device = $devices_result->fetch_assoc()) {
                    $device_age = (int)$device['age'];
                    
                    if ($device_age > 0) {
                        $device['blocked_count'] = count($filter_engine->getBlockedDomainsForAge($device_age));
                        $device['allowed_count'] = count($filter_engine->getAllowedDomainsForAge($device_age));
                        $device['filter_status'] = $device['filter_applications'] > 0 ? 'active' : 'pending';
                    } else {
                        $device['blocked_count'] = 0;
                        $device['allowed_count'] = 0;
                        $device['filter_status'] = 'no_age_set';
                    }
                    
                    $devices[] = $device;
                }
                
                $response['success'] = true;
                $response['devices'] = $devices;
                break;
                
            case 'bulk_apply_age_filters':
                // Apply age-based filters to all devices with valid ages
                $devices_query = "SELECT id, name, age FROM device WHERE age > 0";
                $devices_result = $conn->query($devices_query);
                
                $applied_count = 0;
                $skipped_count = 0;
                $errors = [];
                
                while ($device = $devices_result->fetch_assoc()) {
                    try {
                        $device_id = $device['id'];
                        $device_age = (int)$device['age'];
                        
                        // Export and apply rules for this device
                        $rules = $filter_engine->exportForRouter($device_age);
                        
                        // Log the rule application
                        $log_insert = "INSERT INTO age_filter_logs (device_id, device_age, applied_at, rules_count) 
                                      VALUES ($device_id, $device_age, NOW(), " . 
                                      (count($rules['blacklist']) + count($rules['whitelist'])) . ")";
                        $conn->query($log_insert);
                        
                        $applied_count++;
                    } catch (Exception $e) {
                        $skipped_count++;
                        $errors[] = "Device {$device['name']}: " . $e->getMessage();
                    }
                }
                
                $response['success'] = true;
                $response['message'] = "Age-based filters applied to $applied_count devices" . 
                                     ($skipped_count > 0 ? ", $skipped_count skipped" : "");
                $response['applied_count'] = $applied_count;
                $response['skipped_count'] = $skipped_count;
                $response['errors'] = $errors;
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Create age filter logs table if it doesn't exist
$create_logs = "CREATE TABLE IF NOT EXISTS age_filter_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    device_age INT NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rules_count INT DEFAULT 0,
    INDEX idx_device_id (device_id),
    INDEX idx_applied_at (applied_at),
    FOREIGN KEY (device_id) REFERENCES device(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$conn->query($create_logs);

// Return available endpoints for GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $endpoints = [
        'get_device_age_rules' => 'Get age-based rules for a specific device',
        'apply_age_filters_to_device' => 'Apply age-based filters to a specific device',
        'get_all_devices_age_status' => 'Get age-based filter status for all devices',
        'bulk_apply_age_filters' => 'Apply age-based filters to all devices with valid ages'
    ];
    
    header('Content-Type: application/json');
    echo json_encode([
        'service' => 'Device Age-Based Filter Enforcement',
        'available_endpoints' => $endpoints,
        'method' => 'POST',
        'parameters' => [
            'action' => 'One of the available endpoints',
            'device_id' => 'Required for device-specific actions'
        ]
    ]);
}
?>
