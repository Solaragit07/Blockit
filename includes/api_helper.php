<?php
/**
 * API Helper Functions for BlockIT
 * Handles communication with the RouterOS API
 */

class ApiHelper {
    
    /**
     * Make a POST request to the BlockIT API
     */
    public static function callBlockAPI($endpoint, $data, $timeout = 15) {
    $url = "http://localhost/blockit/API/" . $endpoint;
        
        $postData = http_build_query($data);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Content-Length: ' . strlen($postData)
                ],
                'content' => $postData,
                'timeout' => $timeout
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return [
                'success' => false,
                'error' => 'Failed to connect to API endpoint: ' . $endpoint,
                'http_response_header' => $http_response_header ?? []
            ];
        }

        $decodedResponse = json_decode($response, true);
        
        return [
            'success' => true,
            'data' => $decodedResponse,
            'raw_response' => $response
        ];
    }
    
    /**
     * Update blocking rules for a specific device
     */
    public static function updateDeviceBlocking($macAddress, $sites, $hoursAllowed) {
        // Remove duplicates and clean sites list
        $sites = array_unique(array_filter(array_map('trim', $sites)));
        
        // If sites list is too large, split into chunks
        $maxSitesPerRequest = 50; // Adjust based on your needs
        $siteChunks = array_chunk($sites, $maxSitesPerRequest);
        
        $results = [];
        
        foreach ($siteChunks as $chunk) {
            $data = [
                'mac_address' => $macAddress,
                'sites' => implode(";", $chunk),
                'hours_allowed' => $hoursAllowed
            ];
            
            $result = self::callBlockAPI('block_with_redirect_page.php', $data);
            $results[] = $result;
            
            // Add a small delay between requests to avoid overwhelming the router
            usleep(100000); // 0.1 second delay
        }
        
        return $results;
    }
    
    /**
     * Update blocking rules for all devices
     */
    public static function updateAllDevicesBlocking($conn) {
        // Increase execution time limit to prevent timeouts
        ini_set('max_execution_time', 300); // 5 minutes max
        
        // Limit devices to prevent overwhelming the system
        $query = "SELECT mac_address, timelimit FROM device WHERE mac_address != '' LIMIT 20";
        $result = mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) == 0) {
            return [
                'success' => false,
                'error' => 'No devices found'
            ];
        }
        
        // Get all blocked sites (remove duplicates)
        $blocklistSites = [];
        
        // Get domains from regular blocklist
        $blocklistResult = mysqli_query($conn, "SELECT DISTINCT website FROM blocklist WHERE website != ''");
        while ($row = mysqli_fetch_assoc($blocklistResult)) {
            $blocklistSites[] = trim($row['website']);
        }

        // Get domains from group_block
        $groupblockResult = mysqli_query($conn, "SELECT DISTINCT website FROM group_block WHERE website != ''");
        while ($row = mysqli_fetch_assoc($groupblockResult)) {
            $blocklistSites[] = trim($row['website']);
        }

        // Get domains from application_blocks
        $appBlocksResult = mysqli_query($conn, "SELECT DISTINCT domains FROM application_blocks WHERE status = 'active' AND domains != ''");
        while ($row = mysqli_fetch_assoc($appBlocksResult)) {
            $domains = explode(',', $row['domains']);
            foreach ($domains as $domain) {
                $domain = trim($domain);
                if (!empty($domain)) {
                    $blocklistSites[] = $domain;
                }
            }
        }

        // Get whitelisted sites to exclude
        $whitelistSites = [];
        $whitelistResult = mysqli_query($conn, "SELECT DISTINCT website FROM whitelist WHERE website != ''");
        while ($row = mysqli_fetch_assoc($whitelistResult)) {
            $whitelistSites[] = trim($row['website']);
        }
        
        // Remove duplicates and empty values
        $blocklistSites = array_unique(array_filter($blocklistSites));
        
        // EXCLUDE whitelisted sites from the blocking list
        if (!empty($whitelistSites)) {
            $blocklistSites = array_diff($blocklistSites, $whitelistSites);
        }
        
        $results = [];
        $deviceCount = 0;
        
        while ($device = mysqli_fetch_assoc($result)) {
            $mac = $device['mac_address'];
            $hours = $device['timelimit'];
            
            $deviceResults = self::updateDeviceBlocking($mac, $blocklistSites, $hours);
            $results[$mac] = $deviceResults;
            $deviceCount++;
        }
        
        return [
            'success' => true,
            'devices_updated' => $deviceCount,
            'total_sites' => count($blocklistSites),
            'results' => $results
        ];
    }
    
    /**
     * Log API calls for debugging
     */
    public static function logApiCall($endpoint, $data, $response) {
        $logFile = __DIR__ . '/../logs/api_calls.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoint' => $endpoint,
            'data' => $data,
            'response' => $response
        ];
        
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
}
?>
