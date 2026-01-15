<?php
include '../../connectMySql.php';
include '../../loginverification.php';

if (!logged_in()) {
    http_response_code(403);
    exit('Unauthorized');
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'block_all') {
            // Block all devices
            $update_query = "UPDATE device SET internet = 'Yes'";
            if (mysqli_query($conn, $update_query)) {
                echo json_encode(['success' => true, 'message' => 'All devices blocked']);
            } else {
                throw new Exception('Database update failed');
            }
            
        } elseif ($action === 'unblock_all') {
            // Unblock all devices
            $update_query = "UPDATE device SET internet = 'No'";
            if (mysqli_query($conn, $update_query)) {
                echo json_encode(['success' => true, 'message' => 'All devices unblocked']);
            } else {
                throw new Exception('Database update failed');
            }
            
        } else {
            throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>
