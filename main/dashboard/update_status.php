<?php
include '../../connectMySql.php';
include '../../loginverification.php';
if(logged_in()){
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $sql = "INSERT INTO speedtest (DownloadSpeed) VALUES ('')";
    
        if ($conn->query($sql) === TRUE) {
            echo json_encode(['success' => true, 'message' => 'Row inserted successfully']);
        } else {
            http_response_code(500); 
            echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
        }
    
        $conn->close();
    } else {
        http_response_code(405); 
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
}
else
{
    header('location:../../index.php');
}?>