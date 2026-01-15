<?php
require_once '../../connectMySql.php';
require_once '../../vendor/autoload.php';

use RouterOS\Query;
use RouterOS\Client;

header('Content-Type: application/json');

try {
    // Use correct router IP and include the connection file
    include '../../API/connectMikrotik.php';
    
    $connectedDevices = [];
    $fallbackDevices = [];
    
    // Try MikroTik first
    if (isset($client) && $client !== null) {
        // Get DHCP leases
        $dhcpLeases = $client->query(new Query('/ip/dhcp-server/lease/print'))->read();
        
        // Filter only actually connected devices
        foreach($dhcpLeases as $lease) {
            if(isset($lease['mac-address']) && !empty($lease['mac-address'])) {
                // Check if device is actually connected
                $isConnected = false;
                if(isset($lease['status']) && $lease['status'] === 'bound' && isset($lease['address']) && !empty($lease['address'])) {
                    $isConnected = true;
                } elseif(!isset($lease['status']) && isset($lease['address']) && !empty($lease['address'])) {
                    // For RouterOS without explicit status
                    if(!isset($lease['expires-after']) || $lease['expires-after'] !== '0s') {
                        $isConnected = true;
                    }
                }
                
                if($isConnected) {
                    $connectedDevices[] = $lease;
                }
            }
        }
        
        // Also try ARP table for additional devices
        try {
            $arpEntries = $client->query(new Query('/ip/arp/print'))->read();
            foreach($arpEntries as $arp) {
                if(isset($arp['mac-address']) && isset($arp['address']) && !empty($arp['address'])) {
                    $mac = $arp['mac-address'];
                    // Check if not already in DHCP list
                    $found = false;
                    foreach($connectedDevices as $device) {
                        if($device['mac-address'] === $mac) {
                            $found = true;
                            break;
                        }
                    }
                    if(!$found) {
                        $connectedDevices[] = [
                            'mac-address' => $mac,
                            'address' => $arp['address'],
                            'host-name' => 'ARP Entry',
                            'status' => 'arp'
                        ];
                    }
                }
            }
        } catch (Exception $arpError) {
            // ARP failed, continue with DHCP data
        }
    } else {
        // Fallback: Use local ARP table
        $arp = shell_exec('arp -a');
        if($arp) {
            $lines = explode("\n", $arp);
            foreach($lines as $line) {
                if(preg_match('/(\d+\.\d+\.\d+\.\d+)\s+([0-9a-f-]{17})/i', $line, $matches)) {
                    $ip = $matches[1];
                    $mac = strtoupper(str_replace('-', ':', $matches[2]));
                    
                    $connectedDevices[] = [
                        'mac-address' => $mac,
                        'address' => $ip,
                        'host-name' => 'Local Device',
                        'status' => 'local'
                    ];
                }
            }
        }
    }
    
    // Get devices from database
    $deviceMap = [];
    $query = "SELECT * FROM device";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $deviceMap[$row['mac_address']] = $row;
    }
    
    // Build HTML and devices array
    $devicesHtml = '';
    $connectedCount = count($connectedDevices);
    $devicesArray = []; // For profile page dropdown
    
    if($connectedCount > 0) {
        foreach($connectedDevices as $lease) {
            $macAddress = $lease['mac-address'];
            $ipAddress = isset($lease['address']) ? $lease['address'] : 'N/A';
            $hostName = isset($lease['host-name']) ? $lease['host-name'] : 'Unknown';
            
            if(isset($deviceMap[$macAddress])) {
                $device = $deviceMap[$macAddress];
                $deviceName = $device['name'];
                $deviceType = $device['device'];
                $age = $device['age'];
                $timeLimit = $device['timelimit'];
                $avatar = $device['image'];
                $deviceId = $device['id'];
                $isKnown = true;
            } else {
                $deviceName = !empty($hostName) && $hostName != 'Unknown' ? $hostName : 'Unknown Device';
                $deviceType = 'Unknown';
                $age = 'N/A';
                $timeLimit = 'N/A';
                $avatar = '';
                $deviceId = null;
                $isKnown = false;
            }
            
            // Add to devices array for profile dropdown
            $devicesArray[] = [
                'macAddress' => $macAddress,
                'ipAddress' => $ipAddress,
                'name' => $deviceName,
                'hostName' => $hostName,
                'deviceType' => $deviceType,
                'isKnown' => $isKnown,
                'deviceId' => $deviceId
            ];
            
            $devicesHtml .= '<tr class="' . ($isKnown ? '' : '') . '">
                                <td class="text-center">
                                    <img src="../../image/'.$avatar.'" class="rounded-circle" width="40" height="40" 
                                         onerror="this.src=\'../../img/undraw_profile.svg\'">
                                </td>
                                <td>
                                    <div class="fw-bold">'.$deviceName.'</div>
                                    <small class="text-muted">'.$deviceType.'</small>
                                    '.(!$isKnown ? '<br><small class="text-muted"><i class="fas fa-exclamation-triangle"></i> Unknown Device</small>' : '').'
                                </td>
                                <td>
                                    <code>'.$macAddress.'</code>
                                    <br><small class="text-muted">IP: '.$ipAddress.'</small>
                                </td>
                                <td>'.($isKnown ? $age.' years' : '<span class="text-muted">N/A</span>').'</td>
                                <td>'.($isKnown ? $timeLimit.' hours' : '<span class="text-muted">N/A</span>').'</td>
                                <td>
                                    <div id="activity-'.$macAddress.'" class="activity-monitor">
                                        <div class="activity-status">
                                            <i class="fas fa-sync fa-spin text-muted"></i>
                                            <small>Detecting...</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="bandwidth-info">
                                        <span class="badge badge-light">
                                            <i class="fas fa-tachometer-alt"></i> 
                                            <span class="bandwidth-value">
                                                <div class="bandwidth-rate">
                                                    <div><i class="fas fa-arrow-down text-muted"></i> <span>Loading...</span></div>
                                                    <div><i class="fas fa-arrow-up text-muted"></i> <span>Loading...</span></div>
                                                </div>
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-success connected-status">
                                        <i class="fas fa-wifi connected-icon"></i> Connected
                                    </span>
                                </td>
                                <td>';
            
            if($isKnown) {
                $devicesHtml .= '<a href="../profile/index.php?user_id='.$deviceId.'" class="btn btn-sm btn-primary" title="Edit Profile">
                                    <i class="fas fa-edit"></i>
                                 </a>
                                 <button class="btn btn-sm btn-info" onclick="viewBlocklist('.$deviceId.')" title="View Blocklist">
                                   <i class="fas fa-shield-alt"></i>
                                 </button>
                                 <button class="btn btn-sm btn-warning" onclick="limitBandwidth('.$deviceId.')" title="Limit Bandwidth">
                                   <i class="fas fa-tachometer-alt"></i>
                                 </button>';
            } else {
                // Use data attributes for better reliability
                $devicesHtml .= '<button class="btn btn-sm btn-success btn-create-profile" 
                                        data-mac="'.htmlspecialchars($macAddress, ENT_QUOTES).'"
                                        data-name="'.htmlspecialchars($deviceName, ENT_QUOTES).'"
                                        data-ip="'.htmlspecialchars($ipAddress, ENT_QUOTES).'"
                                        title="Create Profile">
                                    <i class="fas fa-user-plus"></i> Profile
                                 </button>
                                 <button class="btn btn-sm btn-danger btn-block-device" 
                                        data-mac="'.htmlspecialchars($macAddress, ENT_QUOTES).'"
                                        title="Block Device">
                                    <i class="fas fa-ban"></i> Block
                                 </button>';
            }
            
            $devicesHtml .= '</td></tr>';
        }
    } else {
        $devicesHtml = '<tr><td colspan="9" class="text-center text-muted">
                          <i class="fas fa-wifi"></i> No devices currently connected to the network
                        </td></tr>';
    }
    
    echo json_encode([
        'status' => 'success',
        'html' => $devicesHtml,
        'count' => $connectedCount,
        'devices' => $devicesArray,
        'message' => 'Device list refreshed successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error refreshing devices: ' . $e->getMessage(),
        'html' => '<tr><td colspan="9" class="text-center text-danger">
                     <i class="fas fa-exclamation-triangle"></i> 
                     Error: '.$e->getMessage().'
                   </td></tr>',
        'count' => 0
    ]);
}
?>
