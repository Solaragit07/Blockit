<?php
/**
 * PHPMailer - PHP email creation and transport class.
 * Simplified version for BlockIT application
 */

class PHPMailer
{
    public $Host = '';
    public $SMTPAuth = true;
    public $Username = '';
    public $Password = '';
    public $SMTPSecure = 'tls';
    public $Port = 587;
    public $isHTML = true;
    public $From = '';
    public $FromName = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $SMTPDebug = false; // Enable debugging
    private $to = array();
    private $headers = array();
    
    public function __construct()
    {
        $this->headers[] = 'MIME-Version: 1.0';
        $this->headers[] = 'Content-type: text/html; charset=UTF-8';
    }
    
    public function isSMTP()
    {
        // Enable SMTP mode
        return true;
    }
    
    public function addAddress($email, $name = '')
    {
        $this->to[] = array('email' => $email, 'name' => $name);
    }
    
    private function debug($message)
    {
        if ($this->SMTPDebug) {
            error_log("PHPMailer Debug: " . $message);
            echo "DEBUG: " . htmlspecialchars($message) . "<br>\n";
        }
    }
    
    public function send()
    {
        try {
            // Prepare headers
            $headers = implode("\r\n", $this->headers);
            
            if (!empty($this->From)) {
                if (!empty($this->FromName)) {
                    $headers .= "\r\nFrom: {$this->FromName} <{$this->From}>";
                } else {
                    $headers .= "\r\nFrom: {$this->From}";
                }
            }
            
            // Send to each recipient
            foreach ($this->to as $recipient) {
                $to = $recipient['email'];
                if (!empty($recipient['name'])) {
                    $to = "{$recipient['name']} <{$recipient['email']}>";
                }
                
                // Use SMTP if configured
                if ($this->SMTPAuth && !empty($this->Host) && !empty($this->Username) && !empty($this->Password)) {
                    return $this->sendSMTP($recipient['email']);
                } else {
                    // Fallback to PHP mail function
                    return mail($to, $this->Subject, $this->Body, $headers);
                }
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function sendSMTP($to)
    {
        $this->debug("Starting SMTP connection to {$this->Host}:{$this->Port}");
        
        // Create socket connection context with SSL options
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
            ]
        ]);
        
        // Connect to SMTP server with SSL for Gmail
        if ($this->SMTPSecure == 'tls' && $this->Port == 587) {
            // For Gmail TLS, connect without SSL first, then upgrade
            $socket = stream_socket_client(
                "tcp://{$this->Host}:{$this->Port}",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT
            );
        } else {
            // For other configurations
            $socket = stream_socket_client(
                "tcp://{$this->Host}:{$this->Port}",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );
        }
        
        if (!$socket) {
            $this->debug("SMTP Connection failed: $errstr ($errno)");
            error_log("SMTP Connection failed: $errstr ($errno)");
            return false;
        }
        
        // Get server response
        $response = fgets($socket, 515);
        $this->debug("Server greeting: " . trim($response));
        
        if (strpos($response, '220') !== 0) {
            $this->debug("Invalid server greeting");
            fclose($socket);
            return false;
        }
        
        // Send EHLO
        $ehlo_cmd = "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n";
        fputs($socket, $ehlo_cmd);
        $response = fgets($socket, 515);
        $this->debug("EHLO response: " . trim($response));
        
        // Read all EHLO response lines
        while (substr(trim($response), 3, 1) == '-') {
            $response = fgets($socket, 515);
            $this->debug("EHLO additional response: " . trim($response));
        }
        
        // Start TLS if required
        if ($this->SMTPSecure == 'tls') {
            $this->debug("Starting TLS...");
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            $this->debug("STARTTLS response: " . trim($response));
            
            // Check if STARTTLS was accepted (220 Ready for TLS)
            if (strpos($response, '220') === 0) {
                $this->debug("STARTTLS accepted, enabling TLS...");
                
                // Enable crypto - try different methods for compatibility
                $crypto_enabled = false;
                
                // Try TLS 1.2 first (preferred for Gmail)
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto_enabled = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
                    if ($crypto_enabled) {
                        $this->debug("TLS 1.2 enabled successfully");
                    }
                }
                
                // Fallback to general TLS
                if (!$crypto_enabled) {
                    $crypto_enabled = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    if ($crypto_enabled) {
                        $this->debug("TLS enabled successfully");
                    }
                }
                
                if (!$crypto_enabled) {
                    $this->debug("Failed to enable TLS encryption");
                    error_log("Failed to enable TLS encryption");
                    fclose($socket);
                    return false;
                }
                
                // Send EHLO again after TLS
                fputs($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
                $response = fgets($socket, 515);
                $this->debug("EHLO after TLS: " . trim($response));
                
                // Read any additional EHLO response lines
                while (substr(trim($response), 3, 1) == '-') {
                    $response = fgets($socket, 515);
                    $this->debug("EHLO after TLS additional: " . trim($response));
                }
            } else {
                $this->debug("STARTTLS failed - unexpected response: " . trim($response));
                fclose($socket);
                return false;
            }
        }
        
        // Authenticate
        $this->debug("Starting authentication...");
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        $this->debug("AUTH LOGIN response: " . trim($response));
        
        if (strpos($response, '334') !== 0) {
            $this->debug("AUTH LOGIN failed");
            fclose($socket);
            return false;
        }
        
        fputs($socket, base64_encode($this->Username) . "\r\n");
        $response = fgets($socket, 515);
        $this->debug("Username response: " . trim($response));
        
        if (strpos($response, '334') !== 0) {
            $this->debug("Username authentication failed");
            fclose($socket);
            return false;
        }
        
        fputs($socket, base64_encode($this->Password) . "\r\n");
        $response = fgets($socket, 515);
        $this->debug("Password response: " . trim($response));
        
        if (strpos($response, '235') !== 0) {
            $this->debug("Password authentication failed");
            error_log("SMTP Authentication failed: " . trim($response));
            fclose($socket);
            return false;
        }
        
        $this->debug("Authentication successful");
        
        // Send mail
        fputs($socket, "MAIL FROM: <" . $this->From . ">\r\n");
        $response = fgets($socket, 515);
        $this->debug("MAIL FROM response: " . trim($response));
        
        if (strpos($response, '250') !== 0) {
            $this->debug("MAIL FROM failed");
            fclose($socket);
            return false;
        }
        
        fputs($socket, "RCPT TO: <" . $to . ">\r\n");
        $response = fgets($socket, 515);
        $this->debug("RCPT TO response: " . trim($response));
        
        if (strpos($response, '250') !== 0) {
            $this->debug("RCPT TO failed");
            fclose($socket);
            return false;
        }
        
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        $this->debug("DATA response: " . trim($response));
        
        if (strpos($response, '354') !== 0) {
            $this->debug("DATA command failed");
            fclose($socket);
            return false;
        }
        
        // Send headers and body
        $message = "Subject: " . $this->Subject . "\r\n";
        $message .= "From: " . $this->FromName . " <" . $this->From . ">\r\n";
        $message .= "To: <" . $to . ">\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message .= $this->Body . "\r\n";
        $message .= ".\r\n";
        
        fputs($socket, $message);
        $response = fgets($socket, 515);
        $this->debug("Message send response: " . trim($response));
        
        if (strpos($response, '250') !== 0) {
            $this->debug("Message sending failed");
            error_log("SMTP Data sending failed: " . trim($response));
            fclose($socket);
            return false;
        }
        
        // Quit
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        $this->debug("Email sent successfully!");
        return true;
    }
}
?>
