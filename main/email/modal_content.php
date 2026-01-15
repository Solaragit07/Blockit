<?php
session_start();
include '../../connectMySql.php';
include '../../loginverification.php';
require_once '../../includes/IntegratedNotificationService.php';

if(!logged_in()) {
    echo '<div class="alert alert-danger">Please log in to access settings.</div>';
    exit;
}

// Handle AJAX form submissions
if ($_POST) {
    $response = ['success' => false, 'message' => ''];
    
    if(isset($_POST['update_profile'])) {
        $email = $_POST['email'];
        $name = $_POST['name'];
        
        $sql = sprintf("UPDATE admin SET email = '%s', name = '%s' WHERE user_id = '%s'",
            mysqli_real_escape_string($conn, $email),
            mysqli_real_escape_string($conn, $name),
            $_SESSION['user_id']
        );
        
        if(mysqli_query($conn, $sql)) {
            $response['success'] = true;
            $response['message'] = 'Profile updated successfully!';
        } else {
            $response['message'] = 'Failed to update profile.';
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
            $response['success'] = true;
            $response['message'] = 'Notification settings updated successfully!';
        } else {
            $response['message'] = 'Failed to update notification settings.';
        }
    }
    
    if(isset($_POST['test_email'])) {
        $test_email = $_POST['test_email_address'];
        
        if(filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            $notificationService = new IntegratedNotificationService();
            $result = $notificationService->testNotificationSystem($test_email);
            
            if($result['email_test']['success']) {
                $response['success'] = true;
                $response['message'] = "Test email sent successfully to $test_email!";
            } else {
                $response['message'] = "Failed to send test email: " . $result['email_test']['message'];
            }
        } else {
            $response['message'] = 'Invalid email address provided.';
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Get current admin data
$query = "SELECT * FROM admin WHERE user_id = '".$_SESSION['user_id']."'";
$result = $conn->query($query);
$admin_data = $result->fetch_assoc();

// Get notification service settings
$notificationService = new IntegratedNotificationService();
$notificationSettings = $notificationService->getSettings();
?>

<div id="modalAlertContainer"></div>

<!-- Custom CSS for modal styling -->
<style>
.nav-tabs {
    border-bottom: 2px solid #4CAF50;
}

.nav-tabs .nav-link {
    color: #4CAF50 !important;
    border: 1px solid transparent;
    background-color: white;
    font-weight: 500;
}

.nav-tabs .nav-link:hover {
    border-color: #4CAF50;
    color: #45a049 !important;
    background-color: #f8fdf8;
}

.nav-tabs .nav-link.active {
    color: white !important;
    background: linear-gradient(135deg, #4CAF50, #45a049) !important;
    border-color: #4CAF50;
    font-weight: 600;
}

.form-control {
    border: 1px solid #ddd;
    background-color: white;
}

.form-control:focus {
    border-color: #4CAF50;
    box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
    background-color: white;
}

.form-check-input:checked {
    background-color: #4CAF50;
    border-color: #4CAF50;
}

.btn-primary {
    background: linear-gradient(135deg, #4CAF50, #45a049) !important;
    border: none;
    font-weight: 600;
    color: white !important;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #45a049, #3d8b40) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(76, 175, 80, 0.3);
    color: white !important;
}

.text-primary {
    color: #4CAF50 !important;
}

.alert-primary {
    color: #2d5a2f;
    background-color: #f8fdf8;
    border: 1px solid #4CAF50;
}

.tab-content {
    background-color: white;
    padding: 20px;
    border-radius: 0 0 8px 8px;
}

.modal-body {
    background-color: white;
}
</style>

<!-- Tabs for different sections -->
<ul class="nav nav-tabs" id="settingsTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="profile-tab" data-toggle="tab" href="#profile" role="tab" style="color: #4CAF50; background-color: white;">
            <i class="fas fa-user"></i> Profile
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="notifications-tab" data-toggle="tab" href="#notifications" role="tab" style="color: #4CAF50;">
            <i class="fas fa-bell"></i> Notifications
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="email-test-tab" data-toggle="tab" href="#email-test" role="tab" style="color: #4CAF50;">
            <i class="fas fa-paper-plane"></i> Email Test
        </a>
    </li>
</ul>

<div class="tab-content mt-3" id="settingsTabContent">
    <!-- Profile Tab -->
    <div class="tab-pane fade show active" id="profile" role="tabpanel">
        <form id="profileForm">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="modal_name">Administrator Name</label>
                        <input class="form-control" name="name" id="modal_name" type="text" 
                               placeholder="Enter your name" value="<?= htmlspecialchars($admin_data['name']) ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="modal_email">Email Address</label>
                        <input class="form-control" name="email" id="modal_email" type="email" 
                               placeholder="Enter your email" value="<?= htmlspecialchars($admin_data['email']) ?>" required>
                    </div>
                </div>
            </div>
            <input type="hidden" name="update_profile" value="1">
            <button class="btn btn-primary" type="submit">
                <i class="fas fa-save"></i> Save Profile
            </button>
        </form>
    </div>

    <!-- Notifications Tab -->
    <div class="tab-pane fade" id="notifications" role="tabpanel">
        <form id="notificationsForm">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="modal_email_notifications" 
                                   name="email_notifications" <?= $notificationSettings['email_enabled'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="modal_email_notifications">
                                <i class="fas fa-envelope" style="color: #4CAF50;"></i> Email Notifications
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="modal_browser_alerts" 
                                   name="browser_alerts" <?= $notificationSettings['alerts_enabled'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="modal_browser_alerts">
                                <i class="fas fa-bell" style="color: #4CAF50;"></i> Browser Alerts
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6 class="font-weight-bold mb-3">
                        <i class="fas fa-clock" style="color: #4CAF50;"></i> Quiet Hours
                    </h6>
                    <div class="row">
                        <div class="col-6">
                            <label for="modal_quiet_start">Start Time</label>
                            <input type="time" class="form-control" name="quiet_start" id="modal_quiet_start" 
                                   value="<?= $notificationSettings['quiet_hours_start'] ?>">
                        </div>
                        <div class="col-6">
                            <label for="modal_quiet_end">End Time</label>
                            <input type="time" class="form-control" name="quiet_end" id="modal_quiet_end" 
                                   value="<?= $notificationSettings['quiet_hours_end'] ?>">
                        </div>
                    </div>
                </div>
            </div>
            <input type="hidden" name="update_notification_settings" value="1">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Notification Settings
            </button>
        </form>
    </div>

    <!-- Email Test Tab -->
    <div class="tab-pane fade" id="email-test" role="tabpanel">
        <form id="emailTestForm">
            <div class="alert alert-primary">
                <h6><i class="fas fa-info-circle" style="color: #4CAF50;"></i> Email Configuration Status</h6>
                <p class="mb-0">Gmail SMTP configured and ready. Test your email settings below.</p>
            </div>
            <div class="form-group">
                <label for="modal_test_email">Test Email Address</label>
                <input type="email" class="form-control" name="test_email_address" id="modal_test_email" 
                       placeholder="Enter email to test" value="<?= htmlspecialchars($admin_data['email']) ?>" required>
            </div>
            <input type="hidden" name="test_email" value="1">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Send Test Email
            </button>
        </form>
    </div>
</div>

<script>
// Handle form submissions via AJAX
$('#profileForm, #notificationsForm, #emailTestForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize();
    const submitBtn = $(this).find('button[type="submit"]');
    const originalText = submitBtn.html();
    
    // Show loading state
    submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
    
    $.ajax({
        url: '../email/modal_content.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showModalAlert('success', response.message);
            } else {
                showModalAlert('danger', response.message);
            }
        },
        error: function() {
            showModalAlert('danger', 'An error occurred while saving settings.');
        },
        complete: function() {
            // Restore button
            submitBtn.html(originalText).prop('disabled', false);
        }
    });
});

// Handle tab switching with custom styling
$('#settingsTabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    // Remove active styling from all tabs
    $('#settingsTabs .nav-link').each(function() {
        $(this).css({
            'color': '#4CAF50',
            'background': 'white',
            'font-weight': '500'
        });
    });
    
    // Add active styling to current tab
    $(e.target).css({
        'color': 'white',
        'background': 'linear-gradient(135deg, #4CAF50, #45a049)',
        'font-weight': '600'
    });
});

function showModalAlert(type, message) {
    const alertColor = type === 'success' ? '#4CAF50' : '#d32f2f';
    const bgColor = type === 'success' ? '#f8fdf8' : '#fef7f7';
    
    const alertHtml = `
        <div class="alert alert-dismissible fade show" role="alert" style="color: ${alertColor}; background-color: ${bgColor}; border: 1px solid ${alertColor};">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            ${message}
            <button type="button" class="close" data-dismiss="alert" style="color: ${alertColor};">
                <span>&times;</span>
            </button>
        </div>
    `;
    
    $('#modalAlertContainer').html(alertHtml);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);
}
</script>
