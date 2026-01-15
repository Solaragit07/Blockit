<?php
// Test database connection for debugging
header('Content-Type: application/json');

try {
    // Include the database connection
    include_once '../../connectMySql.php';
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Database connection successful',
        'connection_info' => [
            'host' => $conn->getAttribute(PDO::ATTR_CONNECTION_STATUS) ?? 'Unknown',
            'driver' => $conn->getAttribute(PDO::ATTR_DRIVER_NAME) ?? 'Unknown'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage(),
        'error_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>
