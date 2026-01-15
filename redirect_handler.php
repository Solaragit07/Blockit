<?php
/**
 * BlockIT Redirect Handler
 * This file handles all redirected blocked domain requests
 */

// Get the originally requested domain
$requestedDomain = $_SERVER['HTTP_HOST'] ?? '';
$requestedURL = $_SERVER['REQUEST_URI'] ?? '';
$fullURL = $requestedDomain . $requestedURL;

// Log the blocked access attempt
$logEntry = date('Y-m-d H:i:s') . " - Blocked access to: $fullURL from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
file_put_contents('blocked_access.log', $logEntry, FILE_APPEND | LOCK_EX);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Blocked - BlockIT</title>
    <link rel="icon" type="image/x-icon" href="/BlockIT/img/logo1.png" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8fdf8 0%, #e8f5e8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2e7d32;
        }
        
        .block-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(46, 125, 50, 0.1);
            padding: 3rem;
            text-align: center;
            max-width: 600px;
            width: 90%;
            border: 3px solid #4CAF50;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo-container {
            margin-bottom: 2rem;
            animation: bounce 2s ease-in-out infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        .logo {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border-radius: 50%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.3);
        }
        
        .block-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #2e7d32;
        }
        
        .block-subtitle {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 2rem;
        }
        
        .blocked-url {
            background: #ffebee;
            border: 2px solid #e74c3c;
            border-radius: 10px;
            padding: 1rem;
            margin: 2rem 0;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #c62828;
            word-break: break-all;
        }
        
        .reason-box {
            background: #e8f5e8;
            border-left: 5px solid #4CAF50;
            padding: 1.5rem;
            margin: 2rem 0;
            border-radius: 0 10px 10px 0;
            text-align: left;
        }
        
        .reason-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2e7d32;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .reason-text {
            color: #555;
            line-height: 1.6;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            border-left: 3px solid #4CAF50;
        }
        
        .info-title {
            font-weight: 600;
            color: #2e7d32;
            margin-bottom: 0.5rem;
        }
        
        .info-value {
            color: #666;
            font-size: 0.9rem;
        }
        
        .action-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .footer-info {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
            color: #999;
            font-size: 0.9rem;
        }
        
        .countdown {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            z-index: 1000;
        }
        
        @media (max-width: 600px) {
            .block-container {
                padding: 2rem;
                margin: 1rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="block-container">
        <!-- Logo -->
        <div class="logo-container">
            <div class="logo">
                🛡️
            </div>
        </div>
        
        <!-- Title -->
        <h1 class="block-title">BlockIT</h1>
        <p class="block-subtitle">Website Access Blocked</p>
        
        <!-- Blocked URL -->
        <div class="blocked-url">
            🚫 <?php echo htmlspecialchars($fullURL); ?>
        </div>
        
        <!-- Reason -->
        <div class="reason-box">
            <div class="reason-title">
                ℹ️ Why is this blocked?
            </div>
            <div class="reason-text">
                This website has been blocked by your network administrator as part of the internet access policy. 
                BlockIT helps maintain a safe, secure, and productive browsing environment.
            </div>
        </div>
        
        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-card">
                <div class="info-title">🕐 Current Time</div>
                <div class="info-value"><?php echo date('H:i:s'); ?></div>
            </div>
            <div class="info-card">
                <div class="info-title">📅 Date</div>
                <div class="info-value"><?php echo date('Y-m-d'); ?></div>
            </div>
            <div class="info-card">
                <div class="info-title">🌐 Your IP</div>
                <div class="info-value"><?php echo $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; ?></div>
            </div>
            <div class="info-card">
                <div class="info-title">🔒 Block System</div>
                <div class="info-value">BlockIT v2.0</div>
            </div>
        </div>
        
        <?php
        // Check if it's working hours
        $currentHour = (int)date('H');
        $isWorkingHours = ($currentHour >= 8 && $currentHour < 17);
        ?>
        
        <?php if ($isWorkingHours): ?>
        <div class="reason-box" style="background: #fff3cd; border-left-color: #ffc107;">
            <div class="reason-title" style="color: #856404;">
                ⏰ Time-Based Restriction
            </div>
            <div class="reason-text" style="color: #856404;">
                This website is currently blocked during working hours (8:00 AM - 5:00 PM). 
                Access may be available outside these hours.
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="http://<?php echo $_SERVER['HTTP_HOST']; ?>/BlockIT/" class="btn btn-primary">
                🏠 Go to Dashboard
            </a>
            <button onclick="window.history.back()" class="btn btn-secondary">
                ← Go Back
            </button>
        </div>
        
        <!-- Footer -->
        <div class="footer-info">
            <p><strong>BlockIT</strong> - Internet Access Control System</p>
            <p>For assistance, contact your network administrator</p>
            <p>Blocked at: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
    
    <!-- Auto-refresh countdown -->
    <div class="countdown" id="countdown">
        Auto-refresh in: <span id="timer">60</span>s
    </div>
    
    <script>
        // Auto-refresh to check if access is restored
        let timeLeft = 60;
        const timer = document.getElementById('timer');
        
        const countdown = setInterval(function() {
            timeLeft--;
            timer.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(countdown);
                window.location.reload();
            }
        }, 1000);
        
        // Hide countdown after 10 seconds
        setTimeout(function() {
            document.getElementById('countdown').style.display = 'none';
        }, 10000);
    </script>
</body>
</html>
