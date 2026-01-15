<?php
include_once '../../connectMySql.php';
include_once '../../loginverification.php';

function getDateCondition($dateRange, $tableAlias = 'l') {
    switch ($dateRange) {
        case 'today':
            return "DATE($tableAlias.date) = CURDATE()";
        case 'yesterday':
            return "DATE($tableAlias.date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        case '7days':
            return "$tableAlias.date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        case '30days':
            return "$tableAlias.date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        case 'custom':
            $startDate = $_GET['startDate'] ?? date('Y-m-d', strtotime('-7 days'));
            $endDate = $_GET['endDate'] ?? date('Y-m-d');
            return "DATE($tableAlias.date) BETWEEN '$startDate' AND '$endDate'";
        default:
            return "$tableAlias.date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    }
}

function getOverviewStats($dateCondition, $deviceCondition) {
    global $conn;
    
    // Check if logs table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'logs'");
    $hasLogs = $tableCheck->num_rows > 0;
    
    if ($hasLogs) {
        // The logs table has 'date' column based on our check
        $dateColumn = 'date';
        
        // Modify dateCondition to use correct column
        $modifiedDateCondition = str_replace('l.date', "l.$dateColumn", $dateCondition);
        
        // Get total blocked attempts
        $query = "SELECT COUNT(*) as count FROM logs l 
                  LEFT JOIN device d ON l.device_id = d.id 
                  WHERE l.type = 'blocked' AND $modifiedDateCondition $deviceCondition";
        $result = $conn->query($query);
        $totalBlocked = $result ? $result->fetch_assoc()['count'] : 0;
        
        // Get security alerts (redirects and other actions)
        $query = "SELECT COUNT(*) as count FROM logs l 
                  LEFT JOIN device d ON l.device_id = d.id 
                  WHERE l.type IN ('redirected', 'allowed') AND $modifiedDateCondition $deviceCondition";
        $result = $conn->query($query);
        $securityAlerts = $result ? $result->fetch_assoc()['count'] : 0;
    } else {
        // Mock data if no logs
        $totalBlocked = rand(50, 200);
        $securityAlerts = rand(0, 5);
    }
    
    // Get active devices count
    $query = "SELECT COUNT(*) as count FROM device";
    $result = $conn->query($query);
    $activeDevices = $result ? $result->fetch_assoc()['count'] : 0;
    
    // Mock data usage (would come from router in real implementation)
    $dataUsage = rand(500, 2000);
    
    return [
        'totalBlocked' => $totalBlocked,
        'activeDevices' => $activeDevices,
        'dataUsage' => $dataUsage,
        'securityAlerts' => $securityAlerts
    ];
}

function getRecentBlockingEvents($dateCondition, $deviceCondition, $limit = 50) {
    global $conn;
    
    // Check if logs table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'logs'");
    
    if ($tableCheck->num_rows > 0) {
        // The logs table has 'date' column based on our check
        $dateColumn = 'date';
        
        // Modify dateCondition to use correct column
        $modifiedDateCondition = str_replace('l.date', "l.$dateColumn", $dateCondition);
        
        $query = "SELECT 
                    DATE_FORMAT(l.$dateColumn, '%H:%i:%s') as time,
                    COALESCE(d.device_name, 'Unknown Device') as device,
                    COALESCE(l.domain, 'N/A') as blockedSite,
                    COALESCE(l.type, 'General') as category
                  FROM logs l 
                  LEFT JOIN device d ON l.device_id = d.id 
                  WHERE l.type = 'blocked' AND $modifiedDateCondition $deviceCondition
                  ORDER BY l.$dateColumn DESC 
                  LIMIT $limit";
        
        $result = $conn->query($query);
        $events = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $events[] = [
                    'time' => $row['time'],
                    'device' => $row['device'],
                    'blockedSite' => $row['blockedSite'],
                    'category' => $row['category']
                ];
            }
        }
        
        return $events;
    } else {
        // Generate mock data
        $events = [];
        $sites = ['facebook.com', 'twitter.com', 'instagram.com', 'tiktok.com', 'youtube.com'];
        $categories = ['Social Media', 'Entertainment', 'Gaming', 'Adult Content'];
        
        for ($i = 0; $i < 20; $i++) {
            $events[] = [
                'time' => date('H:i:s', strtotime("-{$i} hours")),
                'device' => 'Device ' . rand(1, 5),
                'blockedSite' => $sites[array_rand($sites)],
                'category' => $categories[array_rand($categories)]
            ];
        }
        
        return $events;
    }
}

function getTopBlockedSites($dateCondition, $deviceCondition, $limit = 20) {
    global $conn;
    
    // Check if logs table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'logs'");
    
    if ($tableCheck->num_rows > 0) {
        // The logs table has 'date' column based on our check
        $dateColumn = 'date';
        
        // Modify dateCondition to use correct column
        $modifiedDateCondition = str_replace('l.date', "l.$dateColumn", $dateCondition);
        
        $query = "SELECT 
                    COALESCE(l.domain, 'N/A') as site,
                    COUNT(*) as attempts,
                    COALESCE(l.type, 'General') as category,
                    MAX(DATE_FORMAT(l.$dateColumn, '%Y-%m-%d %H:%i:%s')) as lastAttempt
                  FROM logs l 
                  LEFT JOIN device d ON l.device_id = d.id 
                  WHERE l.type = 'blocked' AND $modifiedDateCondition $deviceCondition
                  GROUP BY l.domain, l.type
                  ORDER BY attempts DESC 
                  LIMIT $limit";
        
        $result = $conn->query($query);
        $sites = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $sites[] = $row;
            }
        }
        
        return $sites;
    } else {
        // Generate mock data
        $sites = [
            ['site' => 'facebook.com', 'attempts' => rand(50, 150), 'category' => 'Social Media', 'lastAttempt' => date('Y-m-d H:i:s')],
            ['site' => 'instagram.com', 'attempts' => rand(30, 100), 'category' => 'Social Media', 'lastAttempt' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
            ['site' => 'tiktok.com', 'attempts' => rand(40, 120), 'category' => 'Entertainment', 'lastAttempt' => date('Y-m-d H:i:s', strtotime('-2 hours'))],
            ['site' => 'youtube.com', 'attempts' => rand(20, 80), 'category' => 'Entertainment', 'lastAttempt' => date('Y-m-d H:i:s', strtotime('-3 hours'))],
            ['site' => 'twitter.com', 'attempts' => rand(25, 75), 'category' => 'Social Media', 'lastAttempt' => date('Y-m-d H:i:s', strtotime('-4 hours'))],
        ];
        
        return $sites;
    }
}

function getDetailedUsageStats($dateCondition, $deviceCondition) {
    global $conn;
    
    // Device table has device_name column based on our check
    $deviceNameColumn = 'device_name';
    
    // Get devices from database  
    $query = "SELECT id, $deviceNameColumn as name, mac_address FROM device ORDER BY $deviceNameColumn";
    $result = $conn->query($query);
    $stats = [];
    
    if ($result) {
        while ($device = $result->fetch_assoc()) {
            // Check if logs exists for this device
            $tableCheck = $conn->query("SHOW TABLES LIKE 'logs'");
            
            if ($tableCheck->num_rows > 0) {
                // The logs table has 'date' column based on our check
                $dateColumn = 'date';
                
                // Modify dateCondition to use correct column
                $modifiedDateCondition = str_replace('l.date', "l.$dateColumn", $dateCondition);
                
                // Get blocked attempts for this device using device_id
                $query2 = "SELECT COUNT(*) as count FROM logs l
                         WHERE l.device_id = '{$device['id']}' AND l.type = 'blocked' AND $modifiedDateCondition";
                $result2 = $conn->query($query2);
                $blockedAttempts = $result2 ? $result2->fetch_assoc()['count'] : 0;
            } else {
                $blockedAttempts = rand(5, 25);
            }
            
            $stats[] = [
                'device' => $device['name'],
                'totalUsage' => rand(100, 1000), // Mock data - would come from router
                'blockedAttempts' => $blockedAttempts,
                'activeHours' => rand(2, 12),
                'lastActivity' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 24) . ' hours')),
                'status' => rand(0, 1) ? 'active' : 'inactive'
            ];
        }
    }
    
    return $stats;
}

function getBlockingChartData($dateCondition, $deviceCondition) {
    global $conn;
    
    // Generate data for the last 7 days
    $labels = [];
    $data = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = $date;
        
        // Check if logs table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'logs'");
        
        if ($tableCheck->num_rows > 0) {
            // The logs table has 'date' column based on our check
            $dateColumn = 'date';
            
            $query = "SELECT COUNT(*) as count FROM logs l 
                      LEFT JOIN device d ON l.device_id = d.id 
                      WHERE l.type = 'blocked' AND DATE(l.$dateColumn) = '$date' $deviceCondition";
            $result = $conn->query($query);
            $count = $result ? $result->fetch_assoc()['count'] : 0;
        } else {
            $count = rand(10, 50);
        }
        
        $data[] = $count;
    }
    
    return [
        'labels' => $labels,
        'data' => $data
    ];
}

function getDeviceUsageDistribution() {
    global $conn;
    
    // This would typically analyze device types from the database
    // For now, returning mock data
    return [
        rand(40, 60), // Mobile devices
        rand(20, 40), // Computers  
        rand(10, 20)  // Others
    ];
}

function getDeviceUsageData($dateCondition, $deviceCondition) {
    global $conn;
    
    // Device table has device_name column based on our check
    $deviceNameColumn = 'd.device_name';
    
    // Simulated device type distribution
    $query = "SELECT 
                CASE 
                    WHEN $deviceNameColumn LIKE '%phone%' OR $deviceNameColumn LIKE '%mobile%' OR $deviceNameColumn LIKE '%iphone%' OR $deviceNameColumn LIKE '%android%' THEN 'Mobile'
                    WHEN $deviceNameColumn LIKE '%computer%' OR $deviceNameColumn LIKE '%pc%' OR $deviceNameColumn LIKE '%laptop%' OR $deviceNameColumn LIKE '%desktop%' THEN 'Computer'
                    ELSE 'Other'
                END as device_type,
                COUNT(*) as count
              FROM device d
              LEFT JOIN activity_log al ON d.id = al.device_id
              WHERE d.id IS NOT NULL
              GROUP BY device_type";
    
    $result = $conn->query($query);
    
    $mobile = 0;
    $computer = 0;
    $other = 0;
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            switch ($row['device_type']) {
                case 'Mobile':
                    $mobile = (int)$row['count'];
                    break;
                case 'Computer':
                    $computer = (int)$row['count'];
                    break;
                default:
                    $other = (int)$row['count'];
                    break;
            }
        }
    }
    
    // If no data, provide mock data
    if ($mobile === 0 && $computer === 0 && $other === 0) {
        $mobile = 15;
        $computer = 8;
        $other = 5;
    }
    
    return [$mobile, $computer, $other];
}
?>
