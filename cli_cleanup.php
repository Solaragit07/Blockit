<?php
// CLI Workspace Cleanup Utility
// Usage (PowerShell): php cli_cleanup.php --mode archive|delete [--dry-run]

if (php_sapi_name() !== 'cli') {
    echo "Run this script from the command line.\n";
    exit(1);
}

error_reporting(E_ALL & ~E_NOTICE);

function normRel($path) {
    $path = str_replace(['\\', '\\'], '/', $path);
    $path = preg_replace('#/{2,}#', '/', $path);
    // remove leading './' or '.\\' only, keep dotfiles like .htaccess
    if (strpos($path, './') === 0) { $path = substr($path, 2); }
    if (strpos($path, '.\\') === 0) { $path = substr($path, 2); }
    return $path;
}

function listAllFiles($baseDir) {
    $files = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $abs = $file->getPathname();
        $rel = normRel(str_replace($baseDir . DIRECTORY_SEPARATOR, '', $abs));
        if (strpos($rel, 'archive/') === 0) continue; // skip archive output
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

function resolveRelativePath($baseRel, $refRel) {
    // Build a combined path and collapse '.' and '..'
    $combined = '';
    if ($baseRel !== '' && $baseRel !== '.') {
        $combined = rtrim($baseRel, '/');
        if ($refRel !== '' && $refRel[0] !== '/') { $combined .= '/' . $refRel; }
        else { $combined = $refRel; }
    } else {
        $combined = $refRel;
    }
    $parts = explode('/', $combined);
    $stack = [];
    foreach ($parts as $p) {
        if ($p === '' || $p === '.') continue;
        if ($p === '..') { array_pop($stack); continue; }
        $stack[] = $p;
    }
    return implode('/', $stack);
}

function extractRefs($content) {
    $refs = [];
    if (!is_string($content) || $content === '') return $refs;
    $patterns = [
        '/\b(include|require|require_once)\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i',
        '/header\s*\(\s*[\'\"]Location:\s*([^\'\"]+)[\'\"]/i',
        '/\b(href|src|action)\s*=\s*[\'\"]([^\'\"]+)[\'\"]/i',
        '/\bfetch\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i',
        '/\baxios\.(get|post|put|delete)\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i',
        '/\$\.ajax\s*\(\s*\{[^}]*url\s*:\s*[\'\"]([^\'\"]+)[\'\"]/is',
        '/file_get_contents\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i',
    ];
    foreach ($patterns as $rx) {
        if (preg_match_all($rx, $content, $m)) {
            $capIndex = count($m) - 1;
            foreach ($m[$capIndex] as $v) { $refs[] = $v; }
        }
    }
    $out = [];
    foreach ($refs as $r) {
        if (!is_string($r)) { continue; }
        $r = trim($r);
        if ($r === '' || strpos($r, 'mailto:') === 0 || preg_match('#^https?://#i', $r)) {
            if (preg_match('#https?://[^/]+/blockit/(.+)$#i', $r, $mm)) { $out[] = normRel($mm[1]); }
            continue;
        }
    $parsed = @parse_url($r);
    if (is_array($parsed) && isset($parsed['path'])) { $r = $parsed['path']; }
    if (!is_string($r)) { continue; }
    $r = ltrim($r, '/');
        $out[] = normRel($r);
    }
    return array_values(array_unique(array_filter($out)));
}

function readFileSafe($abs) {
    $size = @filesize($abs);
    if ($size === false || $size > 2_000_000) return '';
    $c = @file_get_contents($abs);
    return is_string($c) ? $c : '';
}

function ensureDir($path) {
    if (!is_dir($path)) {@mkdir($path, 0777, true);} }

function formatSize($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// Parse args
$mode = 'archive';
$dryRun = false;
foreach ($argv as $a) {
    if (preg_match('/--mode=(archive|delete)/i', $a, $m)) { $mode = strtolower($m[1]); }
    if ($a === '--dry-run') { $dryRun = true; }
}

$workspaceDir = __DIR__;
$all = listAllFiles($workspaceDir);

$preserveDirs = [
    'admin/', 'main/', 'API/', 'vendor/', 'css/', 'js/', 'includes/', 'assets/', 'img/', 'image/', 'uploads/', 'config/',
    'blockit/', 'database/', 'nodejs-mikrotik-api/', 'realtime-server/'
];
$preserveTop = [
    'index.php', 'register.php', 'loginprocess.php', 'loginverification.php', 'logout.php',
    'connectMySql.php', 'blocked.php', 'blocked.html', 'redirect_handler.php',
    'workspace_cleanup.php', 'cli_cleanup.php', '.htaccess', '.htaccess.backup',
    'email_functions.php'
];

// Seed keep set
$keep = [];
foreach ($all as $rel => $_) { if (withinAny($rel, $preserveDirs)) { $keep[$rel] = true; } }
foreach ($preserveTop as $rel) { if (isset($all[$rel])) $keep[$rel] = true; }

// Traverse references
$queue = [];
foreach ($keep as $rel => $_) { if (preg_match('/\.(php|html|js|css)$/i', $rel)) $queue[] = $rel; }
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
        $baseDirRel = dirname($current);
        if ($baseDirRel === '.') $baseDirRel = '';
        if ($ref !== '' && $ref[0] === '.') {
            $combo = resolveRelativePath($baseDirRel, $ref);
            $combo = normRel($combo);
        } else {
            $combo = normRel($ref);
        }
        if (isset($all[$combo]) && !isset($keep[$combo])) {
            $keep[$combo] = true;
            if (preg_match('/\.(php|html|js|css)$/i', $combo)) $queue[] = $combo;
        }
    }
}

$filesToRemove = [];
$removableSize = 0; $totalSize = 0;
foreach ($all as $rel => $meta) {
    $totalSize += $meta['size'];
    if (!isset($keep[$rel])) { $filesToRemove[] = $meta + ['relative' => $rel]; $removableSize += $meta['size']; }
}

echo "BlockIT CLI cleanup\n";
echo "Total files: " . count($all) . ", Unreferenced: " . count($filesToRemove) . ", Reclaim: " . formatSize($removableSize) . "\n";

if ($dryRun) {
    foreach ($filesToRemove as $f) echo "DRY-RUN: would remove " . $f['relative'] . " (" . formatSize($f['size']) . ")\n";
    exit(0);
}

if (empty($filesToRemove)) { echo "Nothing to $mode.\n"; exit(0); }

if (!in_array($mode, ['archive','delete'])) { echo "Invalid mode. Use --mode=archive or --mode=delete\n"; exit(1); }

$ts = date('Ymd-His');
$archRoot = $workspaceDir . DIRECTORY_SEPARATOR . 'archive' . DIRECTORY_SEPARATOR . 'cleanup-' . $ts;
if ($mode === 'archive') ensureDir($archRoot);

$done = 0; $errors = 0;
foreach ($filesToRemove as $file) {
    $src = $file['abs'];
    if (!file_exists($src)) { continue; }
    if ($mode === 'archive') {
        $dst = $archRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file['relative']);
        ensureDir(dirname($dst));
        if (@rename($src, $dst)) { $done++; echo "Archived: {$file['relative']}\n"; } else { $errors++; echo "ERROR archiving: {$file['relative']}\n"; }
    } else {
        if (@unlink($src)) { $done++; echo "Deleted: {$file['relative']}\n"; } else { $errors++; echo "ERROR deleting: {$file['relative']}\n"; }
    }
}

echo "Completed: $done files processed, errors: $errors.\n";
echo ($mode === 'archive') ? ("Archive path: " . $archRoot . "\n") : '';
?>
