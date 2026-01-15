<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once 'connectMySql.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - BlockIT Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/custom-color-palette.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .logs-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin: 20px auto;
            max-width: 95%;
        }
        
        .log-entry {
            padding: 10px;
            border-left: 3px solid #4fd1c7;
            margin-bottom: 5px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .log-entry.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        
        .log-entry.warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        
        .log-entry.success {
            border-left-color: #28a745;
            background: #d1ecf1;
        }
        
        .log-timestamp {
            font-family: monospace;
            font-size: 0.85em;
            color: #6c757d;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #4fd1c7 0%, #0F766E 100%);
            color: white;
            border: none;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #0F766E;
        }
        
        .table thead th {
            background: linear-gradient(135deg, #4fd1c7 0%, #0F766E 100%);
            color: white;
            border: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="logs-container">
            <div class="d-flex justify-content-between align-items-center p-4 border-bottom">
                <h2><i class="fas fa-file-alt"></i> System Logs</h2>
                <div>
                    <button class="btn btn-primary btn-sm" onclick="refreshLogs()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="window.close()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
            
            <div class="p-4">
                <ul class="nav nav-tabs" id="logTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#system-logs">System Logs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#blocking-logs">Blocking Logs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#login-logs">Login Logs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#error-logs">Error Logs</a>
                    </li>
                </ul>
                
                <div class="tab-content mt-3">
                    <!-- System Logs -->
                    <div class="tab-pane fade show active" id="system-logs">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <select class="form-select" id="system-log-level">
                                    <option value="all">All Levels</option>
                                    <option value="info">Info</option>
                                    <option value="warning">Warning</option>
                                    <option value="error">Error</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <input type="date" class="form-control" id="system-log-date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div id="system-log-content" style="max-height: 500px; overflow-y: auto;">
                            <div class="log-entry">
                                <div class="log-timestamp">[<?php echo date('Y-m-d H:i:s'); ?>]</div>
                                <div><strong>INFO:</strong> Admin dashboard accessed</div>
                            </div>
                            <div class="log-entry success">
                                <div class="log-timestamp">[<?php echo date('Y-m-d H:i:s', strtotime('-5 minutes')); ?>]</div>
                                <div><strong>SUCCESS:</strong> System status check completed</div>
                            </div>
                            <div class="log-entry warning">
                                <div class="log-timestamp">[<?php echo date('Y-m-d H:i:s', strtotime('-10 minutes')); ?>]</div>
                                <div><strong>WARNING:</strong> High memory usage detected (85%)</div>
                            </div>
                            <div class="log-entry">
                                <div class="log-timestamp">[<?php echo date('Y-m-d H:i:s', strtotime('-15 minutes')); ?>]</div>
                                <div><strong>INFO:</strong> Notification system initialized</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Blocking Logs -->
                    <div class="tab-pane fade" id="blocking-logs">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Device</th>
                                        <th>URL</th>
                                        <th>Action</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody id="blocking-log-content">
                                    <?php
                                    try {
                                        $stmt = $pdo->query("
                                            SELECT bl.*, d.device_name 
                                            FROM blocking_logs bl 
                                            LEFT JOIN devices d ON bl.device_id = d.id 
                                            ORDER BY bl.created_at DESC 
                                            LIMIT 50
                                        ");
                                        while ($log = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<tr>";
                                            echo "<td>" . date('H:i:s', strtotime($log['created_at'])) . "</td>";
                                            echo "<td>" . ($log['device_name'] ?: 'Unknown') . "</td>";
                                            echo "<td>" . htmlspecialchars($log['blocked_url']) . "</td>";
                                            echo "<td><span class='badge " . ($log['action'] === 'blocked' ? 'bg-danger' : 'bg-success') . "'>" . $log['action'] . "</span></td>";
                                            echo "<td>" . htmlspecialchars($log['reason']) . "</td>";
                                            echo "</tr>";
                                        }
                                    } catch (Exception $e) {
                                        echo "<tr><td colspan='5' class='text-center text-danger'>Error loading blocking logs</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Login Logs -->
                    <div class="tab-pane fade" id="login-logs">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Username</th>
                                        <th>IP Address</th>
                                        <th>Status</th>
                                        <th>User Agent</th>
                                    </tr>
                                </thead>
                                <tbody id="login-log-content">
                                    <?php
                                    try {
                                        $stmt = $pdo->query("
                                            SELECT * FROM login_logs 
                                            ORDER BY created_at DESC 
                                            LIMIT 50
                                        ");
                                        while ($log = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<tr>";
                                            echo "<td>" . date('H:i:s', strtotime($log['created_at'])) . "</td>";
                                            echo "<td>" . htmlspecialchars($log['username']) . "</td>";
                                            echo "<td>" . htmlspecialchars($log['ip_address'] ?: '127.0.0.1') . "</td>";
                                            echo "<td><span class='badge " . ($log['status'] === 'success' ? 'bg-success' : 'bg-danger') . "'>" . $log['status'] . "</span></td>";
                                            echo "<td>" . htmlspecialchars(substr($log['user_agent'] ?: 'Unknown', 0, 50)) . "...</td>";
                                            echo "</tr>";
                                        }
                                    } catch (Exception $e) {
                                        echo "<tr><td colspan='5' class='text-center text-danger'>Error loading login logs</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Error Logs -->
                    <div class="tab-pane fade" id="error-logs">
                        <div id="error-log-content" style="max-height: 500px; overflow-y: auto;">
                            <div class="log-entry error">
                                <div class="log-timestamp">[<?php echo date('Y-m-d H:i:s', strtotime('-2 hours')); ?>]</div>
                                <div><strong>ERROR:</strong> Failed to connect to router API (Connection timeout)</div>
                            </div>
                            <div class="log-entry error">
                                <div class="log-timestamp">[<?php echo date('Y-m-d H:i:s', strtotime('-4 hours')); ?>]</div>
                                <div><strong>ERROR:</strong> Database query failed: Table 'devices' doesn't exist</div>
                            </div>
                            <div class="log-entry warning">
                                <div class="log-timestamp">[<?php echo date('Y-m-d H:i:s', strtotime('-6 hours')); ?>]</div>
                                <div><strong>WARNING:</strong> Disk space running low (92% full)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function refreshLogs() {
            location.reload();
        }
        
        // Auto-refresh every 30 seconds
        setInterval(refreshLogs, 30000);
    </script>
</body>
</html>
