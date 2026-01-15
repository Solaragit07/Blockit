<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connectMySql.php';
include '../../loginverification.php';
include '../../includes/fast_api_helper.php';

// RouterOS API imports
require_once '../../vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

header('Content-Type: application/json');

// Debug log
error_log("Application block request: " . print_r($_POST, true));

if(!logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

// Ensure application_blocks table exists to avoid insert/update failures
try {
    $conn->query("CREATE TABLE IF NOT EXISTS application_blocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id INT NULL,
        application_name VARCHAR(100) NOT NULL,
        application_category VARCHAR(100) NOT NULL,
        block_type VARCHAR(50) DEFAULT 'complete',
        duration INT DEFAULT 0,
        reason VARCHAR(255) NULL,
        domains TEXT,
        ports VARCHAR(255) NULL,
        protocols VARCHAR(255) NULL,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_app_cat (application_name, application_category),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Legacy-safe: ensure all required columns exist; do not rely on column order
    $colsRes = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'application_blocks'");
    $existingCols = [];
    if ($colsRes) {
        while ($r = $colsRes->fetch_assoc()) {
            $existingCols[strtolower($r['COLUMN_NAME'])] = true;
        }
    }
    $addStmts = [];
    if (!isset($existingCols['device_id'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN device_id INT NULL";
    if (!isset($existingCols['application_name'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN application_name VARCHAR(100) NOT NULL";
    if (!isset($existingCols['application_category'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN application_category VARCHAR(100) NOT NULL";
    if (!isset($existingCols['block_type'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN block_type VARCHAR(50) DEFAULT 'complete'";
    if (!isset($existingCols['duration'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN duration INT DEFAULT 0";
    if (!isset($existingCols['reason'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN reason VARCHAR(255) NULL";
    if (!isset($existingCols['domains'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN domains TEXT";
    if (!isset($existingCols['ports'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN ports VARCHAR(255) NULL";
    if (!isset($existingCols['protocols'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN protocols VARCHAR(255) NULL";
    if (!isset($existingCols['status'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN status ENUM('active','inactive') DEFAULT 'active'";
    if (!isset($existingCols['created_at'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    if (!isset($existingCols['updated_at'])) $addStmts[] = "ALTER TABLE application_blocks ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP";
    foreach ($addStmts as $sql) { $conn->query($sql); }

    // Ensure device_id allows NULL for global blocks; clean legacy zeros
    $conn->query("ALTER TABLE application_blocks MODIFY COLUMN device_id INT NULL DEFAULT NULL");
    $conn->query("UPDATE application_blocks SET device_id = NULL WHERE device_id = 0");
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database setup error: ' . $e->getMessage()]);
    exit;
}

$action = $_POST['action'] ?? 'create';

// Application configurations
$applicationConfigs = [
    'Gaming' => [
        'Fortnite' => [
            'domains' => 'fortnite.com,epicgames.com,unrealengine.com',
            'ports' => '80,443,5222,5223',
            'protocols' => 'fortnite'
        ],
        'PUBG' => [
            'domains' => 'pubg.com,krafton.com,battlegrounds.com',
            'ports' => '7000-7999,8000-8999',
            'protocols' => 'pubg'
        ],
        'Minecraft' => [
            'domains' => 'minecraft.net,mojang.com',
            'ports' => '25565,25575',
            'protocols' => 'minecraft'
        ],
        'Roblox' => [
            'domains' => 'roblox.com,rbxcdn.com',
            'ports' => '53,80,443',
            'protocols' => 'roblox'
        ],
        'Steam' => [
            'domains' => 'steampowered.com,steamcommunity.com,steamstatic.com',
            'ports' => '27000-27100',
            'protocols' => 'steam'
        ]
    ],
    'Social Media' => [
        'Facebook' => [
            'domains' => 'facebook.com,fb.com,fbcdn.net,instagram.com',
            'ports' => '80,443',
            'protocols' => 'facebook'
        ],
        'Instagram' => [
            'domains' => 'instagram.com,cdninstagram.com,fbcdn.net',
            'ports' => '80,443',
            'protocols' => 'instagram'
        ],
        'TikTok' => [
            'domains' => 'tiktok.com,musically.com,musical.ly,tiktokcdn.com',
            'ports' => '80,443',
            'protocols' => 'tiktok'
        ],
        'Twitter' => [
            'domains' => 'twitter.com,t.co,twimg.com,x.com',
            'ports' => '80,443',
            'protocols' => 'twitter'
        ],
        'Snapchat' => [
            'domains' => 'snapchat.com,sc-cdn.net',
            'ports' => '80,443',
            'protocols' => 'snapchat'
        ]
    ],
    'Entertainment' => [
        'YouTube' => [
            'domains' => 'youtube.com,youtu.be,googlevideo.com,ytimg.com',
            'ports' => '80,443',
            'protocols' => 'youtube'
        ],
        'Netflix' => [
            'domains' => 'netflix.com,nflxso.net,nflxext.com,nflximg.net',
            'ports' => '80,443',
            'protocols' => 'netflix'
        ],
        'Spotify' => [
            'domains' => 'spotify.com,scdn.co,spoti.fi',
            'ports' => '80,443,57621',
            'protocols' => 'spotify'
        ],
        'Twitch' => [
            'domains' => 'twitch.tv,twitchcdn.net,jtvnw.net',
            'ports' => '80,443',
            'protocols' => 'twitch'
        ],
        'Disney+' => [
            'domains' => 'disneyplus.com,disney.com,bamgrid.com',
            'ports' => '80,443',
            'protocols' => 'disney'
        ]
    ],
    'Communication' => [
        'WhatsApp' => [
            'domains' => 'whatsapp.com,whatsapp.net',
            'ports' => '443,4244,5222',
            'protocols' => 'whatsapp'
        ],
        'Telegram' => [
            'domains' => 'telegram.org,t.me,telegra.ph',
            'ports' => '80,443',
            'protocols' => 'telegram'
        ],
        'Discord' => [
            'domains' => 'discord.com,discordapp.com,discord.gg',
            'ports' => '80,443,50000-65535',
            'protocols' => 'discord'
        ],
        'Zoom' => [
            'domains' => 'zoom.us,zoom.com',
            'ports' => '80,443,8801,8802',
            'protocols' => 'zoom'
        ],
        'Skype' => [
            'domains' => 'skype.com,live.com',
            'ports' => '80,443,1024-65535',
            'protocols' => 'skype'
        ]
    ],
    'E-commerce' => [
        'Amazon' => [
            'domains' => 'amazon.com,amazonwebservices.com,ssl-images-amazon.com',
            'ports' => '80,443',
            'protocols' => 'amazon'
        ],
        'eBay' => [
            'domains' => 'ebay.com,ebayimg.com,ebaycdn.net',
            'ports' => '80,443',
            'protocols' => 'ebay'
        ],
        'Shopee' => [
            'domains' => 'shopee.com,shopee.ph,shp.ee',
            'ports' => '80,443',
            'protocols' => 'shopee'
        ],
        'Lazada' => [
            'domains' => 'lazada.com,lazada.ph,lzd.co',
            'ports' => '80,443',
            'protocols' => 'lazada'
        ]
    ]
];

try {
    switch($action) {
        case 'create':
            $device_id = $_POST['device_id'] ?: null;
            $category = $_POST['category'] ?? '';
            $application = $_POST['application'] ?? '';
            $block_type = $_POST['block_type'] ?? 'complete';
            $duration = $_POST['duration'] ?? 24;
            $reason = $_POST['reason'] ?? '';
            $domains = $_POST['domains'] ?? '';
            $ports = $_POST['ports'] ?? '';
            
            // Validate required fields
            if (empty($application)) {
                throw new Exception('Please select an application');
            }
            
            if (empty($category)) {
                throw new Exception('Please select a category');
            }
            
            // Get application config from predefined list
            if (isset($applicationConfigs[$category][$application])) {
                $appConfig = $applicationConfigs[$category][$application];
                $domains = $appConfig['domains'];
                $ports = $appConfig['ports'];
                $protocols = $appConfig['protocols'];
            } else {
                $protocols = strtolower(str_replace(' ', '_', $application));
                if (empty($domains)) {
                    $domains = strtolower(str_replace(' ', '', $application)) . '.com';
                }
                if (empty($ports)) {
                    $ports = '80,443';
                }
            }
            
            // Get device info if specified
            $deviceName = 'All Devices';
            if ($device_id) {
                $deviceQuery = "SELECT * FROM device WHERE id = ?";
                $stmt = $conn->prepare($deviceQuery);
                $stmt->bind_param("i", $device_id);
                $stmt->execute();
                $device = $stmt->get_result()->fetch_assoc();
                $deviceName = $device['name'] ?? 'Unknown Device';
            }
            
            // Check if application is already blocked for this device/all devices
            $checkQuery = "SELECT id FROM application_blocks WHERE application_name = ? AND application_category = ? AND status = 'active'";
            $checkParams = [$application, $category];
            $checkTypes = "ss";
            
            if ($device_id) {
                $checkQuery .= " AND device_id = ?";
                $checkParams[] = $device_id;
                $checkTypes .= "i";
            } else {
                $checkQuery .= " AND device_id IS NULL";
            }
            
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param($checkTypes, ...$checkParams);
            $checkStmt->execute();
            
            if ($checkStmt->get_result()->num_rows > 0) {
                throw new Exception("Application '{$application}' is already blocked for {$deviceName}");
            }
            
            // Create application block in RouterOS
            $routerResult = createApplicationBlockInRouter($device_id, $application, $category, $block_type, $domains, $ports, $protocols);
            
            if ($routerResult['success']) {
                // Store in database
                $insertQuery = "INSERT INTO application_blocks (device_id, application_name, application_category, block_type, duration, reason, domains, ports, protocols, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("isssisiss", $device_id, $application, $category, $block_type, $duration, $reason, $domains, $ports, $protocols);
                
                if ($stmt->execute()) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => "Successfully blocked '{$application}' for {$deviceName}"
                    ]);
                } else {
                    throw new Exception('Failed to save to database: ' . $stmt->error);
                }
            } else {
                throw new Exception('Failed to configure router: ' . $routerResult['error']);
            }
            break;
            
        case 'update':
            $block_id = $_POST['block_id'];
            $status = $_POST['status'];
            $duration = $_POST['duration'];
            
            // Update database
            $updateQuery = "UPDATE application_blocks SET status = ?, duration = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("sii", $status, $duration, $block_id);
            $stmt->execute();
            
            // Update router configuration
            if ($status == 'active') {
                // Re-enable the block
                updateRouterBlock($block_id, true);
            } else {
                // Disable the block
                updateRouterBlock($block_id, false);
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Application block updated successfully'
            ]);
            break;
            
        case 'delete':
            $block_id = $_POST['block_id'];
            
            // Get block info
            $blockQuery = "SELECT * FROM application_blocks WHERE id = ?";
            $stmt = $conn->prepare($blockQuery);
            $stmt->bind_param("i", $block_id);
            $stmt->execute();
            $block = $stmt->get_result()->fetch_assoc();
            
            if ($block) {
                // Remove from router
                removeApplicationBlockFromRouter($block);
                
                // Remove from database
                $deleteQuery = "DELETE FROM application_blocks WHERE id = ?";
                $stmt = $conn->prepare($deleteQuery);
                $stmt->bind_param("i", $block_id);
                $stmt->execute();
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Application block removed successfully'
                ]);
            } else {
                throw new Exception('Block not found');
            }
            break;
            
        case 'block_category':
            $category = $_POST['category'];
            $device_id = $_POST['device_id'] ?: null;
            $duration = $_POST['duration'] ?? 24;
            
            if (!isset($applicationConfigs[$category])) {
                throw new Exception('Invalid category');
            }
            
            $blockedCount = 0;
            $deviceName = $device_id ? 'selected device' : 'all devices';
            
            foreach ($applicationConfigs[$category] as $appName => $appConfig) {
                // Check if already blocked
                $checkQuery = "SELECT id FROM application_blocks WHERE application_name = ? AND application_category = ?";
                if ($device_id) {
                    $checkQuery .= " AND device_id = ?";
                }
                $checkStmt = $conn->prepare($checkQuery);
                if ($device_id) {
                    $checkStmt->bind_param("ssi", $appName, $category, $device_id);
                } else {
                    $checkStmt->bind_param("ss", $appName, $category);
                }
                $checkStmt->execute();
                
                if ($checkStmt->get_result()->num_rows == 0) {
                    // Create block
                    $routerResult = createApplicationBlockInRouter($device_id, $appName, $category, 'complete', $appConfig['domains'], $appConfig['ports'], $appConfig['protocols']);
                    
                    if ($routerResult['success']) {
                        $insertQuery = "INSERT INTO application_blocks (device_id, application_name, application_category, block_type, duration, reason, domains, ports, protocols, status, created_at) VALUES (?, ?, ?, 'complete', ?, ?, ?, ?, ?, 'active', NOW())";
                        $stmt = $conn->prepare($insertQuery);
                        $reason = "Blocked via category: {$category}";
                        $stmt->bind_param("isssssss", $device_id, $appName, $category, $duration, $reason, $appConfig['domains'], $appConfig['ports'], $appConfig['protocols']);
                        $stmt->execute();
                        $blockedCount++;
                    }
                }
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => "Successfully blocked {$blockedCount} applications in {$category} category for {$deviceName}"
            ]);
            break;
            
        case 'remove_from_list':
            $listType = $_POST['list_type'];
            $website = $_POST['website'];
            
            if ($listType == 'whitelist') {
                $deleteQuery = "DELETE FROM whitelist WHERE website = ?";
            } else {
                $deleteQuery = "DELETE FROM blocklist WHERE website = ?";
            }
            
            $stmt = $conn->prepare($deleteQuery);
            $stmt->bind_param("s", $website);
            $stmt->execute();
            
            echo json_encode([
                'status' => 'success',
                'message' => "Removed {$website} from {$listType}"
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch(Exception $e) {
    error_log("Application block error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'post_data' => $_POST
        ]
    ]);
}

function createApplicationBlockInRouter($device_id, $application, $category, $block_type, $domains, $ports, $protocols) {
    // Enforce using the hardened block pipeline (block_user.php) via FastApiHelper
    try {
        $domainList = array_filter(array_map('trim', explode(',', (string)$domains)));
        if (empty($domainList)) {
            return ['success' => false, 'error' => 'No domains provided'];
        }
        $sites = implode(';', $domainList);

        $ok = 0; $errors = [];
        if ($device_id) {
            // Single device
            $stmt = $GLOBALS['conn']->prepare("SELECT mac_address, timelimit FROM device WHERE id = ?");
            $stmt->bind_param('i', $device_id);
            $stmt->execute();
            $dev = $stmt->get_result()->fetch_assoc();
            if (!$dev || empty($dev['mac_address'])) {
                return ['success' => false, 'error' => 'Device MAC address not found'];
            }
            $api = FastApiHelper::callBlockAPI('block_user.php', [
                'mac_address' => $dev['mac_address'],
                'sites' => $sites,
                'hours_allowed' => $dev['timelimit'] ?? 0,
            ], 12);
            if (!empty($api['success']) && (!isset($api['data']['status']) || $api['data']['status'] === 'success')) {
                $ok++;
            } else {
                $errors[] = $api['data']['message'] ?? $api['error'] ?? 'unknown error';
            }
    } else {
            $res = $GLOBALS['conn']->query("SELECT mac_address, timelimit FROM device WHERE mac_address <> ''");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $api = FastApiHelper::callBlockAPI('block_user.php', [
                        'mac_address' => $row['mac_address'],
                        'sites' => $sites,
                        'hours_allowed' => $row['timelimit'] ?? 0,
                    ], 10);
                    if (!empty($api['success']) && (!isset($api['data']['status']) || $api['data']['status'] === 'success')) {
                        $ok++;
                    } else {
                        $errors[] = $api['data']['message'] ?? $api['error'] ?? 'unknown error';
                    }
                    usleep(25000);
                }
            }
        }

        return $ok > 0 ? ['success' => true] : ['success' => false, 'error' => implode('; ', $errors)];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateRouterBlock($block_id, $enable) {
    try {
        include '../../API/connectMikrotik.php';
        
        if (!$client->connect()) {
            return false;
        }
        
        // Get block info
        $blockQuery = "SELECT * FROM application_blocks WHERE id = ?";
        $stmt = $GLOBALS['conn']->prepare($blockQuery);
        $stmt->bind_param("i", $block_id);
        $stmt->execute();
        $block = $stmt->get_result()->fetch_assoc();
        
        if (!$block) {
            return false;
        }
        
        $comment = "Block {$block['application_name']} ({$block['application_category']})";
        
        // Find and enable/disable firewall rules
        $rules = $client->query((new Query('/ip/firewall/filter/print'))
            ->where('comment', $comment)
        )->read();
        
        foreach ($rules as $rule) {
            if ($enable) {
                $client->query((new Query('/ip/firewall/filter/enable'))
                    ->equal('.id', $rule['.id'])
                )->read();
            } else {
                $client->query((new Query('/ip/firewall/filter/disable'))
                    ->equal('.id', $rule['.id'])
                )->read();
            }
        }
        
        $client->disconnect();
        return true;
        
    } catch (Exception $e) {
        return false;
    }
}

function removeApplicationBlockFromRouter($block) {
    // Remove both legacy rules and Auto block_* rules created via block_user.php
    try {
        include '../../API/connectMikrotik.php';
        if (!$client->connect()) return false;

        $commentLegacy = "Block {$block['application_name']} ({$block['application_category']})";

        // Determine target MACs (device or all devices)
        $macs = [];
        if (!empty($block['device_id'])) {
            $stmt = $GLOBALS['conn']->prepare("SELECT mac_address FROM device WHERE id = ?");
            $stmt->bind_param('i', $block['device_id']);
            $stmt->execute();
            $dev = $stmt->get_result()->fetch_assoc();
            if (!empty($dev['mac_address'])) $macs[] = $dev['mac_address'];
        } else {
            $res = $GLOBALS['conn']->query("SELECT mac_address FROM device WHERE mac_address <> ''");
            if ($res) { while ($row = $res->fetch_assoc()) { $macs[] = $row['mac_address']; } }
        }

        // Remove legacy rules by comment
        $rules = $client->query((new Query('/ip/firewall/filter/print'))->where('comment', $commentLegacy))->read();
        foreach ($rules as $rule) {
            $client->query((new Query('/ip/firewall/filter/remove'))->equal('.id', $rule['.id']))->read();
        }

        // Remove address-list entries by legacy naming
        $legacyList = "blocked-{$block['application_name']}-" . (!empty($block['device_id']) ? 'device' : 'all');
        $addresses = $client->query((new Query('/ip/firewall/address-list/print'))->where('list', $legacyList))->read();
        foreach ($addresses as $address) {
            $client->query((new Query('/ip/firewall/address-list/remove'))->equal('.id', $address['.id']))->read();
        }

    // Remove rules created by block_user.php: comments contain "Auto block for <MAC>" and UDP/443 rule
        if (!empty($macs)) {
            $allRules = $client->query((new Query('/ip/firewall/filter/print')))->read();
            foreach ($macs as $mac) {
                $needle1 = "Auto block for $mac";
                $needle2 = "Auto block TLS for $mac";
        $needle3 = "Auto block HTTP for $mac";
        $needle4 = "Auto block UDP 443 for $mac";
                foreach ($allRules as $r) {
                    $c = $r['comment'] ?? '';
            if (strpos($c, $needle1) !== false || strpos($c, $needle2) !== false || strpos($c, $needle3) !== false || strpos($c, $needle4) !== false) {
                        $client->query((new Query('/ip/firewall/filter/remove'))->equal('.id', $r['.id']))->read();
                    }
                }
                $list = 'blocked-sites-' . str_replace([':', '-'], '', strtolower($mac));
                $entries = $client->query((new Query('/ip/firewall/address-list/print'))->where('list', $list))->read();
                foreach ($entries as $e) {
                    $client->query((new Query('/ip/firewall/address-list/remove'))->equal('.id', $e['.id']))->read();
                }
            }
        }

        $client->disconnect();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
