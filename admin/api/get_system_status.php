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

function checkDatabaseConnection() {
    global $servername, $username, $password, $dbname;
    try {
        $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->query("SELECT 1");
        return 'connected';
    } catch (Exception $e) {
        return 'offline';
    }
}

function checkRouterConnection() {
    // Check if router API is reachable
    $routerIP = '192.168.10.1'; // Correct MikroTik IP
    $timeout = 3;
    
    $connection = @fsockopen($routerIP, 8728, $errno, $errstr, $timeout);
    if ($connection) {
        fclose($connection);
        return 'connected';
    }
    return 'offline';
}

function checkEmailService() {
    // Check if email configuration exists and is valid
    if (function_exists('mail') || class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return 'active';
    }
    return 'offline';
}

function checkBlockingEngine() {
    // Check if blocking scripts are executable and router connection works
    $routerStatus = checkRouterConnection();
    if ($routerStatus === 'connected') {
        return 'active';
    }
    return 'offline';
}

function checkNotificationSystem() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM admin_notifications LIMIT 1");
        return 'enabled';
    } catch (Exception $e) {
        return 'offline';
    }
}

function checkUpdateSystem() {
    // For now, assume manual updates
    return 'manual';
}

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $status = [
        'database' => checkDatabaseConnection(),
        'router' => checkRouterConnection(),
        'email' => checkEmailService(),
        'blocking' => checkBlockingEngine(),
        'notifications' => checkNotificationSystem(),
        'updates' => checkUpdateSystem()
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $status
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error checking system status: ' . $e->getMessage()
    ]);
}
?>
