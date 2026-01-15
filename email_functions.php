<?php
/**
 * Quick Email Functions for BlockIT Integration
 * Include this file in your blocking scripts to add email notifications
 */

require_once __DIR__ . '/includes/EmailNotificationService.php';

/**
 * Send email notification when a website is blocked
 * Call this function from your blocking scripts
 */
function notifyWebsiteBlocked($deviceName, $blockedSite, $additionalInfo = '') {
    try {
        $emailService = new EmailNotificationService();
        return $emailService->sendWebsiteBlockedAlert($deviceName, $blockedSite);
    } catch (Exception $e) {
        error_log("Email notification failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email notification when a device is blocked
 */
function notifyDeviceBlocked($deviceName) {
    try {
        $emailService = new EmailNotificationService();
        return $emailService->sendDeviceBlockedAlert($deviceName);
    } catch (Exception $e) {
        error_log("Email notification failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send custom notification email
 */
function sendCustomNotification($subject, $message, $email = null) {
    try {
        $emailService = new EmailNotificationService();
        return $emailService->sendCustomNotification($subject, $message, $email);
    } catch (Exception $e) {
        error_log("Email notification failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Test email configuration
 */
function testEmailConfiguration($testEmail = null) {
    try {
        $emailService = new EmailNotificationService();
        return $emailService->sendTestEmail($testEmail);
    } catch (Exception $e) {
        error_log("Email test failed: " . $e->getMessage());
        return false;
    }
}

// Example usage in your existing scripts:
/*

// Include this file at the top of your blocking scripts
include_once 'email_functions.php';

// When a website is blocked, call:
notifyWebsiteBlocked('John\'s iPhone', 'facebook.com');

// When a device is blocked, call:
notifyDeviceBlocked('John\'s iPhone');

// For custom notifications:
sendCustomNotification('Custom Alert', 'Your custom message here');

*/
?>
