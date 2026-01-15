<?php
/**
 * Centralized Device Detection Service
 * This replaces all duplicate device detection code across the application
 */

class DeviceDetectionService {
    private $client;
    private $conn;
    
    public function __construct($mikrotikClient, $dbConnection) {
        $this->client = $mikrotikClient;
        $this->conn = $dbConnection;
    }
    
    /**
     * Get only devices that are currently connected to MikroTik router
     * This is the authoritative method for connected device detection
     */
    public function getConnectedDevicesOnly() {
        $connectedDevices = [];
        $connectedMACs = [];
        
        if (!$this->client) {
            return ['devices' => [], 'macs' => []];
        }
        
        try {
            // Step 1: Get DHCP leases - these are devices with active IP assignments
            $dhcpLeases = $this->client->query((new \RouterOS\Query('/ip/dhcp-server/lease/print')))->read();
            
            // Step 2: Get ARP table - these are devices currently reachable
            $arpEntries = $this->client->query((new \RouterOS\Query('/ip/arp/print')))->read();
            
            // Step 3: Create ARP lookup for validation and a list of ARP-only devices
            $arpLookup = [];
            $arpMacIp = [];
            foreach($arpEntries as $arp) {
                if (isset($arp['address']) && isset($arp['mac-address'])) {
                    $ip = $arp['address'];
                    $complete = $arp['complete'] ?? 'false';
                    $invalid = $arp['invalid'] ?? 'false';
                    
                    // Only consider valid, complete ARP entries
                    if ($complete === 'true' && $invalid !== 'true') {
                        $arpLookup[$ip] = $arp;
                        $arpMacIp[$arp['mac-address']] = $ip;
                    }
                }
            }
            
            // Step 4: Filter DHCP leases for truly connected devices
            foreach($dhcpLeases as $lease) {
                if(isset($lease['mac-address']) && !empty($lease['mac-address'])) {
                    $mac = $lease['mac-address'];
                    $ip = $lease['address'] ?? '';
                    $status = $lease['status'] ?? '';
                    $disabled = $lease['disabled'] ?? 'false';
                    $hostname = $lease['host-name'] ?? '';
                    
                    // Log each device being processed
                    error_log("DeviceService: Processing lease - MAC: $mac, IP: $ip, Status: $status, Hostname: $hostname");
                    
                    // Device must be:
                    // 1. Not disabled
                    // 2. Have valid IP
                    // 3. Either lease is active-ish OR have valid ARP entry (for static IPs)
                    $isNotDisabled = ($disabled !== 'true');
                    $hasValidIP = !empty($ip) && $ip !== '0.0.0.0';
                    $isLeaseActive = in_array($status, ['bound','offered','busy'], true);
                    $hasValidARP = isset($arpLookup[$ip]);
                    
                    // Accept if lease looks active OR (has IP and is in ARP table)
                    if ($isNotDisabled && $hasValidIP && ($isLeaseActive || $hasValidARP)) {
                        // Check for duplicate MAC before adding
                        if (!in_array($mac, $connectedMACs)) {
                            $connectedDevices[] = $lease;
                            $connectedMACs[] = $mac;
                            error_log("DeviceService: Added device - MAC: $mac, IP: $ip");
                        } else {
                            error_log("DeviceService: Skipping duplicate MAC: $mac");
                        }
                    } else {
                        error_log("DeviceService: Rejected device - MAC: $mac (disabled: $disabled, validIP: " . ($hasValidIP ? 'yes' : 'no') . ", leaseActive: " . ($isLeaseActive ? 'yes' : 'no') . ", hasARP: " . ($hasValidARP ? 'yes' : 'no') . ")");
                    }
                }
            }

            // Step 5: Include ARP-only devices (e.g., static IP or non-DHCP devices)
            foreach ($arpMacIp as $mac => $ip) {
                if (!in_array($mac, $connectedMACs) && $this->isLocalIP($ip)) {
                    $connectedDevices[] = [
                        'mac-address' => $mac,
                        'address' => $ip,
                        'host-name' => '',
                        'status' => 'arp-only'
                    ];
                    $connectedMACs[] = $mac;
                    error_log("DeviceService: Added ARP-only device - MAC: $mac, IP: $ip");
                }
            }
            
        } catch (Exception $e) {
            error_log("Device detection error: " . $e->getMessage());
        }
        
        return [
            'devices' => $connectedDevices,
            'macs' => $connectedMACs
        ];
    }
    
    /**
     * Get devices with internet connectivity detection
     */
    public function getInternetConnectedDevices() {
        $result = $this->getConnectedDevicesOnly();
        $connectedDevices = $result['devices'];
        
        if (empty($connectedDevices)) {
            return ['devices' => [], 'macs' => [], 'internet_devices' => []];
        }
        
        $internetActiveIPs = [];
        
        try {
            // Method 1: Check active connections for internet traffic
            $connectionsQuery = new \RouterOS\Query('/ip/firewall/connection/print');
            $connectionsQuery->add('?connection-state=established');
            $connections = $this->client->query($connectionsQuery)->read();
            
            foreach($connections as $conn) {
                $srcAddress = $conn['src-address'] ?? '';
                $dstAddress = $conn['dst-address'] ?? '';
                
                if (!empty($srcAddress) && !empty($dstAddress)) {
                    $srcIP = explode(':', $srcAddress)[0];
                    $dstIP = explode(':', $dstAddress)[0];
                    
                    // Check for outbound internet connections
                    if ($this->isLocalIP($srcIP) && !$this->isLocalIP($dstIP)) {
                        $internetActiveIPs[$srcIP] = true;
                    }
                }
            }
            
            // Method 2: If no connections found, check recent DHCP lease activity
            if (empty($internetActiveIPs)) {
                foreach($connectedDevices as $device) {
                    $ip = $device['address'] ?? '';
                    $lastSeen = $device['last-seen'] ?? '';
                    
                    if (!empty($ip) && $this->isLocalIP($ip)) {
                        // If device was seen very recently (within 2 minutes), likely has internet
                        if (strpos($lastSeen, 's') !== false || 
                            (strpos($lastSeen, 'm') !== false && intval($lastSeen) <= 2)) {
                            $internetActiveIPs[$ip] = true;
                        }
                    }
                }
            }
            
            // Method 3: As final fallback, assume devices are online if they have active lease
            if (empty($internetActiveIPs)) {
                foreach($connectedDevices as $device) {
                    $ip = $device['address'] ?? '';
                    if (!empty($ip) && $this->isLocalIP($ip)) {
                        $internetActiveIPs[$ip] = true;
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Internet detection failed: " . $e->getMessage());
            // Fallback: assume all connected devices have internet if we can't detect
            foreach($connectedDevices as $device) {
                $ip = $device['address'] ?? '';
                if (!empty($ip)) {
                    $internetActiveIPs[$ip] = true;
                }
            }
        }
        
        // Separate devices by internet connectivity
        $internetDevices = [];
        $localDevices = [];
        
        foreach($connectedDevices as $device) {
            $ip = $device['address'] ?? '';
            $hasInternet = isset($internetActiveIPs[$ip]);
            $device['hasInternet'] = $hasInternet;
            
            if ($hasInternet) {
                $internetDevices[] = $device;
            } else {
                $localDevices[] = $device;
            }
        }
        
        return [
            'devices' => $connectedDevices,
            'macs' => $result['macs'],
            'internet_devices' => $internetDevices,
            'local_devices' => $localDevices
        ];
    }

    /**
     * Get detailed activity information for a specific device
     */
    public function getDeviceActivity($deviceIP) {
        if (!$this->client) {
            return [
                'activity' => 'Unknown',
                'details' => 'Router connection unavailable',
                'icon' => 'fas fa-question-circle',
                'connections' => []
            ];
        }

        try {
            // Measure time and keep this fast
            $startTs = microtime(true);

            // Query only this device's connections to avoid scanning entire conntrack table
                // Query only this device's connections to avoid scanning entire conntrack table
                // Use regex match to account for ip:port formatting and minimize returned fields
                $qSrc = new \RouterOS\Query('/ip/firewall/connection/print');
                $qSrc->add('=.proplist=src-address,dst-address,reply-dst-address,protocol');
                $qSrc->add('?src-address~^' . $deviceIP . ':');
                $bySrc = $this->client->query($qSrc)->read();

                $qReply = new \RouterOS\Query('/ip/firewall/connection/print');
                $qReply->add('=.proplist=src-address,dst-address,reply-dst-address,protocol');
                $qReply->add('?reply-dst-address~^' . $deviceIP . ':');
                $byReplyDst = $this->client->query($qReply)->read();

            // Merge and cap the number of connections processed
            $connections = array_slice(array_merge($bySrc ?: [], $byReplyDst ?: []), 0, 120);

            $activities = [];
            $detailedConnections = [];
            $hostCounts = [];
            $rdnsBudget = 8; // limit reverse DNS lookups per call
            $maxProcessMs = 300; // stop after ~300ms

            foreach ($connections as $conn) {
                $srcAddressRaw = $conn['src-address'] ?? '';
                $dstAddress = $conn['dst-address'] ?? '';
                $replyDstRaw = $conn['reply-dst-address'] ?? '';
                $protocol = strtolower($conn['protocol'] ?? '');
                $srcIP = explode(':', $srcAddressRaw)[0] ?? '';
                $replyDstIP = explode(':', $replyDstRaw)[0] ?? '';
                $dstPort = explode(':', $dstAddress)[1] ?? '';
                $dstIP = explode(':', $dstAddress)[0] ?? '';

                // Match if device is the original source OR the reply destination (covers UDP and NAT peculiarities)
                $isFromDevice = ($srcIP === $deviceIP) || ($replyDstIP === $deviceIP);
                if (!$isFromDevice) {
                    continue;
                }
                if ($this->isLocalIP($dstIP)) {
                    continue;
                }

                // Try to resolve hostname only for web ports and within tiny budget to avoid timeouts
                $allowLookup = ($protocol === 'tcp' && ($dstPort == 80 || $dstPort == 443) && $rdnsBudget > 0);
                $hostname = $this->resolveHostname($dstIP, $allowLookup);
                if ($allowLookup) { $rdnsBudget--; }

                $activity = $this->categorizeActivity($hostname, $dstPort, $protocol);
                $activities[] = $activity;

                $detailedConnections[] = [
                    'dst_ip' => $dstIP,
                    'dst_port' => $dstPort,
                    'protocol' => $protocol,
                    'hostname' => $hostname,
                    'activity' => $activity
                ];

                // Track base host counts for summary badges
                $h = strtolower($hostname);
                if ($h && !filter_var($h, FILTER_VALIDATE_IP)) {
                    $base = explode(':', $h)[0];
                    $hostCounts[$base] = ($hostCounts[$base] ?? 0) + 1;
                }

                // Time guard: keep this fast per device
                if (((microtime(true) - $startTs) * 1000) > $maxProcessMs) {
                    break;
                }
            }

            // Determine primary activity strictly from live connections (no fallbacks)
            if (empty($activities)) {
                return [
                    'activity' => 'IDLE',
                    'details' => 'No active internet connections',
                    'icon' => 'fas fa-moon',
                    'connections' => $detailedConnections
                ];
            }

            // Count activity types and return the most prominent with breakdown
            $typeList = array_column($activities, 'type');
            $activityCounts = array_count_values($typeList);
            arsort($activityCounts);
            $primaryActivity = key($activityCounts);

            $total = array_sum($activityCounts);
            $primaryCount = $activityCounts[$primaryActivity] ?? 0;
            $dominance = $total > 0 ? ($primaryCount / $total) : 0;

            // Prepare top hosts summary
            arsort($hostCounts);
            $topHosts = array_slice(array_keys($hostCounts), 0, 3);
            $primaryHost = $topHosts[0] ?? '';

            // If multiple categories are active and none dominates, mark as MIXED
            if (count($activityCounts) >= 2 && $dominance < 0.6) {
                // Build a short combined label from top two types
                $types = array_keys($activityCounts);
                $combo = array_slice($types, 0, 2);
                $friendly = function($t){ return ucwords(str_replace('_',' ', strtolower($t))); };
                $comboLabel = implode(' + ', array_map($friendly, $combo));
                return [
                    'activity' => 'MIXED',
                    'details' => $comboLabel . ' (' . $total . ' connections)',
                    'icon' => 'fas fa-layer-group',
                    'connections' => $detailedConnections,
                    'top_hosts' => $topHosts,
                    'primary_host' => $primaryHost,
                    'activity_counts' => $activityCounts
                ];
            }

            $primaryDetails = array_filter($activities, function($a) use ($primaryActivity) { return $a['type'] === $primaryActivity; });
            $firstDetail = reset($primaryDetails);

            return [
                'activity' => $primaryActivity,
                'details' => $firstDetail['details'] ?? 'Active connections detected',
                'icon' => $firstDetail['icon'] ?? 'fas fa-globe',
                'connections' => $detailedConnections,
                'top_hosts' => $topHosts,
                'primary_host' => $primaryHost,
                'activity_counts' => $activityCounts
            ];

        } catch (Exception $e) {
            error_log("Activity detection error for {$deviceIP}: " . $e->getMessage());
            return [
                'activity' => 'ERROR',
                'details' => 'Unable to detect activity',
                'icon' => 'fas fa-exclamation-triangle',
                'connections' => []
            ];
        }
    }

    /**
     * Categorize network activity based on hostname and port
     */
    private function categorizeActivity($hostname, $port, $protocol) {
        $hostname = strtolower($hostname);
        $port = intval($port);
    $domain = $this->baseDomain($hostname);

        // Social Media
        if (strpos($hostname, 'facebook') !== false || strpos($hostname, 'instagram') !== false || 
            strpos($hostname, 'twitter') !== false || strpos($hostname, 'tiktok') !== false ||
            strpos($hostname, 'snapchat') !== false || strpos($hostname, 'linkedin') !== false) {
            return [
                'type' => 'SOCIAL_MEDIA',
                'details' => ($domain ? 'Browsing ' . $domain : 'Social media'),
                'icon' => 'fas fa-users'
            ];
        }

        // Video Streaming
        if (strpos($hostname, 'youtube') !== false || strpos($hostname, 'googlevideo') !== false) {
            return [
                'type' => 'VIDEO_STREAMING',
                'details' => ($domain ?: 'YouTube'),
                'icon' => 'fab fa-youtube'
            ];
        }
        if (strpos($hostname, 'netflix') !== false) {
            return [
                'type' => 'VIDEO_STREAMING',
                'details' => ($domain ?: 'netflix.com'),
                'icon' => 'fas fa-film'
            ];
        }
        if (strpos($hostname, 'twitch') !== false) {
            return [
                'type' => 'VIDEO_STREAMING',
                'details' => ($domain ?: 'twitch.tv'),
                'icon' => 'fab fa-twitch'
            ];
        }

        // Gaming - hostnames (PC & Mobile)
        if (strpos($hostname, 'steam') !== false || strpos($hostname, 'valve') !== false) {
            return [
                'type' => 'GAMING',
                'details' => ($domain ?: 'store.steampowered.com'),
                'icon' => 'fab fa-steam'
            ];
        }
        if (strpos($hostname, 'roblox') !== false) {
            return [
                'type' => 'GAMING',
                'details' => ($domain ?: 'roblox.com'),
                'icon' => 'fas fa-gamepad'
            ];
        }
        if (strpos($hostname, 'minecraft') !== false || strpos($hostname, 'mojang') !== false) {
            return [
                'type' => 'GAMING',
                'details' => ($domain ?: 'minecraft.net'),
                'icon' => 'fas fa-cube'
            ];
        }
        if (strpos($hostname, 'epicgames') !== false || strpos($hostname, 'fortnite') !== false) {
            return [
                'type' => 'GAMING',
                'details' => ($domain ?: 'epicgames.com'),
                'icon' => 'fas fa-gamepad'
            ];
        }
        if (strpos($hostname, 'riot') !== false || strpos($hostname, 'valorant') !== false || strpos($hostname, 'leagueoflegends') !== false || strpos($hostname, 'lol') !== false) {
            return [
                'type' => 'GAMING',
                'details' => ($domain ?: 'riotgames.com'),
                'icon' => 'fas fa-gamepad'
            ];
        }
        if (strpos($hostname, 'playstation') !== false || strpos($hostname, 'psn') !== false) {
            return [
                'type' => 'GAMING',
                'details' => ($domain ?: 'playstation.com'),
                'icon' => 'fab fa-playstation'
            ];
        }
        if (strpos($hostname, 'xboxlive') !== false || strpos($hostname, 'xbox') !== false) {
            return [
                'type' => 'GAMING',
                'details' => ($domain ?: 'xbox.com'),
                'icon' => 'fab fa-xbox'
            ];
        }
        if (strpos($hostname, 'nintendo') !== false) {
            return [
                'type' => 'GAMING',
                'details' => ($domain ?: 'nintendo.com'),
                'icon' => 'fas fa-gamepad'
            ];
        }
        if (strpos($hostname, 'ea') !== false || strpos($hostname, 'origin') !== false || strpos($hostname, 'ea.com') !== false) {
            return [
                'type' => 'GAMING',
                'details' => ($domain ?: 'ea.com'),
                'icon' => 'fas fa-gamepad'
            ];
        }
        if (strpos($hostname, 'blizzard') !== false || strpos($hostname, 'battle.net') !== false) {
            return [
                'type' => 'GAMING',
                'details' => ($domain ?: 'battle.net'),
                'icon' => 'fas fa-gamepad'
            ];
        }
        if (strpos($hostname, 'rockstargames') !== false || strpos($hostname, 'gtav') !== false) {
            return [
                'type' => 'GAMING',
                'details' => ($domain ?: 'rockstargames.com'),
                'icon' => 'fas fa-gamepad'
            ];
        }
        if (strpos($hostname, 'mihoyo') !== false || strpos($hostname, 'hoyoverse') !== false || strpos($hostname, 'genshin') !== false) {
            return [
                'type' => 'GAMING',
                'details' => ($domain ?: 'hoyoverse.com'),
                'icon' => 'fas fa-gamepad'
            ];
        }
        if (strpos($hostname, 'garena') !== false || strpos($hostname, 'freefire') !== false) {
            return [
                'type' => 'GAMING',
                'details' => ($domain ?: 'garena.com'),
                'icon' => 'fas fa-gamepad'
            ];
        }
        if (strpos($hostname, 'mobilelegends') !== false || strpos($hostname, 'moonton') !== false || strpos($hostname, 'mlbb') !== false) {
            return [
                'type' => 'GAMING',
                'details' => ($domain ?: 'mobilelegends.com'),
                'icon' => 'fas fa-gamepad'
            ];
        }
        if (strpos($hostname, 'pubg') !== false || strpos($hostname, 'krafton') !== false || strpos($hostname, 'bgmi') !== false) {
            return [
                'type' => 'GAMING',
                'details' => ($domain ?: 'pubg.com'),
                'icon' => 'fas fa-gamepad'
            ];
        }

        // Communication
        if (strpos($hostname, 'discord') !== false) {
            return [
                'type' => 'COMMUNICATION',
                'details' => ($domain ?: 'discord.com'),
                'icon' => 'fab fa-discord'
            ];
        }
        if (strpos($hostname, 'zoom') !== false || strpos($hostname, 'teams') !== false || 
            strpos($hostname, 'skype') !== false) {
            return [
                'type' => 'COMMUNICATION',
                'details' => ($domain ?: 'video call'),
                'icon' => 'fas fa-video'
            ];
        }
        if (strpos($hostname, 'whatsapp') !== false || strpos($hostname, 'telegram') !== false) {
            return [
                'type' => 'COMMUNICATION',
                'details' => ($domain ?: 'messaging'),
                'icon' => 'fas fa-comment'
            ];
        }

        // Shopping
        if (strpos($hostname, 'amazon') !== false || strpos($hostname, 'ebay') !== false || 
            strpos($hostname, 'shopify') !== false || strpos($hostname, 'alibaba') !== false) {
            return [
                'type' => 'SHOPPING',
                'details' => ($domain ?: 'shopping'),
                'icon' => 'fas fa-shopping-cart'
            ];
        }

        // Education
        if (strpos($hostname, 'khan') !== false || strpos($hostname, 'coursera') !== false || 
            strpos($hostname, 'udemy') !== false || strpos($hostname, 'edu') !== false) {
            return [
                'type' => 'EDUCATION',
                'details' => ($domain ?: 'education'),
                'icon' => 'fas fa-graduation-cap'
            ];
        }

        // Work/Productivity
        if (strpos($hostname, 'office') !== false || strpos($hostname, 'google') !== false || 
            strpos($hostname, 'dropbox') !== false || strpos($hostname, 'github') !== false) {
            return [
                'type' => 'PRODUCTIVITY',
                'details' => ($domain ?: 'productivity'),
                'icon' => 'fas fa-briefcase'
            ];
        }

    // Port-based detection (prioritize gaming-related ports before generic web)
    if ($port == 443 || $port == 80) {
            return [
                'type' => 'WEB_BROWSING',
                'details' => 'Browsing ' . ($domain ?: $hostname),
                'icon' => 'fas fa-globe'
            ];
        }
    // Gaming-related common ports
        if (($port >= 27000 && $port <= 27100) || // Steam range
            $port == 3074 || // Xbox/PS/CoD
            ($port >= 3075 && $port <= 3076) ||
            ($port >= 3478 && $port <= 3480) || // PSN/STUN (often gaming/voice)
            ($port >= 7000 && $port <= 7999) || // Riot/Valorant range
            $port == 3659 // EA
        ) {
            return [
                'type' => 'GAMING',
                'details' => 'Online gaming traffic (port ' . $port . ')',
                'icon' => 'fas fa-gamepad'
            ];
        }
        if ($port >= 6881 && $port <= 6889) {
            return [
                'type' => 'FILE_SHARING',
                'details' => 'BitTorrent/P2P',
                'icon' => 'fas fa-download'
            ];
        }

        // Default fallback
        return [
            'type' => 'NETWORK_ACTIVITY',
            'details' => ($domain ? ('Talking to ' . $domain) : ('Network activity on port ' . $port)),
            'icon' => 'fas fa-network-wired'
        ];
    }

    private function baseDomain($hostname) {
        if (!$hostname || filter_var($hostname, FILTER_VALIDATE_IP)) return '';
        $h = strtolower($hostname);
        // strip common subdomains
        $h = preg_replace('/^(www\.|m\.|mobile\.|api\.|cdn\.|static\.)/i', '', $h);
        $parts = explode('.', $h);
        if (count($parts) >= 2) {
            $last2 = implode('.', array_slice($parts, -2));
            return $last2;
        }
        return $h;
    }

    /**
     * Simple hostname resolution with caching
     */
    private function resolveHostname($ip) {
        // Backward-compatible wrapper; by default do NOT perform reverse DNS lookup to avoid blocking.
        // Accepts optional second parameter to allow a limited rDNS when explicitly requested.
        $allowLookup = false;
        $args = func_get_args();
        if (count($args) > 1) {
            $allowLookup = (bool)$args[1];
        }

        static $cache = [];
        if (isset($cache[$ip])) {
            return $cache[$ip];
        }

        // If not public or lookup not allowed, just return the IP
        $isPublic = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        if (!$allowLookup || !$isPublic) {
            $cache[$ip] = $ip;
            return $cache[$ip];
        }

        // Best-effort rDNS with suppression; if it blocks, PHP may still delay, so we keep usage minimal via budget in caller
        $hostname = @gethostbyaddr($ip);
        $cache[$ip] = ($hostname && $hostname !== $ip && strlen($hostname) < 120) ? strtolower($hostname) : $ip;
        return $cache[$ip];
    }
    
    /**
     * Get device information from database
     */
    public function getDeviceDatabase() {
        $deviceMap = [];
        $query = "SELECT * FROM device";
        $result = $this->conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $deviceMap[$row['mac_address']] = $row;
            }
        }
        
        return $deviceMap;
    }
    
    /**
     * Check if IP address is local/private
     */
    private function isLocalIP($ip) {
        if (empty($ip)) return false;
        
        return (
            strpos($ip, '192.168.') === 0 ||
            strpos($ip, '10.') === 0 ||
            preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip) ||
            strpos($ip, '127.') === 0 ||
            strpos($ip, '169.254.') === 0
        );
    }
    
    /**
     * Test internet connectivity for a specific IP using MikroTik ping
     */
    private function testInternetConnectivity($sourceIP) {
        try {
            // Fast per-device signal: check DHCP lease recency
            $leaseQ = new \RouterOS\Query('/ip/dhcp-server/lease/print');
            $leaseQ->add('=.proplist=last-seen,status');
            $leaseQ->add('?address=' . $sourceIP);
            $lease = $this->client->query($leaseQ)->read();

            if (!empty($lease)) {
                $row = $lease[0];
                $lastSeen = $row['last-seen'] ?? '';
                $status = $row['status'] ?? '';
                // Consider device "active" if seen in seconds or within ~2 minutes, or lease status indicates activity
                if (strpos($lastSeen, 's') !== false) { return true; }
                if (strpos($lastSeen, 'm') !== false && intval($lastSeen) <= 2) { return true; }
                if (in_array($status, ['bound','offered','busy'], true)) { return true; }
            }

            return false;
        } catch (Exception $e) {
            // If ping fails, assume no internet
            return false;
        }
    }
    
    /**
     * Calculate remaining time for a device
     */
    public function calculateRemainingTime($macAddress, $timeLimitHours) {
        try {
            $today = date('Y-m-d');
            $query = "SELECT SUM(TIMESTAMPDIFF(MINUTE, session_start, COALESCE(session_end, NOW()))) as total_minutes 
                      FROM device_sessions 
                      WHERE mac_address = ? AND DATE(session_start) = ?";
            
            $stmt = $this->conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("ss", $macAddress, $today);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();
                
                $usedMinutes = $row['total_minutes'] ?? 0;
                $timeLimitMinutes = $timeLimitHours * 60;
                $remainingMinutes = $timeLimitMinutes - $usedMinutes;
                
                if ($remainingMinutes <= 0) {
                    return '<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Time Exceeded</span>';
                } else {
                    $hours = floor($remainingMinutes / 60);
                    $minutes = $remainingMinutes % 60;
                    
                    if ($hours > 0) {
                        return '<span class="text-success">' . $hours . 'h ' . $minutes . 'm</span>';
                    } else {
                        $colorClass = $minutes <= 30 ? 'text-warning' : 'text-success';
                        return '<span class="' . $colorClass . '">' . $minutes . 'm</span>';
                    }
                }
            } else {
                return '<span class="text-info">' . $timeLimitHours . 'h total</span>';
            }
        } catch (Exception $e) {
            error_log("Error calculating remaining time: " . $e->getMessage());
            return '<span class="text-info">' . $timeLimitHours . 'h total</span>';
        }
    }
}
?>
