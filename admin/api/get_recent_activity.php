<?php
session_start();
header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../connectMySql.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $activities = [];
    
    // Get recent user registrations
    $stmt = $pdo->prepare("
        SELECT 
            'User Registration' as action,
            username as user,
            'success' as status,
            created_at as timestamp,
            '127.0.0.1' as ip_address
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($registrations as $reg) {
        $activities[] = [
            'time' => date('H:i', strtotime($reg['timestamp'])),
            'user' => $reg['user'],
            'action' => $reg['action'],
            'status' => $reg['status'],
            'ip_address' => $reg['ip_address']
        ];
    }
    
    // Get recent login attempts
    $stmt = $pdo->prepare("
        SELECT 
            'Login Attempt' as action,
            username as user,
            status,
            created_at as timestamp,
            ip_address
        FROM login_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $logins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($logins as $login) {
        $activities[] = [
            'time' => date('H:i', strtotime($login['timestamp'])),
            'user' => $login['user'],
            'action' => $login['action'],
            'status' => $login['status'] === 'success' ? 'success' : 'danger',
            'ip_address' => $login['ip_address'] ?: '127.0.0.1'
        ];
    }
    
    // Get recent subscription changes
    $stmt = $pdo->prepare("
        SELECT 
            'Subscription Update' as action,
            username as user,
            'info' as status,
            updated_at as timestamp,
            '127.0.0.1' as ip_address
        FROM users 
        WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND subscription_type IS NOT NULL
        ORDER BY updated_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($subscriptions as $sub) {
        $activities[] = [
            'time' => date('H:i', strtotime($sub['timestamp'])),
            'user' => $sub['user'],
            'action' => $sub['action'],
            'status' => $sub['status'],
            'ip_address' => $sub['ip_address']
        ];
    }
    
    // Sort all activities by timestamp
    usort($activities, function($a, $b) {
        return strcmp($b['time'], $a['time']);
    });
    
    // Limit to latest 15 activities
    $activities = array_slice($activities, 0, 15);
    
    echo json_encode([
        'success' => true,
        'data' => $activities
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading recent activity: ' . $e->getMessage()
    ]);
}
?>
