<?php
include '../../connectMySql.php';
include '../../loginverification.php';
require_once '../../includes/IntegratedNotificationService.php';

if(logged_in()){

// Handle form submissions
$alert_script = '';
$success_message = '';
$error_message = '';

if(isset($_POST['update_profile'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $name = $_POST['name'];

    $sql = sprintf("UPDATE admin SET email = '%s', password = '%s', name = '%s' WHERE user_id = '%s'",
        mysqli_real_escape_string($conn, $email),
        mysqli_real_escape_string($conn, $password),
        mysqli_real_escape_string($conn, $name),
        $_SESSION['user_id']
    );
    
    if(mysqli_query($conn, $sql)) {
        $success_message = "Profile updated successfully!";
    } else {
        $error_message = "Failed to update profile.";
    }
}

if(isset($_POST['test_email'])) {
    $test_email = $_POST['test_email_address'];
    
    if(filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $notificationService = new IntegratedNotificationService();
        $result = $notificationService->testNotificationSystem($test_email);
        
        if($result['email_test']['success']) {
            $success_message = "Test email sent successfully to $test_email!";
            if(isset($result['alert_test']['script'])) {
                $alert_script = $result['alert_test']['script'];
            }
        } else {
            $error_message = "Failed to send test email: " . $result['email_test']['message'];
        }
    } else {
        $error_message = "Invalid email address provided.";
    }
}

if(isset($_POST['test_website_block'])) {
    $device_name = $_POST['device_name'] ?: 'Test Device';
    $blocked_site = $_POST['blocked_site'] ?: 'facebook.com';
    $notification_email = $_POST['notification_email'] ?: null;
    
    $notificationService = new IntegratedNotificationService();
    $result = $notificationService->notifyWebsiteBlocked($device_name, $blocked_site, '', $notification_email);
    
    $success_message = "Website block notification sent!";
    if(isset($result['alert']['script'])) {
        $alert_script = $result['alert']['script'];
    }
}

if(isset($_POST['test_device_block'])) {
    $device_name = $_POST['device_name'] ?: 'Test Device';
    $reason = $_POST['block_reason'] ?: 'Policy violation';
    $notification_email = $_POST['notification_email'] ?: null;
    
    $notificationService = new IntegratedNotificationService();
    $result = $notificationService->notifyDeviceBlocked($device_name, $reason, $notification_email);
    
    $success_message = "Device block notification sent!";
    if(isset($result['alert']['script'])) {
        $alert_script = $result['alert']['script'];
    }
}

if(isset($_POST['update_notification_settings'])) {
    $settings = [
        'email_enabled' => isset($_POST['email_notifications']),
        'alerts_enabled' => isset($_POST['browser_alerts']),
        'quiet_hours_start' => $_POST['quiet_start'],
        'quiet_hours_end' => $_POST['quiet_end']
    ];
    
    $notificationService = new IntegratedNotificationService();
    if($notificationService->updateSettings($settings)) {
        $success_message = "Notification settings updated successfully!";
    } else {
        $error_message = "Failed to update notification settings.";
    }
}

// Get current admin data
$query = "SELECT * FROM admin WHERE user_id = '".$_SESSION['user_id']."'";
$result = $conn->query($query);
$admin_data = $result->fetch_assoc();

// Get notification service settings
$notificationService = new IntegratedNotificationService();
$notificationSettings = $notificationService->getSettings();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>BlockIT - Notification & Email Center</title>
    <link rel="icon" type="image/x-icon" href="../../img/logo1.png" />

    <!-- Custom fonts for this template-->
    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    
    <!-- Custom styles for this template-->
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">`n    `n    <!-- Custom Color Palette -->`n    <link href="../../css/custom-color-palette.css" rel="stylesheet">
    <script src="../../js/sweetalert2.all.min.js"></script>
    
    <style>
        .notification-card {
            transition: transform 0.2s;
            border-left: 4px solid #4e73df;
        }
        .notification-card:hover {
            transform: translateY(-2px);
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #dc3545; }
        .status-warning { background-color: #ffc107; }
        .test-result {
            margin-top: 15px;
            padding: 10px;
            border-radius: 5px;
            display: none;
        }
        .test-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .test-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .integration-code {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
        }
    </style>
</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <?php include'../sidebar.php';?>

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <?php include'../nav.php';?>

                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-bell"></i> Notification & Email Center
                        </h1>
                        <div class="d-none d-lg-inline-block">
                            <span class="status-indicator <?= $notificationSettings['email_enabled'] ? 'status-active' : 'status-inactive' ?>"></span>
                            Email: <?= $notificationSettings['email_enabled'] ? 'Active' : 'Disabled' ?>
                            <span class="status-indicator <?= $notificationSettings['alerts_enabled'] ? 'status-active' : 'status-inactive' ?>" style="margin-left: 15px;"></span>
                            Alerts: <?= $notificationSettings['alerts_enabled'] ? 'Active' : 'Disabled' ?>
                            <?php if($notificationSettings['is_quiet_hours']): ?>
                                <span class="status-indicator status-warning" style="margin-left: 15px;"></span>
                                Quiet Hours
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Alert Messages -->
                    <?php if($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?= $success_message ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php endif; ?>

                    <?php if($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php endif; ?>

                    <div class="row">

                        <!-- Profile & Email Configuration -->
                        <div class="col-xl-6 col-lg-6">
                            <div class="card notification-card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-user-cog"></i> Profile & Email Configuration
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form method="post">
                                        <div class="form-group">
                                            <label for="name">Administrator Name</label>
                                            <input class="form-control" name="name" id="name" type="text" 
                                                   placeholder="Enter your name" value="<?= htmlspecialchars($admin_data['name']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="email">Email Address</label>
                                            <input class="form-control" name="email" id="email" type="email" 
                                                   placeholder="Enter your email" value="<?= htmlspecialchars($admin_data['email']) ?>" required>
                                            <small class="form-text text-muted">This email will receive all notifications</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="password">Password</label>
                                            <input class="form-control" name="password" id="password" type="password" 
                                                   placeholder="Enter your password" value="<?= htmlspecialchars($admin_data['password']) ?>" required>
                                        </div>
                                        <button class="btn btn-primary" name="update_profile" type="submit">
                                            <i class="fas fa-save"></i> Update Profile
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Notification Settings -->
                        <div class="col-xl-6 col-lg-6">
                            <div class="card notification-card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-success">
                                        <i class="fas fa-cog"></i> Notification Settings
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form method="post">
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="email_notifications" 
                                                       name="email_notifications" <?= $notificationSettings['email_enabled'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="email_notifications">
                                                    <i class="fas fa-envelope"></i> Email Notifications
                                                </label>
                                            </div>
                                            <small class="form-text text-muted">Receive email alerts for blocking events</small>
                                        </div>

                                        <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="browser_alerts" 
                                                       name="browser_alerts" <?= $notificationSettings['alerts_enabled'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="browser_alerts">
                                                    <i class="fas fa-exclamation-triangle"></i> Browser Alerts
                                                </label>
                                            </div>
                                            <small class="form-text text-muted">Show popup alerts in the browser</small>
                                        </div>

                                        <div class="form-group">
                                            <label>Quiet Hours (No notifications during these times)</label>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label for="quiet_start" class="small">Start Time</label>
                                                    <input type="time" class="form-control" id="quiet_start" name="quiet_start" 
                                                           value="<?= $notificationSettings['quiet_hours_start'] ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="quiet_end" class="small">End Time</label>
                                                    <input type="time" class="form-control" id="quiet_end" name="quiet_end" 
                                                           value="<?= $notificationSettings['quiet_hours_end'] ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <button class="btn btn-success" name="update_notification_settings" type="submit">
                                            <i class="fas fa-save"></i> Save Settings
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Testing & Demonstration Section -->
                    <div class="row">
                        
                        <!-- Email Testing -->
                        <div class="col-xl-4 col-lg-4">
                            <div class="card notification-card shadow mb-4">
                                <div class="card-header py-3 bg-info text-white">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-paper-plane"></i> Email Testing
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form method="post">
                                        <div class="form-group">
                                            <label for="test_email_address">Test Email Address</label>
                                            <input type="email" class="form-control" name="test_email_address" 
                                                   placeholder="Enter email to test" value="<?= $admin_data['email'] ?>" required>
                                        </div>
                                        <button type="submit" name="test_email" class="btn btn-info btn-sm w-100">
                                            <i class="fas fa-envelope"></i> Send Test Email
                                        </button>
                                    </form>
                                    
                                    <div class="mt-3">
                                        <h6 class="text-muted">SMTP Configuration</h6>
                                        <ul class="list-unstyled small">
                                            <li><strong>Server:</strong> smtp.gmail.com</li>
                                            <li><strong>Port:</strong> 587 (TLS)</li>
                                            <li><strong>Username:</strong> jeanncorollo04@gmail.com</li>
                                            <li><strong>Status:</strong> <span class="text-success">✅ Ready</span></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Website Block Testing -->
                        <div class="col-xl-4 col-lg-4">
                            <div class="card notification-card shadow mb-4">
                                <div class="card-header py-3 bg-warning text-white">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-shield-alt"></i> Website Block Test
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form method="post">
                                        <div class="form-group">
                                            <label for="device_name">Device Name</label>
                                            <input type="text" class="form-control" name="device_name" 
                                                   placeholder="e.g., John's iPhone" value="Test Device">
                                        </div>
                                        <div class="form-group">
                                            <label for="blocked_site">Blocked Site</label>
                                            <input type="text" class="form-control" name="blocked_site" 
                                                   placeholder="e.g., facebook.com" value="facebook.com">
                                        </div>
                                        <div class="form-group">
                                            <label for="notification_email">Custom Email (optional)</label>
                                            <input type="email" class="form-control" name="notification_email" 
                                                   placeholder="Leave empty to use admin email">
                                        </div>
                                        <button type="submit" name="test_website_block" class="btn btn-warning btn-sm w-100">
                                            <i class="fas fa-ban"></i> Test Website Block Alert
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Device Block Testing -->
                        <div class="col-xl-4 col-lg-4">
                            <div class="card notification-card shadow mb-4">
                                <div class="card-header py-3 bg-danger text-white">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-lock"></i> Device Block Test
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form method="post">
                                        <div class="form-group">
                                            <label for="device_name">Device Name</label>
                                            <input type="text" class="form-control" name="device_name" 
                                                   placeholder="e.g., John's iPhone" value="Test Device">
                                        </div>
                                        <div class="form-group">
                                            <label for="block_reason">Block Reason</label>
                                            <select class="form-control" name="block_reason">
                                                <option value="Policy violation">Policy violation</option>
                                                <option value="Time limit exceeded">Time limit exceeded</option>
                                                <option value="Inappropriate content access">Inappropriate content access</option>
                                                <option value="Manual block by administrator">Manual block by administrator</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="notification_email">Custom Email (optional)</label>
                                            <input type="email" class="form-control" name="notification_email" 
                                                   placeholder="Leave empty to use admin email">
                                        </div>
                                        <button type="submit" name="test_device_block" class="btn btn-danger btn-sm w-100">
                                            <i class="fas fa-lock"></i> Test Device Block Alert
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Integration Guide -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card notification-card shadow mb-4">
                                <div class="card-header py-3 bg-primary text-white">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-code"></i> Integration Guide - How to Add Notifications to Your Code
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>1. Include the Notification Service</h6>
                                            <div class="integration-code">require_once 'includes/IntegratedNotificationService.php';

$notificationService = new IntegratedNotificationService();</div>

                                            <h6 class="mt-3">2. Website Block Notification</h6>
                                            <div class="integration-code">// When a website is blocked
$result = $notificationService->notifyWebsiteBlocked(
    'John\'s iPhone',    // Device name
    'facebook.com',     // Blocked site
    '',                 // Additional info (optional)
    'user@email.com'    // Custom email (optional)
);

// Display browser alert if generated
if (isset($result['alert']['script'])) {
    echo $result['alert']['script'];
}</div>

                                            <h6 class="mt-3">3. Device Block Notification</h6>
                                            <div class="integration-code">// When a device is blocked
$result = $notificationService->notifyDeviceBlocked(
    'John\'s iPhone',        // Device name
    'Time limit exceeded',  // Reason
    'user@email.com'        // Custom email (optional)
);

// Display browser alert if generated
if (isset($result['alert']['script'])) {
    echo $result['alert']['script'];
}</div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>4. Custom Notifications</h6>
                                            <div class="integration-code">// Send custom notification
$result = $notificationService->sendCustomNotification(
    'System Alert',          // Title
    'Custom message here',   // Message
    'warning',              // Type: success, error, warning, info
    'user@email.com'        // Custom email (optional)
);

// Display browser alert if generated
if (isset($result['alert']['script'])) {
    echo $result['alert']['script'];
}</div>

                                            <h6 class="mt-3">5. Available Notification Types</h6>
                                            <ul class="list-unstyled">
                                                <li><i class="fas fa-ban text-warning"></i> <strong>Website Blocked:</strong> notifyWebsiteBlocked()</li>
                                                <li><i class="fas fa-lock text-danger"></i> <strong>Device Blocked:</strong> notifyDeviceBlocked()</li>
                                                <li><i class="fas fa-envelope text-info"></i> <strong>Custom Alert:</strong> sendCustomNotification()</li>
                                                <li><i class="fas fa-vial text-success"></i> <strong>System Test:</strong> testNotificationSystem()</li>
                                            </ul>

                                            <h6 class="mt-3">6. Features</h6>
                                            <ul class="list-unstyled">
                                                <li><i class="fas fa-check text-success"></i> Automatic email notifications</li>
                                                <li><i class="fas fa-check text-success"></i> Browser popup alerts</li>
                                                <li><i class="fas fa-check text-success"></i> Quiet hours support</li>
                                                <li><i class="fas fa-check text-success"></i> Custom email recipients</li>
                                                <li><i class="fas fa-check text-success"></i> Comprehensive logging</li>
                                                <li><i class="fas fa-check text-success"></i> Error handling</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Section -->
                    <div class="row">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Email Status</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?= $notificationSettings['email_enabled'] ? 'Active' : 'Disabled' ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-envelope fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Browser Alerts</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?= $notificationSettings['alerts_enabled'] ? 'Active' : 'Disabled' ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-bell fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Quiet Hours</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?= $notificationSettings['quiet_hours_start'] ?> - <?= $notificationSettings['quiet_hours_end'] ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-moon fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Admin Email</div>
                                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                                <?= htmlspecialchars($notificationSettings['admin_email']) ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-user fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

        </div>
        <!-- End of Content Wrapper -->

        <?php include'../footer.php';?>

    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="../../logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="../../vendor/jquery/jquery.min.js"></script>
    <script src="../../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="../../vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="../../js/sb-admin-2.min.js"></script>

    <!-- Display any generated alerts -->
    <?= $alert_script ?>

</body>

</html>
<?php
} else {
    header('location:../../index.php');
}
?>
