<?php
// When a child/device hits a blocked site, this page is served.
// Log the attempt and notify the admin email (with cooldown to avoid spamming).

declare(strict_types=1);

function blockit_get_client_ip(): string {
    // Prefer router-provided query param, then common forwarded headers, then REMOTE_ADDR
    foreach (['ip','ip_address'] as $k) {
        $v = trim((string)($_GET[$k] ?? ''));
        if ($v !== '' && filter_var($v, FILTER_VALIDATE_IP)) return $v;
    }
    foreach (['HTTP_X_FORWARDED_FOR','HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP'] as $h) {
        $raw = (string)($_SERVER[$h] ?? '');
        if ($raw === '') continue;
        $parts = array_map('trim', explode(',', $raw));
        foreach ($parts as $p) {
            if ($p !== '' && filter_var($p, FILTER_VALIDATE_IP)) return $p;
        }
    }
    $ra = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return $ra !== '' ? $ra : '0.0.0.0';
}

function blockit_norm_site(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    if (strpos($v, '://') !== false) {
        $h = parse_url($v, PHP_URL_HOST);
        if (is_string($h) && $h !== '') $v = $h;
    } elseif (strpos($v, '/') !== false) {
        $h = parse_url('http://' . $v, PHP_URL_HOST);
        if (is_string($h) && $h !== '') $v = $h;
    }
    $v = preg_replace('/:\d+$/', '', $v);
    $v = strtolower((string)preg_replace('/[^a-z0-9.\-]/i', '', $v));
    return $v;
}

function blockit_dedup_should_send(string $key, int $cooldownSeconds = 300): bool {
    $path = __DIR__ . '/data/blocked_alert_dedup.json';
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);

    $now = time();
    $data = [];
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) $data = $decoded;
    }

    // Basic prune
    if (count($data) > 5000) {
        asort($data);
        $data = array_slice($data, -2500, null, true);
    }

    $last = (int)($data[$key] ?? 0);
    if ($last > 0 && ($now - $last) < $cooldownSeconds) return false;

    $data[$key] = $now;
    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
    return true;
}

$blocked_site_raw = (string)($_GET['site'] ?? $_GET['url'] ?? ($_SERVER['HTTP_HOST'] ?? 'Unknown'));
$blocked_site = blockit_norm_site($blocked_site_raw) ?: $blocked_site_raw;
$mac = strtolower(trim((string)($_GET['mac'] ?? $_GET['mac_address'] ?? '')));
$ip = blockit_get_client_ip();
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$ts = date('Y-m-d H:i:s');

// Attempt DB log + email notify (never break the blocked page rendering).
try {
    // DB logging (if DB is available)
    $conn = null;
    $connectPath = __DIR__ . '/connectMySql.php';
    if (is_file($connectPath)) {
        @require_once $connectPath; // expects $conn (mysqli)
    }

    // Insert into `logs` table if possible (schema-flexible)
    if (isset($conn) && $conn instanceof mysqli) {
        $colsRes = $conn->query("SHOW COLUMNS FROM `logs`");
        $cols = [];
        if ($colsRes) {
            while ($c = $colsRes->fetch_assoc()) $cols[(string)$c['Field']] = true;
        }

        $fields = [];
        $placeholders = [];
        $types = '';
        $values = [];

        $add = function(string $field, string $type, $value) use (&$fields, &$placeholders, &$types, &$values, $cols) {
            if (!isset($cols[$field])) return;
            $fields[] = "`{$field}`";
            $placeholders[] = '?';
            $types .= $type;
            $values[] = $value;
        };

        // Preferred columns
        $add('type', 's', 'blocked');
        $add('domain', 's', (string)$blocked_site);
        $add('date', 's', (string)$ts);
        $add('action', 's', 'blocked');
        $add('reason', 's', 'blocked-site');
        $add('ip_address', 's', (string)$ip);
        $add('user_agent', 's', (string)$ua);
        $add('mac_address', 's', (string)$mac);

        if (!empty($fields)) {
            $sql = "INSERT INTO `logs` (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$values);
                @$stmt->execute();
                @$stmt->close();
            }
        }
    }

    // Email notify (deduped)
    $dedupKey = sha1(($mac ?: $ip) . '|' . strtolower((string)$blocked_site));
    if (blockit_dedup_should_send($dedupKey, 300)) {
        $deviceLabel = 'Unknown Device';

        if (isset($conn) && $conn instanceof mysqli) {
            $deviceColsRes = $conn->query("SHOW COLUMNS FROM `device`");
            $deviceCols = [];
            if ($deviceColsRes) {
                while ($c = $deviceColsRes->fetch_assoc()) $deviceCols[(string)$c['Field']] = true;
            }

            $nameCol = null;
            foreach (['device_name','name','device'] as $cand) {
                if (isset($deviceCols[$cand])) { $nameCol = $cand; break; }
            }
            $hasMacCol = isset($deviceCols['mac_address']);
            $hasIpCol = isset($deviceCols['ip_address']);

            if ($nameCol && ($hasMacCol || $hasIpCol)) {
                if ($hasMacCol && $mac !== '') {
                    $stmt = $conn->prepare("SELECT `$nameCol` AS nm FROM `device` WHERE `mac_address` = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param('s', $mac);
                        if (@$stmt->execute()) {
                            $r = $stmt->get_result();
                            if ($r && ($row = $r->fetch_assoc()) && !empty($row['nm'])) $deviceLabel = (string)$row['nm'];
                        }
                        @$stmt->close();
                    }
                }
                if ($deviceLabel === 'Unknown Device' && $hasIpCol && $ip !== '') {
                    $stmt = $conn->prepare("SELECT `$nameCol` AS nm FROM `device` WHERE `ip_address` = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param('s', $ip);
                        if (@$stmt->execute()) {
                            $r = $stmt->get_result();
                            if ($r && ($row = $r->fetch_assoc()) && !empty($row['nm'])) $deviceLabel = (string)$row['nm'];
                        }
                        @$stmt->close();
                    }
                }
            }
        }

        $deviceLabel .= " (IP: {$ip}" . ($mac ? ", MAC: {$mac}" : '') . ')';

        $svcPath = __DIR__ . '/includes/EmailNotificationService.php';
        if (is_file($svcPath)) {
            @require_once $svcPath;
            if (class_exists('EmailNotificationService')) {
                $svc = new EmailNotificationService();
                // Fire-and-forget best-effort
                @$svc->sendWebsiteBlockedAlert($deviceLabel, (string)$blocked_site, $ua);
            }
        }
    }
} catch (Throwable $e) {
    // never block rendering
}
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
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .logo {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.3);
        }
        
        .logo svg {
            width: 60px;
            height: 60px;
            fill: white;
        }
        
        .system-name {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2e7d32;
            letter-spacing: 2px;
            margin-bottom: 0.5rem;
        }
        
        .subtitle {
            color: #4CAF50;
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 2rem;
        }
        
        .block-icon {
            font-size: 4rem;
            color: #f44336;
            margin-bottom: 1.5rem;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .block-title {
            font-size: 2rem;
            font-weight: 600;
            color: #f44336;
            margin-bottom: 1rem;
        }
        
        .block-message {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        
        .blocked-site {
            background: #ffebee;
            border: 2px solid #f44336;
            border-radius: 10px;
            padding: 1rem;
            margin: 1.5rem 0;
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            color: #c62828;
            word-break: break-all;
        }
        
        .info-section {
            background: #f8fdf8;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
            border-left: 5px solid #4CAF50;
        }
        
        .info-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2e7d32;
            margin-bottom: 1rem;
        }
        
        .info-list {
            text-align: left;
            color: #666;
            line-height: 1.8;
        }
        
        .info-list li {
            margin-bottom: 0.5rem;
        }
        
        .contact-section {
            background: #e8f5e8;
            border-radius: 15px;
            padding: 1.5rem;
            margin: 2rem 0;
        }
        
        .contact-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2e7d32;
            margin-bottom: 1rem;
        }
        
        .contact-info {
            color: #4CAF50;
            font-weight: 500;
        }
        
        .back-button {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }
        
        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }
        
        .timestamp {
            color: #999;
            font-size: 0.9rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
        }
        
        @media (max-width: 768px) {
            .block-container {
                margin: 1rem;
                padding: 2rem;
            }
            
            .system-name {
                font-size: 2rem;
            }
            
            .block-title {
                font-size: 1.5rem;
            }
            
            .block-message {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="block-container">
        <!-- Logo and System Name -->
        <div class="logo-container">
            <div class="logo">
                <svg viewBox="0 0 24 24">
                    <path d="M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1M12,7C13.4,7 14.8,8.6 14.8,10V11H16V19H8V11H9.2V10C9.2,8.6 10.6,7 12,7M12,8.2C11.2,8.2 10.4,8.7 10.4,10V11H13.6V10C13.6,8.7 12.8,8.2 12,8.2Z"/>
                </svg>
            </div>
            <div class="system-name">BLOCKIT</div>
            <div class="subtitle">Internet Access Control System</div>
        </div>
        
        <!-- Block Notification -->
        <div class="block-icon">🚫</div>
        <div class="block-title">Access Blocked</div>
        <div class="block-message">
            This website has been blocked by your network administrator according to your internet usage policy.
        </div>
        
        <!-- Blocked Site Display -->
        <div class="blocked-site">
            <strong>Blocked Website:</strong><br>
            <span id="blocked-url"><?php echo htmlspecialchars((string)$blocked_site); ?></span>
        </div>
        
        <!-- Information Section -->
        <div class="info-section">
            <div class="info-title">🛡️ Why was this blocked?</div>
            <ul class="info-list">
                <li><strong>Policy Compliance:</strong> This website doesn't comply with your organization's internet usage policy</li>
                <li><strong>Productivity:</strong> Access is restricted to maintain focus during work/study hours</li>
                <li><strong>Security:</strong> The site may pose potential security risks</li>
                <li><strong>Time Management:</strong> Blocking helps maintain healthy internet usage habits</li>
            </ul>
        </div>
        
        <!-- Time-based Information -->
        <div class="info-section">
            <div class="info-title">⏰ Time-Based Access</div>
            <div class="info-list">
                <?php
                $current_hour = date('H');
                $is_restricted_time = ($current_hour < 8 || $current_hour >= 17); // Example: blocked outside 8 AM - 5 PM
                
                if ($is_restricted_time) {
                    echo "<li><strong>Current Status:</strong> <span style='color: #f44336;'>Blocked Hours</span></li>";
                    echo "<li><strong>Available:</strong> 8:00 AM - 5:00 PM (Work Hours)</li>";
                } else {
                    echo "<li><strong>Current Status:</strong> <span style='color: #4CAF50;'>Within Allowed Hours</span></li>";
                    echo "<li><strong>Reason:</strong> Site specifically blocked by administrator</li>";
                }
                ?>
                <li><strong>Current Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></li>
            </div>
        </div>
        
        <!-- Contact Section -->
        <div class="contact-section">
            <div class="contact-title">📞 Need Access?</div>
            <div class="contact-info">
                Contact your network administrator or IT support<br>
                <strong>System:</strong> BlockIT Internet Control<br>
                <strong>Blocked at:</strong> <?php echo date('Y-m-d H:i:s'); ?>
            </div>
        </div>
        
        <!-- Action Button -->
        <button class="back-button" onclick="goBack()">
            ← Go Back
        </button>
        
        <!-- Footer -->
        <div class="timestamp">
            <div>Blocked by BlockIT Internet Access Control System</div>
            <div>Timestamp: <?php echo date('Y-m-d H:i:s T'); ?></div>
            <div>Your IP: <?php echo $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; ?></div>
        </div>
    </div>

    <script>
        // Add some interactive functionality
        function goBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = 'about:blank';
            }
        }
        
        // Auto-refresh every 30 seconds to check if access is restored
        setInterval(function() {
            // You can add logic here to check if the site should be unblocked
            console.log('Checking access status...');
        }, 30000);
        
        // Display additional info based on the blocked site
        document.addEventListener('DOMContentLoaded', function() {
            const blockedUrl = document.getElementById('blocked-url').textContent.toLowerCase();
            
            // Add site-specific messages
            if (blockedUrl.includes('facebook') || blockedUrl.includes('instagram')) {
                addMessage('social', '📱 Social media sites are restricted during work hours to maintain productivity.');
            } else if (blockedUrl.includes('youtube') || blockedUrl.includes('netflix')) {
                addMessage('streaming', '🎬 Video streaming sites use significant bandwidth and are restricted.');
            } else if (blockedUrl.includes('game') || blockedUrl.includes('roblox')) {
                addMessage('gaming', '🎮 Gaming sites are blocked to maintain focus and productivity.');
            }
        });
        
        function addMessage(type, message) {
            const infoSection = document.querySelector('.info-section');
            const messageDiv = document.createElement('div');
            messageDiv.style.cssText = 'background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 10px; margin-top: 10px; color: #856404;';
            messageDiv.innerHTML = '<strong>Additional Info:</strong> ' + message;
            infoSection.appendChild(messageDiv);
        }
    </script>
</body>
</html>
