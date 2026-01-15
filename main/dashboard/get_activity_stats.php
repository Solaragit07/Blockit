<?php
// Prevent timeouts
set_time_limit(10);
ini_set('max_execution_time', 10);

include '../../connectMySql.php';
include '../../loginverification.php';
include '../../includes/DeviceDetectionService.php';

// RouterOS API imports
require_once '../../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'activity_counts' => [],
    'peak_activity' => 'IDLE',
    'total_devices' => 0,
    'error' => '',
    'debug' => []
];

try {
    // Initialize device service
    $deviceService = null;
    $connectedDevices = [];
    
    // Set a very short timeout for this API call
    set_time_limit(8);
    
    // Try multiple common router IPs with very short timeouts
    $router_ips = ['192.168.10.1', '192.168.1.1', '192.168.0.1', '10.0.0.1'];
    $router_ip = null;
    
    $response['debug'][] = "Testing router connections...";
    
    foreach ($router_ips as $test_ip) {
        $socket_test = @fsockopen($test_ip, 8728, $errno, $errstr, 1); // 1 second timeout
        if ($socket_test) {
            fclose($socket_test);
            $router_ip = $test_ip;
            $response['debug'][] = "Router found at: $test_ip";
            break;
        } else {
            $response['debug'][] = "Router not found at: $test_ip ($errstr)";
        }
    }
    
    if ($router_ip) {
        $response['debug'][] = "Attempting MikroTik connection to $router_ip...";
        
        // Set an alarm to prevent hanging
        $start_time = time();
        
        try {
            include '../../API/connectMikrotik.php';
            
            $connection_time = time() - $start_time;
            if ($connection_time > 5) {
                throw new Exception("MikroTik connection took too long ($connection_time seconds)");
            }
            
            if (isset($client) && $client !== null) {
                $response['debug'][] = "MikroTik connection successful in {$connection_time}s";
                $deviceService = new DeviceDetectionService($client, $conn);
                $deviceData = $deviceService->getInternetConnectedDevices();
                $connectedDevices = $deviceData['devices'];
                $response['debug'][] = "Found " . count($connectedDevices) . " connected devices";
            } else {
                $response['debug'][] = "MikroTik connection failed - client is null";
            }
        } catch (Exception $mikrotik_error) {
            $response['debug'][] = "MikroTik connection error: " . $mikrotik_error->getMessage();
            $deviceService = null;
        }
    } else {
        $response['debug'][] = "No router found on any tested IP";
    }
    
    // If no MikroTik connection, use browser-based detection
    if (!$deviceService || empty($connectedDevices)) {
        $response['debug'][] = "MikroTik unavailable, using browser-based detection";
        
        // Simple browser-based activity detection
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $activityType = 'WEB_BROWSING';
        $activityDisplay = 'Web Browsing';
        
        if (strpos($referrer, 'facebook.com') !== false || strpos($referrer, 'fb.com') !== false) {
            $activityType = 'SOCIAL_MEDIA';
            $activityDisplay = 'Social Media';
        } elseif (strpos($referrer, 'chat.openai.com') !== false || strpos($referrer, 'chatgpt.com') !== false) {
            $activityType = 'PRODUCTIVITY';
            $activityDisplay = 'Productivity';
        } elseif (strpos($referrer, 'youtube.com') !== false) {
            $activityType = 'VIDEO_STREAMING';
            $activityDisplay = 'Video Streaming';
        } elseif (strpos($userAgent, 'Mobile') !== false) {
            $activityType = 'COMMUNICATION';
            $activityDisplay = 'Mobile Activity';
        }
        
        $response['success'] = true;
        $response['activity_counts'] = [$activityType => 1];
        $response['peak_activity'] = $activityDisplay;
        $response['total_devices'] = 1;
        $response['debug'][] = "Detected activity: $activityType from referrer: $referrer";
        echo json_encode($response);
        exit;
    }
    
    // Collect activity statistics
    $activityCounts = [];
    $totalActiveDevices = 0;
    
    $response['debug'][] = "Starting activity detection for " . count($connectedDevices) . " devices...";
    
    foreach ($connectedDevices as $device) {
        $ip = $device['address'] ?? $device['ip'] ?? '';
        $mac = $device['mac-address'] ?? $device['mac'] ?? 'unknown';
        
        $response['debug'][] = "Checking device: $mac at IP: $ip";
        
        if ($ip !== 'N/A' && $ip !== '') {
            try {
                $detectedActivity = $deviceService->getDeviceActivity($ip);
                if ($detectedActivity && isset($detectedActivity['activity'])) {
                    $activityType = $detectedActivity['activity'];
                    $activityCounts[$activityType] = ($activityCounts[$activityType] ?? 0) + 1;
                    $totalActiveDevices++;
                    $response['debug'][] = "Device $mac ($ip): $activityType - " . $detectedActivity['details'];
                } else {
                    $response['debug'][] = "Device $mac ($ip): No activity detected";
                }
            } catch (Exception $e) {
                error_log("Activity stats collection failed for {$ip}: " . $e->getMessage());
                $activityCounts['WEB_BROWSING'] = ($activityCounts['WEB_BROWSING'] ?? 0) + 1;
                $totalActiveDevices++;
                $response['debug'][] = "Device $mac ($ip): Error - " . $e->getMessage();
            }
        } else {
            $activityCounts['IDLE'] = ($activityCounts['IDLE'] ?? 0) + 1;
            $totalActiveDevices++;
            $response['debug'][] = "Device $mac: No IP address";
        }
    }
    
    // Calculate peak activity
    $peakActivity = 'IDLE';
    $maxCount = 0;
    foreach ($activityCounts as $activity => $count) {
        if ($count > $maxCount) {
            $maxCount = $count;
            $peakActivity = $activity;
        }
    }
    
    // Format peak activity for display
    $peakActivityDisplay = ucwords(str_replace('_', ' ', strtolower($peakActivity)));
    
    $response['success'] = true;
    $response['activity_counts'] = $activityCounts;
    $response['peak_activity'] = $peakActivityDisplay;
    $response['total_devices'] = $totalActiveDevices;
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    error_log("Activity stats API error: " . $e->getMessage());
}

echo json_encode($response);
?>
