<?php
include '../../connectMySql.php';
include '../../loginverification.php';
include '../../includes/api_helper.php';

header('Content-Type: application/json');

if (!logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'delete':
            deleteDevice();
            break;
        case 'block_all':
            blockAllDevices();
            break;
        case 'unblock_all':
            unblockAllDevices();
            break;
        case 'update_bandwidth':
            updateBandwidth();
            break;
        default:
            saveDevice();
            break;
    }
}

function saveDevice() {
    global $conn;
    
    $name = $_POST['name'] ?? '';
    $mac = $_POST['mac'] ?? '';
    $timelimit = $_POST['timelimit'] ?? 8;
    $internet = $_POST['internet'] ?? 'No';
    $bandwidth = $_POST['bandwidth'] ?? 3;
    $customDownload = $_POST['customDownload'] ?? '';
    $customUpload = $_POST['customUpload'] ?? '';
    $isEdit = $_POST['isEdit'] ?? false;
    $originalMAC = $_POST['originalMAC'] ?? '';
    
    if (empty($name) || empty($mac)) {
        echo json_encode(['success' => false, 'message' => 'Name and MAC address are required']);
        return;
    }
    
    // Validate MAC address format
    if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac)) {
        echo json_encode(['success' => false, 'message' => 'Invalid MAC address format']);
        return;
    }
    
    if ($isEdit) {
        // Update existing device
        $query = "UPDATE device SET name = ?, mac_address = ?, timelimit = ?, internet = ?, bandwidth = ? WHERE mac_address = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssisss", $name, $mac, $timelimit, $internet, $bandwidth, $originalMAC);
    } else {
        // Check if device already exists
        $checkQuery = "SELECT id FROM device WHERE mac_address = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("s", $mac);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Device with this MAC address already exists']);
            return;
        }
        
        // Insert new device
        $query = "INSERT INTO device (name, mac_address, timelimit, internet, bandwidth) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssisi", $name, $mac, $timelimit, $internet, $bandwidth);
    }
    
    if ($stmt->execute()) {
        // Update RouterOS settings
        $routerSuccess = updateRouterSettings($mac, $bandwidth, $internet, $timelimit);
        
        if ($routerSuccess) {
            echo json_encode(['success' => true, 'message' => 'Device saved successfully']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Device saved but router update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
}

function deleteDevice() {
    global $conn;
    
    $mac = $_POST['mac'] ?? '';
    
    if (empty($mac)) {
        echo json_encode(['success' => false, 'message' => 'MAC address is required']);
        return;
    }
    
    // Delete from database
    $query = "DELETE FROM device WHERE mac_address = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $mac);
    
    if ($stmt->execute()) {
        // Remove from RouterOS
        removeFromRouter($mac);
        echo json_encode(['success' => true, 'message' => 'Device deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete device']);
    }
}

function blockAllDevices() {
    global $conn;
    
    // Update all devices to blocked
    $query = "UPDATE device SET internet = 'Yes'";
    
    if ($conn->query($query)) {
        // Update RouterOS for all devices
        $devicesQuery = "SELECT mac_address FROM device";
        $result = $conn->query($devicesQuery);
        
        while ($row = $result->fetch_assoc()) {
            blockDeviceInRouter($row['mac_address']);
        }
        
        echo json_encode(['success' => true, 'message' => 'All devices blocked successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to block all devices']);
    }
}

function unblockAllDevices() {
    global $conn;
    
    // Update all devices to unblocked
    $query = "UPDATE device SET internet = 'No'";
    
    if ($conn->query($query)) {
        // Update RouterOS for all devices
        $devicesQuery = "SELECT mac_address FROM device";
        $result = $conn->query($devicesQuery);
        
        while ($row = $result->fetch_assoc()) {
            unblockDeviceInRouter($row['mac_address']);
        }
        
        echo json_encode(['success' => true, 'message' => 'All devices unblocked successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to unblock all devices']);
    }
}

function updateRouterSettings($mac, $bandwidth, $internet, $timelimit) {
    include_once('../../API/connectMikrotik.php');
    
    if (!$client->connect()) {
        return false;
    }
    
    try {
        // Update bandwidth limit
        $bandwidthValue = $bandwidth == 1 ? '1M' : ($bandwidth == 2 ? '5M' : '10M');
        
        // Create or update queue
        $queueName = "queue_" . str_replace(":", "", $mac);
        $target = $mac . "/32";
        
        // Remove existing queue if it exists
        $existingQueues = $client->query('/queue/simple/print', [
            '?name' => $queueName
        ]);
        
        foreach ($existingQueues as $queue) {
            $client->query('/queue/simple/remove', [
                '.id' => $queue['.id']
            ]);
        }
        
        // Add new queue
        $client->query('/queue/simple/add', [
            'name' => $queueName,
            'target' => $target,
            'max-limit' => $bandwidthValue . '/' . $bandwidthValue
        ]);
        
        // Handle internet blocking
        if ($internet == 'Yes') {
            blockDeviceInRouter($mac);
        } else {
            unblockDeviceInRouter($mac);
        }
        
        // Handle time limit (create scheduler)
        if ($timelimit > 0) {
            $schedulerName = "schedule_" . str_replace(":", "", $mac);
            
            // Remove existing scheduler
            $existingSchedulers = $client->query('/system/scheduler/print', [
                '?name' => $schedulerName
            ]);
            
            foreach ($existingSchedulers as $scheduler) {
                $client->query('/system/scheduler/remove', [
                    '.id' => $scheduler['.id']
                ]);
            }
            
            // Create new scheduler
            $currentTime = new DateTime();
            $endTime = clone $currentTime;
            $endTime->add(new DateInterval("PT{$timelimit}H"));
            
            $client->query('/system/scheduler/add', [
                'name' => $schedulerName,
                'start-time' => $endTime->format('H:i:s'),
                'start-date' => $endTime->format('M/d/Y'),
                'on-event' => "/ip firewall address-list add list=blocked_devices address=$mac"
            ]);
        }
        
        $client->disconnect();
        return true;
        
    } catch (Exception $e) {
        $client->disconnect();
        return false;
    }
}

function blockDeviceInRouter($mac) {
    include_once('../../API/connectMikrotik.php');
    
    if ($client->connect()) {
        try {
            $client->query('/ip/firewall/address-list/add', [
                'list' => 'blocked_devices',
                'address' => $mac
            ]);
            $client->disconnect();
        } catch (Exception $e) {
            $client->disconnect();
        }
    }
}

function unblockDeviceInRouter($mac) {
    include_once('../../API/connectMikrotik.php');
    
    if ($client->connect()) {
        try {
            $blockedDevices = $client->query('/ip/firewall/address-list/print', [
                '?list' => 'blocked_devices',
                '?address' => $mac
            ]);
            
            foreach ($blockedDevices as $device) {
                $client->query('/ip/firewall/address-list/remove', [
                    '.id' => $device['.id']
                ]);
            }
            $client->disconnect();
        } catch (Exception $e) {
            $client->disconnect();
        }
    }
}

function removeFromRouter($mac) {
    include_once('../../API/connectMikrotik.php');
    
    if ($client->connect()) {
        try {
            // Remove from blocked devices
            unblockDeviceInRouter($mac);
            
            // Remove queue
            $queueName = "queue_" . str_replace(":", "", $mac);
            $existingQueues = $client->query('/queue/simple/print', [
                '?name' => $queueName
            ]);
            
            foreach ($existingQueues as $queue) {
                $client->query('/queue/simple/remove', [
                    '.id' => $queue['.id']
                ]);
            }
            
            // Remove scheduler
            $schedulerName = "schedule_" . str_replace(":", "", $mac);
            $existingSchedulers = $client->query('/system/scheduler/print', [
                '?name' => $schedulerName
            ]);
            
            foreach ($existingSchedulers as $scheduler) {
                $client->query('/system/scheduler/remove', [
                    '.id' => $scheduler['.id']
                ]);
            }
            
            $client->disconnect();
        } catch (Exception $e) {
            $client->disconnect();
        }
    }
}

function updateBandwidth() {
    global $conn;
    
    $deviceId = $_POST['device_id'] ?? '';
    $bandwidth = $_POST['bandwidth'] ?? '';
    
    if (empty($deviceId) || empty($bandwidth)) {
        echo json_encode(['success' => false, 'message' => 'Device ID and bandwidth are required']);
        return;
    }
    
    // Get device MAC address
    $query = "SELECT mac_address FROM device WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $deviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Device not found']);
        return;
    }
    
    $device = $result->fetch_assoc();
    $macAddress = $device['mac_address'];
    
    // Convert bandwidth value
    $bandwidthMapping = [
        '1M' => 1,
        '2M' => 2, 
        '5M' => 5,
        '10M' => 10,
        '20M' => 20,
        'unlimited' => 0
    ];
    
    $bandwidthValue = isset($bandwidthMapping[$bandwidth]) ? $bandwidthMapping[$bandwidth] : 3;
    
    // Update database
    $updateQuery = "UPDATE device SET bandwidth = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ii", $bandwidthValue, $deviceId);
    
    if ($updateStmt->execute()) {
        // Update RouterOS queue
        $routerSuccess = updateRouterBandwidth($macAddress, $bandwidth);
        
        if ($routerSuccess) {
            echo json_encode(['success' => true, 'message' => 'Bandwidth limit updated successfully']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Database updated but router update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update database']);
    }
}

function updateRouterBandwidth($macAddress, $bandwidth) {
    try {
        include_once('../../API/connectMikrotik.php');
        
        if (!$client) {
            return false;
        }
        
        // Remove existing queue
        $queueName = "queue_" . str_replace(":", "", $macAddress);
        $existingQueues = $client->query((new RouterOS\Query('/queue/simple/print'))
            ->where('name', $queueName))->read();
        
        foreach ($existingQueues as $queue) {
            $client->query((new RouterOS\Query('/queue/simple/remove'))
                ->equal('.id', $queue['.id']))->read();
        }
        
        // Add new queue if not unlimited
        if ($bandwidth !== 'unlimited') {
            $target = $macAddress;
            $maxLimit = $bandwidth . '/' . $bandwidth;
            
            $client->query((new RouterOS\Query('/queue/simple/add'))
                ->equal('name', $queueName)
                ->equal('target', $target)
                ->equal('max-limit', $maxLimit))->read();
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Router bandwidth update error: " . $e->getMessage());
        return false;
    }
}
?>
