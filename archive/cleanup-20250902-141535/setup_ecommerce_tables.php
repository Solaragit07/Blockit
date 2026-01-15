<?php
// Simple setup runner for e-commerce tables
require_once __DIR__ . '/connectMySql.php';

$sqlFile = __DIR__ . '/database/create_ecommerce_tables.sql';
if (!file_exists($sqlFile)) {
    echo "SQL file not found: $sqlFile\n";
    exit(1);
}

$sql = file_get_contents($sqlFile);
// Normalize newlines
$sql = str_replace(["\r\n", "\r"], "\n", $sql);
// Strip single-line comments starting with --
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
// Split by semicolon terminators
$rawParts = preg_split('/;\s*\n/', $sql);
$queries = [];
foreach ($rawParts as $part) {
    $part = trim($part);
    if ($part !== '') { $queries[] = $part; }
}

$ok = 0; $fail = 0; $errors = [];
foreach ($queries as $q) {
    if ($conn->query($q) === TRUE) { $ok++; }
    else { $fail++; $errors[] = $conn->error . " in query: " . $q; }
}

echo "Executed: $ok successful, $fail failed\n";
if ($fail) { print_r($errors); }
// Seed common platforms if table exists and is empty
if (!$fail) {
    try {
        $check = $conn->query("SHOW TABLES LIKE 'ecommerce_platforms'");
        if ($check && $check->num_rows > 0) {
            $cntRes = $conn->query("SELECT COUNT(*) AS c FROM ecommerce_platforms");
            $row = $cntRes ? $cntRes->fetch_assoc() : ['c'=>0];
            if ((int)$row['c'] === 0) {
                $seed = [
                    ['Amazon','amazon.com','browsing',''],
                    ['eBay','ebay.com','browsing',''],
                    ['Shopee','shopee.ph','browsing',''],
                    ['Lazada','lazada.com.ph','browsing',''],
                    ['SHEIN','shein.com','browsing','']
                ];
                $stmt = $conn->prepare("INSERT INTO ecommerce_platforms (name,url,access,reason) VALUES (?,?,?,?)");
                foreach ($seed as $s) { $stmt->bind_param('ssss', $s[0], $s[1], $s[2], $s[3]); $stmt->execute(); }
                $stmt->close();
                echo "Seeded common platforms (5)\n";
            }
        }
    } catch (Throwable $e) { echo "Seed error: " . $e->getMessage() . "\n"; }
}
exit($fail ? 1 : 0);
?>
