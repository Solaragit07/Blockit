<?php
/**
 * Optimized API Helper for Fast Blocking
 */
class OptimizedApiHelper {
    
    /**
     * Fast batch update for all devices - uses chunked processing
     */
    public static function fastUpdateAllDevices($conn) {
        $startTime = microtime(true);
        
        // Get all active devices
        $deviceQuery = "SELECT mac_address, timelimit FROM device WHERE status = 'active'";
        $deviceResult = mysqli_query($conn, $deviceQuery);
        
        if (!$deviceResult || mysqli_num_rows($deviceResult) == 0) {
            return [
                'success' => false,
                'error' => 'No active devices found',
                'execution_time' => 0
            ];
        }
        
        // Get all unique blocked sites (optimized query)
        $sites = [];
        $siteQueries = [
            "SELECT DISTINCT website FROM blocklist WHERE website != '' AND website IS NOT NULL",
            "SELECT DISTINCT website FROM group_block WHERE website != '' AND website IS NOT NULL"
        ];
        
        foreach ($siteQueries as $query) {
            $result = mysqli_query($conn, $query);
            while ($row = mysqli_fetch_assoc($result)) {
                $site = trim($row['website']);
                if (!empty($site) && $site !== 'na') {
                    $sites[] = $site;
                }
            }
        }
        
        $sites = array_unique($sites);
        
        // Process devices in batch
        $devices = [];
        while ($device = mysqli_fetch_assoc($deviceResult)) {
            $devices[] = $device;
        }
        
        $results = [];
        $deviceCount = 0;
        
        // Use bulk router operations instead of individual calls
        try {
            require_once '../vendor/autoload.php';
            include '../API/connectMikrotik.php';
            
            // Create one global address list instead of per-device lists
            $globalListName = "blockit-global-sites";
            
            // Clear existing global list
            $query = new RouterOS\Query('/ip/firewall/address-list/print');
            $query->where('list', $globalListName);
            $existingAddresses = $client->query($query)->read();
            
            foreach ($existingAddresses as $address) {
                $removeQuery = new RouterOS\Query('/ip/firewall/address-list/remove');
                $removeQuery->equal('.id', $address['.id']);
                $client->query($removeQuery)->read();
            }
            
            // Add all sites to global list in chunks of 10
            $chunks = array_chunk($sites, 10);
            foreach ($chunks as $chunk) {
                foreach ($chunk as $site) {
                    $addQuery = new RouterOS\Query('/ip/firewall/address-list/add');
                    $addQuery->equal('address', $site);
                    $addQuery->equal('list', $globalListName);
                    $addQuery->equal('comment', "BlockIT - " . date('Y-m-d H:i:s'));
                    $client->query($addQuery)->read();
                }
            }
            
            // Create firewall rules for each device to use the global list
            foreach ($devices as $device) {
                $mac = $device['mac_address'];
                $hours = intval($device['timelimit']);
                
                // Remove existing rules for this device
                $existingRules = $client->query(
                    (new RouterOS\Query('/ip/firewall/filter/print'))
                    ->where('comment', "BlockIT-" . $mac . "*")
                )->read();
                
                foreach ($existingRules as $rule) {
                    $client->query(
                        (new RouterOS\Query('/ip/firewall/filter/remove'))
                        ->equal('.id', $rule['.id'])
                    )->read();
                }
                
                // Create new optimized rule using global address list
                if ($hours > 0) {
                    $client->query(
                        (new RouterOS\Query('/ip/firewall/filter/add'))
                        ->equal('chain', 'forward')
                        ->equal('src-mac-address', $mac)
                        ->equal('dst-address-list', $globalListName)
                        ->equal('action', 'drop')
                        ->equal('comment', "BlockIT-" . $mac . "-sites")
                    )->read();
                }
                
                $deviceCount++;
                $results[$mac] = ['success' => true, 'method' => 'global_list'];
            }
            
            $executionTime = round(microtime(true) - $startTime, 2);
            
            return [
                'success' => true,
                'devices_updated' => $deviceCount,
                'total_sites' => count($sites),
                'method' => 'optimized_global_list',
                'execution_time' => $executionTime,
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
     * Quick single application block
     */
    public static function quickBlockApp($conn, $appName, $domains) {
        $startTime = microtime(true);
        
        // Add domains to blocklist if not exists
        $addedCount = 0;
        foreach ($domains as $domain) {
            $checkQuery = "SELECT id FROM blocklist WHERE website = ?";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("s", $domain);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                $insertQuery = "INSERT INTO blocklist (website) VALUES (?)";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("s", $domain);
                $insertStmt->execute();
                $addedCount++;
            }
        }
        
        // Use fast update method
        $updateResult = self::fastUpdateAllDevices($conn);
        $updateResult['domains_added'] = $addedCount;
        $updateResult['application'] = $appName;
        
        return $updateResult;
    }
}
?>