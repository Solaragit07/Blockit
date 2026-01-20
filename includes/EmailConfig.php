<?php
/**
 * Email Configuration for BlockIT
 * Gmail SMTP Settings
 */

class EmailConfig
{
    // Gmail SMTP defaults (override via environment variables)
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_SECURE = 'tls';
    const SMTP_AUTH = true;

    // Default sender information (override via environment variables)
    const FROM_EMAIL = '';
    const FROM_NAME = 'BlockIT System';
    
    // Email templates
    const TEMPLATE_PATH = __DIR__ . '/email_templates/';
    
    public static function getMailer()
    {
        require_once __DIR__ . '/SimpleMailer.php';

        $mail = new SimpleMailer(false, self::getSmtpSettings());
        return $mail;
    }

    /**
     * Environment-driven SMTP settings.
     *
     * Recommended env vars:
     * - BLOCKIT_SMTP_USER
     * - BLOCKIT_SMTP_PASS (Gmail App Password)
     * - BLOCKIT_SMTP_FROM (optional; defaults to user)
     * - BLOCKIT_SMTP_FROM_NAME
     * - BLOCKIT_SMTP_HOST (optional)
     * - BLOCKIT_SMTP_PORT (optional)
     */
    public static function getSmtpSettings(): array
    {
        $env = static function (string $key, $default = null) {
            $v = getenv($key);
            if ($v === false || $v === '') return $default;
            return $v;
        };

        $user = (string)$env('BLOCKIT_SMTP_USER', '');
        $pass = (string)$env('BLOCKIT_SMTP_PASS', '');
        // Gmail app passwords are often displayed with spaces; SMTP expects no spaces.
        $pass = preg_replace('/\s+/', '', $pass);
        $from = (string)$env('BLOCKIT_SMTP_FROM', self::FROM_EMAIL);
        if ($from === '' && $user !== '') $from = $user;

        return [
            'host' => (string)$env('BLOCKIT_SMTP_HOST', self::SMTP_HOST),
            'port' => (int)$env('BLOCKIT_SMTP_PORT', self::SMTP_PORT),
            'username' => $user,
            'password' => $pass,
            'from' => $from,
            'fromName' => (string)$env('BLOCKIT_SMTP_FROM_NAME', self::FROM_NAME),
        ];
    }

    /** Fallback recipient email when admin record is missing. */
    public static function getDefaultRecipientEmail(): string
    {
        $s = self::getSmtpSettings();
        return (string)($s['from'] ?? '');
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
        $subject = "🚫 BlockIT: Blocked site access attempt";
        
        $message = "
        <h2>Blocked Website Access Attempt</h2>
        <p><strong>{$deviceName}</strong> tried to access a blocked website. The attempt was blocked.</p>
        
        <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;'>
            <h3>Incident Details:</h3>
            <ul>
                <li><strong>Device:</strong> {$deviceName}</li>
                <li><strong>Blocked Site:</strong> {$blockedSite}</li>
                <li><strong>Status:</strong> Blocked</li>
                <li><strong>Time:</strong> {$timestamp}</li>
            </ul>
        </div>
        
        <p>This notification was sent automatically by your BlockIT system to keep you informed of blocked access attempts.</p>
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
                <li><strong>From Email:</strong> " . (self::getSmtpSettings()['from'] ?? '') . "</li>
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
