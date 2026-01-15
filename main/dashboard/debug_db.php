<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection and table...\n";

try {
    require_once '../../connectMySql.php';
    echo "✅ Database connection successful\n";
    
    // Check if the table exists
    $result = $conn->query("SHOW TABLES LIKE 'device_time_limits'");
    if ($result->num_rows > 0) {
        echo "✅ Table 'device_time_limits' exists\n";
        
        // Check table structure
        $structure = $conn->query("DESCRIBE device_time_limits");
        echo "📋 Table structure:\n";
        while ($row = $structure->fetch_assoc()) {
            echo "  - {$row['Field']} ({$row['Type']})\n";
        }
        
        // Check if there are any records
        $count = $conn->query("SELECT COUNT(*) as count FROM device_time_limits");
        $countResult = $count->fetch_assoc();
        echo "📊 Total records: {$countResult['count']}\n";
        
        // Check active records
        $activeCount = $conn->query("SELECT COUNT(*) as count FROM device_time_limits WHERE is_active = TRUE");
        $activeResult = $activeCount->fetch_assoc();
        echo "📊 Active records: {$activeResult['count']}\n";
        
    } else {
        echo "❌ Table 'device_time_limits' does not exist\n";
        
        // Create the table
        $createSQL = "CREATE TABLE IF NOT EXISTS device_time_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mac_address VARCHAR(17) NOT NULL,
            hostname VARCHAR(255),
            time_limit_minutes INT NOT NULL,
            start_time DATETIME NOT NULL,
            end_time DATETIME,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mac_active (mac_address, is_active),
            INDEX idx_end_time (end_time)
        )";
        
        if ($conn->query($createSQL)) {
            echo "✅ Table created successfully\n";
        } else {
            echo "❌ Error creating table: " . $conn->error . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
