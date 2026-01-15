<?php
// Test script to isolate the timeout issue
set_time_limit(3);

echo "Starting timeout test...\n";

echo "1. Testing session start...\n";
session_start();
echo "   Session started OK\n";

echo "2. Testing MySQL connection...\n";
include 'connectMySql.php';
echo "   MySQL connection OK\n";

echo "3. Testing login verification...\n";
include 'loginverification.php';
echo "   Login verification OK\n";

echo "4. Testing if logged in...\n";
$status = logged_in();
echo "   Logged in status: " . ($status ? "YES" : "NO") . "\n";

echo "All tests completed successfully!\n";
?>
