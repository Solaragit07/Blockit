<?php
echo "<h1>🎯 BlockIT System Status</h1>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Web server is working correctly!</p>";

echo "<hr>";
echo "<h2>🔗 Quick Links</h2>";
echo "<ul>";
echo "<li><a href='index.php'>Main Dashboard</a></li>";
echo "<li><a href='simple_connection_test.php'>Simple Connection Test</a></li>";
echo "<li><a href='get_real_time_devices.php'>Device Detection API</a></li>";
echo "</ul>";

echo "<hr>";
echo "<h2>📋 System Information</h2>";
echo "<p><strong>Server:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Script Path:</strong> " . $_SERVER['SCRIPT_FILENAME'] . "</p>";
?>
