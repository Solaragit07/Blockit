<?php
session_start();
include '../../connectMySql.php';
include '../../loginverification.php';
include '../../includes/DeviceDetectionService.php';

// Check if user is logged in
if(!logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Release session lock to avoid blocking concurrent AJAX
if (session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close();
}

// RouterOS API imports
require_once '../../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

// Response header for JSON
header('Content-Type: application/json');

try {
    // Initialize device detection service
    $deviceService = null;
    $connectedDevices = [];
    $connectedCount = 0;
    
    include '../../API/connectMikrotik.php';
    if (isset($client) && $client !== null) {
        $deviceService = new DeviceDetectionService($client, $conn);
        
        // Get only devices currently connected to MikroTik
        $deviceData = $deviceService->getConnectedDevicesOnly();
        $connectedDevices = $deviceData['devices'];
        $connectedCount = count($connectedDevices);
        
        // Get device database for additional info
        $deviceMap = $deviceService->getDeviceDatabase();
    }
    
    // Generate HTML for device table
    $html = '';
    
    if (!empty($connectedDevices) && $deviceService) {
        foreach ($connectedDevices as $lease) {
            $macAddress = $lease['mac-address'];
            $ipAddress = isset($lease['address']) ? $lease['address'] : 'N/A';
            $hostName = isset($lease['host-name']) ? $lease['host-name'] : 'Unknown';
            
            // Check if device is in database
            if (isset($deviceMap[$macAddress])) {
                // Known device
                $device = $deviceMap[$macAddress];
                $deviceName = $device['name'];
                $deviceType = $device['device'];
                $age = $device['age'];
                $timeLimitHours = $device['timelimit'];
                $avatar = $device['image'];
                $deviceId = $device['id'];
                $isKnown = true;
                
                $remainingTime = $deviceService->calculateRemainingTime($macAddress, $timeLimitHours);
            } else {
                // Unknown device
                $deviceName = !empty($hostName) && $hostName != 'Unknown' ? $hostName : 'Unknown Device';
                $deviceType = 'Unknown';
                $age = 'N/A';
                $remainingTime = 'N/A';
                $avatar = '';
                $deviceId = null;
                $isKnown = false;
            }
            
            // Generate connection status
            $statusClass = 'success';
            $statusText = 'Connected';
            $statusIcon = 'wifi';
            $currentTime = date('H:i:s');
            
            // Check for time exceeded
            if($isKnown && $remainingTime && strpos($remainingTime, 'Time Exceeded') !== false) {
                $statusClass = 'danger';
                $statusText = 'Time Exceeded';
                $statusIcon = 'ban';
            }
            
            $html .= '<tr class="' . ($isKnown ? '' : 'unknown-device') . '">
                        <td class="text-center">
                            <img src="../../image/' . $avatar . '" class="rounded-circle" width="40" height="40" 
                                 onerror="this.src=\'../../img/undraw_profile.svg\'">
                        </td>
                        <td>
                            <div class="fw-bold">' . htmlspecialchars($deviceName) . '</div>
                            <small class="text-muted">' . htmlspecialchars($deviceType) . '</small>
                            ' . (!$isKnown ? '<br><small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Unknown Device</small>' : '') . '
                        </td>
                        <td>
                            <code>' . htmlspecialchars($macAddress) . '</code>
                            <br><small class="text-muted">IP: ' . htmlspecialchars($ipAddress) . '</small>
                        </td>
                        <td>' . ($isKnown ? $age . ' years' : '<span class="text-muted">N/A</span>') . '</td>
                        <td>' . ($isKnown ? $remainingTime : '<span class="text-muted">N/A</span>') . '</td>
                        <td>
                            <div class="activity-status">
                                <i class="fas fa-wifi text-success"></i>
                                <strong class="text-success">Connected</strong>
                                <br><small class="text-success">Active on MikroTik</small>
                                <br><small class="text-muted">Updated: ' . $currentTime . '</small>
                            </div>
                        </td>
                        <td>
                            <div class="bandwidth-info">
                                <span class="badge badge-success">
                                    <i class="fas fa-check"></i> Connected
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-' . $statusClass . '">
                                <i class="fas fa-' . $statusIcon . '"></i> ' . $statusText . '
                            </span>
                        </td>
                        <td>';
            
            if ($isKnown) {
                $html .= '<a href="../profile/index.php?user_id=' . $deviceId . '" class="btn btn-sm btn-primary" title="Edit Profile">
                            <i class="fas fa-edit"></i> Profile
                          </a>
                          <button class="btn btn-sm btn-danger btn-block-device" 
                                 data-mac="' . htmlspecialchars($macAddress, ENT_QUOTES) . '"
                                 title="Block Device">
                            <i class="fas fa-ban"></i> Block
                          </button>';
            } else {
                $html .= '<button class="btn btn-sm btn-success btn-create-profile" 
                                 data-mac="' . htmlspecialchars($macAddress, ENT_QUOTES) . '"
                                 data-name="' . htmlspecialchars($deviceName, ENT_QUOTES) . '"
                                 data-ip="' . htmlspecialchars($ipAddress, ENT_QUOTES) . '"
                                 title="Create Profile">
                            <i class="fas fa-user-plus"></i> Profile
                          </button>
                          <button class="btn btn-sm btn-danger btn-block-device" 
                                 data-mac="' . htmlspecialchars($macAddress, ENT_QUOTES) . '"
                                 title="Block Device">
                            <i class="fas fa-ban"></i> Block
                          </button>';
            }
            
            $html .= '</td></tr>';
        }
    } else {
        $html = '<tr><td colspan="9" class="text-center text-muted">
                    <i class="fas fa-wifi"></i> No devices currently connected to MikroTik router
                    <br><small>Last checked: ' . date('H:i:s') . '</small>
                 </td></tr>';
    }
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'html' => $html,
        'count' => $connectedCount,
        'timestamp' => time(),
        'message' => 'Connected devices updated successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Real-time device update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update device list: ' . $e->getMessage(),
        'html' => '<tr><td colspan="9" class="text-center text-danger">
                     <i class="fas fa-exclamation-triangle"></i> Error loading devices
                     <br><small>Please try refreshing the page</small>
                   </td></tr>',
        'count' => 0
    ]);
}
?>
