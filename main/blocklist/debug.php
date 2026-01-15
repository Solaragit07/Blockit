<?php
include '../../connectMySql.php';
include '../../includes/api_helper.php';

echo "=== BLOCKLIST INTERFACE DEBUG ===\n\n";

// Check current time and blocking status
echo "Current time: " . date('H:i:s') . "\n";

// Test adding a site manually
if (isset($_GET['test_site'])) {
    $testSite = $_GET['test_site'];
    echo "Testing with site: $testSite\n\n";
    
    // Add to blocklist if not exists
    $existing = mysqli_query($conn, "SELECT * FROM blocklist WHERE website = '$testSite'");
    if (mysqli_num_rows($existing) == 0) {
        mysqli_query($conn, "INSERT INTO blocklist (website) VALUES ('$testSite')");
        echo "✅ Added $testSite to blocklist\n";
    } else {
        echo "✅ $testSite already in blocklist\n";
    }
    
    // Now update all devices
    echo "Updating all devices...\n";
    $updateResult = ApiHelper::updateAllDevicesBlocking($conn);
    
    if ($updateResult['success']) {
        echo "✅ SUCCESS: Updated {$updateResult['devices_updated']} devices with {$updateResult['total_sites']} sites\n\n";
        
        // Show detailed results
        foreach ($updateResult['results'] as $mac => $deviceResults) {
            echo "Device $mac:\n";
            foreach ($deviceResults as $i => $result) {
                if ($result['success']) {
                    echo "  Chunk $i: ✅ Success\n";
                    if (isset($result['data']['messages'])) {
                        foreach ($result['data']['messages'] as $msg) {
                            echo "    • $msg\n";
                        }
                    }
                } else {
                    echo "  Chunk $i: ❌ Error - " . $result['error'] . "\n";
                }
            }
        }
    } else {
        echo "❌ FAILED: " . $updateResult['error'] . "\n";
    }
} else {
    // Show current status
    echo "=== CURRENT DATABASE STATUS ===\n";
    
    $blocklist = mysqli_query($conn, "SELECT * FROM blocklist");
    echo "Blocklist sites: " . mysqli_num_rows($blocklist) . "\n";
    while ($row = mysqli_fetch_assoc($blocklist)) {
        echo "  • " . $row['website'] . "\n";
    }
    
    $devices = mysqli_query($conn, "SELECT * FROM device");
    echo "\nDevices: " . mysqli_num_rows($devices) . "\n";
    while ($row = mysqli_fetch_assoc($devices)) {
        echo "  • " . $row['mac_address'] . " (limit: " . $row['timelimit'] . "h)\n";
    }
    
    echo "\n=== TEST LINKS ===\n";
    echo "Test adding YouTube: ?test_site=youtube.com\n";
    echo "Test adding Facebook: ?test_site=facebook.com\n";
    echo "Test adding Instagram: ?test_site=instagram.com\n";
}
?>
