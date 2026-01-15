<!DOCTYPE html>
<html>
<head>
    <title>BlockIt Dashboard Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>BlockIt Dashboard Diagnostics</h1>
    
    <div class="test-section">
        <h2>1. Session Test</h2>
        <?php
        session_start();
        if (isset($_SESSION['user_id'])) {
            echo '<p class="success">✅ Session active - User ID: ' . $_SESSION['user_id'] . '</p>';
        } else {
            echo '<p class="error">❌ No active session</p>';
            echo '<p class="info">Setting temporary session for testing...</p>';
            $_SESSION['user_id'] = 1; // Temporary
        }
        ?>
    </div>

    <div class="test-section">
        <h2>2. Database Connection Test</h2>
        <?php
        try {
            include '../connectMySql.php';
            echo '<p class="success">✅ Database connection successful</p>';
        } catch (Exception $e) {
            echo '<p class="error">❌ Database connection failed: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>

    <div class="test-section">
        <h2>3. MikroTik API Test</h2>
        <?php
        try {
            include '../API/connectMikrotik.php';
            if (isset($client) && $client !== null) {
                echo '<p class="success">✅ MikroTik API connection successful</p>';
            } else {
                echo '<p class="error">❌ MikroTik API client is null</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">❌ MikroTik API connection failed: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>

    <div class="test-section">
        <h2>4. Device Detection Service Test</h2>
        <?php
        if (isset($client) && $client !== null) {
            try {
                include '../includes/DeviceDetectionService.php';
                $deviceService = new DeviceDetectionService($client, $conn);
                echo '<p class="success">✅ Device detection service initialized</p>';
                
                $deviceData = $deviceService->getInternetConnectedDevices();
                echo '<p class="success">✅ Device data retrieved</p>';
                echo '<p class="info">Found ' . count($deviceData['devices']) . ' devices</p>';
                
                if (!empty($deviceData['devices'])) {
                    echo '<h3>Device List:</h3>';
                    echo '<pre>';
                    foreach ($deviceData['devices'] as $i => $device) {
                        echo "Device " . ($i + 1) . ":\n";
                        echo "  Hostname: " . ($device['host-name'] ?? $device['hostname'] ?? 'Unknown') . "\n";
                        echo "  MAC: " . ($device['mac-address'] ?? $device['mac'] ?? 'Unknown') . "\n";
                        echo "  IP: " . ($device['address'] ?? $device['ip'] ?? 'N/A') . "\n";
                        echo "\n";
                    }
                    echo '</pre>';
                } else {
                    echo '<p class="info">ℹ️ No devices found</p>';
                }
            } catch (Exception $e) {
                echo '<p class="error">❌ Device detection failed: ' . $e->getMessage() . '</p>';
            }
        } else {
            echo '<p class="error">❌ Cannot test device detection - no API client</p>';
        }
        ?>
    </div>

    <div class="test-section">
        <h2>5. Real-time API Test</h2>
        <button onclick="testRealtimeAPI()">Test Real-time API</button>
        <div id="api-result"></div>
    </div>

    <div class="test-section">
        <h2>6. Dashboard Test</h2>
        <p><a href="index.php" target="_blank">Open Dashboard in New Tab</a></p>
        <p><a href="test_realtime_api.php" target="_blank">Test Real-time API Directly</a></p>
    </div>

    <script>
        function testRealtimeAPI() {
            const resultDiv = document.getElementById('api-result');
            resultDiv.innerHTML = '<p class="info">Testing real-time API...</p>';
            
            fetch('get_real_time_devices.php')
                .then(response => response.json())
                .then(data => {
                    console.log('API Response:', data);
                    if (data.success) {
                        resultDiv.innerHTML = '<p class="success">✅ Real-time API working</p>' +
                                            '<p class="info">Found ' + data.count + ' devices</p>' +
                                            '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    } else {
                        resultDiv.innerHTML = '<p class="error">❌ API returned error: ' + data.message + '</p>';
                    }
                })
                .catch(error => {
                    console.error('API Error:', error);
                    resultDiv.innerHTML = '<p class="error">❌ API call failed: ' + error.message + '</p>';
                });
        }
    </script>
</body>
</html>
