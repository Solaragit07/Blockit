<?php
require_once 'connectMySql.php';

echo "🔍 Checking database tables...\n";

// Check for application_blocks table
$result = $pdo->query('SHOW TABLES LIKE "application_blocks"');
if ($result->rowCount() == 0) {
    echo "❌ application_blocks table missing\n";
} else {
    echo "✅ application_blocks table exists\n";
}

// Check for application_categories table
$result = $pdo->query('SHOW TABLES LIKE "application_categories"');
if ($result->rowCount() == 0) {
    echo "❌ application_categories table missing\n";
} else {
    echo "✅ application_categories table exists\n";
}

// Check for device table
$result = $pdo->query('SHOW TABLES LIKE "device"');
if ($result->rowCount() == 0) {
    echo "❌ device table missing\n";
} else {
    echo "✅ device table exists\n";
}
?>
