<?php
include 'connectMySql.php';
require_once 'vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

echo "<h2>📱 Connected Devices - Real Time</h2>";
echo "<style>
body{font-family:Arial;margin:20px;} 
.device{border:1px solid #ccc;padding:15px;margin:10px 0;border-radius:8px;} 
.active{border-color:#28a745;background:#f8fff9;} 
.recent{border-color:#ffc107;background:#fff9e6;} 
.offline{border-color:#dc3545;background:#fff5f5;} 
.status{font-weight:bold;padding:5px 10px;border-radius:4px;color:white;margin:5px 0;}
.status-active{background:#28a745;}
.status-recent{background:#ffc107;color:#000;}
.status-offline{background:#dc3545;}
table{border-collapse:collapse;width:100%;margin:20px 0;}
th,td{border:1px solid #ddd;padding:8px;text-align:left;}
th{background:#f8f9fa;}
.refresh{margin:20px 0;padding:10px;background:#e9ecef;border-radius:4px;}
</style>";

echo "<div class='refresh'>🔄 Auto-refresh every 10 seconds | Last updated: " . date('H:i:s') . "</div>";

try {
    include 'API/connectMikrotik.php';
    if (!isset($client) || $client === null) {
        throw new Exception("Could not connect to MikroTik router");
    }
    
    // Get DHCP leases
    $dhcpLeases = $client->query((new Query('/ip/dhcp-server/lease/print')))->read();
    
    // Get ARP table
    $arpEntries = $client->query((new Query('/ip/arp/print')))->read();
    
    // Create ARP map
    $arpMap = [];
    foreach($arpEntries as $arp) {
        if(isset($arp['address'])) {
            $arpMap[$arp['address']] = $arp;
        }
    }
    
    // Get torch data for internet activity
    $internetActive = [];
    try {
        $torchQuery = new Query('/tool/torch');
        $torchQuery->add('=interface=bridge-lan');
        $torchQuery->add('=duration=3');
        $torchData = $client->query($torchQuery)->read();
        
        foreach($torchData as $entry) {
            if(isset($entry['src']) && isset($entry['dst'])) {
                $srcIP = $entry['src'];
                $dstIP = $entry['dst'];
                $rxBytes = intval($entry['rx-bytes'] ?? 0);
                $txBytes = intval($entry['tx-bytes'] ?? 0);
                
                // Check for internet traffic (non-local destination)
                if(($rxBytes > 0 || $txBytes > 0)) {
                    if(preg_match('/^192\.168\./', $srcIP) && !preg_match('/^192\.168\./', $dstIP)) {
                        $internetActive[$srcIP] = true;
                    }
                    if(preg_match('/^192\.168\./', $dstIP) && !preg_match('/^192\.168\./', $srcIP)) {
                        $internetActive[$dstIP] = true;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Torch failed, continue without internet detection
    }
    
    echo "<table>";
    echo "<tr><th>Device</th><th>IP Address</th><th>MAC Address</th><th>Status</th><th>Connection Type</th><th>Last Seen</th></tr>";
    
    $deviceCount = 0;
    foreach($dhcpLeases as $lease) {
        if(isset($lease['address']) && isset($lease['mac-address'])) {
            $ip = $lease['address'];
            $mac = $lease['mac-address'];
            $hostname = $lease['host-name'] ?? 'Unknown Device';
            $dhcpStatus = $lease['status'] ?? 'unknown';
            $activeTime = $lease['active-time'] ?? '';
            
            // Check ARP status
            $arpStatus = 'Not in ARP';
            $isReachable = false;
            if(isset($arpMap[$ip])) {
                $arp = $arpMap[$ip];
                $isComplete = isset($arp['complete']) && $arp['complete'] === 'true';
                $isInvalid = isset($arp['invalid']) && $arp['invalid'] === 'true';
                $isDynamic = isset($arp['dynamic']) && $arp['dynamic'] === 'true';
                
                if($isComplete) {
                    $arpStatus = 'Complete ✅';
                    $isReachable = true;
                } elseif($isDynamic && !$isInvalid) {
                    $arpStatus = 'Dynamic 🔄';
                    $isReachable = true;
                } elseif($isInvalid) {
                    $arpStatus = 'Invalid ❌';
                } else {
                    $arpStatus = 'Incomplete ⏳';
                }
            }
            
            // Check internet activity
            $hasInternet = isset($internetActive[$ip]);
            
            // Determine overall status
            if($isReachable && $hasInternet) {
                $status = 'Active Online';
                $statusClass = 'status-active';
                $deviceClass = 'device active';
            } elseif($isReachable) {
                $status = 'Active Local';
                $statusClass = 'status-active';
                $deviceClass = 'device active';
            } elseif($dhcpStatus === 'bound') {
                $status = 'Recently Connected';
                $statusClass = 'status-recent';
                $deviceClass = 'device recent';
            } elseif($dhcpStatus === 'waiting') {
                $status = 'Recently Connected';
                $statusClass = 'status-recent';
                $deviceClass = 'device recent';
            } elseif(!empty($activeTime)) {
                $status = 'Recently Active';
                $statusClass = 'status-recent';
                $deviceClass = 'device recent';
            } else {
                $status = 'Offline';
                $statusClass = 'status-offline';
                $deviceClass = 'device offline';
            }
            
            // Only show devices that have some connection evidence
            if($isReachable || $dhcpStatus === 'bound' || $dhcpStatus === 'waiting' || !empty($activeTime)) {
                echo "<tr>";
                echo "<td><strong>$hostname</strong></td>";
                echo "<td>$ip</td>";
                echo "<td>$mac</td>";
                echo "<td><span class='status $statusClass'>$status</span></td>";
                echo "<td>$arpStatus" . ($hasInternet ? " 🌐" : "") . "</td>";
                echo "<td>" . ($activeTime ?: 'N/A') . "</td>";
                echo "</tr>";
                $deviceCount++;
            }
        }
    }
    echo "</table>";
    
    echo "<div class='refresh'>";
    echo "<strong>Total Connected Devices: $deviceCount</strong><br>";
    echo "🟢 Active Online = Currently reachable + Internet activity<br>";
    echo "🟢 Active Local = Currently reachable, no internet detected<br>";
    echo "🟡 Recently Connected = DHCP bound/waiting but not currently reachable<br>";
    echo "🔴 Offline = No current activity<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color:red;'>Error: " . $e->getMessage() . "</div>";
}

echo "<script>setTimeout(function(){location.reload();}, 10000);</script>";
?>
