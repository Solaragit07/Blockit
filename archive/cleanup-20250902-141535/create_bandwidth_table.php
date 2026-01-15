<?php
require_once 'connectMySql.php';

$sql = file_get_contents('database/bandwidth_limits.sql');

try {
    $pdo->exec($sql);
    echo "✅ bandwidth_limits table created successfully\n";
} catch (PDOException $e) {
    echo "❌ Error creating table: " . $e->getMessage() . "\n";
}
?>
