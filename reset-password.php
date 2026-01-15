<?php
// reset-password.php (user_id-only version)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/connectMySql.php'; // provides $conn (mysqli) + send_notice()

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

if (!function_exists('log_event')) {
  function log_event($m){ error_log($m); }
}
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function csrf_check(string $t): bool {
  return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}
function fetch_token(mysqli $conn, string $selector) : ?array {
  $stmt = $conn->prepare('SELECT id, user_id, validator_hash, expires_at, consumed_at
                          FROM password_resets
                          WHERE selector = ? AND consumed_at IS NULL AND expires_at > NOW()
                          LIMIT 1');
  $stmt->bind_param('s', $selector);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $row ?: null;
}
function get_user_email(mysqli $conn, int $userId): ?string {
  // user_id-only lookup
  $stmt = $conn->prepare('SELECT email FROM admin WHERE user_id = ? LIMIT 1');
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $row['email'] ?? null;
}
function password_ok(string $p): bool {
  return strlen($p) >= 10;
}

$err = null; $ok = false;
$selector = $validator = '';

// --- GET: verify token presence so we can show the form ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $t = (string)($_GET['t'] ?? '');
  if (strpos($t, '-') !== false) {
    [$selector, $validator] = explode('-', $t, 2);
  }
  if (!ctype_xdigit($selector) || !ctype_xdigit($validator)) {
    $err = 'Invalid or expired reset link.';
  } else {
    $row = fetch_token($conn, $selector);
    if (!$row || !hash_equals($row['validator_hash'], hash('sha256', $validator))) {
      $err = 'Invalid or expired reset link.';
    }
  }
}

// --- POST: change password ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $selector  = (string)($_POST['selector'] ?? '');
  $validator = (string)($_POST['validator'] ?? '');
  $pwd1      = (string)($_POST['password'] ?? '');
  $pwd2      = (string)($_POST['password_confirm'] ?? '');
  $csrf      = (string)($_POST['csrf'] ?? '');

  if (!csrf_check($csrf)) {
    $err = 'Invalid request. Please reload the page.';
  } elseif (!ctype_xdigit($selector) || !ctype_xdigit($validator)) {
    $err = 'Invalid or expired reset link.';
  } elseif ($pwd1 !== $pwd2) {
    $err = 'Passwords do not match.';
  } elseif (!password_ok($pwd1)) {
    $err = 'Password must be at least 10 characters.';
  } else {
    $conn->begin_transaction();
    try {
      $row = fetch_token($conn, $selector);
      if (!$row || !hash_equals($row['validator_hash'], hash('sha256', $validator))) {
        throw new RuntimeException('Invalid or expired reset link.');
      }

      $userId = (int)$row['user_id'];
       $algo   = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
       $pwdHash = password_hash($pwd1, $algo);

      // new — write the hashed password into the existing 'password' column
        $stmt = $conn->prepare('UPDATE admin SET password = ? WHERE user_id = ? LIMIT 1');

      $stmt->bind_param('si', $pwdHash, $userId);
      $stmt->execute();
      if ($stmt->affected_rows < 1) {
        $stmt->close();
        throw new RuntimeException('Account not found or unchanged.');
      }
      $stmt->close();

      // Consume the token
      $stmt = $conn->prepare('UPDATE password_resets SET consumed_at = NOW() WHERE id = ?');
      $stmt->bind_param('i', $row['id']);
      $stmt->execute();
      $stmt->close();

      $conn->commit();

      // Rotate PHP session id
      session_regenerate_id(true);

      // Optional: security notification
      if ($email = get_user_email($conn, $userId)) {
        @send_notice(
          $email,
          'Your BlockIT password was changed',
          "Hello,\n\nYour BlockIT account password was just changed. ".
          "If this wasn’t you, please reset it again immediately.\n\n— BlockIT"
        );
      }

      $ok = true;
    } catch (Throwable $e) {
      $conn->rollback();
      $err = $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Password • BlockIT</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f3f4f6;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center}
.card{background:#fff;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.08);padding:28px;max-width:420px;width:100%}
h1{margin:0 0 8px;font-size:22px}
p{color:#6b7280;margin:0 0 18px}
label{display:block;margin:12px 0 6px;font-weight:600;font-size:14px}
input[type=password]{width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:10px;font-size:15px}
button{margin-top:16px;width:100%;padding:12px 16px;border:0;border-radius:10px;background:linear-gradient(135deg,#0dcaf0,#087990);color:#fff;font-weight:600;cursor:pointer}
.alert{margin-top:12px;border-radius:10px;padding:12px 14px;font-size:14px}
.alert.error{background:#fef2f2;border:1px solid #ef4444;color:#b91c1c}
.alert.ok{background:#ecfdf5;border:1px solid #10b981;color:#065f46}
.muted{color:#6b7280;font-size:14px}
</style>
</head>
<body>
  <div class="card">
    <?php if ($ok): ?>
      <h1>Password changed</h1>
      <p class="muted">Your password has been updated. You can now sign in.</p>
      <div class="alert ok">Success! <a href="/index.php">Go to Login</a></div>
    <?php else: ?>
      <h1>Reset Password</h1>
      <?php if ($err): ?>
        <div class="alert error"><?= htmlspecialchars($err, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <?php endif; ?>

      <?php if (!$err || (isset($_POST['selector']) && isset($_POST['validator']))): ?>
      <form method="POST" autocomplete="off">
        <label for="password">New password</label>
        <input type="password" id="password" name="password" required placeholder="At least 10 characters">

        <label for="password_confirm">Confirm new password</label>
        <input type="password" id="password_confirm" name="password_confirm" required placeholder="Repeat password">

        <input type="hidden" name="selector" value="<?= htmlspecialchars($selector, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="validator" value="<?= htmlspecialchars($validator, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <button type="submit">Change password</button>
      </form>
      <p class="muted">This link expires shortly and can be used once.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
</html>
