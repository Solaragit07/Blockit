<?php
include '../../connectMySql.php';
include '../../loginverification.php';
include '../../includes/EmailConfig.php';
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
    $notification_email = $_POST['notification_email'];
    
    if(empty($notification_email) || !filter_var($notification_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please provide a valid email address for notifications.";
    } else {
        $notificationService = new IntegratedNotificationService();
        $result = $notificationService->notifyWebsiteBlocked($device_name, $blocked_site, '', $notification_email);
        
        $success_message = "Website block notification sent to $notification_email!";
        if(isset($result['alert']['script'])) {
            $alert_script = $result['alert']['script'];
        }
    }
}

if(isset($_POST['test_device_block'])) {
    $device_name = $_POST['device_name'] ?: 'Test Device';
    $reason = $_POST['block_reason'] ?: 'Policy violation';
    $notification_email = $_POST['notification_email'];
    
    if(empty($notification_email) || !filter_var($notification_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please provide a valid email address for notifications.";
    } else {
        $notificationService = new IntegratedNotificationService();
        $result = $notificationService->notifyDeviceBlocked($device_name, $reason, $notification_email);
        
        $success_message = "Device block notification sent to $notification_email!";
        if(isset($result['alert']['script'])) {
            $alert_script = $result['alert']['script'];
        }
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

if(isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Get current admin data to verify current password
    $query = "SELECT password FROM admin WHERE user_id = '".$_SESSION['user_id']."'";
    $result = $conn->query($query);
    $admin = $result->fetch_assoc();
    
    if($admin['password'] === $current_password) {
        if($new_password === $confirm_password) {
            if(strlen($new_password) >= 6) {
                $sql = sprintf("UPDATE admin SET password = '%s' WHERE user_id = '%s'",
                    mysqli_real_escape_string($conn, $new_password),
                    $_SESSION['user_id']
                );
                
                if(mysqli_query($conn, $sql)) {
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Failed to update password.";
                }
            } else {
                $error_message = "Password must be at least 6 characters long.";
            }
        } else {
            $error_message = "New passwords do not match.";
        }
    } else {
        $error_message = "Current password is incorrect.";
    }
}

// Get current admin data
$query = "SELECT * FROM admin WHERE user_id = '".$_SESSION['user_id']."'";
$result = $conn->query($query);
$admin_data = $result->fetch_assoc();

// Get notification service settings
$notificationService = new IntegratedNotificationService();
$notificationSettings = $notificationService->getSettings();

$query = "SELECT * FROM admin WHERE user_id = '".$_SESSION['user_id'] ."'";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>BlockIT - Email & Notification Center</title>
    <link rel="icon" type="image/x-icon" href="../../img/logo1.png" />

    <!-- Custom fonts for this template-->
    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">`n    `n    <!-- Custom Color Palette -->`n    <link href="../../css/custom-color-palette.css" rel="stylesheet">
    <script src="../../js/sweetalert2.all.min.js"></script>
    
    <style>
        .notification-card {
            transition: transform 0.2s;
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
        .status-active { background-color: #4CAF50; }
        .status-inactive { background-color: #858796; }
        .status-warning { background-color: #f6c23e; }
        
        /* Green theme overrides */
        .text-primary { color: #4CAF50 !important; }
        .btn-primary { 
            background: linear-gradient(135deg, #4CAF50, #45a049) !important;
            border-color: #4CAF50 !important;
            color: white !important;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #45a049, #3d8b40) !important;
            border-color: #45a049 !important;
            color: white !important;
        }
        .alert-primary {
            color: #2d5a2f;
            background-color: #f8fdf8;
            border-color: #4CAF50;
        }
        .form-control:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }
        .form-check-input:checked {
            background-color: #4CAF50;
            border-color: #4CAF50;
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #545b62);
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background: linear-gradient(135deg, #545b62, #3e444a);
            border-color: #545b62;
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
                        <div>
                            <h1 class="h3 mb-0 text-gray-800">
                                <i class="fas fa-envelope"></i> Email & Notification Center
                            </h1>
                            <p class="text-muted">Manage email configuration, notifications, and alerts</p>
                        </div>
                        <div class="text-right">
                            <span class="status-indicator status-active"></span>
                            Email: <?= $notificationSettings['email_enabled'] ? 'Enabled' : 'Disabled' ?>
                            <br>
                            <span class="status-indicator <?= $notificationSettings['alerts_enabled'] ? 'status-active' : 'status-inactive' ?>"></span>
                            Alerts: <?= $notificationSettings['alerts_enabled'] ? 'Enabled' : 'Disabled' ?>
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

                    <!-- Content Row -->
                     <div class="row">
                        <!-- Profile & Email Configuration -->
                        <div class="col-xl-6 col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3" style="background: linear-gradient(135deg, #4CAF50, #45a049);">
                                    <h6 class="m-0 font-weight-bold text-white">
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
                                        </div>
                                        <div class="form-group">
                                            <label for="password">Password</label>
                                            <input class="form-control" name="password" id="password" type="password" 
                                                   placeholder="Enter your password" value="<?= htmlspecialchars($admin_data['password']) ?>" required>
                                        </div>
                                        <button class="btn btn-primary" name="update_profile" type="submit">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Notification Settings -->
                        <div class="col-xl-6 col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3" style="background: linear-gradient(135deg, #4CAF50, #45a049);">
                                    <h6 class="m-0 font-weight-bold text-white">
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
                                                    <i class="fas fa-envelope" style="color: #4CAF50;"></i> Email Notifications
                                                </label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="browser_alerts" 
                                                       name="browser_alerts" <?= $notificationSettings['alerts_enabled'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="browser_alerts">
                                                    <i class="fas fa-bell" style="color: #4CAF50;"></i> Browser Alerts
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <h6 class="font-weight-bold mt-4 mb-3">
                                            <i class="fas fa-clock" style="color: #4CAF50;"></i> Quiet Hours
                                        </h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <label for="quiet_start">Start Time</label>
                                                <input type="time" class="form-control" name="quiet_start" id="quiet_start" 
                                                       value="<?= $notificationSettings['quiet_hours_start'] ?>">
                                            </div>
                                            <div class="col-6">
                                                <label for="quiet_end">End Time</label>
                                                <input type="time" class="form-control" name="quiet_end" id="quiet_end" 
                                                       value="<?= $notificationSettings['quiet_hours_end'] ?>">
                                            </div>
                                        </div>
                                        
                                        <button type="submit" name="update_notification_settings" class="btn btn-primary mt-3">
                                            <i class="fas fa-save"></i> Save Notification Settings
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Configuration Status -->
                    <div class="row">
                        <div class="col-xl-12">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3" style="background: linear-gradient(135deg, #4CAF50, #45a049);">
                                    <h6 class="m-0 font-weight-bold text-white">
                                        <i class="fas fa-server"></i> Email Configuration Status
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-success" role="alert">
                                        <h6 class="alert-heading"><i class="fas fa-check-circle"></i> Gmail SMTP Configured</h6>
                                        <p class="mb-2">Your email system is configured with the following settings:</p>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <ul class="mb-0">
                                                    <li><strong>SMTP Server:</strong> smtp.gmail.com</li>
                                                    <li><strong>Port:</strong> 587 (TLS)</li>
                                                    <li><strong>Username:</strong> jeanncorollo04@gmail.com</li>
                                                    <li><strong>Status:</strong> <span style="color: #4CAF50;">✅ Ready</span></li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="text-muted mb-2">Automatic Email Notifications:</h6>
                                                <ul class="list-unstyled mb-0">
                                                    <li><i class="fas fa-shield-alt" style="color: #4CAF50;"></i> Blocked website access attempts</li>
                                                    <li><i class="fas fa-mobile-alt" style="color: #4CAF50;"></i> Device blocking/unblocking alerts</li>
                                                    <li><i class="fas fa-exclamation-triangle" style="color: #4CAF50;"></i> System status updates</li>
                                                    <li><i class="fas fa-clock text-secondary"></i> Scheduled reports</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <form method="post" class="mt-3">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="form-group">
                                                    <label for="test_email_address">Test Email Address:</label>
                                                    <input type="email" class="form-control" name="test_email_address" id="test_email_address" 
                                                           placeholder="Enter email to test" value="<?= htmlspecialchars($admin_data['email']) ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label>&nbsp;</label>
                                                <button type="submit" name="test_email" class="btn btn-primary btn-block">
                                                    <i class="fas fa-paper-plane"></i> Send Test Email
                                                </button>
                                                <a href="test_system.php" class="btn btn-secondary btn-sm btn-block mt-2">
                                                    <i class="fas fa-vial"></i> Advanced Testing
                                                </a>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Testing -->
                    <div class="row">
                        <!-- Website Block Test -->
                        <div class="col-xl-6 col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3" style="background: linear-gradient(135deg, #4CAF50, #45a049);">
                                    <h6 class="m-0 font-weight-bold text-white">
                                        <i class="fas fa-ban"></i> Test Website Block Notification
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3">Test website blocking notifications and alerts:</p>
                                    <form method="post">
                                        <div class="form-group">
                                            <label for="device_name">Device Name</label>
                                            <input type="text" class="form-control" name="device_name" id="device_name" 
                                                   placeholder="e.g., John's iPhone" value="Test Device">
                                        </div>
                                        <div class="form-group">
                                            <label for="blocked_site">Blocked Website</label>
                                            <input type="text" class="form-control" name="blocked_site" id="blocked_site" 
                                                   placeholder="e.g., facebook.com" value="facebook.com">
                                        </div>
                                        <div class="form-group">
                                            <label for="notification_email">Email Address</label>
                                            <input type="email" class="form-control" name="notification_email" 
                                                   placeholder="Enter notification email address" value="<?= htmlspecialchars($admin_data['email']) ?>" required>
                                        </div>
                                        <button type="submit" name="test_website_block" class="btn btn-primary btn-sm w-100">
                                            <i class="fas fa-ban"></i> Test Website Block Alert
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Device Block Test -->
                        <div class="col-xl-6 col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3" style="background: linear-gradient(135deg, #4CAF50, #45a049);">
                                    <h6 class="m-0 font-weight-bold text-white">
                                        <i class="fas fa-lock"></i> Test Device Block Notification
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3">Test device blocking notifications and alerts:</p>
                                    <form method="post">
                                        <div class="form-group">
                                            <label for="device_name2">Device Name</label>
                                            <input type="text" class="form-control" name="device_name" id="device_name2" 
                                                   placeholder="e.g., John's iPhone" value="Test Device">
                                        </div>
                                        <div class="form-group">
                                            <label for="block_reason">Block Reason</label>
                                            <input type="text" class="form-control" name="block_reason" id="block_reason" 
                                                   placeholder="e.g., Time limit exceeded" value="Policy violation">
                                        </div>
                                        <div class="form-group">
                                            <label for="notification_email2">Email Address</label>
                                            <input type="email" class="form-control" name="notification_email" 
                                                   placeholder="Enter notification email address" value="<?= htmlspecialchars($admin_data['email']) ?>" required>
                                        </div>
                                        <button type="submit" name="test_device_block" class="btn btn-primary btn-sm w-100">
                                            <i class="fas fa-lock"></i> Test Device Block Alert
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>


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
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
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

    <!-- Page level plugins -->
    <script src="../../vendor/chart.js/Chart.min.js"></script>

    <!-- Page level custom scripts -->
    <script src="../../js/demo/chart-area-demo.js"></script>
    <script src="../../js/demo/chart-pie-demo.js"></script>
    <script src="../../vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="../../vendor/datatables/dataTables.bootstrap4.min.js"></script>

    <script>
      $(function () {
        $("#dataTable").DataTable({
          "responsive": true,
        });
      });
    </script>

    <!-- Display any generated alerts -->
    <?= $alert_script ?>

</body>

</html>
<?php
}
}
else
{
    header('location:../../index.php');
}?>