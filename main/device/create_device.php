<?php
include '../../connectMySql.php';
include '../../loginverification.php';

if (!logged_in()) {
    header('location:../../index.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $device_name = mysqli_real_escape_string($conn, $_POST['device_name']);
    $mac_address = mysqli_real_escape_string($conn, $_POST['mac_address']);
    $device_type = mysqli_real_escape_string($conn, $_POST['device_type']);
    $time_limit = (int)$_POST['time_limit'];
    
    // Validate MAC address format
    if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac_address)) {
        $_SESSION['error'] = "Invalid MAC address format. Please use XX:XX:XX:XX:XX:XX format.";
        header('location:index.php');
        exit;
    }
    
    // Check if MAC address already exists
    $check_query = "SELECT id FROM device WHERE mac_address = '$mac_address'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $_SESSION['error'] = "A device with this MAC address already exists.";
        header('location:index.php');
        exit;
    }
    
    // Insert new device
    $insert_query = "INSERT INTO device (name, device, mac_address, timelimit, age, bandwidth, internet) 
                     VALUES ('$device_name', '$device_type', '$mac_address', $time_limit, 0, 1, 'No')";
    
    if (mysqli_query($conn, $insert_query)) {
        $_SESSION['success'] = "Device profile '$device_name' created successfully!";
        
        // Optionally apply blocking rules immediately
        include '../../includes/api_helper.php';
        
        // Get blocked sites
        $blockSites = [];
        $blocklist = mysqli_query($conn, "SELECT DISTINCT website FROM blocklist WHERE website != ''");
        while ($row = mysqli_fetch_assoc($blocklist)) {
            $blockSites[] = trim($row['website']);
        }
        
        if (!empty($blockSites)) {
            $updateResult = ApiHelper::updateDeviceBlocking($mac_address, $blockSites, $time_limit);
            if ($updateResult[0]['success']) {
                $_SESSION['success'] .= " Blocking rules applied to router.";
            }
        }
    } else {
        $_SESSION['error'] = "Error creating device profile: " . mysqli_error($conn);
    }
} else {
    $_SESSION['error'] = "Invalid request method.";
}

header('location:index.php');
exit;
?>
