<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1); // Show errors for debugging
ini_set('log_errors', 1);
include 'connectMySql.php';

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$registration_successful = false;
$errors = [];
$debug_info = [];

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug_info[] = "POST request received";
    
    // Check if this is a registration form submission
    if (isset($_POST['REGISTER']) || isset($_POST['form_submitted'])) {
        $debug_info[] = "Registration form submission confirmed";
        
        // Log that we received a POST request
        error_log("Registration form submitted - POST data received");
        
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $debug_info[] = "Form data: name='$name', email='$email', password_length=" . strlen($password);
        error_log("Registration form data - Name: $name, Email: $email, Password length: " . strlen($password));
    
    // Server-side validation
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    $debug_info[] = "Validation completed. Errors: " . count($errors);
    
    // Check if email already exists
    if (empty($errors)) {
        $debug_info[] = "Checking for existing email: '$email'";
        $check_email_query = "SELECT user_id, email FROM admin WHERE email = ?";
        $stmt = $conn->prepare($check_email_query);
        
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $existing_user = $result->fetch_assoc();
                $errors[] = "Email address is already registered";
                $debug_info[] = "Email already exists in database - User ID: " . $existing_user['user_id'];
            } else {
                $debug_info[] = "Email is available - proceeding with registration";
            }
            $stmt->close(); // Close the statement
        } else {
            $errors[] = "Database error during email check";
            $debug_info[] = "Failed to prepare email check statement: " . $conn->error;
        }
    }
    
    // If no errors, create the account
    if (empty($errors)) {
        $debug_info[] = "Proceeding with account creation...";
        
        // Check current user count
        $count_before = mysqli_query($conn, "SELECT COUNT(*) as count FROM admin");
        $before_count = mysqli_fetch_assoc($count_before)['count'];
        $debug_info[] = "Admin users before insert: $before_count";
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Make sure user_id is auto increment - fix the table if needed
        $conn->query("ALTER TABLE admin MODIFY user_id INT(11) AUTO_INCREMENT");
        
        $insert_query = "INSERT INTO admin (name, email, password, status) VALUES (?, ?, ?, 'ACTIVE')";
        $stmt = $conn->prepare($insert_query);
        
        if ($stmt) {
            $stmt->bind_param("sss", $name, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                $registration_successful = true;
                $debug_info[] = "SUCCESS! Account created with user_id: $user_id";
                $_SESSION['registration_success'] = "Account created successfully! Please sign in with your new credentials.";
                
                // Log the successful registration
                error_log("Registration successful: user_id=$user_id, email=$email, name=$name");
                
                // Verify the user was actually inserted
                $verify_query = "SELECT user_id, name, email FROM admin WHERE user_id = ?";
                $verify_stmt = $conn->prepare($verify_query);
                $verify_stmt->bind_param("i", $user_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                
                if ($verify_result->num_rows > 0) {
                    $debug_info[] = "User verification successful - found in database";
                } else {
                    $debug_info[] = "WARNING: User not found after insert!";
                }
                $verify_stmt->close();
                
                // Check user count after insert
                $count_after = mysqli_query($conn, "SELECT COUNT(*) as count FROM admin");
                $after_count = mysqli_fetch_assoc($count_after)['count'];
                $debug_info[] = "Admin users after insert: $after_count";
            } else {
                $errors[] = "Error creating account: " . $stmt->error;
                $debug_info[] = "INSERT failed: " . $stmt->error;
                error_log("Registration failed during INSERT: " . $stmt->error);
            }
            $stmt->close(); // Close the INSERT statement
        } else {
            $errors[] = "Error preparing registration statement: " . $conn->error;
            $debug_info[] = "Failed to prepare INSERT statement: " . $conn->error;
            error_log("Registration failed during statement preparation: " . $conn->error);
        }
    }
    
    // Log debug info for troubleshooting
    error_log("Registration debug: " . implode(" | ", $debug_info));
    } else {
        $debug_info[] = "POST received but not a registration form";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - BlockIT</title>
    <link rel="icon" type="image/x-icon" href="img/logo1.png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="js/sweetalert2.all.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #0dcaf0 0%, #087990 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 650px;
        }

        .left-section {
            background: #f8fafc;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .logo-small {
            position: absolute;
            top: 30px;
            left: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-small img {
            width: 40px;
            height: 40px;
        }

        .logo-small span {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
        }

        .family-image {
            width: 100%;
            height: 280px;
            background: url('img/family-fun-for-the-smart-generat.png') center center;
            background-size: cover;
            border-radius: 15px;
            margin-top: 50px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .content-text h2 {
            color: #1f2937;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.3;
        }

        .content-text p {
            color: #6b7280;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .features {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .feature-item .check-icon {
            width: 24px;
            height: 24px;
            background: #22c55e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .feature-item .check-icon i {
            color: white;
            font-size: 12px;
        }

        .feature-item span {
            color: #4b5563;
            font-size: 15px;
            font-weight: 500;
        }

        .right-section {
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .welcome-header {
            text-align: left;
            margin-bottom: 40px;
        }

        .welcome-header h1 {
            color: #1f2937;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .welcome-header p {
            color: #6b7280;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            color: #374151;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 16px 20px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .form-group input:focus {
            outline: none;
            border-color: #22c55e;
            background: white;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }

        .form-group input::placeholder {
            color: #9ca3af;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .register-btn {
            width: 100%;
            background: #22c55e;
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 30px;
        }

        .register-btn:hover {
            background: #16a34a;
            transform: translateY(-1px);
        }

        .login-text {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-text span {
            color: #6b7280;
            font-size: 14px;
        }

        .login-text a {
            color: #22c55e;
            text-decoration: none;
            font-weight: 600;
        }

        .divider {
            text-align: center;
            margin-bottom: 25px;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e5e7eb;
        }

        .divider span {
            background: white;
            padding: 0 20px;
            color: #9ca3af;
            font-size: 14px;
        }

        .social-buttons {
            display: flex;
            gap: 15px;
        }

        .social-btn {
            flex: 1;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            color: #374151;
        }

        .social-btn:hover {
            border-color: #22c55e;
            background: #f0fdf4;
            color: #374151;
            text-decoration: none;
        }

        .google-btn i {
            color: #ea4335;
        }

        .apple-btn i {
            color: #000;
        }

        .error-message {
            color: #ef4444;
            font-size: 12px;
            margin-top: 5px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .register-container {
                grid-template-columns: 1fr;
                max-width: 400px;
            }

            .left-section {
                display: none;
            }

            .right-section {
                padding: 40px 30px;
            }

            .welcome-header h1 {
                font-size: 28px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .right-section {
                padding: 30px 20px;
            }

            .welcome-header h1 {
                font-size: 24px;
            }

            .social-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <!-- Temporary Debug Info -->
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <div style="position: fixed; top: 10px; right: 10px; background: #fff; border: 2px solid #007bff; padding: 15px; border-radius: 5px; z-index: 9999; max-width: 350px; font-size: 11px; max-height: 80vh; overflow-y: auto;">
        <strong>🔍 DEBUG - Registration Process:</strong><br>
        <?php if (isset($_POST['REGISTER'])): ?>
            ✅ REGISTER button pressed<br>
        <?php else: ?>
            ❌ REGISTER button NOT detected<br>
        <?php endif; ?>
        <?php if (isset($_POST['form_submitted'])): ?>
            ✅ Form submission detected<br>
        <?php endif; ?>
        
        <strong>All POST Data:</strong><br>
        <?php foreach ($_POST as $key => $value): ?>
            • <?= htmlspecialchars($key) ?>: <?= htmlspecialchars(is_string($value) ? $value : print_r($value, true)) ?><br>
        <?php endforeach; ?>
        
        <strong>Form Data:</strong><br>
        • Name: <?= htmlspecialchars($_POST['name'] ?? 'NOT SET') ?><br>
        • Email: <?= htmlspecialchars($_POST['email'] ?? 'NOT SET') ?><br>
        • Password Length: <?= strlen($_POST['password'] ?? '') ?><br>
        
        <?php if (!empty($debug_info)): ?>
            <strong>Process Steps:</strong><br>
            <?php foreach ($debug_info as $info): ?>
                • <?= htmlspecialchars($info) ?><br>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <strong style="color: red;">❌ Errors:</strong><br>
            <?php foreach ($errors as $error): ?>
                • <?= htmlspecialchars($error) ?><br>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if ($registration_successful): ?>
            <strong style="color: green;">✅ Registration Successful!</strong><br>
            <small>Check phpMyAdmin to verify the new user was added.</small>
        <?php endif; ?>
        
        <hr style="margin: 10px 0;">
        <small><strong>DB Connection:</strong> <?= $conn ? 'Connected' : 'Failed' ?></small>
    </div>
    <?php endif; ?>
    <div class="register-container">
        <!-- Left Section -->
        <div class="left-section">
            <div class="logo-small">
                <img src="img/logo1.png" alt="BlockIT Logo">
                <span>BlockIt</span>
            </div>
            
            <div class="family-image"></div>
            
            <div class="content-text">
                <h2>Join Our Family Protection</h2>
                <p>Create your BlockIt account and start protecting your family's online experience with advanced filtering and monitoring tools.</p>
                
                <div class="features">
                    <div class="feature-item">
                        <div class="check-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <span>Block inappropriate websites automatically</span>
                    </div>
                    <div class="feature-item">
                        <div class="check-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <span>Manage screen time across all devices</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Section -->
        <div class="right-section">
            <div class="welcome-header">
                <h1>Create Account</h1>
                <p>Start protecting your family online today</p>
            </div>

            <form method="POST" action="register.php" id="registerForm">
                <input type="hidden" name="form_submitted" value="1">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required placeholder="Enter your full name" 
                           value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email address"
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required placeholder="Create a password">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
                    </div>
                </div>

                <button type="submit" name="REGISTER" class="register-btn" id="registerBtn">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>

            <div class="login-text">
                <span>Already have an account? <a href="index.php">Sign In</a></span>
            </div>

            <div class="divider">
                <span>Or continue with</span>
            </div>

            <div class="social-buttons">
                <a href="#" class="social-btn google-btn">
                    <i class="fab fa-google"></i>
                    Google
                </a>
                <a href="#" class="social-btn apple-btn">
                    <i class="fab fa-apple"></i>
                    Apple
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const registerBtn = document.getElementById('registerBtn');
            
            form.addEventListener('submit', function(e) {
                const name = document.getElementById('name').value.trim();
                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                // Basic validation
                if (!name || !email || !password || !confirmPassword) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Missing Information',
                        text: 'Please fill in all fields',
                        confirmButtonColor: '#22c55e'
                    });
                    return;
                }
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Password Mismatch',
                        text: 'Passwords do not match',
                        confirmButtonColor: '#22c55e'
                    });
                    return;
                }
                
                if (password.length < 6) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Password Too Short',
                        text: 'Password must be at least 6 characters',
                        confirmButtonColor: '#22c55e'
                    });
                    return;
                }
                
                // Show loading state immediately
                registerBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
                registerBtn.disabled = true;
                
                // Let the form submit normally to PHP (no preventDefault for valid forms)
            });
        });

        // Show success/error messages
        <?php if (!empty($errors)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Registration Failed',
                html: '<?= implode("<br>", $errors) ?>',
                confirmButtonColor: '#22c55e'
            });
        <?php endif; ?>

        <?php if ($registration_successful): ?>
            Swal.fire({
                icon: 'success',
                title: 'Account Created Successfully!',
                html: `
                    <div style="text-align: center;">
                        <p>Your BlockIT account has been created successfully!</p>
                        <div style="background: #d4edda; padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid #c3e6cb;">
                            <i class="fas fa-check-circle" style="color: #155724; font-size: 24px;"></i>
                            <p style="margin: 10px 0; color: #155724;">
                                <strong>Welcome to BlockIT!</strong><br>
                                You will be redirected to the sign-in page.
                            </p>
                        </div>
                    </div>
                `,
                confirmButtonColor: '#22c55e',
                confirmButtonText: '<i class="fas fa-sign-in-alt"></i> Go to Sign In',
                timer: 5000,
                timerProgressBar: true,
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(() => {
                window.location.href = 'index.php';
            });
        <?php endif; ?>
    </script>
</body>

</html>
