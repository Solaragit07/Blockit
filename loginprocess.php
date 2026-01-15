<?php
require_once __DIR__ . '/connectMySql.php';

// Detect AJAX for JSON responses
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($isAjax) {
    header('Content-Type: application/json');
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.', 'type' => 'error']);
    } else {
        echo "<script src='js/sweetalert2.all.min.js'></script>
        <body onload='error()'></body>
        <script>function error(){Swal.fire({icon:'error',title:'Invalid request',text:'Use the login form.'})}</script>";
        include __DIR__ . '/index.php';
    }
    exit;
}

// Read credentials
$email    = trim($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');

// Basic validation
if ($email === '' || $password === '') {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required!', 'type' => 'error']);
    } else {
        echo "<script src='js/sweetalert2.all.min.js'></script>
        <body onload='error()'></body>
        <script>function error(){Swal.fire({icon:'error',title:'Login failed!',text:'Email and password are required!'})}</script>";
        include __DIR__ . '/index.php';
    }
    exit;
}

// Start session with secure cookie flags
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Strict'
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Look up user by email
    $sql = "SELECT user_id, name, email, password FROM admin WHERE email = ? LIMIT 1";
    if (!$stmt = $conn->prepare($sql)) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('s', $email);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    $user   = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    // Verify credentials
    if (!$user || !password_verify($password, $user['password'] ?? '')) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password!', 'type' => 'error']);
        } else {
            echo "<script src='js/sweetalert2.all.min.js'></script>
            <body onload='error()'></body>
            <script>function error(){Swal.fire({icon:'error',title:'Login failed!',text:'Invalid email or password!'})}</script>";
            include __DIR__ . '/index.php';
        }
        exit;
    }

    // Successful login: harden session and set data
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['user_id'];
    $_SESSION['name']    = (string)$user['name'];
    $_SESSION['email']   = (string)$user['email'];

        // Default redirect
        $redirect = 'main/dashboard/index.php';

        // If user was redirected to login with ?next=, honor it (same-host only)
        if (!empty($_SESSION['login_next'])) {
            $rawNext = (string)$_SESSION['login_next'];
            unset($_SESSION['login_next']);

            $decoded = urldecode($rawNext);
            $parts = @parse_url($decoded);

            // Accept either absolute URL to same host, or relative path
            $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
            $path = '';

            if (is_array($parts) && isset($parts['host'])) {
                $nextHost = strtolower((string)$parts['host']);
                if ($nextHost === $host) {
                    $path = (string)($parts['path'] ?? '/');
                    $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
                    $path = $path . $query;
                }
            } elseif ($decoded !== '' && $decoded[0] === '/') {
                $path = $decoded;
            }

            if ($path !== '' && $path[0] === '/') {
                $redirect = ltrim($path, '/');
            }
        }

    if ($isAjax) {
        echo json_encode(['success' => true, 'message' => 'Login successful!', 'redirect' => $redirect, 'type' => 'success']);
    } else {
        header('Location: ' . $redirect);
    }
    exit;

} catch (Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.', 'type' => 'error']);
    } else {
        echo "<script src='js/sweetalert2.all.min.js'></script>
        <body onload='error()'></body>
        <script>function error(){Swal.fire({icon:'error',title:'System Error!',text:'Database error. Please try again.'})}</script>";
        include __DIR__ . '/index.php';
    }
    exit;
}
?>