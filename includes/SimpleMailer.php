<?php
/**
 * Simple SMTP Mailer for BlockIT
 * Handles Gmail SMTP with proper TLS negotiation
 */

class SimpleMailer
{
    private $host = 'smtp.gmail.com';
    private $port = 587;
    private $username = 'jeanncorollo04@gmail.com';
    private $password = 'lbqc tjaj nple magx';
    private $from = 'jeanncorollo04@gmail.com';
    private $fromName = 'BlockIT System';
    private $debug = false;
    
    public function __construct($debug = false)
    {
        $this->debug = $debug;
    }
    
    private function debug($message)
    {
        if ($this->debug) {
            echo "DEBUG: " . $message . "<br>\n";
            error_log("SimpleMailer Debug: " . $message);
        }
    }
    
    public function sendEmail($to, $subject, $body, $isHTML = true)
    {
        return $this->sendMail($to, $subject, $body, $isHTML);
    }
    
    public function sendMail($to, $subject, $body, $isHTML = true)
    {
        try {
            // Connect to Gmail SMTP
            $this->debug("Connecting to {$this->host}:{$this->port}");
            
            $socket = fsockopen($this->host, $this->port, $errno, $errstr, 30);
            if (!$socket) {
                throw new Exception("Connection failed: $errstr ($errno)");
            }
            
            // Read greeting
            $response = fgets($socket);
            $this->debug("Greeting: " . trim($response));
            if (!$this->checkResponse($response, '220')) {
                throw new Exception("Invalid greeting: " . trim($response));
            }
            
            // Send EHLO
            $this->sendCommand($socket, "EHLO localhost");
            $this->readMultilineResponse($socket, '250');
            
            // Start TLS
            $this->debug("Starting TLS...");
            $this->sendCommand($socket, "STARTTLS");
            $response = $this->readResponse($socket);
            $this->debug("STARTTLS response: " . trim($response));
            
            if (!$this->checkResponse($response, '220')) {
                throw new Exception("STARTTLS failed: " . trim($response));
            }
            
            // Enable TLS encryption
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("Failed to enable TLS encryption");
            }
            $this->debug("TLS enabled successfully");
            
            // Send EHLO again
            $this->sendCommand($socket, "EHLO localhost");
            $this->readMultilineResponse($socket, '250');
            
            // Authenticate
            $this->debug("Authenticating...");
            $this->sendCommand($socket, "AUTH LOGIN");
            $this->readResponse($socket, '334');
            
            $this->sendCommand($socket, base64_encode($this->username));
            $this->readResponse($socket, '334');
            
            $this->sendCommand($socket, base64_encode($this->password));
            $this->readResponse($socket, '235');
            $this->debug("Authentication successful");
            
            // Send mail
            $this->sendCommand($socket, "MAIL FROM: <{$this->from}>");
            $this->readResponse($socket, '250');
            
            $this->sendCommand($socket, "RCPT TO: <{$to}>");
            $this->readResponse($socket, '250');
            
            $this->sendCommand($socket, "DATA");
            $this->readResponse($socket, '354');
            
            // Send headers and body
            $headers = "From: {$this->fromName} <{$this->from}>\r\n";
            $headers .= "To: <{$to}>\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            if ($isHTML) {
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            } else {
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            }
            $headers .= "\r\n";
            
            $message = $headers . $body . "\r\n.\r\n";
            fputs($socket, $message);
            
            $this->readResponse($socket, '250');
            $this->debug("Email sent successfully");
            
            // Quit
            $this->sendCommand($socket, "QUIT");
            fclose($socket);
            
            return true;
            
        } catch (Exception $e) {
            $this->debug("Error: " . $e->getMessage());
            if (isset($socket) && $socket) {
                fclose($socket);
            }
            return false;
        }
    }
    
    private function sendCommand($socket, $command)
    {
        $this->debug(">>> " . $command);
        fputs($socket, $command . "\r\n");
    }
    
    private function readResponse($socket, $expectedCode = null)
    {
        $response = fgets($socket);
        $this->debug("<<< " . trim($response));
        
        if ($expectedCode && !$this->checkResponse($response, $expectedCode)) {
            throw new Exception("Expected {$expectedCode}, got: " . trim($response));
        }
        
        return $response;
    }
    
    private function readMultilineResponse($socket, $expectedCode = null)
    {
        $response = $this->readResponse($socket, $expectedCode);
        
        // Read additional lines if this is a multiline response
        while (substr(trim($response), 3, 1) == '-') {
            $response = $this->readResponse($socket);
        }
        
        return $response;
    }
    
    private function checkResponse($response, $expectedCode)
    {
        return strpos(trim($response), $expectedCode) === 0;
    }
    
    public function sendTestEmail($to)
    {
        $subject = "✅ BlockIT Email Test - Simple SMTP";
        
        $message = $this->getHTMLTemplate(
            "Email Test Successful",
            "
            <h2>Email Test Successful</h2>
            <p>Your BlockIT email system is working with Simple SMTP implementation.</p>
            
            <div style='background-color: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0;'>
                <h3>System Information:</h3>
                <ul>
                    <li><strong>PHP Version:</strong> " . phpversion() . "</li>
                    <li><strong>Server:</strong> " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "</li>
                    <li><strong>From Email:</strong> " . $this->from . "</li>
                    <li><strong>Test Time:</strong> " . date('Y-m-d H:i:s') . "</li>
                </ul>
            </div>
            
            <p>If you received this email, your email notifications are working correctly!</p>
            
            <p>You will now receive email notifications for:</p>
            <ul>
                <li>Blocked website access attempts</li>
                <li>Device blocking/unblocking alerts</li>
                <li>System status updates</li>
            </ul>
            "
        );
        
        return $this->sendEmail($to, $subject, $message);
    }
    
    public function sendBlockingAlert($to, $deviceName, $blockedSite, $timestamp)
    {
        $subject = "🚨 BlockIT Alert: Blocked Website Access Attempt";
        
        $message = $this->getHTMLTemplate(
            "Blocked Website Access Detected",
            "
            <h2>Blocked Website Access Detected</h2>
            <p>Someone tried to access a blocked website on your network.</p>
            
            <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;'>
                <h3>Incident Details:</h3>
                <ul>
                    <li><strong>Device:</strong> {$deviceName}</li>
                    <li><strong>Blocked Site:</strong> {$blockedSite}</li>
                    <li><strong>Time:</strong> {$timestamp}</li>
                    <li><strong>Server:</strong> " . $_SERVER['SERVER_NAME'] . "</li>
                </ul>
            </div>
            
            <p>This notification was sent automatically by your BlockIT system.</p>
            "
        );
        
        return $this->sendEmail($to, $subject, $message);
    }
    
    private function getHTMLTemplate($title, $content)
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

// Quick test
if (isset($_GET['simple_test'])) {
    $mailer = new SimpleMailer();
    $testEmail = $_GET['email'] ?? 'jeanncorollo04@gmail.com';
    
    $result = $mailer->sendTestEmail($testEmail);
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $result ? 'success' : 'error',
        'message' => $result ? 'Simple mail test sent successfully' : 'Failed to send simple mail test',
        'email' => $testEmail
    ]);
    exit;
}
?>
