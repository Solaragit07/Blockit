<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die('Unauthorized');
}

require_once '../connectMySql.php';

$type = $_GET['type'] ?? 'all';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $filename = 'blockit_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if ($type === 'all' || $type === 'users') {
        // Export users
        fputcsv($output, ['=== USERS ===']);
        fputcsv($output, ['ID', 'Username', 'Email', 'Subscription Type', 'Status', 'Created At', 'Last Login']);
        
        $stmt = $pdo->query("
            SELECT 
                id, username, email, subscription_type, subscription_status, 
                created_at, last_login_at 
            FROM users 
            ORDER BY created_at DESC
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        fputcsv($output, []);
    }
    
    if ($type === 'all' || $type === 'devices') {
        // Export devices
        fputcsv($output, ['=== DEVICES ===']);
        fputcsv($output, ['ID', 'User ID', 'Device Name', 'MAC Address', 'IP Address', 'Status', 'Last Seen']);
        
        $stmt = $pdo->query("
            SELECT 
                id, user_id, device_name, mac_address, ip_address, 
                status, last_seen 
            FROM devices 
            ORDER BY last_seen DESC
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        fputcsv($output, []);
    }
    
    if ($type === 'all' || $type === 'blocking_logs') {
        // Export blocking logs (last 1000 entries)
        fputcsv($output, ['=== BLOCKING LOGS (Last 1000) ===']);
        fputcsv($output, ['ID', 'Device ID', 'Blocked URL', 'Action', 'Reason', 'Created At']);
        
        $stmt = $pdo->query("
            SELECT 
                id, device_id, blocked_url, action, reason, created_at 
            FROM blocking_logs 
            ORDER BY created_at DESC 
            LIMIT 1000
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        fputcsv($output, []);
    }
    
    if ($type === 'all' || $type === 'notifications') {
        // Export notifications
        fputcsv($output, ['=== NOTIFICATIONS ===']);
        fputcsv($output, ['ID', 'Title', 'Message', 'Type', 'Priority', 'Status', 'Created At']);
        
        $stmt = $pdo->query("
            SELECT 
                id, title, message, type, priority, status, created_at 
            FROM admin_notifications 
            ORDER BY created_at DESC
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    
} catch (Exception $e) {
    header('Content-Type: text/plain');
    echo 'Error exporting data: ' . $e->getMessage();
}
?>
