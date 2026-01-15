<?php
require_once 'connectMySql.php';

echo "🔍 Creating missing database tables...\n";

$sql = file_get_contents('database/create_application_tables.sql');

// Split SQL into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if (!empty($statement)) {
        try {
            if ($conn->query($statement)) {
                echo "✅ SQL statement executed successfully\n";
            } else {
                echo "❌ Error: " . $conn->error . "\n";
            }
        } catch (Exception $e) {
            echo "❌ Exception: " . $e->getMessage() . "\n";
        }
    }
}

echo "✅ Database setup complete!\n";
?>
