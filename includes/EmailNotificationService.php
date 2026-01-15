<?php
/**
 * Email Notification Service for BlockIT
 * Handles all email notifications in the system
 */

require_once __DIR__ . '/EmailConfig.php';

class EmailNotificationService
{
    private $adminEmail;
    private $adminName;
    
    public function __construct()
    {
        // Get admin email from database
        $this->loadAdminDetails();
    }
    
    private function loadAdminDetails()
    {
        try {
            include __DIR__ . '/../connectMySql.php';
            
            $query = "SELECT email, name FROM admin WHERE user_id = 1 LIMIT 1";
            $result = $conn->query($query);
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $this->adminEmail = $row['email'];
                $this->adminName = $row['name'];
            } else {
                // Fallback to default
                $this->adminEmail = 'jeanncorollo04@gmail.com';
                $this->adminName = 'BlockIT Admin';
            }
        } catch (Exception $e) {
            // Fallback to default
            $this->adminEmail = 'jeanncorollo04@gmail.com';
            $this->adminName = 'BlockIT Admin';
        }
    }
    
    /**
     * Send blocking alert when a website is blocked
     */
    public function sendWebsiteBlockedAlert($deviceName, $blockedSite, $userAgent = '')
    {
        if (empty($this->adminEmail)) {
            return false;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        
        return EmailConfig::sendBlockingAlert(
            $this->adminEmail,
            $deviceName,
            $blockedSite,
            $timestamp
        );
    }
    
    /**
     * Send alert when a device is blocked
     */
    public function sendDeviceBlockedAlert($deviceName)
    {
        if (empty($this->adminEmail)) {
            return false;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        
        return EmailConfig::sendDeviceBlockedAlert(
            $this->adminEmail,
            $deviceName,
            $this->adminName,
            $timestamp
        );
    }
    
    /**
     * Send custom notification email
     */
    public function sendCustomNotification($subject, $message, $recipientEmail = null)
    {
        $email = $recipientEmail ?: $this->adminEmail;
        
        if (empty($email)) {
            return false;
        }
        
        return EmailConfig::sendNotificationEmail($email, $subject, $message);
    }
    
    /**
     * Send daily/weekly report
     */
    public function sendActivityReport($reportData)
    {
        if (empty($this->adminEmail)) {
            return false;
        }
        
        $subject = "📊 BlockIT Activity Report - " . date('Y-m-d');
        
        $message = "
        <h2>Daily Activity Report</h2>
        <p>Here's a summary of today's network activity:</p>
        
        <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0;'>
            <h3>Statistics:</h3>
            <ul>
                <li><strong>Blocked Attempts:</strong> " . ($reportData['blocked_attempts'] ?? 0) . "</li>
                <li><strong>Active Devices:</strong> " . ($reportData['active_devices'] ?? 0) . "</li>
                <li><strong>Blocked Devices:</strong> " . ($reportData['blocked_devices'] ?? 0) . "</li>
                <li><strong>Most Blocked Site:</strong> " . ($reportData['most_blocked_site'] ?? 'N/A') . "</li>
            </ul>
        </div>
        
        <p>For detailed information, please log in to your BlockIT dashboard.</p>
        ";
        
        return EmailConfig::sendNotificationEmail($this->adminEmail, $subject, $message);
    }
    
    /**
     * Test email functionality
     */
    public function sendTestEmail($testEmail = null)
    {
        $email = $testEmail ?: $this->adminEmail;
        return EmailConfig::sendTestEmail($email);
    }
    
    /**
     * Get admin email for external use
     */
    public function getAdminEmail()
    {
        return $this->adminEmail;
    }
}

// Create a global instance for easy access
$emailService = new EmailNotificationService();
?>
