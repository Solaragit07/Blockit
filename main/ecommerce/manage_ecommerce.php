<?php
session_start();
include '../../connectMySql.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'save_settings':
            $settings = $_POST['settings'] ?? '';
            
            if (empty($settings)) {
                throw new Exception('Settings data is required');
            }
            
            // Validate JSON
            $settingsData = json_decode($settings, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid settings format');
            }
            
            // Check if user settings exist
            $stmt = $conn->prepare("SELECT id FROM ecommerce_settings WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing settings
                $stmt = $conn->prepare("UPDATE ecommerce_settings SET settings = ?, updated_at = NOW() WHERE user_id = ?");
                $stmt->bind_param("si", $settings, $user_id);
            } else {
                // Create new settings
                $stmt = $conn->prepare("INSERT INTO ecommerce_settings (user_id, settings, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                $stmt->bind_param("is", $user_id, $settings);
            }
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Settings saved successfully'
                ]);
            } else {
                throw new Exception('Failed to save settings');
            }
            break;
            
        case 'load_settings':
            $stmt = $conn->prepare("SELECT settings FROM ecommerce_settings WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                echo json_encode([
                    'success' => true,
                    'settings' => json_decode($row['settings'], true)
                ]);
            } else {
                // Return default settings
                echo json_encode([
                    'success' => true,
                    'settings' => [
                        'blockAccess' => false,
                        'blockPurchases' => false,
                        'notifications' => false,
                        'notificationMethods' => [],
                        'blockedCategories' => [],
                        'platforms' => []
                    ]
                ]);
            }
            break;
            
        case 'add_platform':
            $platform_name = $_POST['platform_name'] ?? '';
            $platform_url = $_POST['platform_url'] ?? '';
            $access_level = $_POST['access_level'] ?? '';
            $reason = $_POST['reason'] ?? '';
            
            if (empty($platform_name) || empty($platform_url)) {
                throw new Exception('Platform name and URL are required');
            }
            
            $stmt = $conn->prepare("INSERT INTO ecommerce_platforms (user_id, platform_name, platform_url, access_level, reason, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("issss", $user_id, $platform_name, $platform_url, $access_level, $reason);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Platform added successfully',
                    'platform_id' => $conn->insert_id
                ]);
            } else {
                throw new Exception('Failed to add platform');
            }
            break;
            
        case 'remove_platform':
            $platform_id = $_POST['platform_id'] ?? '';
            
            if (empty($platform_id)) {
                throw new Exception('Platform ID is required');
            }
            
            $stmt = $conn->prepare("DELETE FROM ecommerce_platforms WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $platform_id, $user_id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Platform removed successfully'
                ]);
            } else {
                throw new Exception('Failed to remove platform');
            }
            break;
            
        case 'get_platforms':
            $stmt = $conn->prepare("SELECT * FROM ecommerce_platforms WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $platforms = [];
            while ($row = $result->fetch_assoc()) {
                $platforms[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'platforms' => $platforms
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
