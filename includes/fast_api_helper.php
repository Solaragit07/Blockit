<?php
/**
 * Fast API Helper for BlockIT
 * Optimized version to prevent timeouts and improve performance
 */

// Prevent duplicate class declaration
if (!class_exists('FastApiHelper')) {

class FastApiHelper {
    
    /**
     * Make a POST request to the BlockIT API with timeout handling
     */
    public static function callBlockAPI($endpoint, $data, $timeout = 15) {
    // Use lowercase path to match actual deployment folder
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
                'error' => 'Failed to connect to API endpoint: ' . $endpoint
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
     * Update application blocking with redirect to block page
     */
    public static function fastUpdateApplicationBlockingWithRedirect($conn, $applicationName) {
        // Increase execution time limit to prevent timeouts
        ini_set('max_execution_time', 180); // 3 minutes max
        
        // Get domains for this specific application (prefer DB-saved list, then fallback)
        $appDomains = [];
        if ($stmt = mysqli_prepare($conn, "SELECT domains FROM application_blocks WHERE application_name = ? AND status = 'active'")) {
            mysqli_stmt_bind_param($stmt, 's', $applicationName);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $domainsStr);
            while (mysqli_stmt_fetch($stmt)) {
                $parts = array_map('trim', explode(',', (string)$domainsStr));
                foreach ($parts as $d) { if ($d !== '') { $appDomains[] = $d; } }
            }
            mysqli_stmt_close($stmt);
        }
        if (empty($appDomains)) {
            $appDomains = self::getApplicationDomains($applicationName);
        }
        
        if (empty($appDomains)) {
            return ['success' => false, 'error' => 'No domains found for application'];
        }
        
        // Set up DNS redirects instead of blocking
        try {
            require_once '../vendor/autoload.php';
            include '../API/connectMikrotik.php';
            
            // Determine BlockIT web server IP (Apache host) to serve blocked.html
            $blockitServerIP = null;
            if (!empty($_SERVER['SERVER_ADDR'])) {
                $blockitServerIP = $_SERVER['SERVER_ADDR'];
            } else {
                // Fallback to hostname resolution
                $host = gethostname();
                if ($host) { $blockitServerIP = gethostbyname($host); }
            }
            if (!$blockitServerIP || $blockitServerIP === '127.0.0.1') {
                // Last resort fallback if local resolution fails
                $blockitServerIP = '192.168.10.1';
            }
            
            $redirectCount = 0;
            $errors = [];
            
            foreach ($appDomains as $domain) {
                try {
                    // Remove existing DNS entries for this domain
                    $existingEntries = $client->query((new RouterOS\Query('/ip/dns/static/print'))
                        ->where('name', $domain))->read();
                    
                    foreach ($existingEntries as $entry) {
                        if (isset($entry['comment']) && strpos($entry['comment'], 'BlockIT') !== false) {
                            $client->query((new RouterOS\Query('/ip/dns/static/remove'))
                                ->equal('.id', $entry['.id']))->read();
                        }
                    }
                    
                    // Add DNS redirect to BlockIT server
                    $client->query((new RouterOS\Query('/ip/dns/static/add'))
                        ->equal('name', $domain)
                        ->equal('address', $blockitServerIP)
                        ->equal('comment', "BlockIT redirect - $applicationName"))->read();
                    
                    $redirectCount++;
                    
                    // Also handle www subdomain
                    if (strpos($domain, 'www.') !== 0) {
                        $wwwDomain = 'www.' . $domain;
                        
                        // Remove existing www entries
                        $existingWww = $client->query((new RouterOS\Query('/ip/dns/static/print'))
                            ->where('name', $wwwDomain))->read();
                        
                        foreach ($existingWww as $entry) {
                            if (isset($entry['comment']) && strpos($entry['comment'], 'BlockIT') !== false) {
                                $client->query((new RouterOS\Query('/ip/dns/static/remove'))
                                    ->equal('.id', $entry['.id']))->read();
                            }
                        }
                        
                        $client->query((new RouterOS\Query('/ip/dns/static/add'))
                            ->equal('name', $wwwDomain)
                            ->equal('address', $blockitServerIP)
                            ->equal('comment', "BlockIT redirect - $applicationName (www)"))->read();
                        
                        $redirectCount++;
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Failed to set redirect for $domain: " . $e->getMessage();
                }
                
                // Small delay to prevent router overload
                usleep(100000); // 0.1 seconds
            }
            
            return [
                'success' => true,
                'devices_updated' => 'DNS redirect',
                'redirects_created' => $redirectCount,
                'application' => $applicationName,
                'domains_count' => count($appDomains),
                'total_sites' => count($appDomains),
                'redirect_method' => 'DNS redirect to block page',
                'blockitServerIP' => $blockitServerIP,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to set up DNS redirects: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Fast update for application blocking - only updates devices that need changes
     */
    public static function fastUpdateApplicationBlocking($conn, $applicationName) {
        // Increase execution time limit to prevent timeouts
        ini_set('max_execution_time', 180); // 3 minutes max
        
        // Get domains for this specific application
        // Priority: use domains saved in DB (what UI actually added) then fallback to built-ins
        $appDomains = [];
        if ($stmt = mysqli_prepare($conn, "SELECT domains FROM application_blocks WHERE application_name = ? AND status = 'active'")) {
            mysqli_stmt_bind_param($stmt, 's', $applicationName);
            mysqli_stmt_execute($stmt);
            // Use bind_result for compatibility even when mysqlnd is missing
            mysqli_stmt_bind_result($stmt, $domainsStr);
            while (mysqli_stmt_fetch($stmt)) {
                $parts = array_map('trim', explode(',', (string)$domainsStr));
                foreach ($parts as $d) { if ($d !== '') { $appDomains[] = $d; } }
            }
            mysqli_stmt_close($stmt);
        }
        if (empty($appDomains)) {
            $appDomains = self::getApplicationDomains($applicationName);
        }
        // Normalize and de-duplicate
        $appDomains = array_values(array_unique(array_filter(array_map(function($d){
            $d = strtolower(trim($d));
            $d = preg_replace('#^https?://#','', $d);
            $d = preg_replace('/^www\./','', $d);
            return $d;
        }, $appDomains))));
        
        if (empty($appDomains)) {
            return ['success' => false, 'error' => 'No domains found for application'];
        }
        
        // Get active devices (limit to reasonable number)
    $query = "SELECT mac_address, timelimit FROM device WHERE mac_address != ''";
        $deviceResult = mysqli_query($conn, $query);
        
        if (!$deviceResult || mysqli_num_rows($deviceResult) == 0) {
            return ['success' => false, 'error' => 'No devices found'];
        }
        
        $results = [];
        $deviceCount = 0;
        
        while ($device = mysqli_fetch_assoc($deviceResult)) {
            $mac = $device['mac_address'];
            $hours = $device['timelimit'] ?? 0;
            
            // Update only this application's domains for this device
            try {
                // Use hard drop/block endpoint for standard blocking (no redirect)
                $apiResult = self::callBlockAPI('block_user.php', [
                    'mac_address' => $mac,
                    'sites' => implode(";", $appDomains),
                    'hours_allowed' => $hours
                ], 10); // Short timeout
            } catch (Exception $e) {
                $apiResult = ['success' => false, 'error' => 'Timeout: ' . $e->getMessage()];
            }
            
            $results[$mac] = $apiResult;
            $deviceCount++;
            
            // Small delay to prevent router overload
            usleep(50000); // 0.05 seconds
        }
        
        return [
            'success' => true,
            'devices_updated' => $deviceCount,
            'application' => $applicationName,
            'domains_count' => count($appDomains),
            'results' => $results
        ];
    }
    
    /**
     * Get domains for specific applications
     */
    private static function getApplicationDomains($applicationName) {
        $domainMap = [
            'TikTok' => [
                // Core + CDN + legacy + oversea
                'tiktok.com','www.tiktok.com','musically.com','musical.ly',
                'bytedance.com','tiktokcdn.com','tiktokv.com','ttlivecdn.com',
                'tiktok-realtime.com','amemv.com','snssdk.com','ibyteimg.com',
                'pstatp.com','byteoversea.com','ibytedtos.com','muscdn.com','byteimg.com'
            ],
            'WhatsApp' => [
                'whatsapp.com','whatsapp.net','wa.me','web.whatsapp.com',
                'static.whatsapp.net','mmg.whatsapp.net','wa.me','whatsapp-plus.info'
            ],
            'Instagram' => [
                'instagram.com','www.instagram.com','cdninstagram.com','igcdn.com',
                'scontent.cdninstagram.com','scontent.xx.fbcdn.net','fb.com'
            ],
            'Facebook' => [
                'facebook.com','www.facebook.com','fb.com','fbcdn.net','messenger.com',
                'm.facebook.com','web.facebook.com','static.xx.fbcdn.net','connect.facebook.net'
            ],
            'YouTube' => [
                'youtube.com','www.youtube.com','youtu.be','googlevideo.com','ytimg.com',
                'm.youtube.com','music.youtube.com','i.ytimg.com','i1.ytimg.com','i2.ytimg.com','i3.ytimg.com',
                'youtube.googleapis.com','youtubei.googleapis.com','youtube-nocookie.com','yt3.ggpht.com'
            ],
            'Netflix' => [
                'netflix.com','www.netflix.com','nflxso.net','nflxext.com','nflximg.net','nflxvideo.net',
                'netflixdnstest0.com','netflixdnstest1.com','netflixdnstest2.com'
            ],
            'Spotify' => [
                'spotify.com','www.spotify.com','scdn.co','spoti.fi','spotifycdn.com','audio-fa.scdn.co','heads-fa.scdn.co'
            ],
            'Discord' => [
                'discord.com','www.discord.com','discordapp.com','discord.gg','cdn.discordapp.com','media.discordapp.net','images-ext-1.discordapp.net'
            ],
            'Telegram' => [
                'telegram.org','www.telegram.org','t.me','telegram.me','web.telegram.org','core.telegram.org','my.telegram.org'
            ],
            'Zoom' => [
                'zoom.us','www.zoom.us','zoom.com','zoomgov.com','zmcdn.com','zoomus.com','zoom.com.cn'
            ],
        ];
        
        return $domainMap[$applicationName] ?? [];
    }

    /**
     * Public accessor so UI endpoints (e.g., quick_app_block.php) can fetch domains
     * from the same source of truth used by router updates.
     */
    public static function getDomainsForApplication($applicationName) {
        return self::getApplicationDomains($applicationName);
    }

    /**
     * Trigger router-wide global block/unblock for an application via API/block_global.php
     */
    public static function globalBlockApplication($applicationName, $action = 'block') {
        $action = strtolower($action) === 'unblock' ? 'unblock' : 'block';
        return self::callBlockAPI('block_global.php', [
            'action' => $action,
            'app' => $applicationName
        ], 20);
    }
    
    /**
     * Background update for all devices (non-blocking)
     */
    public static function backgroundUpdateAllDevices($conn, $useRedirect = false, $deviceLimit = 0) {
        // Increase execution time limit to prevent timeouts
        ini_set('max_execution_time', 300); // 5 minutes max
        
        // Get a smaller set of most critical blocked sites
        $criticalSites = [];
        
        // Get recently added sites (last 10)
        $recentResult = mysqli_query($conn, 
            "SELECT DISTINCT website FROM blocklist WHERE website != '' ORDER BY id DESC LIMIT 10"
        );
        while ($row = mysqli_fetch_assoc($recentResult)) {
            $criticalSites[] = trim($row['website']);
        }
        
        // Get active application blocks
        $appResult = mysqli_query($conn, 
            "SELECT DISTINCT domains FROM application_blocks WHERE status = 'active' AND domains != '' LIMIT 5"
        );
        while ($row = mysqli_fetch_assoc($appResult)) {
            $domains = explode(',', $row['domains']);
            foreach ($domains as $domain) {
                $domain = trim($domain);
                if (!empty($domain)) {
                    $criticalSites[] = $domain;
                }
            }
        }
        
        // Get whitelisted sites to exclude from critical sites
        $whitelistSites = [];
        $whitelistResult = mysqli_query($conn, "SELECT DISTINCT website FROM whitelist WHERE website != ''");
        while ($row = mysqli_fetch_assoc($whitelistResult)) {
            $whitelistSites[] = trim($row['website']);
        }
        
        // Remove duplicates and limit
        $criticalSites = array_unique(array_filter($criticalSites));
        
        // EXCLUDE whitelisted sites from critical sites
        if (!empty($whitelistSites)) {
            $criticalSites = array_diff($criticalSites, $whitelistSites);
        }
        
        $criticalSites = array_slice($criticalSites, 0, 30); // Limit to 30 sites
        
        if (empty($criticalSites)) {
            return ['success' => false, 'error' => 'No critical sites to update'];
        }
        
        // If redirect mode, push DNS redirects once (device-agnostic)
        if ($useRedirect) {
            try {
                require_once '../vendor/autoload.php';
                include '../API/connectMikrotik.php';
                if (!isset($client) || !$client) {
                    return ['success' => false, 'error' => 'Router connection failed for redirect'];
                }
                $redirected = 0; $redirectErrors = [];
                // Choose BlockIT web server IP (Apache host) for block page
                $blockitServerIP = null;
                if (!empty($_SERVER['SERVER_ADDR'])) {
                    $blockitServerIP = $_SERVER['SERVER_ADDR'];
                } else {
                    $host = gethostname();
                    if ($host) { $blockitServerIP = gethostbyname($host); }
                }
                if (!$blockitServerIP || $blockitServerIP === '127.0.0.1') {
                    $blockitServerIP = '192.168.10.1';
                }
                // Add DNS static entries for each site
                foreach ($criticalSites as $domain) {
                    $domain = strtolower(trim($domain));
                    $domain = preg_replace('#^https?://#','', $domain);
                    $domain = preg_replace('/^www\./','', $domain);
                    if ($domain === '') continue;
                    try {
                        // remove existing BlockIT entries first
                        $existing = $client->query((new \RouterOS\Query('/ip/dns/static/print'))
                            ->where('name', $domain))->read();
                        foreach ($existing as $e) {
                            if (isset($e['comment']) && strpos($e['comment'], 'BlockIT') !== false) {
                                $client->query((new \RouterOS\Query('/ip/dns/static/remove'))->equal('.id', $e['.id']))->read();
                            }
                        }
                        $client->query((new \RouterOS\Query('/ip/dns/static/add'))
                            ->equal('name', $domain)
                            ->equal('address', $blockitServerIP)
                            ->equal('comment', 'BlockIT redirect'))
                        ->read();
                        // also add www.
                        $client->query((new \RouterOS\Query('/ip/dns/static/add'))
                            ->equal('name', 'www.' . $domain)
                            ->equal('address', $blockitServerIP)
                            ->equal('comment', 'BlockIT redirect www'))
                        ->read();
                        $redirected += 2;
                    } catch (\Throwable $e) {
                        $redirectErrors[] = $domain . ': ' . $e->getMessage();
                    }
                    usleep(50000);
                }
                return [
                    'success' => true,
                    'devices_updated' => 'dns-redirect',
                    'total_sites' => count($criticalSites),
                    'sites_updated' => count($criticalSites),
                    'blocking_method' => 'redirect',
                    'redirects_created' => $redirected,
                    'errors' => $redirectErrors
                ];
            } catch (\Throwable $e) {
                return ['success' => false, 'error' => 'Redirect mode failed: ' . $e->getMessage()];
            }
        } else {
            // Drop mode: apply per-device via API
            $query = "SELECT mac_address, timelimit FROM device WHERE mac_address != ''";
            if (is_int($deviceLimit) && $deviceLimit > 0) {
                $query .= " LIMIT " . intval($deviceLimit);
            }
            $deviceResult = mysqli_query($conn, $query);
            if (!$deviceResult || mysqli_num_rows($deviceResult) == 0) {
                return ['success' => false, 'error' => 'No devices found'];
            }
            $deviceCount = 0; $errors = []; $ok = 0;
            $apiEndpoint = 'block_user.php';
            while ($device = mysqli_fetch_assoc($deviceResult)) {
                $mac = $device['mac_address'];
                $hours = $device['timelimit'] ?? 0;
                try {
                    $apiResult = self::callBlockAPI($apiEndpoint, [
                        'mac_address' => $mac,
                        'sites' => implode(';', $criticalSites),
                        'hours_allowed' => $hours
                    ], 8);
                    if (!$apiResult['success'] || (isset($apiResult['data']['status']) && $apiResult['data']['status'] !== 'success')) {
                        $errors[] = "Failed for $mac: " . ($apiResult['error'] ?? ($apiResult['data']['message'] ?? 'unknown'));
                    } else {
                        $ok++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Exception for $mac: " . $e->getMessage();
                }
                $deviceCount++;
                usleep(25000);
            }
            return [
                'success' => $ok > 0,
                'devices_updated' => $deviceCount,
                'total_sites' => count($criticalSites),
                'sites_updated' => count($criticalSites),
                'blocking_method' => 'drop',
                'api_endpoint' => $apiEndpoint,
                'errors' => $errors
            ];
        }
    }
}

} // End of class_exists check
?>
