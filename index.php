<?php
// --- Session only (no CSRF) ---
$__secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $__secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// --- Domain guard (no redirects to missing paths) ---
$host   = strtolower($_SERVER['HTTP_HOST'] ?? '');
$uri    = $_SERVER['REQUEST_URI'] ?? '';
$script = $_SERVER['SCRIPT_NAME'] ?? '';

$allowedHosts = ['localhost', '127.0.0.1', 'blockit.site', 'www.blockit.site'];
$isPrivateLan = (bool)preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.)/', $host);
$alreadyHandler = (stripos($script, 'redirect_handler.php') !== false) || (stripos($uri, 'redirect_handler.php') !== false);

if (!$alreadyHandler && !in_array($host, $allowedHosts, true) && !$isPrivateLan) {
    include __DIR__ . '/redirect_handler.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>BlockIT - Family Internet Protection</title>
    <link rel="icon" type="image/x-icon" href="img/logo1.png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="js/sweetalert2.all.min.js"></script>

    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #0dcaf0 0%, #087990 100%);
            background-size:300% 300%;
            animation: gradientShift 8s ease infinite;
            min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px;
        }
        @keyframes gradientShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        .login-container {
            background:#b6effb; border-radius:20px;
            box-shadow:0 20px 60px rgba(0,0,0,0.1), 0 0 40px rgba(13,202,240,0.2);
            overflow:hidden; width:100%; max-width:850px; display:grid; grid-template-columns:1fr 1fr;
            min-height:580px; border:1px solid rgba(13,202,240,0.3); backdrop-filter:blur(10px);
        }
        .left-section { background:rgba(182,239,251,0.7); padding:40px 35px; display:flex; flex-direction:column; justify-content:center; position:relative; }
        .logo-small { position:absolute; top:30px; left:30px; display:flex; align-items:center; gap:10px; }
        .logo-small img { width:40px; height:40px; }
        .logo-small span { font-size:18px; font-weight:700; color:#1f2937; }
        .family-image { width:100%; height:240px; background:url('img/family-fun-for-the-smart-generat.png') center center/cover; border-radius:15px; margin:40px 0 30px; box-shadow:0 10px 30px rgba(0,0,0,0.1); }
        .content-text h2 { color:#1f2937; font-size:28px; font-weight:700; margin-bottom:20px; line-height:1.3; }
        .content-text p { color:#6b7280; font-size:16px; line-height:1.6; margin-bottom:30px; }
        .features { display:flex; flex-direction:column; gap:15px; }
        .feature-item { display:flex; align-items:center; gap:12px; }
        .feature-item .check-icon { width:24px; height:24px; background:linear-gradient(135deg,#0dcaf0,#087990); border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 4px 15px rgba(13,202,240,0.3); }
        .feature-item .check-icon i { color:#fff; font-size:12px; }
        .feature-item span { color:#4b5563; font-size:15px; font-weight:500; }
        .right-section { padding:40px 35px; display:flex; flex-direction:column; justify-content:center; }
        .welcome-header { text-align:left; margin-bottom:30px; }
        .welcome-header h1 { color:#1f2937; font-size:32px; font-weight:700; margin-bottom:8px; }
        .welcome-header p { color:#6b7280; font-size:16px; }
        .form-group { margin-bottom:25px; }
        .form-group label { display:block; color:#374151; font-size:14px; font-weight:600; margin-bottom:8px; }
        .form-group input { width:100%; padding:16px 20px; border:1px solid #d1d5db; border-radius:10px; font-size:16px; transition:all .3s ease; background:#f9fafb; }
        .form-group input:focus { outline:none; border-color:#0dcaf0; background:#fff; box-shadow:0 0 0 3px rgba(13,202,240,.1), 0 4px 20px rgba(13,202,240,.2); }
        .form-group input::placeholder { color:#9ca3af; }
        .forgot-password { text-align:right; margin-bottom:30px; }
        .forgot-password a { color:#6b7280; text-decoration:none; font-size:14px; }
        .forgot-password a:hover { color:#0dcaf0; }
        .sign-in-btn { width:100%; background:linear-gradient(135deg,#0dcaf0,#087990); color:#fff; border:none; padding:16px 24px; border-radius:10px; font-size:16px; font-weight:600; cursor:pointer; transition:all .3s ease; margin-bottom:30px; box-shadow:0 4px 20px rgba(13,202,240,.3); }
        .sign-in-btn:hover { background:linear-gradient(135deg,#087990,#0dcaf0); transform:translateY(-2px); box-shadow:0 8px 30px rgba(13,202,240,.4); }
        .account-text { text-align:center; margin-bottom:30px; }
        .account-text span { color:#6b7280; font-size:14px; }
        .account-text a { color:#087990; text-decoration:none; font-weight:600; }
        @media (max-width: 768px) { .login-container { grid-template-columns:1fr; max-width:400px; } .left-section { display:none; } .right-section { padding:40px 30px; } .welcome-header h1 { font-size:28px; } }
        @media (max-width: 480px) { body { padding:10px; } .right-section { padding:30px 20px; } .welcome-header h1 { font-size:24px; } }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Left Section -->
        <div class="left-section">
            <div class="logo-small">
                <img src="img/logo1.png" alt="BlockIT Logo">
                <span>BlockIt</span>
            </div>

            <div class="family-image"></div>

            <div class="content-text">
                <h2>Keep Your Family Safe Online</h2>
                <p>BlockIt keeps your children safe online by blocking inappropriate sites and managing screen time, all from one easy dashboard.</p>

                <div class="features">
                    <div class="feature-item">
                        <div class="check-icon"><i class="fas fa-check"></i></div>
                        <span>Block inappropriate websites automatically</span>
                    </div>
                    <div class="feature-item">
                        <div class="check-icon"><i class="fas fa-check"></i></div>
                        <span>Manage screen time across all devices</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Section -->
        <div class="right-section">
            <div class="welcome-header">
                <h1>Welcome Back</h1>
                <p>Sign in to your BlockIt dashboard</p>
            </div>

            <form method="POST" action="loginprocess.php" autocomplete="off">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>

                <div class="forgot-password"><a href="/forgot-password.php">Forgot Password?</a>
</div>

                <button type="submit" name="LOGIN" class="sign-in-btn">Sign In</button>
            </form>

            <div class="account-text">
                <span>Don't have an account? <a href="register.php">Create Account</a></span>
            </div>
        </div>
    </div>

    <script>
    // Clean, single validation block (no duplicates)
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('form');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const loginBtn = document.querySelector('.sign-in-btn');

        function showFieldError(input, message) {
            input.style.borderColor = '#ef4444';
            input.style.backgroundColor = '#fef2f2';
            const existingError = input.parentNode.querySelector('.error-message');
            if (existingError) existingError.remove();
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.style.color = '#ef4444';
            errorDiv.style.fontSize = '12px';
            errorDiv.style.marginTop = '5px';
            errorDiv.textContent = message;
            input.parentNode.appendChild(errorDiv);
        }

        function showFieldSuccess(input) {
            input.style.borderColor = '#0dcaf0';
            input.style.backgroundColor = 'rgba(182, 239, 251, 0.2)';
            const existingError = input.parentNode.querySelector('.error-message');
            if (existingError) existingError.remove();
        }

        function validateEmail(input) {
            const email = input.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email === '') { showFieldError(input, 'Email is required'); return false; }
            if (!emailRegex.test(email)) { showFieldError(input, 'Please enter a valid email address'); return false; }
            showFieldSuccess(input); return true;
        }

        function validatePassword(input) {
            const password = input.value;
            if (password === '') { showFieldError(input, 'Password is required'); return false; }
            if (password.length < 3) { showFieldError(input, 'Password must be at least 3 characters'); return false; }
            showFieldSuccess(input); return true;
        }

        function validateForm() {
            const emailValid = validateEmail(emailInput);
            const passwordValid = validatePassword(passwordInput);
            return emailValid && passwordValid;
        }

        emailInput.addEventListener('blur', () => validateEmail(emailInput));
        passwordInput.addEventListener('blur', () => validatePassword(passwordInput));

        form.addEventListener('submit', function (e) {
            if (!validateForm()) {
                e.preventDefault();
                return;
            }
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
            loginBtn.disabled = true;
        });
    });

    // Optional flash messages
    <?php if(isset($_SESSION['login_error'])): ?>
    Swal.fire({ icon: 'error', title: 'Login Failed', text: '<?= addslashes($_SESSION['login_error']); ?>', confirmButtonColor: '#0dcaf0' });
    <?php unset($_SESSION['login_error']); endif; ?>

    <?php if(isset($_SESSION['logout_message'])): ?>
    Swal.fire({ icon: 'success', title: 'Logged Out', text: '<?= addslashes($_SESSION['logout_message']); ?>', confirmButtonColor: '#0dcaf0' });
    <?php unset($_SESSION['logout_message']); endif; ?>

    <?php if(isset($_SESSION['registration_success'])): ?>
    Swal.fire({ icon: 'success', title: 'Registration Successful', text: '<?= addslashes($_SESSION['registration_success']); ?>', confirmButtonColor: '#0dcaf0' });
    <?php unset($_SESSION['registration_success']); endif; ?>
    </script>
</body>
</html>
