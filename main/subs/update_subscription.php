<?php
session_start();
include '../../connectMySql.php';
require_once '../../admin/admin_notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get POST data
$plan = $_POST['plan'] ?? '';
$user_id = $_SESSION['user_id'];

// Validate plan
$valid_plans = ['free', 'premium'];
if (!in_array($plan, $valid_plans)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid plan']);
    exit;
}

try {
    // Check if user subscription record exists
    $stmt = $conn->prepare("SELECT id, plan FROM user_subscriptions WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $old_plan = null;
    $action = 'created';
    
    if ($result->num_rows > 0) {
        // Get current plan for notification
        $current_subscription = $result->fetch_assoc();
        $old_plan = $current_subscription['plan'];
        $action = 'updated';
        
        // Update existing subscription
        $stmt = $conn->prepare("UPDATE user_subscriptions SET plan = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("si", $plan, $user_id);
    } else {
        // Create new subscription record
        $stmt = $conn->prepare("INSERT INTO user_subscriptions (user_id, plan, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->bind_param("is", $user_id, $plan);
    }
    
    if ($stmt->execute()) {
        // Create admin notification for subscription change
        if (class_exists('AdminNotifications')) {
            $adminNotifications = new AdminNotifications();
            $adminNotifications->createSubscriptionNotification($user_id, $action, $plan, $old_plan);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Subscription updated successfully',
            'plan' => $plan
        ]);
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update subscription: ' . $e->getMessage()]);
}

$conn->close();
?>
