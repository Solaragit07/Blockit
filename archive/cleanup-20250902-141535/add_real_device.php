<?php
include 'connectMySql.php';

if ($_POST['action'] ?? '' === 'add_device') {
    $deviceName = $_POST['device_name'] ?? '';
    $macAddress = $_POST['mac_address'] ?? '';
    $ipAddress = $_POST['ip_address'] ?? '';
    $deviceType = $_POST['device_type'] ?? 'Unknown';
    
    if (!empty($deviceName) && !empty($macAddress) && !empty($ipAddress)) {
        // Add to device_profiles table
        $query = "INSERT INTO device_profiles (device_name, mac_address, ip_address, device_type, created_at) 
                  VALUES (?, ?, ?, ?, NOW()) 
                  ON DUPLICATE KEY UPDATE 
                  device_name = VALUES(device_name), 
                  ip_address = VALUES(ip_address), 
                  device_type = VALUES(device_type)";
        
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("ssss", $deviceName, $macAddress, $ipAddress, $deviceType);
            if ($stmt->execute()) {
                $message = "✅ Device '$deviceName' added successfully!";
            } else {
                $message = "❌ Error adding device: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "❌ Database error: " . $conn->error;
        }
    } else {
        $message = "❌ Please fill in all required fields";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Real Device</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <h1>📱 Add Real Device Manually</h1>
    
    <?php if (isset($message)): ?>
        <div class="message <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <div class="info">
        <strong>📋 How to find device information:</strong><br>
        • <strong>Device Name:</strong> Check WiFi settings or device name<br>
        • <strong>MAC Address:</strong> Look in device network settings (format: AA:BB:CC:DD:EE:FF)<br>
        • <strong>IP Address:</strong> Check device network info or router admin panel<br>
    </div>
    
    <form method="POST">
        <input type="hidden" name="action" value="add_device">
        
        <div class="form-group">
            <label for="device_name">Device Name *:</label>
            <input type="text" id="device_name" name="device_name" placeholder="e.g., John's iPhone" required>
        </div>
        
        <div class="form-group">
            <label for="mac_address">MAC Address *:</label>
            <input type="text" id="mac_address" name="mac_address" placeholder="e.g., 00:1A:2B:3C:4D:5E" required>
        </div>
        
        <div class="form-group">
            <label for="ip_address">IP Address *:</label>
            <input type="text" id="ip_address" name="ip_address" placeholder="e.g., 192.168.1.100" required>
        </div>
        
        <div class="form-group">
            <label for="device_type">Device Type:</label>
            <select id="device_type" name="device_type">
                <option value="Smartphone">📱 Smartphone</option>
                <option value="Laptop">💻 Laptop</option>
                <option value="Desktop">🖥️ Desktop</option>
                <option value="Tablet">📱 Tablet</option>
                <option value="Smart TV">📺 Smart TV</option>
                <option value="Gaming Console">🎮 Gaming Console</option>
                <option value="Other">🔧 Other</option>
            </select>
        </div>
        
        <button type="submit">➕ Add Device</button>
    </form>
    
    <div style="margin-top: 30px;">
        <a href="main/dashboard/" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">🏠 View Dashboard</a>
        <a href="test_real_devices.php" style="background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-left: 10px;">🧪 Test Router Connection</a>
    </div>
</body>
</html>
