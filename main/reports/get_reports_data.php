<?php
include_once '../../connectMySql.php';
include_once '../../loginverification.php';
include_once 'reports_functions.php';

header('Content-Type: application/json');

if (!logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_devices':
            getDevices();
            break;
        case 'get_all_reports':
            getAllReports();
            break;
        case 'get_blocking_events':
            getBlockingEvents();
            break;
        case 'get_usage_stats':
            getUsageStats();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function getDevices() {
    global $conn;
    
    // Check which device name column exists
    $deviceColumns = $conn->query("SHOW COLUMNS FROM device");
    $deviceNameColumn = 'device_name'; // Default
    $hasDeviceName = false;
    $hasName = false;
    
    if ($deviceColumns) {
        while ($col = $deviceColumns->fetch_assoc()) {
            if ($col['Field'] == 'device_name') $hasDeviceName = true;
            if ($col['Field'] == 'name') $hasName = true;
        }
    }
    
    // Use appropriate column name
    if ($hasName) {
        $deviceNameColumn = 'name';
    } elseif ($hasDeviceName) {
        $deviceNameColumn = 'device_name';
    }
    
    $query = "SELECT id, $deviceNameColumn as name, mac_address FROM device ORDER BY $deviceNameColumn";
    $result = $conn->query($query);
    
    $devices = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $devices[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'devices' => $devices]);
}

function getAllReports() {
    global $conn;
    
    $dateRange = $_GET['dateRange'] ?? '7days';
    $deviceFilter = $_GET['device'] ?? 'all';
    $reportType = $_GET['reportType'] ?? 'all';
    
    // Calculate date range
    $dateCondition = getDateCondition($dateRange, 'l');
    
    // Build device condition - convert device ID to MAC address if needed
    $deviceCondition = '';
    if ($deviceFilter !== 'all') {
        // Get MAC address for the device ID
        $deviceQuery = "SELECT mac_address FROM device WHERE id = '$deviceFilter'";
        $deviceResult = $conn->query($deviceQuery);
        if ($deviceResult && $deviceRow = $deviceResult->fetch_assoc()) {
            $deviceCondition = "AND d.mac_address = '{$deviceRow['mac_address']}'";
        }
    }
    
    $data = [
        'overview' => getOverviewStats($dateCondition, $deviceCondition),
        'blockingChart' => getBlockingChartData($dateCondition, $deviceCondition),
        'deviceUsage' => getDeviceUsageData($dateCondition, $deviceCondition),
        'blockingEvents' => getRecentBlockingEvents($dateCondition, $deviceCondition),
        'topBlocked' => getTopBlockedSites($dateCondition, $deviceCondition),
        'usageStats' => getDetailedUsageStats($dateCondition, $deviceCondition)
    ];
    
    echo json_encode(['success' => true, 'data' => $data]);
}

function createActivityLogTable() {
    global $conn;
    
    $query = "CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id INT,
        action VARCHAR(50),
        blocked_site VARCHAR(255),
        category VARCHAR(100),
        data_usage DECIMAL(10,2) DEFAULT 0,
        session_duration INT DEFAULT 0,
        severity VARCHAR(20) DEFAULT 'low',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_device_id (device_id),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (device_id) REFERENCES device(id) ON DELETE CASCADE
    )";
    
    $conn->query($query);
    
    // Insert some sample data if table is empty
    $countQuery = "SELECT COUNT(*) as count FROM activity_log";
    $result = $conn->query($countQuery);
    $count = $result->fetch_assoc()['count'];
    
    if ($count == 0) {
        insertSampleData();
    }
}

function insertSampleData() {
    global $conn;
    
    // Get device IDs
    $deviceQuery = "SELECT id FROM device LIMIT 5";
    $deviceResult = $conn->query($deviceQuery);
    $deviceIds = [];
    
    while ($row = $deviceResult->fetch_assoc()) {
        $deviceIds[] = $row['id'];
    }
    
    if (empty($deviceIds)) {
        return; // No devices to create sample data for
    }
    
    $sampleData = [
        ['facebook.com', 'Social Media'],
        ['youtube.com', 'Entertainment'],
        ['tiktok.com', 'Social Media'],
        ['instagram.com', 'Social Media'],
        ['twitter.com', 'Social Media'],
        ['reddit.com', 'Forum'],
        ['twitch.tv', 'Gaming'],
        ['netflix.com', 'Entertainment']
    ];
    
    // Insert sample blocking events for the last 7 days
    for ($day = 0; $day < 7; $day++) {
        for ($i = 0; $i < rand(5, 15); $i++) {
            $deviceId = $deviceIds[array_rand($deviceIds)];
            $site = $sampleData[array_rand($sampleData)];
            $timestamp = date('Y-m-d H:i:s', strtotime("-$day days -" . rand(1, 1440) . " minutes"));
            
            $query = "INSERT INTO activity_log (device_id, action, blocked_site, category, data_usage, session_duration, severity, created_at) 
                      VALUES (?, 'blocked', ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query);
            $dataUsage = rand(10, 100) / 10;
            $sessionDuration = rand(60, 3600);
            $severity = rand(1, 10) > 8 ? 'high' : 'low';
            
            $stmt->bind_param("issfiss", $deviceId, $site[0], $site[1], $dataUsage, $sessionDuration, $severity, $timestamp);
            $stmt->execute();
        }
    }
}

function getBlockingEvents() {
    global $conn;
    $dateCondition = getDateCondition($_GET['dateRange'] ?? '7days', 'l');
    
    // Convert device ID to MAC address if needed
    $deviceCondition = '';
    if ($_GET['device'] !== 'all') {
        $deviceQuery = "SELECT mac_address FROM device WHERE id = '{$_GET['device']}'";
        $deviceResult = $conn->query($deviceQuery);
        if ($deviceResult && $deviceRow = $deviceResult->fetch_assoc()) {
            $deviceCondition = "AND d.mac_address = '{$deviceRow['mac_address']}'";
        }
    }
    
    $events = getRecentBlockingEvents($dateCondition, $deviceCondition);
    echo json_encode(['success' => true, 'events' => $events]);
}

function getUsageStats() {
    global $conn;
    $dateCondition = getDateCondition($_GET['dateRange'] ?? '7days', 'l');
    
    // Convert device ID to MAC address if needed
    $deviceCondition = '';
    if ($_GET['device'] !== 'all') {
        $deviceQuery = "SELECT mac_address FROM device WHERE id = '{$_GET['device']}'";
        $deviceResult = $conn->query($deviceQuery);
        if ($deviceResult && $deviceRow = $deviceResult->fetch_assoc()) {
            $deviceCondition = "AND d.mac_address = '{$deviceRow['mac_address']}'";
        }
    }
    
    $stats = getDetailedUsageStats($dateCondition, $deviceCondition);
    echo json_encode(['success' => true, 'stats' => $stats]);
}
?>
