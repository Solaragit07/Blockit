<?php
session_start();

// Clear all device detection caches
unset($_SESSION['mikrotik_internet_devices']);
unset($_SESSION['realtime_devices']);
unset($_SESSION['mikrotik_devices']);

echo "<h1>🧹 Cache Cleared</h1>";
echo "<p>All device detection caches have been cleared.</p>";
echo "<p><a href='main/dashboard/'>Go to Dashboard</a> to see fresh results</p>";
echo "<p><a href='test_device_fix.php'>Run Device Fix Test</a></p>";
echo "<p><a href='debug_device_detection_detailed.php'>Run Detailed Debug</a></p>";
?>
