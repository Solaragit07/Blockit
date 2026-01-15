<?php
require_once 'connectMySql.php';

echo "🔍 Creating age-based filter tables...\n";

$sql = file_get_contents('database/age_based_tables.sql');

// Split SQL into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if (!empty($statement)) {
        try {
            if ($conn->query($statement)) {
                echo "✅ Age-based table created successfully\n";
            } else {
                echo "❌ Error: " . $conn->error . "\n";
            }
        } catch (Exception $e) {
            echo "❌ Exception: " . $e->getMessage() . "\n";
        }
    }
}

echo "✅ Age-based filtering setup complete!\n";
?>
