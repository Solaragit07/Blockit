<?php
/**
 * Quick Performance Fix for BlockIT
 * This provides immediate performance improvement for blocking operations
 */

class QuickPerformanceFix {
    
    /**
     * Fast blocking update using chunked domain processing
     */
    public static function quickUpdateDevices($conn) {
        $startTime = microtime(true);
        
        // Get all active devices  
        $deviceQuery = "SELECT mac_address, timelimit FROM device";
        $deviceResult = mysqli_query($conn, $deviceQuery);
        
        if (!$deviceResult || mysqli_num_rows($deviceResult) == 0) {
            return [
                'success' => false,
                'error' => 'No devices found',
                'execution_time' => 0
            ];
        }
        
        // Get unique blocked sites (limit to most recent 30 for speed)
        $sites = [];
        
        // Get from blocklist (most recent 30)
        $blocklistResult = mysqli_query($conn, "SELECT DISTINCT website FROM blocklist WHERE website != '' AND website IS NOT NULL ORDER BY id DESC LIMIT 30");
        while ($row = mysqli_fetch_assoc($blocklistResult)) {
            $site = trim($row['website']);
            if (!empty($site) && $site !== 'na') {
                $sites[] = $site;
            }
        }
        
        // Get from application blocks
        $appBlocksResult = mysqli_query($conn, "SELECT DISTINCT domains FROM application_blocks WHERE status = 'active' AND domains != ''");
        while ($row = mysqli_fetch_assoc($appBlocksResult)) {
            $domains = explode(',', $row['domains']);
            foreach ($domains as $domain) {
                $domain = trim($domain);
                if (!empty($domain)) {
                    $sites[] = $domain;
                }
            }
        }
        
        $sites = array_unique($sites);
        
        // Process devices with chunked API calls
        $results = [];
        $deviceCount = 0;
        
        try {
            require_once '../vendor/autoload.php';
            include '../API/connectMikrotik.php';
            
            while ($device = mysqli_fetch_assoc($deviceResult)) {
                $mac = $device['mac_address'];
                $hours = intval($device['timelimit']);
                
                // Use existing API helper but with smaller chunks
                $chunks = array_chunk($sites, 5); // Small chunks for speed
                $deviceResults = [];
                
                foreach ($chunks as $chunk) {
                    $apiCall = 'http://localhost/blockit/API/block_user.php';
                    $postData = http_build_query([
                        'mac_address' => $mac,
                        'sites' => implode(';', $chunk),
                        'hours_allowed' => $hours
                    ]);
                    
                    $context = stream_context_create([
                        'http' => [
                            'method' => 'POST',
                            'header' => 'Content-type: application/x-www-form-urlencoded',
                            'content' => $postData,
                            'timeout' => 5 // 5 second timeout per chunk
                        ]
                    ]);
                    
                    $response = @file_get_contents($apiCall, false, $context);
                    $deviceResults[] = [
                        'success' => ($response !== false),
                        'chunk_size' => count($chunk)
                    ];
                }
                
                $results[$mac] = $deviceResults;
                $deviceCount++;
            }
            
            $executionTime = round(microtime(true) - $startTime, 2);
            
            return [
                'success' => true,
                'devices_updated' => $deviceCount,
                'total_sites' => count($sites),
                'execution_time' => $executionTime,
                'method' => 'chunked_processing',
                'results' => $results
            ];
            
        } catch (Exception $e) {
            $executionTime = round(microtime(true) - $startTime, 2);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => $executionTime
            ];
        }
    }
    
    /**
     * Quick single app block - optimized version
     */
    public static function quickBlockSingleApp($conn, $appName, $domains) {
        // Add domains to blocklist quickly
        $addedCount = 0;
        
        $stmt = $conn->prepare("INSERT IGNORE INTO blocklist (website) VALUES (?)");
        foreach ($domains as $domain) {
            $stmt->bind_param("s", $domain);
            if ($stmt->execute()) {
                $addedCount++;
            }
        }
        
        // Quick update with just the new domains
        $quickUpdate = self::quickUpdateDevices($conn);
        $quickUpdate['domains_added'] = $addedCount;
        $quickUpdate['application'] = $appName;
        
        return $quickUpdate;
    }
}
?>
