<?php
/**
 * BlockIT Workspace Cleanup (Dependency-aware)
 * Safely identify files not referenced by the User/Admin modules and archive or delete them.
 *
 * Approach:
 * - Treat entire admin/ and main/ as core modules (kept)
 * - Discover API and top-level files referenced by admin/main via includes, links, ajax/fetch, and redirects
 * - Preserve common asset folders (css/js/vendor/img/assets/includes)
 * - Everything else is a candidate for archive/delete with a review list
 */

session_start();

// Color scheme
$colors = [
    'primary' => '#0dcaf0',
    'success' => '#28a745',
    'warning' => '#ffc107',
    'danger' => '#dc3545',
    'dark' => '#343a40'
];

// Helpers
function normRel($path) {
    $path = str_replace(['\\', '\\'], '/', $path);
    $path = preg_replace('#/{2,}#', '/', $path);
    $path = ltrim($path, './');
    return $path;
}

function listAllFiles($baseDir) {
    $files = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $abs = $file->getPathname();
        $rel = normRel(str_replace($baseDir . DIRECTORY_SEPARATOR, '', $abs));
        // Skip archive output itself
        if (strpos($rel, 'archive/') === 0) continue;
        $files[$rel] = [ 'abs' => $abs, 'size' => $file->getSize() ];
    }
    return $files;
}

function withinAny($rel, $prefixes) {
    foreach ($prefixes as $p) {
        if ($p === '') continue;
        if (strpos($rel, $p) === 0) return true;
    }
    return false;
}

function extractRefs($content) {
    $refs = [];
    if (!is_string($content) || $content === '') return $refs;

    $patterns = [
        // PHP includes/requires
    '/\b(include|include_once|require|require_once)\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i',
    '/\b(include|include_once|require|require_once)\s*[\'\"]([^\'\"]+)[\'\"]/i',
        // header Location
        '/header\s*\(\s*[\'\"]Location:\s*([^\'\"]+)[\'\"]/i',
        // HTML href/src/action
        '/\b(href|src|action)\s*=\s*[\'\"]([^\'\"]+)[\'\"]/i',
        // JS fetch / axios / $.ajax URL
        '/\bfetch\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i',
        '/\baxios\.(get|post|put|delete)\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i',
        '/\$\.ajax\s*\(\s*\{[^}]*url\s*:\s*[\'\"]([^\'\"]+)[\'\"]/is',
        // file_get_contents/curl
        '/file_get_contents\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i',
    ];
    foreach ($patterns as $rx) {
        if (preg_match_all($rx, $content, $m)) {
            // last capturing group has the URL/path
            $capIndex = count($m) - 1; 
            foreach ($m[$capIndex] as $v) {
                $refs[] = $v;
            }
        }
    }

    // Normalize to workspace-relative where possible
    $out = [];
    foreach ($refs as $r) {
        $r = trim($r);
        if ($r === '' || strpos($r, 'mailto:') === 0 || preg_match('#^https?://#i', $r)) {
            // Map http://localhost/blockit/... back to relative
            if (preg_match('#https?://[^/]+/blockit/(.+)$#i', $r, $mm)) {
                $out[] = normRel($mm[1]);
            }
            continue;
        }
    // strip query/hash
    $parsed = @parse_url($r);
    if (is_array($parsed) && isset($parsed['path'])) { $r = $parsed['path']; }
    // drop leading /
    $r = ltrim($r, '/');
    $out[] = normRel($r);
    }
    return array_values(array_unique(array_filter($out)));
}

function readFileSafe($abs) {
    $size = @filesize($abs);
    if ($size === false || $size > 2_000_000) return ''; // skip huge files
    $c = @file_get_contents($abs);
    return is_string($c) ? $c : '';
}

function ensureDir($path) {
    if (!is_dir($path)) {
        @mkdir($path, 0777, true);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BlockIT Workspace Cleanup</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, <?php echo $colors['primary']; ?> 0%, #087990 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: <?php echo $colors['dark']; ?>;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .file-category {
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }
        .category-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .category-title {
            font-weight: 600;
            color: <?php echo $colors['dark']; ?>;
        }
        .file-count {
            background: <?php echo $colors['primary']; ?>;
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85em;
        }
        .file-list {
            max-height: 300px;
            overflow-y: auto;
            padding: 15px 20px;
        }
        .file-item {
            padding: 8px 0;
            border-bottom: 1px solid #f1f3f4;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .file-item:last-child {
            border-bottom: none;
        }
        .file-name {
            font-family: monospace;
            color: #495057;
        }
        .file-size {
            font-size: 0.8em;
            color: #6c757d;
        }
        .btn {
            background: <?php echo $colors['danger']; ?>;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px;
            transition: all 0.3s ease;
        }
        .btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .btn-success {
            background: <?php echo $colors['success']; ?>;
        }
        .btn-success:hover {
            background: #218838;
        }
        .summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: <?php echo $colors['success']; ?>;
            transition: width 0.3s ease;
        }
        .core-files {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-broom"></i> BlockIT Workspace Cleanup</h1>
            <p>Remove unused files, test files, and diagnostic files while preserving core functionality</p>
        </div>
        
        <div class="content">
            <?php
            // Build dependency-aware keep set
            function formatFileSize($bytes) {
                if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
                if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
                return $bytes . ' B';
            }

            $workspaceDir = __DIR__;
            $all = listAllFiles($workspaceDir);

            $preserveDirs = [
                'admin/', 'main/', 'vendor/', 'css/', 'js/', 'includes/', 'assets/', 'img/', 'image/', 'uploads/', 'config/'
            ];
            $preserveTop = [
                'index.php', 'register.php', 'loginprocess.php', 'loginverification.php', 'logout.php',
                'connectMySql.php', 'blocked.php', 'blocked.html', 'redirect_handler.php'
            ];

            // Seeds: anything under preserveDirs + preserveTop files that exist
            $keep = [];
            foreach ($all as $rel => $_) {
                if (withinAny($rel, $preserveDirs)) { $keep[$rel] = true; }
            }
            foreach ($preserveTop as $rel) {
                if (isset($all[$rel])) $keep[$rel] = true;
            }

            // Discover API and other references from admin/main + top
            $queue = [];
            foreach ($keep as $rel => $_) {
                // only enqueue code-like files to parse
                if (preg_match('/\.(php|html|js|css)$/i', $rel)) $queue[] = $rel;
            }

            $visited = [];
            while ($queue) {
                $current = array_shift($queue);
                if (isset($visited[$current])) continue;
                $visited[$current] = true;
                $abs = $all[$current]['abs'] ?? null;
                if (!$abs || !is_file($abs)) continue;
                $content = readFileSafe($abs);
                if ($content === '') continue;
                $refs = extractRefs($content);
                foreach ($refs as $ref) {
                    // Normalize relative paths (handle ./ and ../ from current)
                    $baseDirRel = dirname($current);
                    if ($baseDirRel === '.') $baseDirRel = '';
                    if ($ref !== '' && $ref[0] === '.') {
                        // Resolve ../ and ./ segments
                        $parts = explode('/', ($baseDirRel ? $baseDirRel . '/' : '') . $ref);
                        $stack = [];
                        foreach ($parts as $p) {
                            if ($p === '' || $p === '.') continue;
                            if ($p === '..') { array_pop($stack); continue; }
                            $stack[] = $p;
                        }
                        $combo = normRel(implode('/', $stack));
                    } else {
                        $combo = normRel($ref);
                    }
                    // Only keep within workspace
                    if (isset($all[$combo])) {
                        if (!isset($keep[$combo])) {
                            $keep[$combo] = true;
                            if (preg_match('/\.(php|html|js|css)$/i', $combo)) $queue[] = $combo;
                        }
                    }
                }
            }

            // Separate keep and candidates
            $coreFilesFound = [];
            $filesToRemove = [];
            $totalSize = 0; $removableSize = 0;
            foreach ($all as $rel => $meta) {
                $totalSize += $meta['size'];
                if (isset($keep[$rel])) {
                    $coreFilesFound[] = ['relative' => $rel, 'path' => $meta['abs'], 'size' => $meta['size']];
                } else {
                    $filesToRemove[] = ['relative' => $rel, 'path' => $meta['abs'], 'size' => $meta['size']];
                    $removableSize += $meta['size'];
                }
            }

            // Categorize removal candidates for readability
            $categories = [
                'API (Unreferenced)' => [],
                'Top-level Scripts (Unreferenced)' => [],
                'Docs/Markdown' => [],
                'Node Services' => [],
                'Realtimes/Servers' => [],
                'Other Assets' => [],
            ];
            foreach ($filesToRemove as $file) {
                $rel = $file['relative'];
                if (strpos($rel, 'API/') === 0) {
                    $categories['API (Unreferenced)'][] = $file;
                } elseif (preg_match('/\.(md|markdown)$/i', $rel)) {
                    $categories['Docs/Markdown'][] = $file;
                } elseif (strpos($rel, 'nodejs-mikrotik-api/') === 0) {
                    $categories['Node Services'][] = $file;
                } elseif (strpos($rel, 'realtime-server/') === 0) {
                    $categories['Realtimes/Servers'][] = $file;
                } elseif (strpos($rel, '/') === false) {
                    $categories['Top-level Scripts (Unreferenced)'][] = $file;
                } else {
                    $categories['Other Assets'][] = $file;
                }
            }

            $action = $_POST['action'] ?? '';
            if ($action === 'archive' || $action === 'delete') {
                $ts = date('Ymd-His');
                $archDir = $workspaceDir . DIRECTORY_SEPARATOR . 'archive' . DIRECTORY_SEPARATOR . 'cleanup-' . $ts;
                if ($action === 'archive') ensureDir($archDir);
                echo "<div class='summary'>";
                echo "<h3><i class='fas fa-cog fa-spin'></i> " . ($action==='archive'?'Archiving':'Deleting') . " Unused Files...</h3>";
                echo "<div class='progress-bar'><div class='progress-fill' style='width: 0%'></div></div>";
                echo "</div>";

                $done = 0; $errors = []; $totalFiles = count($filesToRemove);
                foreach ($filesToRemove as $idx => $file) {
                    $src = $file['path'];
                    if (!file_exists($src)) { $done++; continue; }
                    if ($action === 'archive') {
                        $dst = $archDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file['relative']);
                        ensureDir(dirname($dst));
                        if (@rename($src, $dst)) { $done++; } else { $errors[] = $file['relative']; }
                    } else {
                        if (@unlink($src)) { $done++; } else { $errors[] = $file['relative']; }
                    }
                    $progress = ($idx + 1) / max(1,$totalFiles) * 100;
                    echo "<script>document.querySelector('.progress-fill').style.width = '" . round($progress,2) . "%';</script>";
                    flush();
                }
                echo "<div class='summary'>";
                echo "<h3><i class='fas fa-check-circle' style='color: {$colors['success']}'></i> " . ($action==='archive'?'Archive':'Delete') . " Complete</h3>";
                echo "<p><strong>Files Processed:</strong> {$done}</p>";
                echo "<p><strong>Space Impact:</strong> " . formatFileSize($removableSize) . "</p>";
                if (!empty($errors)) {
                    echo "<p><strong>Errors:</strong> " . count($errors) . " files could not be processed</p>";
                }
                echo "</div>";
                if (!empty($errors)) {
                    echo "<div class='file-category'>";
                    echo "<div class='category-header'>";
                    echo "<span class='category-title'><i class='fas fa-exclamation-triangle'></i> Files with Errors</span>";
                    echo "<span class='file-count'>" . count($errors) . "</span>";
                    echo "</div><div class='file-list'>";
                    foreach ($errors as $e) echo "<div class='file-item'><span class='file-name'>{$e}</span></div>";
                    echo "</div></div>";
                }
            } else {
                // Show analysis
                echo "<div class='summary'>";
                echo "<h3><i class='fas fa-chart-bar'></i> Workspace Analysis (Dependency-aware)</h3>";
                echo "<p><strong>Total Files:</strong> " . count($all) . "</p>";
                echo "<p><strong>Unused Candidates:</strong> " . count($filesToRemove) . "</p>";
                echo "<p><strong>Preserved (User/Admin/Assets):</strong> " . count($coreFilesFound) . "</p>";
                echo "<p><strong>Total Size:</strong> " . formatFileSize(array_sum(array_column($all, 'size'))) . "</p>";
                echo "<p><strong>Space to Reclaim:</strong> " . formatFileSize($removableSize) . "</p>";
                echo "</div>";

                echo "<div class='warning-box'>";
                echo "<i class='fas fa-exclamation-triangle'></i> <strong>Tip:</strong> Prefer Archive to allow easy rollback. Delete is permanent.";
                echo "</div>";

                foreach ($categories as $categoryName => $categoryFiles) {
                    if (empty($categoryFiles)) continue;
                    $categorySize = array_sum(array_column($categoryFiles, 'size'));
                    echo "<div class='file-category'>";
                    echo "<div class='category-header'>";
                    echo "<span class='category-title'><i class='fas fa-folder-minus'></i> {$categoryName}</span>";
                    echo "<span class='file-count'>" . count($categoryFiles) . " files (" . formatFileSize($categorySize) . ")</span>";
                    echo "</div><div class='file-list'>";
                    foreach ($categoryFiles as $file) {
                        echo "<div class='file-item'><span class='file-name'>{$file['relative']}</span><span class='file-size'>" . formatFileSize($file['size']) . "</span></div>";
                    }
                    echo "</div></div>";
                }

                echo "<div class='file-category core-files'>";
                echo "<div class='category-header'>";
                echo "<span class='category-title'><i class='fas fa-shield-alt'></i> Preserved Files (User/Admin/Dependencies)</span>";
                echo "<span class='file-count'>" . count($coreFilesFound) . " files</span>";
                echo "</div><div class='file-list'>";
                foreach (array_slice($coreFilesFound, 0, 30) as $file) {
                    echo "<div class='file-item'><span class='file-name'>{$file['relative']}</span><span class='file-size'>" . formatFileSize($file['size']) . "</span></div>";
                }
                if (count($coreFilesFound) > 30) {
                    echo "<div class='file-item'><span class='file-name'>... and " . (count($coreFilesFound) - 30) . " more</span></div>";
                }
                echo "</div></div>";

                echo "<div style='text-align: center; margin: 30px 0;'>";
                echo "<form method='POST' style='display:inline;margin:0 10px;'>";
                echo "<input type='hidden' name='action' value='archive'>";
                echo "<button type='submit' class='btn btn-success' onclick='return confirm(\"Archive " . count($filesToRemove) . " files to /archive?\")'><i class='fas fa-box'></i> Archive Unused</button>";
                echo "</form>";
                echo "<form method='POST' style='display:inline;margin:0 10px;'>";
                echo "<input type='hidden' name='action' value='delete'>";
                echo "<button type='submit' class='btn' onclick='return confirm(\"Permanently delete " . count($filesToRemove) . " files? This cannot be undone.\")'><i class='fas fa-trash'></i> Delete Unused</button>";
                echo "</form>";
                echo "</div>";
            }
            ?>
        </div>
    </div>
    
    <script>
        // Auto-refresh progress bar
        if (document.querySelector('.progress-fill')) {
            setInterval(() => {
                location.reload();
            }, 2000);
        }
    </script>
</body>
</html>
