<?php
/**
 * BlockIT Auto Cleanup Script
 * Removes unused files automatically without confirmation
 */

set_time_limit(300);
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== BlockIT Auto Workspace Cleanup ===\n";
echo "Starting automatic cleanup process...\n\n";

// Define patterns for files to remove
$removePatterns = [
    // Test files
    '/test.*\.php$/',
    '/.*test\.php$/',
    
    // Debug files
    '/debug.*\.php$/',
    '/.*debug\.php$/',
    
    // Diagnostic files
    '/.*diagnostic.*\.php$/',
    
    // Emergency/cleanup utilities
    '/.*emergency.*\.php$/',
    '/.*cleanup.*\.php$/',
    '/.*troubleshoot.*\.php$/',
    
    // Quick test utilities
    '/quick_.*\.php$/',
    
    // Performance/connection tests
    '/.*performance.*\.php$/',
    '/.*connection.*\.php$/',
    '/.*ping.*\.php$/',
    '/.*arp.*\.php$/',
    
    // Device detection tests
    '/.*device.*detection.*\.php$/',
    '/.*detection.*\.php$/',
    
    // Firewall tests
    '/.*firewall.*\.php$/',
    
    // Mikrotik tests
    '/.*mikrotik.*test.*\.php$/',
    '/standalone_.*\.php$/',
    '/simple_.*\.php$/',
    
    // Various utilities
    '/force_.*\.php$/',
    '/enhanced_.*\.php$/',
    '/strict_.*\.php$/',
    '/ultra_.*\.php$/',
    '/robust_.*\.php$/',
    '/direct_.*\.php$/',
    '/manual_.*\.php$/',
    '/auto_.*\.php$/',
    '/fix_.*\.php$/',
    '/enable_.*\.php$/',
    '/check_.*\.php$/',
    
    // API test files
    '/api_.*devices.*\.php$/',
    '/.*api.*test.*\.php$/',
    
    // DHCP utilities
    '/.*dhcp.*\.php$/',
    
    // Backup files and logs
    '/.*\.bak$/',
    '/.*\.log$/',
    '/.*\.txt$/',
    '/.*\.bat$/',
    
    // Compressed files
    '/.*\.7z$/',
    '/.*\.zip$/',
    
    // Temporary/malformed files
    '/.*\.tmp$/',
    '/^close\(\)$/',
    '/^fetch_assoc\(\)$/',
    '/^query\(_.*$/',
    
    // Specific files to remove
    '/^cookies\.txt$/',
    '/^Endpoint\.txt$/',
    '/^workspace_cleanup\.php$/',
    '/^direct_cleanup\.php$/',
    '/^refactor_cleanup\.php$/',
];

// Core directories and files to preserve
$preservePatterns = [
    '/^main\//',
    '/^admin\//',
    '/^css\//',
    '/^js\//',
    '/^img\//',
    '/^includes\//',
    '/^vendor\//',
    '/^index\.php$/',
    '/^register\.php$/',
    '/^loginprocess\.php$/',
    '/^loginverification\.php$/',
    '/^logout\.php$/',
    '/^connectMySql\.php$/',
    '/^blocked\.(php|html)$/',
    '/^redirect_handler\.php$/',
    '/^create_backup\.php$/',
    '/^email_functions\.php$/',
    '/^API\/(connectMikrotik|block_user|get_active_users|update_user_status|insert_log|limit_bandwith|update_email)\.php$/',
];

function scanDirectory($dir, $baseDir = '') {
    $files = [];
    if (!is_dir($dir)) return $files;
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        $relativePath = $baseDir ? $baseDir . '/' . $item : $item;
        
        if (is_dir($fullPath)) {
            $files = array_merge($files, scanDirectory($fullPath, $relativePath));
        } else {
            $files[] = [
                'path' => $fullPath,
                'relative' => $relativePath,
                'size' => filesize($fullPath)
            ];
        }
    }
    return $files;
}

function shouldPreserve($relativePath, $preservePatterns) {
    foreach ($preservePatterns as $pattern) {
        if (preg_match($pattern, $relativePath)) {
            return true;
        }
    }
    return false;
}

function shouldRemove($relativePath, $removePatterns) {
    foreach ($removePatterns as $pattern) {
        if (preg_match($pattern, $relativePath)) {
            return true;
        }
    }
    return false;
}

// Scan workspace
$workspaceDir = __DIR__;
$allFiles = scanDirectory($workspaceDir);

// Find files to remove
$filesToRemove = [];
$totalSize = 0;

foreach ($allFiles as $file) {
    if (shouldPreserve($file['relative'], $preservePatterns)) {
        continue; // Skip preserved files
    }
    
    if (shouldRemove($file['relative'], $removePatterns)) {
        $filesToRemove[] = $file;
        $totalSize += $file['size'];
    }
}

echo "Found " . count($filesToRemove) . " files to remove\n";
echo "Total space to free: " . number_format($totalSize / 1024 / 1024, 2) . " MB\n\n";

if (empty($filesToRemove)) {
    echo "No files to remove!\n";
    exit;
}

// Remove files
$deleted = 0;
$errors = [];

foreach ($filesToRemove as $file) {
    if (file_exists($file['path'])) {
        if (unlink($file['path'])) {
            echo "✓ Deleted: " . $file['relative'] . "\n";
            $deleted++;
        } else {
            echo "✗ Error: " . $file['relative'] . "\n";
            $errors[] = $file['relative'];
        }
    }
}

echo "\n=== CLEANUP COMPLETE ===\n";
echo "Files deleted: $deleted\n";
echo "Errors: " . count($errors) . "\n";
echo "Space freed: " . number_format($totalSize / 1024 / 1024, 2) . " MB\n";
echo "\nWorkspace cleanup successful!\n";
?>
