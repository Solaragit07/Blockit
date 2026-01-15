<?php
/**
 * Integrated Notification Service for BlockIT
 * Combines email notifications with browser alerts and UI feedback
 */

require_once __DIR__ . '/EmailConfig.php';

class IntegratedNotificationService
{
    private $adminEmail;
    private $adminName;
    private $enableEmail;
    private $enableAlerts;
    private $quietHoursStart;
    private $quietHoursEnd;
    
    public function __construct($enableEmail = true, $enableAlerts = true)
    {
        $this->enableEmail = $enableEmail;
        $this->enableAlerts = $enableAlerts;
        $this->loadSettings();
    }
    
    private function loadSettings()
    {
        try {
            include __DIR__ . '/../connectMySql.php';
            
            // Load admin details
            $query = "SELECT email, name FROM admin WHERE user_id = 1 LIMIT 1";
            $result = $conn->query($query);
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $this->adminEmail = $row['email'];
                $this->adminName = $row['name'];
            } else {
                $this->adminEmail = 'jeanncorollo04@gmail.com';
                $this->adminName = 'BlockIT Admin';
            }
            
            // Load notification settings (you can create a notification_settings table later)
            $this->quietHoursStart = '22:00';
            $this->quietHoursEnd = '07:00';
            
        } catch (Exception $e) {
            error_log("Failed to load notification settings: " . $e->getMessage());
            $this->adminEmail = 'jeanncorollo04@gmail.com';
            $this->adminName = 'BlockIT Admin';
        }
    }
    
    /**
     * Check if we're in quiet hours
     */
    private function isQuietHours()
    {
        $currentTime = date('H:i');
        $start = $this->quietHoursStart;
        $end = $this->quietHoursEnd;
        
        // Handle overnight quiet hours (e.g., 22:00 to 07:00)
        if ($start > $end) {
            return ($currentTime >= $start || $currentTime <= $end);
        } else {
            return ($currentTime >= $start && $currentTime <= $end);
        }
    }
    
    /**
     * Send website blocking notification
     */
    public function notifyWebsiteBlocked($deviceName, $blockedSite, $additionalInfo = '', $userEmail = null)
    {
        $results = [];
        $timestamp = date('Y-m-d H:i:s');
        
        // Send email notification (unless in quiet hours)
        if ($this->enableEmail && !$this->isQuietHours()) {
            try {
                $emailResult = $this->sendWebsiteBlockedEmail($deviceName, $blockedSite, $timestamp, $userEmail);
                $results['email'] = [
                    'success' => $emailResult,
                    'message' => $emailResult ? 'Email notification sent' : 'Email notification failed'
                ];
            } catch (Exception $e) {
                $results['email'] = [
                    'success' => false,
                    'message' => 'Email error: ' . $e->getMessage()
                ];
            }
        } else {
            $results['email'] = [
                'success' => false,
                'message' => 'Email disabled or quiet hours active'
            ];
        }
        
        // Generate browser alert
        if ($this->enableAlerts) {
            $results['alert'] = $this->generateWebsiteBlockedAlert($deviceName, $blockedSite);
        }
        
        // Log the notification
        $this->logNotification('website_blocked', $deviceName, $blockedSite, $results);
        
        return $results;
    }
    
    /**
     * Send device blocking notification
     */
    public function notifyDeviceBlocked($deviceName, $reason = 'Policy violation', $userEmail = null)
    {
        $results = [];
        $timestamp = date('Y-m-d H:i:s');
        
        // Send email notification (unless in quiet hours)
        if ($this->enableEmail && !$this->isQuietHours()) {
            try {
                $emailResult = $this->sendDeviceBlockedEmail($deviceName, $reason, $timestamp, $userEmail);
                $results['email'] = [
                    'success' => $emailResult,
                    'message' => $emailResult ? 'Email notification sent' : 'Email notification failed'
                ];
            } catch (Exception $e) {
                $results['email'] = [
                    'success' => false,
                    'message' => 'Email error: ' . $e->getMessage()
                ];
            }
        } else {
            $results['email'] = [
                'success' => false,
                'message' => 'Email disabled or quiet hours active'
            ];
        }
        
        // Generate browser alert
        if ($this->enableAlerts) {
            $results['alert'] = $this->generateDeviceBlockedAlert($deviceName, $reason);
        }
        
        // Log the notification
        $this->logNotification('device_blocked', $deviceName, $reason, $results);
        
        return $results;
    }
    
    /**
     * Send custom notification
     */
    public function sendCustomNotification($title, $message, $type = 'info', $userEmail = null)
    {
        $results = [];
        
        // Send email notification
        if ($this->enableEmail && !$this->isQuietHours()) {
            try {
                $emailResult = EmailConfig::sendNotificationEmail(
                    $userEmail ?: $this->adminEmail,
                    $title,
                    $message
                );
                $results['email'] = [
                    'success' => $emailResult,
                    'message' => $emailResult ? 'Email notification sent' : 'Email notification failed'
                ];
            } catch (Exception $e) {
                $results['email'] = [
                    'success' => false,
                    'message' => 'Email error: ' . $e->getMessage()
                ];
            }
        }
        
        // Generate browser alert
        if ($this->enableAlerts) {
            $results['alert'] = $this->generateCustomAlert($title, $message, $type);
        }
        
        return $results;
    }
    
    /**
     * Send website blocked email
     */
    private function sendWebsiteBlockedEmail($deviceName, $blockedSite, $timestamp, $userEmail = null)
    {
        $recipient = $userEmail ?: $this->adminEmail;
        return EmailConfig::sendBlockingAlert($recipient, $deviceName, $blockedSite, $timestamp);
    }
    
    /**
     * Send device blocked email
     */
    private function sendDeviceBlockedEmail($deviceName, $reason, $timestamp, $userEmail = null)
    {
        $recipient = $userEmail ?: $this->adminEmail;
        return EmailConfig::sendDeviceBlockedAlert($recipient, $deviceName, $this->adminName, $timestamp);
    }
    
    /**
     * Generate website blocked alert for browser
     */
    private function generateWebsiteBlockedAlert($deviceName, $blockedSite)
    {
        $alertScript = "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: '🚨 Website Blocked!',
                    html: `
                        <div style='text-align: left;'>
                            <p><strong>Device:</strong> {$deviceName}</p>
                            <p><strong>Blocked Site:</strong> {$blockedSite}</p>
                            <p><strong>Time:</strong> " . date('H:i:s') . "</p>
                            <p><strong>Action:</strong> Access attempt blocked by BlockIT</p>
                        </div>
                    `,
                    confirmButtonText: 'Acknowledge',
                    confirmButtonColor: '#dc3545',
                    timer: 10000,
                    timerProgressBar: true
                });
            } else {
                alert('🚨 Website Blocked!\\n\\nDevice: {$deviceName}\\nBlocked Site: {$blockedSite}\\nTime: " . date('H:i:s') . "');
            }
        });
        </script>";
        
        return [
            'type' => 'website_blocked',
            'script' => $alertScript,
            'data' => [
                'device' => $deviceName,
                'site' => $blockedSite,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    /**
     * Generate device blocked alert for browser
     */
    private function generateDeviceBlockedAlert($deviceName, $reason)
    {
        $alertScript = "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: '🔒 Device Blocked!',
                    html: `
                        <div style='text-align: left;'>
                            <p><strong>Device:</strong> {$deviceName}</p>
                            <p><strong>Reason:</strong> {$reason}</p>
                            <p><strong>Time:</strong> " . date('H:i:s') . "</p>
                            <p><strong>Action:</strong> Device access has been restricted</p>
                        </div>
                    `,
                    confirmButtonText: 'Understood',
                    confirmButtonColor: '#dc3545',
                    timer: 15000,
                    timerProgressBar: true
                });
            } else {
                alert('🔒 Device Blocked!\\n\\nDevice: {$deviceName}\\nReason: {$reason}\\nTime: " . date('H:i:s') . "');
            }
        });
        </script>";
        
        return [
            'type' => 'device_blocked',
            'script' => $alertScript,
            'data' => [
                'device' => $deviceName,
                'reason' => $reason,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    /**
     * Generate custom alert for browser
     */
    private function generateCustomAlert($title, $message, $type = 'info')
    {
        $iconMap = [
            'success' => 'success',
            'error' => 'error',
            'warning' => 'warning',
            'info' => 'info',
            'question' => 'question'
        ];
        
        $icon = $iconMap[$type] ?? 'info';
        
        $alertScript = "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: '{$icon}',
                    title: '" . addslashes($title) . "',
                    html: '" . addslashes($message) . "',
                    confirmButtonText: 'OK',
                    timer: 8000,
                    timerProgressBar: true
                });
            } else {
                alert('" . addslashes($title) . "\\n\\n" . addslashes($message) . "');
            }
        });
        </script>";
        
        return [
            'type' => 'custom',
            'script' => $alertScript,
            'data' => [
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    /**
     * Log notification to database or file
     */
    private function logNotification($type, $subject, $details, $results)
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'subject' => $subject,
            'details' => $details,
            'email_sent' => $results['email']['success'] ?? false,
            'alert_generated' => isset($results['alert']),
            'recipient' => $this->adminEmail
        ];
        
        error_log("BlockIT Notification: " . json_encode($logEntry));
        
        // You can add database logging here later
        /*
        try {
            include __DIR__ . '/../connectMySql.php';
            $stmt = $conn->prepare("INSERT INTO notification_log (timestamp, type, subject, details, email_sent, alert_generated, recipient) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $logEntry['timestamp'],
                $logEntry['type'],
                $logEntry['subject'],
                $logEntry['details'],
                $logEntry['email_sent'] ? 1 : 0,
                $logEntry['alert_generated'] ? 1 : 0,
                $logEntry['recipient']
            ]);
        } catch (Exception $e) {
            error_log("Failed to log notification: " . $e->getMessage());
        }
        */
    }
    
    /**
     * Test the notification system
     */
    public function testNotificationSystem($testEmail = null)
    {
        $results = [];
        
        // Test email
        try {
            $emailResult = EmailConfig::sendTestEmail($testEmail ?: $this->adminEmail);
            $results['email_test'] = [
                'success' => $emailResult,
                'message' => $emailResult ? 'Email test successful' : 'Email test failed'
            ];
        } catch (Exception $e) {
            $results['email_test'] = [
                'success' => false,
                'message' => 'Email test error: ' . $e->getMessage()
            ];
        }
        
        // Test alert
        $results['alert_test'] = $this->generateCustomAlert(
            'Notification Test',
            'This is a test of the BlockIT integrated notification system. Both email and alert notifications are working correctly.',
            'success'
        );
        
        return $results;
    }
    
    /**
     * Get notification settings
     */
    public function getSettings()
    {
        return [
            'admin_email' => $this->adminEmail,
            'admin_name' => $this->adminName,
            'email_enabled' => $this->enableEmail,
            'alerts_enabled' => $this->enableAlerts,
            'quiet_hours_start' => $this->quietHoursStart,
            'quiet_hours_end' => $this->quietHoursEnd,
            'is_quiet_hours' => $this->isQuietHours()
        ];
    }
    
    /**
     * Update notification settings
     */
    public function updateSettings($settings)
    {
        if (isset($settings['email_enabled'])) {
            $this->enableEmail = (bool)$settings['email_enabled'];
        }
        
        if (isset($settings['alerts_enabled'])) {
            $this->enableAlerts = (bool)$settings['alerts_enabled'];
        }
        
        if (isset($settings['quiet_hours_start'])) {
            $this->quietHoursStart = $settings['quiet_hours_start'];
        }
        
        if (isset($settings['quiet_hours_end'])) {
            $this->quietHoursEnd = $settings['quiet_hours_end'];
        }
        
        // Save to database (implement this later)
        // $this->saveSettings();
        
        return true;
    }
}
?>
