<?php
/**
 * Enhanced MikroTik Bandwidth Monitor for BlockIt
 * Real-time bandwidth monitoring with activity categorization
 */

class MikroTikBandwidthMonitor {
    private $client;
    private $connected = false;
    private $config;
    
    // Activity categorization thresholds (bytes per second)
    const ACTIVITY_THRESHOLDS = [
        'READING' => ['min' => 1024, 'max' => 1048576, 'pattern' => 'sustained'],
        'SCROLLING' => ['min' => 102400, 'max' => 5242880, 'pattern' => 'bursts'],
        'WATCHING' => ['min' => 2097152, 'max' => 52428800, 'pattern' => 'sustained_high'],
        'PLAYING' => ['min' => 512000, 'max' => 10485760, 'pattern' => 'low_latency']
    ];
    
    // Domain patterns for activity detection
    const DOMAIN_PATTERNS = [
        'READING' => ['wikipedia.org', 'reddit.com', 'stackoverflow.com', 'github.com', 'news'],
        'SCROLLING' => ['facebook.com', 'instagram.com', 'twitter.com', 'tiktok.com', 'discord.com'],
        'WATCHING' => ['youtube.com', 'netflix.com', 'twitch.tv', 'spotify.com', 'hulu.com'],
        'PLAYING' => ['steam.com', 'epicgames.com', 'battle.net', 'minecraft.net', 'roblox.com']
    ];
    
    public function __construct($routerIP = '192.168.10.1', $username = 'admin', $password = '') {
        $this->config = [
            'host' => $routerIP,
            'user' => $username,
            'pass' => $password,
            'port' => 8728
        ];
        
        // Include RouterOS API
        require_once __DIR__ . '/../vendor/autoload.php';
        
        try {
            $this->client = new \RouterOS\Client([
                'host' => $this->config['host'],
                'user' => $this->config['user'],
                'pass' => $this->config['pass'],
                'port' => $this->config['port']
            ]);
            $this->connected = true;
        } catch (Exception $e) {
            error_log("MikroTik connection failed: " . $e->getMessage());
            $this->connected = false;
        }
    }
    
    /**
     * Get real-time bandwidth data for all devices
     */
    public function getRealTimeBandwidthData() {
        if (!$this->connected) {
            return ['error' => 'Not connected to MikroTik router'];
        }
        
        try {
            $devices = [];
            
            // Get active DHCP leases
            $dhcpLeases = $this->client->query(new \RouterOS\Query('/ip/dhcp-server/lease/print'))->read();
            
            // Get Simple Queue data for per-device bandwidth
            $queues = $this->client->query(new \RouterOS\Query('/queue/simple/print'))->read();
            
            // Get ARP entries for additional device info
            $arpEntries = $this->client->query(new \RouterOS\Query('/ip/arp/print'))->read();
            
            // Get interface statistics
            $interfaces = $this->client->query(new \RouterOS\Query('/interface/print', ['stats' => '']))->read();
            
            // Process each active device
            foreach ($dhcpLeases as $lease) {
                if (isset($lease['status']) && $lease['status'] === 'bound' && !empty($lease['address'])) {
                    $deviceIP = $lease['address'];
                    $deviceMAC = $lease['mac-address'] ?? '';
                    $hostname = $lease['host-name'] ?? 'Unknown Device';
                    
                    // Find corresponding queue data
                    $queueData = $this->findQueueByTarget($queues, $deviceIP);
                    
                    // Get ARP info
                    $arpInfo = $this->findARPByIP($arpEntries, $deviceIP);
                    
                    // Calculate bandwidth
                    $bandwidth = $this->calculateBandwidth($queueData);
                    
                    // Categorize activity
                    $activity = $this->categorizeActivity($bandwidth, $deviceIP, $hostname);
                    
                    $devices[] = [
                        'ip' => $deviceIP,
                        'mac' => $deviceMAC,
                        'hostname' => $hostname,
                        'interface' => $arpInfo['interface'] ?? 'bridge',
                        'bandwidth' => $bandwidth,
                        'activity' => $activity,
                        'last_seen' => time(),
                        'status' => 'active'
                    ];
                }
            }
            
            // Get total router bandwidth
            $totalBandwidth = $this->getTotalBandwidth($interfaces);
            
            return [
                'success' => true,
                'devices' => $devices,
                'total_bandwidth' => $totalBandwidth,
                'device_count' => count($devices),
                'timestamp' => time(),
                'router_info' => [
                    'ip' => $this->config['host'],
                    'connected' => true
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Error fetching bandwidth data: " . $e->getMessage());
            return [
                'error' => 'Failed to fetch bandwidth data: ' . $e->getMessage(),
                'connected' => false
            ];
        }
    }
    
    /**
     * Find queue data by target IP
     */
    private function findQueueByTarget($queues, $targetIP) {
        foreach ($queues as $queue) {
            if (isset($queue['target']) && strpos($queue['target'], $targetIP) !== false) {
                return $queue;
            }
        }
        return null;
    }
    
    /**
     * Find ARP entry by IP
     */
    private function findARPByIP($arpEntries, $ip) {
        foreach ($arpEntries as $arp) {
            if (isset($arp['address']) && $arp['address'] === $ip) {
                return $arp;
            }
        }
        return [];
    }
    
    /**
     * Calculate bandwidth from queue data
     */
    private function calculateBandwidth($queueData) {
        if (!$queueData) {
            return [
                'download_rate' => 0,
                'upload_rate' => 0,
                'total_rate' => 0,
                'download_bytes' => 0,
                'upload_bytes' => 0,
                'total_bytes' => 0,
                'download_packets' => 0,
                'upload_packets' => 0
            ];
        }
        
        $downloadRate = (int)($queueData['rate-down'] ?? 0);
        $uploadRate = (int)($queueData['rate-up'] ?? 0);
        $downloadBytes = (int)($queueData['bytes-down'] ?? 0);
        $uploadBytes = (int)($queueData['bytes-up'] ?? 0);
        $downloadPackets = (int)($queueData['packets-down'] ?? 0);
        $uploadPackets = (int)($queueData['packets-up'] ?? 0);
        
        return [
            'download_rate' => $downloadRate,
            'upload_rate' => $uploadRate,
            'total_rate' => $downloadRate + $uploadRate,
            'download_bytes' => $downloadBytes,
            'upload_bytes' => $uploadBytes,
            'total_bytes' => $downloadBytes + $uploadBytes,
            'download_packets' => $downloadPackets,
            'upload_packets' => $uploadPackets,
            'formatted' => [
                'download_rate' => $this->formatBytes($downloadRate) . '/s',
                'upload_rate' => $this->formatBytes($uploadRate) . '/s',
                'total_rate' => $this->formatBytes($downloadRate + $uploadRate) . '/s',
                'download_bytes' => $this->formatBytes($downloadBytes),
                'upload_bytes' => $this->formatBytes($uploadBytes),
                'total_bytes' => $this->formatBytes($downloadBytes + $uploadBytes)
            ]
        ];
    }
    
    /**
     * Categorize device activity based on bandwidth patterns
     */
    private function categorizeActivity($bandwidth, $deviceIP, $hostname) {
        $totalRate = $bandwidth['total_rate'];
        $downloadRate = $bandwidth['download_rate'];
        $uploadRate = $bandwidth['upload_rate'];
        
        // Default activity
        $activity = [
            'type' => 'IDLE',
            'confidence' => 0,
            'description' => 'No significant activity',
            'icon' => 'fas fa-moon',
            'color' => '#6c757d'
        ];
        
        if ($totalRate < 1024) { // Less than 1KB/s
            return $activity;
        }
        
        // Analyze patterns
        $downloadRatio = $downloadRate > 0 ? $downloadRate / ($downloadRate + $uploadRate) : 0;
        $uploadRatio = 1 - $downloadRatio;
        
        // PLAYING - Gaming pattern: moderate bandwidth, balanced up/down, low latency indicators
        if ($totalRate >= self::ACTIVITY_THRESHOLDS['PLAYING']['min'] && 
            $totalRate <= self::ACTIVITY_THRESHOLDS['PLAYING']['max'] &&
            $uploadRatio > 0.2 && $uploadRatio < 0.8) { // Balanced traffic
            
            $confidence = min(85 + ($uploadRatio * 15), 100);
            $activity = [
                'type' => 'PLAYING',
                'confidence' => $confidence,
                'description' => 'Gaming or interactive application',
                'icon' => 'fas fa-gamepad',
                'color' => '#6f42c1',
                'details' => [
                    'pattern' => 'Balanced bidirectional traffic',
                    'bandwidth_category' => 'moderate'
                ]
            ];
        }
        // WATCHING - Video streaming: high download, low upload
        elseif ($totalRate >= self::ACTIVITY_THRESHOLDS['WATCHING']['min'] && 
                $downloadRatio > 0.85) { // High download ratio
            
            $confidence = min(70 + ($downloadRatio * 30), 100);
            $activity = [
                'type' => 'WATCHING',
                'confidence' => $confidence,
                'description' => 'Video streaming or media consumption',
                'icon' => 'fas fa-play-circle',
                'color' => '#dc3545',
                'details' => [
                    'pattern' => 'High download, sustained traffic',
                    'bandwidth_category' => 'high'
                ]
            ];
        }
        // SCROLLING - Social media: medium bandwidth, bursts
        elseif ($totalRate >= self::ACTIVITY_THRESHOLDS['SCROLLING']['min'] && 
                $totalRate <= self::ACTIVITY_THRESHOLDS['SCROLLING']['max'] &&
                $downloadRatio > 0.7) { // Mostly download
            
            $confidence = 60 + min(($totalRate / self::ACTIVITY_THRESHOLDS['SCROLLING']['max']) * 25, 25);
            $activity = [
                'type' => 'SCROLLING',
                'confidence' => $confidence,
                'description' => 'Social media browsing',
                'icon' => 'fas fa-mobile-alt',
                'color' => '#17a2b8',
                'details' => [
                    'pattern' => 'Medium bandwidth, image/video loading',
                    'bandwidth_category' => 'medium'
                ]
            ];
        }
        // READING - Text content: low sustained bandwidth
        elseif ($totalRate >= self::ACTIVITY_THRESHOLDS['READING']['min'] && 
                $totalRate <= self::ACTIVITY_THRESHOLDS['READING']['max']) {
            
            $confidence = 50 + min(25, 25 - ($totalRate / self::ACTIVITY_THRESHOLDS['READING']['max']) * 25);
            $activity = [
                'type' => 'READING',
                'confidence' => $confidence,
                'description' => 'Text browsing or reading',
                'icon' => 'fas fa-book-open',
                'color' => '#28a745',
                'details' => [
                    'pattern' => 'Low sustained bandwidth',
                    'bandwidth_category' => 'low'
                ]
            ];
        }
        // BROWSING - General web browsing
        else {
            $confidence = 40;
            $activity = [
                'type' => 'BROWSING',
                'confidence' => $confidence,
                'description' => 'General web browsing',
                'icon' => 'fas fa-globe',
                'color' => '#ffc107',
                'details' => [
                    'pattern' => 'Mixed traffic pattern',
                    'bandwidth_category' => 'variable'
                ]
            ];
        }
        
        // Add bandwidth details
        $activity['bandwidth_details'] = [
            'total_rate' => $totalRate,
            'download_ratio' => round($downloadRatio * 100, 1),
            'upload_ratio' => round($uploadRatio * 100, 1)
        ];
        
        return $activity;
    }
    
    /**
     * Get total router bandwidth
     */
    private function getTotalBandwidth($interfaces) {
        $totalRxBytes = 0;
        $totalTxBytes = 0;
        $totalRxRate = 0;
        $totalTxRate = 0;
        
        foreach ($interfaces as $interface) {
            if (isset($interface['type']) && $interface['type'] !== 'loopback') {
                $totalRxBytes += (int)($interface['rx-byte'] ?? 0);
                $totalTxBytes += (int)($interface['tx-byte'] ?? 0);
                // Note: Rate calculation would need monitoring over time
            }
        }
        
        return [
            'total_rx_bytes' => $totalRxBytes,
            'total_tx_bytes' => $totalTxBytes,
            'total_bytes' => $totalRxBytes + $totalTxBytes,
            'formatted' => [
                'total_rx' => $this->formatBytes($totalRxBytes),
                'total_tx' => $this->formatBytes($totalTxBytes),
                'total' => $this->formatBytes($totalRxBytes + $totalTxBytes)
            ]
        ];
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Get historical bandwidth data (simplified version)
     */
    public function getHistoricalData($deviceIP, $hours = 24) {
        // This would typically read from a database
        // For now, return mock data structure
        return [
            'device_ip' => $deviceIP,
            'period_hours' => $hours,
            'data_points' => [], // Would contain time-series data
            'summary' => [
                'total_download' => 0,
                'total_upload' => 0,
                'peak_time' => null,
                'most_active_activity' => 'UNKNOWN'
            ]
        ];
    }
    
    /**
     * Check if router is reachable
     */
    public function isConnected() {
        return $this->connected;
    }
    
    /**
     * Get router system information
     */
    public function getSystemInfo() {
        if (!$this->connected) {
            return ['error' => 'Not connected'];
        }
        
        try {
            $system = $this->client->query(new \RouterOS\Query('/system/resource/print'))->read();
            $identity = $this->client->query(new \RouterOS\Query('/system/identity/print'))->read();
            
            return [
                'identity' => $identity[0]['name'] ?? 'MikroTik Router',
                'version' => $system[0]['version'] ?? 'Unknown',
                'uptime' => $system[0]['uptime'] ?? 'Unknown',
                'cpu_load' => $system[0]['cpu-load'] ?? 0,
                'free_memory' => $system[0]['free-memory'] ?? 0,
                'total_memory' => $system[0]['total-memory'] ?? 0
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Set bandwidth limit for a device
     */
    public function setBandwidthLimit($macAddress, $downloadLimitBps, $uploadLimitBps) {
        if (!$this->connected) {
            error_log("setBandwidthLimit: Not connected to router");
            return false;
        }
        
        try {
            // Convert bytes per second to MikroTik format (with units)
            $downloadLimit = $this->formatBandwidthLimit($downloadLimitBps);
            $uploadLimit = $this->formatBandwidthLimit($uploadLimitBps);
            
            // Create or update simple queue for the device
            $queueName = "limit_" . str_replace(':', '', $macAddress);
            
            // First, try to remove existing queue if any
            $existingQueues = $this->client->query((new \RouterOS\Query('/queue/simple/print'))
                ->where('name', $queueName))->read();
            
            foreach ($existingQueues as $queue) {
                $this->client->query((new \RouterOS\Query('/queue/simple/remove'))
                    ->equal('.id', $queue['.id']))->read();
            }
            
            // Add new bandwidth limit queue
            $this->client->query((new \RouterOS\Query('/queue/simple/add'))
                ->equal('name', $queueName)
                ->equal('target', $macAddress)
                ->equal('max-limit', $uploadLimit . '/' . $downloadLimit)
                ->equal('comment', 'BlockIt Bandwidth Limit'))->read();
            
            error_log("setBandwidthLimit: Successfully set limit for $macAddress - Down: $downloadLimit, Up: $uploadLimit");
            return true;
            
        } catch (Exception $e) {
            error_log("setBandwidthLimit error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Format bandwidth limit for MikroTik
     */
    private function formatBandwidthLimit($bytesPerSecond) {
        if ($bytesPerSecond >= 1024 * 1024 * 1024) {
            return round($bytesPerSecond / (1024 * 1024 * 1024), 2) . 'G';
        } elseif ($bytesPerSecond >= 1024 * 1024) {
            return round($bytesPerSecond / (1024 * 1024), 2) . 'M';
        } elseif ($bytesPerSecond >= 1024) {
            return round($bytesPerSecond / 1024, 2) . 'k';
        } else {
            return $bytesPerSecond;
        }
    }
}

// API endpoint for real-time data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $monitor = new MikroTikBandwidthMonitor();
    
    switch ($_GET['action']) {
        case 'bandwidth':
            echo json_encode($monitor->getRealTimeBandwidthData());
            break;
            
        case 'system':
            echo json_encode($monitor->getSystemInfo());
            break;
            
        case 'historical':
            $deviceIP = $_GET['device'] ?? '';
            $hours = (int)($_GET['hours'] ?? 24);
            echo json_encode($monitor->getHistoricalData($deviceIP, $hours));
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}
?>
