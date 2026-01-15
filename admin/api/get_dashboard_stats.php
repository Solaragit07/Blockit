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
    
    $stats = [];
    
    // Get total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get premium users
    $stmt = $pdo->query("SELECT COUNT(*) as premium FROM users WHERE subscription_type = 'premium' AND subscription_status = 'active'");
    $stats['premium_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['premium'];
    
    // Calculate monthly revenue (premium users * $9.99)
    $monthlyRevenue = $stats['premium_users'] * 9.99;
    $stats['monthly_revenue'] = '$' . number_format($monthlyRevenue, 0);
    
    // Get active devices (devices that have been active in last 24 hours)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT device_id) as active FROM device_usage WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['active_devices'] = $result ? $result['active'] : 0;
    
    // Get blocked requests today
    $stmt = $pdo->query("SELECT COUNT(*) as blocked FROM blocking_logs WHERE DATE(created_at) = CURDATE() AND action = 'blocked'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['blocked_today'] = $result ? $result['blocked'] : 0;
    
    // Get total requests today
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM blocking_logs WHERE DATE(created_at) = CURDATE()");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_requests'] = $result ? $result['total'] : 0;
    
    // Get blocked requests today
    $stmt = $pdo->query("SELECT COUNT(*) as blocked FROM blocking_logs WHERE DATE(created_at) = CURDATE() AND action = 'blocked'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['blocked_requests'] = $result ? $result['blocked'] : 0;
    
    // Calculate allowed requests
    $stats['allowed_requests'] = $stats['total_requests'] - $stats['blocked_requests'];
    
    // Get new users today
    $stmt = $pdo->query("SELECT COUNT(*) as new_users FROM users WHERE DATE(created_at) = CURDATE()");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['new_users'] = $result ? $result['new_users'] : 0;
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
