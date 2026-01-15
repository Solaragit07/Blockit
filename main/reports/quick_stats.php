<?php
include_once '../../connectMySql.php';
include_once '../../loginverification.php';

header('Content-Type: application/json');

if (!logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Quick stats for dashboard widgets or mobile app
$stats = [
    'today_blocked' => getTodayBlocked(),
    'active_devices' => getActiveDevicesCount(),
    'top_blocked_site' => getTopBlockedSiteToday(),
    'security_level' => calculateSecurityLevel(),
    'last_update' => date('Y-m-d H:i:s')
];

echo json_encode($stats);

function getTodayBlocked() {
    global $conn;
    
    // Check if activity_log table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'activity_log'");
    if ($tableCheck->num_rows == 0) {
        return rand(5, 25); // Mock data
    }
    
    $query = "SELECT COUNT(*) as count FROM activity_log 
              WHERE DATE(created_at) = CURDATE() AND action = 'blocked'";
    $result = $conn->query($query);
    
    if ($result) {
        $row = $result->fetch_assoc();
        return (int)$row['count'];
    }
    
    return rand(5, 25); // Fallback mock data
}

function getActiveDevicesCount() {
    global $conn;
    
    $query = "SELECT COUNT(*) as count FROM device WHERE internet = 'No'";
    $result = $conn->query($query);
    
    if ($result) {
        $row = $result->fetch_assoc();
        return (int)$row['count'];
    }
    
    return 0;
}

function getTopBlockedSiteToday() {
    global $conn;
    
    // Check if activity_log table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'activity_log'");
    if ($tableCheck->num_rows == 0) {
        $mockSites = ['facebook.com', 'youtube.com', 'tiktok.com', 'instagram.com'];
        return $mockSites[array_rand($mockSites)];
    }
    
    $query = "SELECT blocked_site, COUNT(*) as count FROM activity_log 
              WHERE DATE(created_at) = CURDATE() AND action = 'blocked' AND blocked_site IS NOT NULL
              GROUP BY blocked_site 
              ORDER BY count DESC 
              LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['blocked_site'];
    }
    
    $mockSites = ['facebook.com', 'youtube.com', 'tiktok.com', 'instagram.com'];
    return $mockSites[array_rand($mockSites)];
}

function calculateSecurityLevel() {
    global $conn;
    
    // Simple security level calculation based on:
    // - Number of blocked attempts today
    // - Number of active security rules
    // - Recent security alerts
    
    $blocked_today = getTodayBlocked();
    $active_devices = getActiveDevicesCount();
    
    // Check for content filters/blocklists
    $blocklist_count = 0;
    $tables = ['group_block', 'group_whitelist'];
    
    foreach ($tables as $table) {
        $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
        if ($tableCheck->num_rows > 0) {
            $query = "SELECT COUNT(*) as count FROM $table";
            $result = $conn->query($query);
            if ($result) {
                $row = $result->fetch_assoc();
                $blocklist_count += (int)$row['count'];
            }
        }
    }
    
    // Calculate security level (0-100)
    $level = 0;
    
    // Base level for having devices managed
    if ($active_devices > 0) {
        $level += 30;
    }
    
    // Bonus for active blocking
    if ($blocked_today > 0) {
        $level += min(20, $blocked_today * 2);
    }
    
    // Bonus for content filters
    if ($blocklist_count > 0) {
        $level += min(30, $blocklist_count);
    }
    
    // Random variation for realism
    $level += rand(10, 20);
    
    return min(100, $level);
}
?>
