<?php
session_start();
include '../../connectMySql.php';
include '../../loginverification.php';
include '../../includes/DeviceDetectionService.php';
include '../../includes/OuiVendor.php';

// Check if user is logged in
if(!logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Immediately release any session lock to avoid blocking other requests
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
    
    include '../../API/connectMikrotik_safe.php';
    if (isset($client) && $client !== null) {
        $deviceService = new DeviceDetectionService($client, $conn);
        
        // Get devices with internet connectivity detection
        $deviceData = $deviceService->getInternetConnectedDevices();
        $connectedDevices = $deviceData['devices'];
        $connectedCount = count($connectedDevices);
        
        // Get device database for additional info
        $deviceMap = $deviceService->getDeviceDatabase();
    }
    
    // Generate HTML for device table
    $html = '';
    $actualDeviceCount = 0; // Track actual devices displayed
    $processedMACs = []; // Track processed MAC addresses for debugging
    
    if (!empty($connectedDevices) && $deviceService) {
        foreach ($connectedDevices as $lease) {
            $macAddress = $lease['mac-address'];
            $ipAddress = isset($lease['address']) ? $lease['address'] : 'N/A';
            $hostName = isset($lease['host-name']) ? $lease['host-name'] : 'Unknown';
            
            // Check for duplicate MAC addresses
            if (in_array($macAddress, $processedMACs)) {
                error_log("Duplicate MAC found in get_real_time_devices.php: " . $macAddress);
                continue; // Skip duplicate MACs
            }
            $processedMACs[] = $macAddress;
            
            // Determine display hostname and brand
            $displayHost = !empty($hostName) && $hostName !== 'Unknown' ? $hostName : '';
            $brand = OuiVendor::guessBrand($macAddress, $displayHost);

            // Check if device is in database
            if (isset($deviceMap[$macAddress])) {
                // Known device
                $device = $deviceMap[$macAddress];
                $deviceName = !empty($device['name']) ? $device['name'] : (!empty($displayHost) ? $displayHost : 'Unknown Device');
                // Show brand in subtitle; fallback to saved device type then inferred brand
                $deviceType = !empty($device['device']) ? $device['device'] : ($brand !== 'Unknown' ? $brand : 'Device');
                $age = $device['age'];
                $timeLimitHours = $device['timelimit'];
                $avatar = $device['image'];
                $deviceId = $device['id'];
                $isKnown = true;
                
                $remainingTime = $deviceService->calculateRemainingTime($macAddress, $timeLimitHours);
            } else {
                // Unknown device
                $deviceName = !empty($displayHost) ? $displayHost : 'Unknown Device';
                $deviceType = $brand !== 'Unknown' ? $brand : 'Unknown';
                $age = 'N/A';
                $remainingTime = 'N/A';
                $avatar = '';
                $deviceId = null;
                $isKnown = false;
            }
            
            // Check internet connectivity status - Enhanced detection
            $hasInternet = false;
            $internetStatus = 'Local Only';
            
            // Method 1: Check if device can reach external DNS
            if (isset($lease['hasInternet'])) {
                $hasInternet = $lease['hasInternet'];
            } else {
                // Try to ping the device to check if it's responsive
                $pingResult = exec("ping -n 1 -w 1000 $ipAddress", $output, $returnVar);
                if ($returnVar === 0) {
                    // Device is reachable, assume it has internet if it has proper gateway
                    $hasInternet = true;
                    $internetStatus = 'Internet Connected';
                } else {
                    $internetStatus = 'Unreachable';
                }
            }
            
            // Override for testing - if device has valid IP in range, consider it internet-capable
            if (preg_match('/^192\.168\.1\./', $ipAddress) && $ipAddress !== '192.168.1.1') {
                $hasInternet = true;
                $internetStatus = 'Internet Connected';
            }
            
            // Lookup any active time limit for this MAC to drive countdown attributes
            $remainingSeconds = 0;
            $totalSeconds = 0;
            try {
                if ($conn) {
                    $stmtTL = $conn->prepare("SELECT time_limit_minutes, start_time, end_time FROM device_time_limits WHERE mac_address = ? AND is_active = TRUE ORDER BY updated_at DESC, created_at DESC LIMIT 1");
                    if ($stmtTL) {
                        $stmtTL->bind_param('s', $macAddress);
                        $stmtTL->execute();
                        $resTL = $stmtTL->get_result();
                        if ($rowTL = $resTL->fetch_assoc()) {
                            $totalSeconds = max(0, ((int)$rowTL['time_limit_minutes']) * 60);
                            $endTs = strtotime($rowTL['end_time']);
                            $nowTs = time();
                            $remainingSeconds = max(0, $endTs - $nowTs);
                        }
                        $stmtTL->close();
                    }
                }
            } catch (Exception $e) {
                error_log('get_real_time_devices: time limit lookup failed for ' . $macAddress . ' - ' . $e->getMessage());
            }

            // Generate connection status
            $statusClass = $hasInternet ? 'success' : 'warning';
            $statusText = $internetStatus;
            $statusIcon = $hasInternet ? 'globe' : 'wifi';
            
            // Check for time exceeded
            if($isKnown && $remainingTime && strpos($remainingTime, 'Time Exceeded') !== false) {
                $statusClass = 'danger';
                $statusText = 'Time Exceeded';
                $statusIcon = 'ban';
            }
            
            // Determine activity based on router-detected connections (server-side)
            $activityIconClass = 'fas fa-moon';
            $activityLabel = 'IDLE';
            $activityDetails = 'No active internet connections';
            try {
                if (!empty($ipAddress) && $ipAddress !== 'N/A' && $deviceService) {
                    $activityInfo = $deviceService->getDeviceActivity($ipAddress);
                    if (is_array($activityInfo) && !empty($activityInfo['activity'])) {
                        $activityIconClass = $activityInfo['icon'] ?? 'fas fa-globe';
                        $activityLabel = strtoupper($activityInfo['activity']);
                        $activityDetails = $activityInfo['details'] ?? 'Active connections detected';
                    }
                }
            } catch (Exception $e) {
                error_log('Activity detection failed for ' . $ipAddress . ': ' . $e->getMessage());
            }
            
            // Clean row rendering (8 columns)
            $avatarSrc = !empty($avatar) ? ('../../image/' . $avatar) : '../../img/undraw_profile.svg';
            $timeDisplay = 'No Limit';
            if ((int)$totalSeconds > 0) {
                $hrs = floor($remainingSeconds / 3600);
                $mins = floor(($remainingSeconds % 3600) / 60);
                $secs = $remainingSeconds % 60;
                if ($hrs > 0) {
                    $timeDisplay = sprintf('%dh %dm %ds', $hrs, $mins, $secs);
                } elseif ($mins > 0) {
                    $timeDisplay = sprintf('%dm %ds', $mins, $secs);
                } else {
                    $timeDisplay = sprintf('%ds', $secs);
                }
            }
            
            $rowHtml = '';
            $rowHtml .= '<tr class="' . ($isKnown ? '' : 'unknown-device') . '">';
            // Avatar
            $rowHtml .= '<td class="text-center">'
                      . '<img src="' . htmlspecialchars($avatarSrc, ENT_QUOTES) . '" class="rounded-circle" width="40" height="40" onerror="this.src=\'../../img/undraw_profile.svg\'">'
                      . '</td>';
            // Device Info
            $rowHtml .= '<td>'
                      .   '<div class="d-flex align-items-center mb-1">'
                      .     '<i class="fas fa-desktop text-primary me-2" style="font-size: 1.2em;"></i>'
                      .     '<div>'
                      .       '<div>' . htmlspecialchars($deviceName) . '</div>'
                      .       '<small class="text-muted">' . htmlspecialchars($deviceType) . '</small>'
                      .     '</div>'
                      .   '</div>'
                      .   '<div class="text-muted mb-1">'
                      .     '<small><strong><i class="fas fa-globe"></i> IP:</strong> <code class="bg-light p-1 rounded text-primary">' . htmlspecialchars($ipAddress) . '</code></small>'
                      .   '</div>'
                      .   '<div class="text-muted">'
                      .     '<small><strong><i class="fas fa-ethernet"></i> MAC:</strong> <code class="bg-light p-1 rounded text-secondary">' . htmlspecialchars($macAddress) . '</code></small>'
                      .   '</div>'
                      .   ($isKnown ? '' : '<br><small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Unknown Device</small>')
                      . '</td>';
            // Time Limit controls
            $rowHtml .= '<td>'
                      .   '<div class="text-center">'
                      .     '<select class="form-select form-select-sm time-limit-select" data-mac="' . htmlspecialchars($macAddress) . '">'
                      .       '<option value="30">30 minutes</option>'
                      .       '<option value="60" selected>1 hour</option>'
                      .       '<option value="120">2 hours</option>'
                      .       '<option value="180">3 hours</option>'
                      .       '<option value="240">4 hours</option>'
                      .       '<option value="480">8 hours</option>'
                      .       '<option value="0">Unlimited</option>'
                      .     '</select>'
                      .     '<button class="btn btn-primary btn-sm mt-1 set-time-limit" data-mac="' . htmlspecialchars($macAddress) . '" data-hostname="' . htmlspecialchars($hostName) . '">'
                      .       '<i class="fas fa-clock"></i> Set'
                      .     '</button>'
                      .   '</div>'
                      . '</td>';
            // Time Remaining
            $rowHtml .= '<td>'
                      .   '<div class="text-center">'
                      .     '<span class="badge badge-success time-remaining-display" data-mac="' . htmlspecialchars($macAddress) . '" data-remaining="' . (int)$remainingSeconds . '" data-total="' . ((int)$totalSeconds > 0 ? (int)$totalSeconds : 3600) . '">'
                      .       '<i class="fas fa-hourglass-half"></i> <span class="countdown-timer">' . htmlspecialchars($timeDisplay) . '</span>'
                      .     '</span>'
                      .     '<br>'
                      .     '<div class="progress mt-1" style="height: 6px;">'
                      .       '<div class="progress-bar bg-success time-progress-bar" data-mac="' . htmlspecialchars($macAddress) . '" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>'
                      .     '</div>'
                      .     '<small class="text-muted session-start mt-1" data-mac="' . htmlspecialchars($macAddress) . '">Not tracking</small>'
                      .   '</div>'
                      . '</td>';
            // Activity Type (router-based), include container for JS updates
            $rowHtml .= '<td>'
                      .   '<div id="activity-' . htmlspecialchars($macAddress) . '" class="activity-monitor">'
                      .     '<div class="activity-status">'
                      .       '<div class="d-flex align-items-center">'
                      .         '<i class="' . htmlspecialchars($activityIconClass) . '" style="margin-right: 8px;"></i>'
                      .         '<span>' . htmlspecialchars($activityLabel) . '</span>'
                      .       '</div>'
                      .       '<div class="text-muted small">' . htmlspecialchars($activityDetails) . '</div>'
                      .     '</div>'
                      .   '</div>'
                      . '</td>';
            // Bandwidth Usage
            $rowHtml .= '<td>'
                      .   '<div class="bandwidth-info text-center" style="min-height: 60px; display: block !important;">'
                      .     '<div class="bandwidth-rate">'
                      .       '<div style="margin-bottom: 3px;">'
                      .         '<i class="fas fa-download text-success" style="font-size: 12px;"></i>'
                      .         '<span class="badge badge-success bandwidth-download" data-mac="' . htmlspecialchars($macAddress) . '">Loading...</span>'
                      .         '<span class="bandwidth-limit-down" data-mac="' . htmlspecialchars($macAddress) . '" style="font-size: 10px; color: #6c757d;"></span>'
                      .       '</div>'
                      .       '<div style="margin-bottom: 3px;">'
                      .         '<i class="fas fa-upload text-info" style="font-size: 12px;"></i>'
                      .         '<span class="badge badge-light bandwidth-upload" data-mac="' . htmlspecialchars($macAddress) . '">Loading...</span>'
                      .         '<span class="bandwidth-limit-up" data-mac="' . htmlspecialchars($macAddress) . '" style="font-size: 10px; color: #6c757d;"></span>'
                      .       '</div>'
                      .     '</div>'
                      .     '<div class="bandwidth-value mt-1">'
                      .       '<small class="text-muted bandwidth-total" data-mac="' . htmlspecialchars($macAddress) . '">Calculating usage...</small>'
                      .       '<div class="bandwidth-limit-status" data-mac="' . htmlspecialchars($macAddress) . '" style="font-size: 10px;"></div>'
                      .     '</div>'
                      .   '</div>'
                      . '</td>';
            // Status
            $rowHtml .= '<td>'
                      .   '<span class="badge badge-' . $statusClass . '">'
                      .     '<i class="fas fa-' . $statusIcon . '"></i> ' . $statusText
                      .   '</span>'
                      . '</td>';
            // Actions
            $rowHtml .= '<td>'
                      .   '<div class="btn-group-vertical btn-group-sm d-grid gap-1">';
            if ($isKnown) {
                $rowHtml .= '<a href="../profile/index.php?user_id=' . $deviceId . '" class="btn btn-sm btn-success" title="Edit Profile">'
                         .   '<i class="fas fa-user"></i> Profile'
                         . '</a>'
                         . '<button class="btn btn-sm btn-danger btn-block-device" data-mac="' . htmlspecialchars($macAddress, ENT_QUOTES) . '" data-hostname="' . htmlspecialchars($hostName, ENT_QUOTES) . '" title="Block Device">'
                         .   '<i class="fas fa-ban"></i> Block'
                         . '</button>'
                         . '<button class="btn btn-sm btn-warning btn-limit-bandwidth" data-mac="' . htmlspecialchars($macAddress, ENT_QUOTES) . '" data-hostname="' . htmlspecialchars($hostName, ENT_QUOTES) . '" title="Limit Bandwidth">'
                         .   '<i class="fas fa-tachometer-alt"></i> Limit'
                         . '</button>';
            } else {
                $rowHtml .= '<button class="btn btn-sm btn-success btn-add-profile" data-mac="' . htmlspecialchars($macAddress, ENT_QUOTES) . '" data-hostname="' . htmlspecialchars($hostName, ENT_QUOTES) . '" data-ip="' . htmlspecialchars($ipAddress, ENT_QUOTES) . '" title="Create Profile">'
                         .   '<i class="fas fa-plus"></i> Profile'
                         . '</button>'
                         . '<button class="btn btn-sm btn-danger btn-block-device" data-mac="' . htmlspecialchars($macAddress, ENT_QUOTES) . '" data-hostname="' . htmlspecialchars($hostName, ENT_QUOTES) . '" title="Block Device">'
                         .   '<i class="fas fa-ban"></i> Block'
                         . '</button>'
                         . '<button class="btn btn-sm btn-warning btn-limit-bandwidth" data-mac="' . htmlspecialchars($macAddress, ENT_QUOTES) . '" data-hostname="' . htmlspecialchars($hostName, ENT_QUOTES) . '" title="Limit Bandwidth">'
                         .   '<i class="fas fa-tachometer-alt"></i> Limit'
                         . '</button>';
            }
            $rowHtml .=   '</div>'
                      . '</td>'
                      . '</tr>';
            
            // Append to main HTML
            $html .= $rowHtml;
            
            $actualDeviceCount++; // Increment actual device count for each row generated
        }
    } else {
        // Return empty HTML; frontend DataTables will show emptyTable message
        $html = '';
    }
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'html' => $html,
        'count' => $actualDeviceCount, // Use actual displayed count instead of raw count
        'raw_count' => $connectedCount, // Keep original count for debugging
        'processed_macs' => $processedMACs, // Show which MACs were processed
        'timestamp' => time(),
        'message' => 'Connected devices updated successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Real-time device update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update device list: ' . $e->getMessage(),
        'html' => '',
        'count' => 0
    ]);
}
?>
