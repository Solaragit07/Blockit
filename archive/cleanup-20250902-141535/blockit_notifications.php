<?php
/**
 * Simple BlockIT Notification Helper Functions
 * Include this file in your existing blocking scripts to add instant notifications
 */

require_once __DIR__ . '/includes/IntegratedNotificationService.php';

/**
 * Quick notification for website blocking
 * Usage: blockitNotifyWebsite('John\'s iPhone', 'facebook.com');
 */
function blockitNotifyWebsite($deviceName, $blockedSite, $userEmail = null, $showAlert = true) {
    try {
        $service = new IntegratedNotificationService(true, $showAlert);
        $result = $service->notifyWebsiteBlocked($deviceName, $blockedSite, '', $userEmail);
        
        // Return alert script for non-AJAX requests
        if ($showAlert && isset($result['alert']['script'])) {
            return $result['alert']['script'];
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("BlockIT notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Quick notification for device blocking
 * Usage: blockitNotifyDevice('John\'s iPhone', 'Time limit exceeded');
 */
function blockitNotifyDevice($deviceName, $reason = 'Policy violation', $userEmail = null, $showAlert = true) {
    try {
        $service = new IntegratedNotificationService(true, $showAlert);
        $result = $service->notifyDeviceBlocked($deviceName, $reason, $userEmail);
        
        // Return alert script for non-AJAX requests
        if ($showAlert && isset($result['alert']['script'])) {
            return $result['alert']['script'];
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("BlockIT notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send custom BlockIT notification
 * Usage: blockitNotifyCustom('System Alert', 'Custom message here', 'warning');
 */
function blockitNotifyCustom($title, $message, $type = 'info', $userEmail = null, $showAlert = true) {
    try {
        $service = new IntegratedNotificationService(true, $showAlert);
        $result = $service->sendCustomNotification($title, $message, $type, $userEmail);
        
        // Return alert script for non-AJAX requests
        if ($showAlert && isset($result['alert']['script'])) {
            return $result['alert']['script'];
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("BlockIT notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Test the notification system
 * Usage: blockitTestNotifications('test@email.com');
 */
function blockitTestNotifications($testEmail = null) {
    try {
        $service = new IntegratedNotificationService();
        return $service->testNotificationSystem($testEmail);
    } catch (Exception $e) {
        error_log("BlockIT notification test error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notification settings
 * Usage: $settings = blockitGetSettings();
 */
function blockitGetSettings() {
    try {
        $service = new IntegratedNotificationService();
        return $service->getSettings();
    } catch (Exception $e) {
        error_log("BlockIT settings error: " . $e->getMessage());
        return false;
    }
}

/**
 * Example integration for your existing blocking scripts:
 * 
 * // At the top of your blocking script:
 * include_once 'blockit_notifications.php';
 * 
 * // When you block a website:
 * $alertScript = blockitNotifyWebsite('John\'s iPhone', 'facebook.com');
 * if ($alertScript) {
 *     echo $alertScript; // This will show the popup alert
 * }
 * 
 * // When you block a device:
 * $alertScript = blockitNotifyDevice('John\'s iPhone', 'Time limit exceeded');
 * if ($alertScript) {
 *     echo $alertScript; // This will show the popup alert
 * }
 * 
 * // For AJAX requests (no popup alerts):
 * $result = blockitNotifyWebsite('John\'s iPhone', 'facebook.com', null, false);
 * 
 * // Custom notifications:
 * $alertScript = blockitNotifyCustom('System Maintenance', 'The system will restart in 5 minutes', 'warning');
 * if ($alertScript) {
 *     echo $alertScript;
 * }
 */
?>
