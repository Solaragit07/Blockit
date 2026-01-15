<?php
/**
 * BlockIt Database Setup Script for XAMPP
 * This script will create the database and all required tables
 * Run this script once to set up your BlockIt system
 */

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "blockit";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>BlockIt Database Setup</h2>";
echo "<p>Setting up BlockIt database...</p>";

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green;'>✓ Database '$dbname' created successfully or already exists</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating database: " . $conn->error . "</p>";
    exit();
}

// Select database
$conn->select_db($dbname);

// Read and execute SQL file
$sqlFile = __DIR__ . '/blockit_complete.sql';

if (!file_exists($sqlFile)) {
    echo "<p style='color: red;'>✗ SQL file not found: $sqlFile</p>";
    exit();
}

$sql = file_get_contents($sqlFile);

// Remove comments and split into individual queries
$sql = preg_replace('/--.*$/m', '', $sql);
$sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
$queries = array_filter(array_map('trim', explode(';', $sql)));

$successCount = 0;
$errorCount = 0;
$errors = [];

foreach ($queries as $query) {
    if (empty($query)) continue;
    
    if ($conn->multi_query($query)) {
        do {
            // Store first result set
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        
        $successCount++;
    } else {
        $errorCount++;
        $errors[] = "Error in query: " . substr($query, 0, 50) . "... - " . $conn->error;
    }
}

echo "<h3>Setup Results:</h3>";
echo "<p style='color: green;'>✓ Successful queries: $successCount</p>";

if ($errorCount > 0) {
    echo "<p style='color: red;'>✗ Failed queries: $errorCount</p>";
    echo "<h4>Errors:</h4>";
    foreach ($errors as $error) {
        echo "<p style='color: red; font-size: 12px;'>$error</p>";
    }
} else {
    echo "<p style='color: green;'>✓ All tables created successfully!</p>";
}

// Verify tables
echo "<h3>Database Tables:</h3>";
$result = $conn->query("SHOW TABLES");
if ($result->num_rows > 0) {
    echo "<ul>";
    while($row = $result->fetch_array()) {
        echo "<li>" . $row[0] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>No tables found</p>";
}

// Test admin login
echo "<h3>Default Admin Account:</h3>";
$result = $conn->query("SELECT * FROM admin WHERE email = 'admin@gmail.com'");
if ($result && $result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Default admin account created</p>";
    echo "<p><strong>Email:</strong> admin@gmail.com</p>";
    echo "<p><strong>Password:</strong> 123</p>";
    echo "<p style='color: orange;'>⚠️ Please change the default password after first login!</p>";
} else {
    echo "<p style='color: red;'>✗ Admin account not found</p>";
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>BlockIt Database Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h2 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        h3 {
            color: #007bff;
            margin-top: 30px;
        }
        ul {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="success">
        <h4>Setup Complete!</h4>
        <p>Your BlockIt database has been set up successfully. You can now:</p>
        <ol>
            <li>Access your BlockIt application</li>
            <li>Login with the default admin credentials</li>
            <li>Start configuring your blocking rules</li>
        </ol>
    </div>
    
    <div class="warning">
        <h4>Important Security Notes:</h4>
        <ul>
            <li>Change the default admin password immediately</li>
            <li>Remove or secure this setup file after use</li>
            <li>Configure proper database user permissions</li>
        </ul>
    </div>
</body>
</html>