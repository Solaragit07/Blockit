<?php
/**
 * BlockIT Direct Cleanup Script
 * Removes unused files directly via command line execution
 */

// Set time limit and error reporting
set_time_limit(300);
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== BlockIT Workspace Cleanup Script ===\n";
echo "Starting cleanup process...\n\n";

// Define files to keep (core functionality)
$corePatterns = [
    // Main entry points
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
    
    // Keep entire directories
    '/^main\//',
    '/^admin\//',
    '/^css\//',
    '/^js\//',
    '/^img\//',
    '/^includes\//',
    '/^vendor\//',
    
    // Essential API files only
    '/^API\/(connectMikrotik|block_user|get_active_users|update_user_status|insert_log|limit_bandwith|update_email)\.php$/',
];

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
    
    // Specific files
    '/^cookies\.txt$/',
    '/^Endpoint\.txt$/',
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
            // Recursively scan subdirectories
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

function shouldKeepFile($relativePath, $corePatterns) {
    foreach ($corePatterns as $pattern) {
        if (preg_match($pattern, $relativePath)) {
            return true;
        }
    }
    return false;
}

function shouldRemoveFile($relativePath, $removePatterns) {
    foreach ($removePatterns as $pattern) {
        if (preg_match($pattern, $relativePath)) {
            return true;
        }
    }
    return false;
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

// Scan workspace
$workspaceDir = __DIR__;
echo "Scanning workspace directory: $workspaceDir\n";

$allFiles = scanDirectory($workspaceDir);
echo "Found " . count($allFiles) . " total files\n\n";

// Categorize files
$filesToRemove = [];
$filesToKeep = [];
$totalSize = 0;
$removableSize = 0;

foreach ($allFiles as $file) {
    $totalSize += $file['size'];
    
    if (shouldKeepFile($file['relative'], $corePatterns)) {
        $filesToKeep[] = $file;
    } elseif (shouldRemoveFile($file['relative'], $removePatterns)) {
        $filesToRemove[] = $file;
        $removableSize += $file['size'];
    } else {
        $filesToKeep[] = $file;
    }
}

echo "=== ANALYSIS RESULTS ===\n";
echo "Total files: " . count($allFiles) . "\n";
echo "Files to keep: " . count($filesToKeep) . "\n";
echo "Files to remove: " . count($filesToRemove) . "\n";
echo "Total size: " . formatFileSize($totalSize) . "\n";
echo "Space to free: " . formatFileSize($removableSize) . "\n\n";

if (empty($filesToRemove)) {
    echo "No files to remove!\n";
    exit;
}

echo "=== FILES TO BE REMOVED ===\n";
foreach ($filesToRemove as $file) {
    echo $file['relative'] . " (" . formatFileSize($file['size']) . ")\n";
}

echo "\n=== CORE FILES TO KEEP (sample) ===\n";
$keepSample = array_slice($filesToKeep, 0, 20);
foreach ($keepSample as $file) {
    echo $file['relative'] . "\n";
}
if (count($filesToKeep) > 20) {
    echo "... and " . (count($filesToKeep) - 20) . " more files\n";
}

echo "\nDo you want to proceed with deletion? (y/N): ";
$handle = fopen("php://stdin", "r");
$confirmation = trim(fgets($handle));
fclose($handle);

if (strtolower($confirmation) !== 'y') {
    echo "Cleanup cancelled.\n";
    exit;
}

echo "\n=== STARTING CLEANUP ===\n";
$deleted = 0;
$errors = [];

foreach ($filesToRemove as $file) {
    if (file_exists($file['path'])) {
        if (unlink($file['path'])) {
            echo "✓ Deleted: " . $file['relative'] . "\n";
            $deleted++;
        } else {
            echo "✗ Error deleting: " . $file['relative'] . "\n";
            $errors[] = $file['relative'];
        }
    } else {
        echo "! File not found: " . $file['relative'] . "\n";
    }
}

echo "\n=== CLEANUP COMPLETE ===\n";
echo "Files deleted: $deleted\n";
echo "Errors: " . count($errors) . "\n";
echo "Space freed: " . formatFileSize($removableSize) . "\n";

if (!empty($errors)) {
    echo "\nFiles with errors:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
}

echo "\nCleanup complete! Your workspace has been refactored.\n";
echo "Core user and admin modules have been preserved.\n";
?>
