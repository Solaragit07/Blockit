<?php
// Setup device sessions table
include 'connectMySql.php';

$sql = "CREATE TABLE IF NOT EXISTS device_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mac_address VARCHAR(17) NOT NULL,
    session_start DATETIME NOT NULL,
    session_end DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mac_date (mac_address, session_start)
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Device sessions table created successfully\n";
} else {
    echo "❌ Error creating table: " . $conn->error . "\n";
}

// Insert some test sessions for the mock devices
$testSessions = [
    ['02:00:00:00:00:01', '2025-08-20 08:00:00'], // iPhone started 2 hours ago
    ['02:00:00:00:00:02', '2025-08-20 09:30:00'], // MacBook started 30 minutes ago
    ['02:00:00:00:00:03', '2025-08-20 09:45:00']  // Samsung started 15 minutes ago
];

foreach ($testSessions as $session) {
    $mac = $session[0];
    $startTime = $session[1];
    
    // Check if session already exists
    $checkQuery = "SELECT id FROM device_sessions WHERE mac_address = ? AND DATE(session_start) = CURDATE() AND session_end IS NULL";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("s", $mac);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows == 0) {
        // Insert new session
        $insertQuery = "INSERT INTO device_sessions (mac_address, session_start) VALUES (?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("ss", $mac, $startTime);
        
        if ($insertStmt->execute()) {
            echo "✅ Created test session for $mac\n";
        } else {
            echo "❌ Error creating session for $mac: " . $conn->error . "\n";
        }
        $insertStmt->close();
    } else {
        echo "ℹ️ Session already exists for $mac\n";
    }
    $checkStmt->close();
}

$conn->close();
echo "\n🎯 Database setup complete! Refresh your dashboard to see persistent time tracking.\n";
?>
