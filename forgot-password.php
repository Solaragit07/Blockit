<?php
// forgot-password.php
// Depends on: connect.php (+ your send_notice())
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/connectMySql.php';

// --- security headers & session ---
$__secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime'=>0,'path'=>'/','domain'=>'',
    'secure'=>$__secure,'httponly'=>true,'samesite'=>'Strict'
  ]);
  session_start();
}
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// --- small helpers ---
if (!function_exists('log_event')) {
  function log_event($m){ error_log($m); }
}
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function csrf_check(string $token): bool {
  return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}
function ip_bin(): string {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  $bin = @inet_pton($ip);
  return $bin !== false ? $bin : inet_pton('0.0.0.0');
}
function base_url(): string {
  // Adjust if you want a hardcoded domain
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'blockit.site';
  return $scheme . '://' . $host;
}

// --- form handling ---
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $token = (string)($_POST['csrf'] ?? '');

  // Always return generic text
  $flash = 'If that email exists, we’ve sent password reset instructions.';

  if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && csrf_check($token)) {

    // Find admin by email (table name: admin; columns: email + id OR user_id)
    $stmt = $conn->prepare('SELECT user_id FROM admin WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($admin) {
      $userId = (int)($admin['user_id'] ?? $admin['id']);

      // Generate split token
      $selector  = bin2hex(random_bytes(8));
      $validator = bin2hex(random_bytes(32));
      $hash      = hash('sha256', $validator);
      $expiresAt = (new DateTime('+20 minutes'))->format('Y-m-d H:i:s');

      // Remove active tokens for this user
      $stmt = $conn->prepare('DELETE FROM password_resets WHERE user_id = ? AND consumed_at IS NULL');
      $stmt->bind_param('i', $userId);
      $stmt->execute();
      $stmt->close();

      // Insert new token
      $stmt = $conn->prepare('INSERT INTO password_resets (user_id, selector, validator_hash, expires_at, created_ip)
                              VALUES (?,?,?,?,?)');
      $ipBin = ip_bin();
      $stmt->bind_param('issss', $userId, $selector, $hash, $expiresAt, $dummy); // bind blob safely below
      // mysqli can't bind VARBINARY directly as resource with this signature; use send_long_data:
      $dummy = ''; // placeholder; send as long data:
      $stmt->send_long_data(4, $ipBin);
      $stmt->execute();
      $stmt->close();

      $resetLink = base_url() . "/reset-password.php?t={$selector}-{$validator}";

      // Use your mail helper
      $mailOk = send_notice(
        $email,
        'Reset your BlockIT password',
        "We received a request to reset your BlockIT password.\n\n".
        "Click this link within 20 minutes:\n{$resetLink}\n\n".
        "If you didn’t request this, you can ignore this email."
      );
      if (!$mailOk) log_event("send_notice() failed for {$email}");
    }
  }
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Forgot Password • BlockIT</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f3f4f6;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center}
.card{background:#fff;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.08);padding:28px;max-width:420px;width:100%}
h1{margin:0 0 8px;font-size:22px}
p{color:#6b7280;margin:0 0 18px}
label{display:block;margin:12px 0 6px;font-weight:600;font-size:14px}
input[type=email]{width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:10px;font-size:15px}
button{margin-top:16px;width:100%;padding:12px 16px;border:0;border-radius:10px;background:linear-gradient(135deg,#0dcaf0,#087990);color:#fff;font-weight:600;cursor:pointer}
.alert{margin-top:12px;border-radius:10px;padding:12px 14px;font-size:14px;background:#ecfeff;border:1px solid #06b6d4;color:#0e7490}
</style>
</head>
<body>
  <div class="card">
    <h1>Forgot Password</h1>
    <p>Enter your account email to receive a reset link.</p>

    <?php if ($flash): ?>
      <div class="alert"><?= htmlspecialchars($flash, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required placeholder="you@example.com">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <button type="submit">Send reset link</button>
    </form>
  </div>
</body>
</html>
