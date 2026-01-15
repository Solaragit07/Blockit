<?php
/**
 * Real-time Bandwidth Monitoring API Endpoint
 * 
 * This endpoint provides device bandwidth data for the real-time dashboard
 * It can be used as a fallback when WebSocket connection is not available
 */

// Enable CORS for API access
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include the bandwidth monitor class
require_once '../../includes/MikroTikBandwidthMonitor.php';

try {
    // Initialize the bandwidth monitor
    $monitor = new MikroTikBandwidthMonitor('192.168.10.1', 'admin', '');
    
    // Get current bandwidth data
    $bandwidthData = $monitor->getCurrentBandwidth();
    
    // Format response
    $response = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $bandwidthData,
        'meta' => [
            'total_devices' => count($bandwidthData['devices']),
            'monitoring_active' => true,
            'source' => 'php_api'
        ]
    ];
    
    // Log API access for debugging
    error_log("Real-time API accessed at " . date('Y-m-d H:i:s') . " - " . count($bandwidthData['devices']) . " devices");
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Error response
    $errorResponse = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => [
            'devices' => [],
            'summary' => [
                'total_download' => 0,
                'total_upload' => 0,
                'peak_activity' => 'UNKNOWN'
            ]
        ],
        'meta' => [
            'total_devices' => 0,
            'monitoring_active' => false,
            'source' => 'php_api'
        ]
    ];
    
    error_log("Real-time API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode($errorResponse, JSON_PRETTY_PRINT);
}
?>
