<?php
/**
 * Email Configuration for BlockIT
 * Gmail SMTP Settings
 */

class EmailConfig
{
    // Gmail SMTP Configuration
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_SECURE = 'tls';
    const SMTP_AUTH = true;
    
    // Your Gmail credentials
    const SMTP_USERNAME = 'jeanncorollo04@gmail.com';
    const SMTP_PASSWORD = 'lbqc tjaj nple magx';
    
    // Default sender information
    const FROM_EMAIL = 'jeanncorollo04@gmail.com';
    const FROM_NAME = 'BlockIT System';
    
    // Email templates
    const TEMPLATE_PATH = __DIR__ . '/email_templates/';
    
    public static function getMailer()
    {
        require_once __DIR__ . '/SimpleMailer.php';
        
        $mail = new SimpleMailer();
        return $mail;
    }
    
    public static function sendNotificationEmail($to, $subject, $message, $isHTML = true)
    {
        try {
            $mail = self::getMailer();
            
            // Add HTML template if HTML is requested
            if ($isHTML) {
                $message = self::getHTMLTemplate($subject, $message);
            }
            
            // Send email using SimpleMailer
            return $mail->sendEmail($to, $subject, $message, $isHTML);
        } catch (Exception $e) {
            error_log("Email Error: " . $e->getMessage());
            return false;
        }
    }
    
    public static function sendBlockingAlert($to, $deviceName, $blockedSite, $timestamp)
    {
        $subject = "🚨 BlockIT Alert: Blocked Website Access Attempt";
        
        $message = "
        <h2>Blocked Website Access Detected</h2>
        <p>Someone tried to access a blocked website on your network.</p>
        
        <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;'>
            <h3>Incident Details:</h3>
            <ul>
                <li><strong>Device:</strong> {$deviceName}</li>
                <li><strong>Blocked Site:</strong> {$blockedSite}</li>
                <li><strong>Time:</strong> {$timestamp}</li>
            </ul>
        </div>
        
        <p>This notification was sent automatically by your BlockIT system to keep you informed of network activity.</p>
        ";
        
        return self::sendNotificationEmail($to, $subject, $message);
    }
    
    public static function sendDeviceBlockedAlert($to, $deviceName, $parentName, $timestamp)
    {
        $subject = "🔒 BlockIT Alert: Device Access Restricted";
        
        $message = "
        <h2>Device Access Restricted</h2>
        <p>A device has been blocked from internet access.</p>
        
        <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>
            <h3>Block Details:</h3>
            <ul>
                <li><strong>Device:</strong> {$deviceName}</li>
                <li><strong>Restricted by:</strong> {$parentName}</li>
                <li><strong>Time:</strong> {$timestamp}</li>
            </ul>
        </div>
        
        <p>The device will remain blocked until manually unblocked through the BlockIT dashboard.</p>
        ";
        
        return self::sendNotificationEmail($to, $subject, $message);
    }
    
    public static function sendTestEmail($to)
    {
        $subject = "✅ BlockIT Email Test - Configuration Successful";
        
        $message = "
        <h2>Email Configuration Test</h2>
        <p>Congratulations! Your BlockIT email system is working correctly.</p>
        
        <div style='background-color: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0;'>
            <h3>Configuration Details:</h3>
            <ul>
                <li><strong>SMTP Server:</strong> " . self::SMTP_HOST . "</li>
                <li><strong>Port:</strong> " . self::SMTP_PORT . "</li>
                <li><strong>Encryption:</strong> " . strtoupper(self::SMTP_SECURE) . "</li>
                <li><strong>From Email:</strong> " . self::FROM_EMAIL . "</li>
            </ul>
        </div>
        
        <p>You will now receive email notifications for:</p>
        <ul>
            <li>Blocked website access attempts</li>
            <li>Device blocking/unblocking alerts</li>
            <li>System status updates</li>
        </ul>
        ";
        
        return self::sendNotificationEmail($to, $subject, $message);
    }
    
    private static function getHTMLTemplate($title, $content)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background-color: #f8f9fa; padding: 30px; border-radius: 0 0 5px 5px; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                h1, h2, h3 { margin-top: 0; }
                .logo { font-size: 24px; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='header'>
                <div class='logo'>🛡️ BlockIT</div>
                <p>Internet Access Control System</p>
            </div>
            <div class='content'>
                {$content}
            </div>
            <div class='footer'>
                <p>This email was sent automatically by BlockIT System<br>
                Please do not reply to this email.</p>
            </div>
        </body>
        </html>";
    }
}
?>
