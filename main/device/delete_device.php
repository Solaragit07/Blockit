<?php
include '../../connectMySql.php';
include '../../loginverification.php';

if (!logged_in()) {
    header('location:../../index.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['device_id'])) {
    $device_id = (int)$_POST['device_id'];
    
    // Get device info before deletion
    $device_query = "SELECT name, mac_address FROM device WHERE id = $device_id";
    $device_result = mysqli_query($conn, $device_query);
    
    if ($device_row = mysqli_fetch_assoc($device_result)) {
        $device_name = $device_row['name'];
        $mac_address = $device_row['mac_address'];
        
        // Delete from database
        $delete_query = "DELETE FROM device WHERE id = $device_id";
        
        if (mysqli_query($conn, $delete_query)) {
            $_SESSION['success'] = "Device '$device_name' deleted successfully!";
            
            // Optionally remove blocking rules from router
            try {
                require_once '../../vendor/autoload.php';
                use RouterOS\Client;
                use RouterOS\Query;
                
                include '../../API/connectMikrotik.php';
                
                // Remove firewall rules for this device
                $rules = $client->query((new Query('/ip/firewall/filter/print'))
                    ->where('comment', "Auto block for $mac_address"))->read();
                
                foreach ($rules as $rule) {
                    $client->query((new Query('/ip/firewall/filter/remove'))
                        ->equal('.id', $rule['.id']))->read();
                }
                
                // Remove schedulers for this device
                $schedulers = $client->query((new Query('/system/scheduler/print'))
                    ->where('name', "*$mac_address*"))->read();
                
                foreach ($schedulers as $scheduler) {
                    $client->query((new Query('/system/scheduler/remove'))
                        ->equal('.id', $scheduler['.id']))->read();
                }
                
                // Remove address list entries
                $addressListName = "blocked-sites-" . str_replace([':', '-'], '', $mac_address);
                $addressEntries = $client->query((new Query('/ip/firewall/address-list/print'))
                    ->where('list', $addressListName))->read();
                
                foreach ($addressEntries as $entry) {
                    $client->query((new Query('/ip/firewall/address-list/remove'))
                        ->equal('.id', $entry['.id']))->read();
                }
                
                $_SESSION['success'] .= " Router rules cleaned up.";
                
            } catch (Exception $e) {
                $_SESSION['warning'] = "Device deleted but router cleanup failed: " . $e->getMessage();
            }
            
        } else {
            $_SESSION['error'] = "Error deleting device: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "Device not found.";
    }
} else {
    $_SESSION['error'] = "Invalid request.";
}

header('location:index.php');
exit;
?>
