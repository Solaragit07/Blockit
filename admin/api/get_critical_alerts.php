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
    
    $alerts = [];
    
    // Check for critical system issues
    
    // 1. Check for excessive failed login attempts
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as failed_attempts 
        FROM login_logs 
        WHERE status = 'failed' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute();
    $failedLogins = $stmt->fetch(PDO::FETCH_ASSOC)['failed_attempts'];
    
    if ($failedLogins > 10) {
        $alerts[] = [
            'title' => 'High Failed Login Attempts',
            'message' => "Detected {$failedLogins} failed login attempts in the last hour. Possible brute force attack.",
            'severity' => 'high',
            'time' => date('H:i')
        ];
    }
    
    // 2. Check for database connection issues
    try {
        $stmt = $pdo->query("SELECT 1");
    } catch (Exception $e) {
        $alerts[] = [
            'title' => 'Database Connection Issue',
            'message' => 'Database connection unstable or experiencing issues.',
            'severity' => 'critical',
            'time' => date('H:i')
        ];
    }
    
    // 3. Check for expired premium subscriptions
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as expired 
        FROM users 
        WHERE subscription_type = 'premium' 
        AND subscription_end_date < NOW() 
        AND subscription_status = 'active'
    ");
    $stmt->execute();
    $expiredSubs = $stmt->fetch(PDO::FETCH_ASSOC)['expired'];
    
    if ($expiredSubs > 0) {
        $alerts[] = [
            'title' => 'Expired Premium Subscriptions',
            'message' => "{$expiredSubs} premium subscriptions have expired but are still marked as active.",
            'severity' => 'medium',
            'time' => date('H:i')
        ];
    }
    
    // 4. Check for excessive blocking (possible false positives)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as blocked_count 
        FROM blocking_logs 
        WHERE action = 'blocked' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute();
    $blockedCount = $stmt->fetch(PDO::FETCH_ASSOC)['blocked_count'];
    
    if ($blockedCount > 1000) {
        $alerts[] = [
            'title' => 'High Blocking Activity',
            'message' => "Unusual high blocking activity: {$blockedCount} blocks in the last hour. Check for false positives.",
            'severity' => 'medium',
            'time' => date('H:i')
        ];
    }
    
    // 5. Check for unprocessed notifications
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending 
        FROM admin_notifications 
        WHERE status = 'pending' 
        AND created_at <= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");
    $stmt->execute();
    $pendingNotifications = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
    
    if ($pendingNotifications > 5) {
        $alerts[] = [
            'title' => 'Unprocessed Notifications',
            'message' => "{$pendingNotifications} notifications have been pending for over 30 minutes.",
            'severity' => 'low',
            'time' => date('H:i')
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $alerts
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading critical alerts: ' . $e->getMessage()
    ]);
}
?>
