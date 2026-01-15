<?php
include 'connectMySql.php';

echo "=== ADDING ADDITIONAL TIKTOK DOMAINS ===\n\n";

$additionalTikTokDomains = [
    'tiktokv.com', 'tiktokcdn.com', 'ibytedtos.com', 'ibyteimg.com',
    'pstatp.com', 'snssdk.com', 'amemv.com', 'toutiao.com',
    'ixigua.com', 'bdxiguaimg.com', 'bdxiguastatic.com'
];

$addedCount = 0;
$skippedCount = 0;

foreach ($additionalTikTokDomains as $domain) {
    // Check if domain already exists
    $checkQuery = "SELECT id FROM blocklist WHERE website = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("s", $domain);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows == 0) {
        // Add to blocklist
        $insertQuery = "INSERT INTO blocklist (website) VALUES (?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("s", $domain);
        
        if ($insertStmt->execute()) {
            echo "✅ Added: $domain\n";
            $addedCount++;
        } else {
            echo "❌ Failed to add: $domain\n";
        }
    } else {
        echo "⚠️ Already exists: $domain\n";
        $skippedCount++;
    }
}

echo "\nSummary:\n";
echo "- Added: $addedCount domains\n";
echo "- Skipped: $skippedCount domains\n";

if ($addedCount > 0) {
    echo "\nUpdating all devices with new TikTok domains...\n";
    include 'includes/api_helper.php';
    
    try {
        $updateResult = ApiHelper::updateAllDevicesBlocking($conn);
        if ($updateResult['success']) {
            echo "✅ Successfully updated " . $updateResult['devices_updated'] . " devices\n";
        } else {
            echo "❌ Failed to update devices: " . $updateResult['error'] . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Error updating devices: " . $e->getMessage() . "\n";
    }
}

$conn->close();
?>
