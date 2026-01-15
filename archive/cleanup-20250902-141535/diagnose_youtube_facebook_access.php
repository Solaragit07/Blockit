<?php
include 'connectMySql.php';

echo "<!DOCTYPE html><html><head><title>YouTube & Facebook Access Diagnostic</title>";
echo "<style>
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; }
.box { border: 1px solid #ccc; padding: 15px; margin: 10px 0; border-radius: 5px; }
</style></head><body>";

echo "<h1>🔍 YouTube & Facebook Access Diagnostic</h1>";

// Get your current IP/MAC for testing
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
echo "<p class='info'>Your current IP: <strong>$clientIP</strong></p>";

echo "<div class='box'>";
echo "<h2>1. 📋 Checking Database Blocklist</h2>";

// Check if YouTube/Facebook are in blocklist
$youtubeBlocked = [];
$facebookBlocked = [];

$result = $conn->query("SELECT website FROM blocklist WHERE website LIKE '%youtube%' OR website LIKE '%googlevideo%'");
while($row = $result->fetch_assoc()) {
    $youtubeBlocked[] = $row['website'];
}

$result = $conn->query("SELECT website FROM blocklist WHERE website LIKE '%facebook%' OR website LIKE '%fb.com%'");
while($row = $result->fetch_assoc()) {
    $facebookBlocked[] = $row['website'];
}

echo "<h3>YouTube-related domains in blocklist:</h3>";
if (empty($youtubeBlocked)) {
    echo "<p class='success'>✅ No YouTube domains found in blocklist</p>";
} else {
    echo "<p class='error'>❌ Found blocked YouTube domains:</p><ul>";
    foreach($youtubeBlocked as $domain) {
        echo "<li>$domain</li>";
    }
    echo "</ul>";
}

echo "<h3>Facebook-related domains in blocklist:</h3>";
if (empty($facebookBlocked)) {
    echo "<p class='success'>✅ No Facebook domains found in blocklist</p>";
} else {
    echo "<p class='error'>❌ Found blocked Facebook domains:</p><ul>";
    foreach($facebookBlocked as $domain) {
        echo "<li>$domain</li>";
    }
    echo "</ul>";
}
echo "</div>";

echo "<div class='box'>";
echo "<h2>2. 🔥 Checking Router Firewall Rules</h2>";

try {
    require_once 'vendor/autoload.php';
    include 'API/connectMikrotik.php';
    
    // Check for global blocking rules
    $globalRules = $client->query((new RouterOS\Query('/ip/firewall/filter/print'))
        ->where('comment', 'Global website blocking*'))->read();
    
    echo "<h3>Global Blocking Rules:</h3>";
    if (empty($globalRules)) {
        echo "<p class='success'>✅ No global blocking rules found</p>";
    } else {
        echo "<p class='error'>❌ Found " . count($globalRules) . " global blocking rules</p>";
        foreach($globalRules as $rule) {
            $disabled = ($rule['disabled'] ?? 'no') === 'yes' ? 'DISABLED' : 'ACTIVE';
            echo "<p>• " . ($rule['comment'] ?? 'No comment') . " - Status: $disabled</p>";
        }
        echo "<p class='warning'>⚠️ Global rules block ALL devices from accessing blocked sites 24/7</p>";
    }
    
    // Check for device-specific rules
    $deviceRules = $client->query((new RouterOS\Query('/ip/firewall/filter/print'))
        ->where('comment', 'Auto block for*'))->read();
    
    echo "<h3>Device-Specific Blocking Rules:</h3>";
    if (empty($deviceRules)) {
        echo "<p class='info'>ℹ️ No device-specific blocking rules found</p>";
    } else {
        echo "<p class='warning'>⚠️ Found " . count($deviceRules) . " device-specific blocking rules</p>";
        foreach(array_slice($deviceRules, 0, 5) as $rule) {
            $disabled = ($rule['disabled'] ?? 'no') === 'yes' ? 'DISABLED' : 'ACTIVE';
            echo "<p>• " . ($rule['comment'] ?? 'No comment') . " - Status: $disabled</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Could not connect to router: " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<div class='box'>";
echo "<h2>3. 📝 Checking Address Lists</h2>";

try {
    // Check for global address lists
    $addressLists = $client->query((new RouterOS\Query('/ip/firewall/address-list/print')))->read();
    
    $globalBlockedSites = [];
    $deviceBlockedSites = [];
    
    foreach($addressLists as $entry) {
        $listName = $entry['list'] ?? '';
        $address = $entry['address'] ?? '';
        
        if (strpos($listName, 'global') !== false || strpos($listName, 'Global') !== false) {
            $globalBlockedSites[] = $address;
        } elseif (strpos($listName, 'blocked-sites') === 0) {
            $deviceBlockedSites[] = $address;
        }
    }
    
    echo "<h3>Global Blocked Sites:</h3>";
    if (empty($globalBlockedSites)) {
        echo "<p class='success'>✅ No global blocked sites</p>";
    } else {
        echo "<p class='error'>❌ Found " . count($globalBlockedSites) . " globally blocked sites</p>";
        $youtubeInGlobal = array_filter($globalBlockedSites, function($site) {
            return strpos($site, 'youtube') !== false || strpos($site, 'googlevideo') !== false;
        });
        $facebookInGlobal = array_filter($globalBlockedSites, function($site) {
            return strpos($site, 'facebook') !== false || strpos($site, 'fb.com') !== false;
        });
        
        if (!empty($youtubeInGlobal)) {
            echo "<p class='error'>🚫 YouTube domains in global block: " . implode(', ', $youtubeInGlobal) . "</p>";
        }
        if (!empty($facebookInGlobal)) {
            echo "<p class='error'>🚫 Facebook domains in global block: " . implode(', ', $facebookInGlobal) . "</p>";
        }
    }
    
    echo "<h3>Device-Specific Blocked Sites:</h3>";
    if (empty($deviceBlockedSites)) {
        echo "<p class='info'>ℹ️ No device-specific blocked sites</p>";
    } else {
        echo "<p class='warning'>⚠️ Found " . count($deviceBlockedSites) . " device-specific blocked sites</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Could not check address lists: " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<div class='box'>";
echo "<h2>4. ⏰ Time-Based Blocking Status</h2>";

$currentHour = (int)date('H');
echo "<p class='info'>Current time: " . date('H:i:s') . " (Hour: $currentHour)</p>";

// Check devices with time limits
$result = $conn->query("SELECT mac_address, device_name, timelimit FROM device WHERE timelimit > 0 LIMIT 5");
if ($result->num_rows > 0) {
    echo "<h3>Devices with Time-Based Blocking:</h3>";
    while($device = $result->fetch_assoc()) {
        $allowedUntil = 8 + $device['timelimit']; // Assuming 8 AM start
        $isAllowed = ($currentHour >= 8 && $currentHour < $allowedUntil);
        $status = $isAllowed ? '🟢 ALLOWED' : '🔴 BLOCKED';
        echo "<p>• {$device['device_name']} ({$device['mac_address']}): $status (until {$allowedUntil}:00)</p>";
    }
} else {
    echo "<p class='info'>ℹ️ No devices with time-based blocking found</p>";
}
echo "</div>";

echo "<div class='box'>";
echo "<h2>5. 🔧 Possible Solutions</h2>";

if (!empty($youtubeBlocked) || !empty($facebookBlocked) || !empty($globalBlockedSites)) {
    echo "<h3>❌ BLOCKING DETECTED - Here's how to unblock:</h3>";
    echo "<ol>";
    
    if (!empty($youtubeBlocked) || !empty($facebookBlocked)) {
        echo "<li><strong>Remove from Database Blocklist:</strong>";
        echo "<ul>";
        if (!empty($youtubeBlocked)) {
            echo "<li>Remove YouTube domains: " . implode(', ', $youtubeBlocked) . "</li>";
        }
        if (!empty($facebookBlocked)) {
            echo "<li>Remove Facebook domains: " . implode(', ', $facebookBlocked) . "</li>";
        }
        echo "</ul>";
        echo "<a href='main/blocklist/index.php' target='_blank'>→ Go to Blocklist Management</a></li>";
    }
    
    if (!empty($globalRules)) {
        echo "<li><strong>Disable Global Rules:</strong> Global blocking rules are active and affect ALL devices</li>";
        echo "<form method='post' style='display:inline;'>";
        echo "<button type='submit' name='disable_global' style='background:red;color:white;padding:5px 10px;'>Disable Global Blocking</button>";
        echo "</form>";
    }
    
    echo "<li><strong>Emergency Unblock:</strong> Clear all blocking rules</li>";
    echo "<form method='post' style='display:inline;'>";
    echo "<button type='submit' name='emergency_unblock' style='background:orange;color:white;padding:5px 10px;' onclick='return confirm(\"Remove ALL blocking rules?\")'>Emergency Unblock</button>";
    echo "</form>";
    
    echo "</ol>";
} else {
    echo "<h3>✅ NO BLOCKING DETECTED</h3>";
    echo "<p>YouTube and Facebook should be accessible. If you're still having issues:</p>";
    echo "<ol>";
    echo "<li><strong>Clear browser cache and cookies</strong></li>";
    echo "<li><strong>Try different browsers</strong> (Chrome, Firefox, Edge)</li>";
    echo "<li><strong>Check DNS settings</strong> - Make sure you're using router DNS</li>";
    echo "<li><strong>Test direct IP access</strong> - Some apps may use IP addresses</li>";
    echo "<li><strong>Check for VPN or proxy</strong> - These might be interfering</li>";
    echo "</ol>";
}
echo "</div>";

// Handle form submissions
if (isset($_POST['disable_global'])) {
    try {
        include 'API/connectMikrotik.php';
        
        $globalRules = $client->query((new RouterOS\Query('/ip/firewall/filter/print'))
            ->where('comment', 'Global website blocking*'))->read();
        
        foreach($globalRules as $rule) {
            $client->query((new RouterOS\Query('/ip/firewall/filter/set'))
                ->equal('.id', $rule['.id'])
                ->equal('disabled', 'yes'))->read();
        }
        
        echo "<div class='box'><p class='success'>✅ Global blocking rules disabled!</p></div>";
        echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
        
    } catch (Exception $e) {
        echo "<div class='box'><p class='error'>❌ Error disabling global rules: " . $e->getMessage() . "</p></div>";
    }
}

if (isset($_POST['emergency_unblock'])) {
    try {
        include 'API/connectMikrotik.php';
        
        // Remove all blocking address lists
        $addressLists = $client->query((new RouterOS\Query('/ip/firewall/address-list/print')))->read();
        $removed = 0;
        
        foreach($addressLists as $list) {
            $listName = $list['list'] ?? '';
            if (strpos($listName, 'block') !== false || strpos($listName, 'Global') !== false) {
                $client->query((new RouterOS\Query('/ip/firewall/address-list/remove'))
                    ->equal('.id', $list['.id']))->read();
                $removed++;
            }
        }
        
        // Remove all blocking filter rules
        $filterRules = $client->query((new RouterOS\Query('/ip/firewall/filter/print')))->read();
        
        foreach($filterRules as $rule) {
            $comment = $rule['comment'] ?? '';
            if (strpos($comment, 'Auto block') === 0 || strpos($comment, 'Global website') === 0) {
                $client->query((new RouterOS\Query('/ip/firewall/filter/remove'))
                    ->equal('.id', $rule['.id']))->read();
                $removed++;
            }
        }
        
        // Clear database blocks
        $conn->query("DELETE FROM blocklist WHERE website LIKE '%youtube%' OR website LIKE '%facebook%'");
        $conn->query("UPDATE device SET status = 'active' WHERE status = 'blocked'");
        
        echo "<div class='box'><p class='success'>✅ Emergency unblock completed! Removed $removed rules. All sites should now be accessible.</p></div>";
        echo "<script>setTimeout(function(){ location.reload(); }, 3000);</script>";
        
    } catch (Exception $e) {
        echo "<div class='box'><p class='error'>❌ Error during emergency unblock: " . $e->getMessage() . "</p></div>";
    }
}

echo "<div class='box'>";
echo "<h2>6. 🧪 Quick Access Test</h2>";
echo "<p>Test access to these sites:</p>";
echo "<ul>";
echo "<li><a href='http://youtube.com' target='_blank'>YouTube.com</a> (should open if unblocked)</li>";
echo "<li><a href='http://facebook.com' target='_blank'>Facebook.com</a> (should open if unblocked)</li>";
echo "<li><a href='http://google.com' target='_blank'>Google.com</a> (control test - should always work)</li>";
echo "</ul>";
echo "</div>";

echo "</body></html>";
?>
