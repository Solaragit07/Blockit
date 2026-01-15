<?php
// Suppress any output before JSON
error_reporting(0); // Disable error reporting for clean JSON
ini_set('display_errors', 0);
ini_set('max_execution_time', 60); // Limit to 1 minute for app blocking
ob_start();

include '../../connectMySql.php';
include '../../loginverification.php';
include '../../includes/fast_api_helper.php';

// Clean any output buffer and set JSON header
ob_clean();
header('Content-Type: application/json');

if(!logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests allowed']);
    exit;
}

// Ensure required tables exist
try {
    $conn->query("CREATE TABLE IF NOT EXISTS application_blocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id INT NULL,
        application_name VARCHAR(100) NOT NULL,
        application_category VARCHAR(100) NOT NULL,
        block_type VARCHAR(50) DEFAULT 'complete',
        duration INT DEFAULT 0,
        reason VARCHAR(255) NULL,
        domains TEXT,
        ports VARCHAR(255) NULL,
        protocols VARCHAR(255) NULL,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_app_cat (application_name, application_category),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $conn->query("CREATE TABLE IF NOT EXISTS blocklist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        website VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Legacy-safe: ensure all required columns exist (don’t depend on column order)
    $colsRes = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'application_blocks'");
    $existingCols = [];
    if ($colsRes) {
        while ($r = $colsRes->fetch_assoc()) {
            $existingCols[strtolower($r['COLUMN_NAME'])] = true;
        }
    }
    $addStmts = [];
    if (!isset($existingCols['device_id'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN device_id INT NULL";
    if (!isset($existingCols['application_name'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN application_name VARCHAR(100) NOT NULL";
    if (!isset($existingCols['application_category'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN application_category VARCHAR(100) NOT NULL";
    if (!isset($existingCols['block_type'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN block_type VARCHAR(50) DEFAULT 'complete'";
    if (!isset($existingCols['duration'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN duration INT DEFAULT 0";
    if (!isset($existingCols['reason'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN reason VARCHAR(255) NULL";
    if (!isset($existingCols['domains'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN domains TEXT";
    if (!isset($existingCols['ports'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN ports VARCHAR(255) NULL";
    if (!isset($existingCols['protocols'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN protocols VARCHAR(255) NULL";
    if (!isset($existingCols['status'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN status ENUM('active','inactive') DEFAULT 'active'";
    if (!isset($existingCols['created_at'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    if (!isset($existingCols['updated_at'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP";
    foreach ($addStmts as $sql) { $conn->query($sql); }

    // Ensure device_id allows NULL and has NULL default (older schemas used 0 and NOT NULL)
    $conn->query("ALTER TABLE application_blocks MODIFY COLUMN device_id INT NULL DEFAULT NULL");
    // Clean legacy placeholder values that violate FK
    $conn->query("UPDATE application_blocks SET device_id = NULL WHERE device_id = 0");
} catch (Exception $e) {
    // If table creation fails, return a clear error
    echo json_encode(['status' => 'error', 'message' => 'Database setup error: ' . $e->getMessage()]);
    exit;
}

// Use centralized domain source to avoid duplication and mismatches

try {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'quick_block' && isset($_POST['app_name'])) {
        $appName = $_POST['app_name'];
        
    // Get domains from a single source of truth
    $domains = FastApiHelper::getDomainsForApplication($appName);
    if (!empty($domains)) {
            $domainsString = implode(',', $domains);
            
            // Check if application is already blocked
            $checkAppQuery = "SELECT id FROM application_blocks WHERE application_name = ? AND status = 'active'";
            $checkAppStmt = $conn->prepare($checkAppQuery);
            if (!$checkAppStmt) {
                echo json_encode(['status' => 'error', 'message' => 'DB error (prepare check): ' . $conn->error]);
                exit;
            }
            $checkAppStmt->bind_param("s", $appName);
            $checkAppStmt->execute();
            $checkAppStmt->store_result();
            
            if ($checkAppStmt->num_rows > 0) {
                // Instead of exiting, re-apply router enforcement so existing blocks are refreshed
                try {
                    $updateResult = FastApiHelper::fastUpdateApplicationBlocking($conn, $appName);
                    $msg = ($updateResult['success'] ?? false)
                        ? "Re-applied $appName block to " . ($updateResult['devices_updated'] ?? 0) . " devices."
                        : ("Attempted to re-apply $appName block, but: " . ($updateResult['error'] ?? 'unknown error'));
                    echo json_encode(['status' => 'success', 'message' => $msg]);
                } catch (Exception $e) {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to re-apply block: ' . $e->getMessage()]);
                }
                exit;
            }
            
            // Get application category based on app name
            $category = 'Social Media'; // Default category
            $categoryMapping = [
                'WhatsApp' => 'Communication',
                'TikTok' => 'Social Media',
                'Instagram' => 'Social Media', 
                'Facebook' => 'Social Media',
                'YouTube' => 'Entertainment',
                'Netflix' => 'Entertainment',
                'Spotify' => 'Entertainment',
                'Discord' => 'Communication',
                'Telegram' => 'Communication',
                'Zoom' => 'Communication'
            ];
            
            if (isset($categoryMapping[$appName])) {
                $category = $categoryMapping[$appName];
            }
            
            // Add to application_blocks table
            $insertAppQuery = "INSERT INTO application_blocks (application_name, application_category, domains, status, reason) VALUES (?, ?, ?, 'active', 'Blocked via Quick Application Blocks')";
            $insertAppStmt = $conn->prepare($insertAppQuery);
            if (!$insertAppStmt) {
                echo json_encode(['status' => 'error', 'message' => 'DB error (prepare insert): ' . $conn->error]);
                exit;
            }
            $insertAppStmt->bind_param("sss", $appName, $category, $domainsString);
            
            if ($insertAppStmt->execute()) {
                // Also add individual domains to blocklist for backward compatibility
                $addedCount = 0;
                foreach ($domains as $domain) {
                    // Check if domain already exists
                    $checkQuery = "SELECT id FROM blocklist WHERE website = ?";
                    $checkStmt = $conn->prepare($checkQuery);
                    if (!$checkStmt) {
                        echo json_encode(['status' => 'error', 'message' => 'DB error (prepare domain check): ' . $conn->error]);
                        exit;
                    }
                    $checkStmt->bind_param("s", $domain);
                    $checkStmt->execute();
                    $checkStmt->store_result();
                    
                    if ($checkStmt->num_rows == 0) {
                        // Add to blocklist
                        $insertQuery = "INSERT INTO blocklist (website) VALUES (?)";
                        $insertStmt = $conn->prepare($insertQuery);
                        if (!$insertStmt) {
                            echo json_encode(['status' => 'error', 'message' => 'DB error (prepare blocklist insert): ' . $conn->error]);
                            exit;
                        }
                        $insertStmt->bind_param("s", $domain);
                        
                        if ($insertStmt->execute()) {
                            $addedCount++;
                        }
                    }
                }
                
                // Update devices with fast API helper - based on selected method
                try {
                    // Check if user selected redirect method
                    $useRedirect = isset($_POST['use_redirect']) && $_POST['use_redirect'] === 'redirect';
                    
                    if ($useRedirect) {
                        $updateResult = FastApiHelper::fastUpdateApplicationBlockingWithRedirect($conn, $appName);
                        
                        if ($updateResult['success']) {
                            $responseMessage = "✅ $appName blocked with redirect page! Updated " . $updateResult['devices_updated'] . " devices with " . $updateResult['domains_count'] . " domains. Users will see a custom block page when accessing blocked sites.";
                        } else {
                            $responseMessage = "⚠️ $appName added to blocklist, but redirect setup had issues: " . $updateResult['error'];
                        }
                    } else {
                        $updateResult = FastApiHelper::fastUpdateApplicationBlocking($conn, $appName);
                        
                        if ($updateResult['success']) {
                            $responseMessage = "✅ $appName blocked successfully! Updated " . $updateResult['devices_updated'] . " devices with " . $updateResult['domains_count'] . " domains.";
                        } else {
                            $responseMessage = "⚠️ $appName added to blocklist, but router update had issues: " . $updateResult['error'];
                        }
                    }
                } catch (Exception $e) {
                    error_log("Fast API Helper error: " . $e->getMessage());
                    $responseMessage = "✅ $appName added to blocklist. Router will update in background.";
                }
                
                echo json_encode([
                    'status' => 'success', 
                    'message' => $responseMessage
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to add application block: ' . $insertAppStmt->error]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Application not found or has no domain map']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    }
    
} catch (Exception $e) {
    // Clean any output buffer before sending JSON
    ob_clean();
    error_log("Quick block error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to block application: ' . $e->getMessage()]);
}

// Clean up
ob_end_flush();
?>
