<?php
/**
 * Send Parental Notifications
 * This file handles sending notifications to parents when device time limits are reached
 */

header('Content-Type: application/json');

// Include database connection
include_once '../../API/connectMikrotik.php';
include_once '../../email_functions.php';

if (!isset($conn)) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests allowed']);
    exit;
}

// Get POST data
$mac_address = $_POST['mac_address'] ?? '';
$device_name = $_POST['device_name'] ?? '';
$notification_type = $_POST['notification_type'] ?? '';
$remaining_minutes = (int)($_POST['remaining_minutes'] ?? 0);
$timestamp = $_POST['timestamp'] ?? '';

// Validate required fields
if (empty($mac_address) || empty($notification_type)) {
    echo json_encode(['status' => 'error', 'message' => 'MAC address and notification type are required']);
    exit;
}

try {
    // Create parental_notifications table if it doesn't exist
    $createTableSQL = "CREATE TABLE IF NOT EXISTS parental_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mac_address VARCHAR(17) NOT NULL,
        device_name VARCHAR(255),
        notification_type VARCHAR(50) NOT NULL,
        remaining_minutes INT,
        message TEXT,
        sent_at DATETIME NOT NULL,
        email_sent BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mac_type (mac_address, notification_type),
        INDEX idx_sent_at (sent_at)
    )";
    $conn->query($createTableSQL);

    // Prepare notification message based on type
    $message = '';
    $email_subject = '';
    $email_body = '';
    
    switch ($notification_type) {
        case '15 minute warning':
            $message = "Your child's device '$device_name' has 15 minutes of internet time remaining.";
            $email_subject = "BlockIt Alert: 15 Minutes Remaining - $device_name";
            $email_body = "
                <h3>⏰ 15 Minute Warning</h3>
                <p><strong>Device:</strong> $device_name</p>
                <p><strong>Time Remaining:</strong> $remaining_minutes minutes</p>
                <p>Your child's internet time is almost up. The device will be automatically blocked when the time limit is reached.</p>
                <hr>
                <small>This is an automated notification from BlockIt Parental Control System</small>
            ";
            break;
            
        case '5 minute warning':
            $message = "Your child's device '$device_name' has only 5 minutes of internet time remaining!";
            $email_subject = "BlockIt Alert: 5 Minutes Remaining - $device_name";
            $email_body = "
                <h3>🚨 5 Minute Warning</h3>
                <p><strong>Device:</strong> $device_name</p>
                <p><strong>Time Remaining:</strong> $remaining_minutes minutes</p>
                <p>Your child's internet time is about to expire. The device will be automatically blocked very soon.</p>
                <hr>
                <small>This is an automated notification from BlockIt Parental Control System</small>
            ";
            break;
            
        case 'time expired':
            $message = "Your child's device '$device_name' has reached its time limit and has been automatically blocked.";
            $email_subject = "BlockIt Alert: Time Limit Reached - $device_name";
            $email_body = "
                <h3>🛑 Time Limit Reached</h3>
                <p><strong>Device:</strong> $device_name</p>
                <p><strong>Status:</strong> Automatically blocked</p>
                <p>Your child's internet time limit has been reached. The device has been automatically blocked from accessing the internet.</p>
                <p>You can extend the time limit or unblock the device through the BlockIt dashboard.</p>
                <hr>
                <small>This is an automated notification from BlockIt Parental Control System</small>
            ";
            break;
            
        default:
            $message = "Device '$device_name' time limit notification: $notification_type";
            $email_subject = "BlockIt Alert: $notification_type - $device_name";
            $email_body = "
                <h3>📱 Device Time Notification</h3>
                <p><strong>Device:</strong> $device_name</p>
                <p><strong>Alert Type:</strong> $notification_type</p>
                <p>$message</p>
                <hr>
                <small>This is an automated notification from BlockIt Parental Control System</small>
            ";
    }

    // Insert notification into database
    $insertStmt = $conn->prepare("INSERT INTO parental_notifications 
        (mac_address, device_name, notification_type, remaining_minutes, message, sent_at) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    $insertStmt->bind_param("sssiss", $mac_address, $device_name, $notification_type, $remaining_minutes, $message, $timestamp);
    
    if ($insertStmt->execute()) {
        $notification_id = $conn->insert_id;
        
        // Get parent email addresses (you may need to configure this)
        $parent_emails = getParentEmails($conn);
        
        $email_sent = false;
        
        // Send email notifications if emails are configured
        if (!empty($parent_emails) && function_exists('sendEmail')) {
            foreach ($parent_emails as $email) {
                if (sendEmail($email, $email_subject, $email_body)) {
                    $email_sent = true;
                }
            }
            
            // Update notification record with email status
            $updateStmt = $conn->prepare("UPDATE parental_notifications SET email_sent = ? WHERE id = ?");
            $updateStmt->bind_param("ii", $email_sent, $notification_id);
            $updateStmt->execute();
        }
        
        // Log the notification
        error_log("Parental notification sent: $notification_type for $device_name ($mac_address)");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Notification sent successfully',
            'notification_id' => $notification_id,
            'email_sent' => $email_sent,
            'notification_type' => $notification_type,
            'device_name' => $device_name
        ]);
        
    } else {
        throw new Exception("Failed to save notification: " . $conn->error);
    }

} catch (Exception $e) {
    error_log("Error in send_parental_notification.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

/**
 * Get parent email addresses from configuration or database
 */
function getParentEmails($conn) {
    // You can configure parent emails here or fetch from database
    // For now, return example emails - you should configure this
    return [
        // 'parent1@example.com',
        // 'parent2@example.com'
    ];
    
    // Alternative: fetch from database
    /*
    try {
        $stmt = $conn->prepare("SELECT email FROM parent_contacts WHERE is_active = TRUE");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $emails = [];
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row['email'];
        }
        return $emails;
    } catch (Exception $e) {
        error_log("Error fetching parent emails: " . $e->getMessage());
        return [];
    }
    */
}

$conn->close();
?>
