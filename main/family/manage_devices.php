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
        case 'add_device':
            $device_name = $_POST['device_name'] ?? '';
            $device_type = $_POST['device_type'] ?? '';
            $family_code = $_POST['family_code'] ?? '';
            
            if (empty($device_name) || empty($device_type)) {
                throw new Exception('Device name and type are required');
            }
            
            $stmt = $conn->prepare("INSERT INTO family_devices (user_id, device_name, device_type, family_code, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("isss", $user_id, $device_name, $device_type, $family_code);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Device added successfully',
                    'device_id' => $conn->insert_id
                ]);
            } else {
                throw new Exception('Failed to add device');
            }
            break;
            
        case 'remove_device':
            $device_id = $_POST['device_id'] ?? '';
            
            if (empty($device_id)) {
                throw new Exception('Device ID is required');
            }
            
            $stmt = $conn->prepare("DELETE FROM family_devices WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $device_id, $user_id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Device removed successfully'
                ]);
            } else {
                throw new Exception('Failed to remove device');
            }
            break;
            
        case 'update_device':
            $device_id = $_POST['device_id'] ?? '';
            $device_name = $_POST['device_name'] ?? '';
            
            if (empty($device_id) || empty($device_name)) {
                throw new Exception('Device ID and name are required');
            }
            
            $stmt = $conn->prepare("UPDATE family_devices SET device_name = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sii", $device_name, $device_id, $user_id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Device updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update device');
            }
            break;
            
        case 'get_devices':
            $stmt = $conn->prepare("SELECT * FROM family_devices WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $devices = [];
            while ($row = $result->fetch_assoc()) {
                $devices[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'devices' => $devices
            ]);
            break;
            
        case 'update_family_code':
            $new_code = $_POST['family_code'] ?? '';
            
            if (empty($new_code)) {
                throw new Exception('Family code is required');
            }
            
            // Update user's family code
            $stmt = $conn->prepare("UPDATE users SET family_code = ? WHERE id = ?");
            $stmt->bind_param("si", $new_code, $user_id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Family code updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update family code');
            }
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
