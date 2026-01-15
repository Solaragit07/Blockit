<?php
// /public_html/main/account/API/account_update.php
declare(strict_types=1);

$APP_ROOT = $_SERVER['DOCUMENT_ROOT'];
require_once $APP_ROOT . '/connectMySql.php';   // provides $conn (mysqli)
require_once $APP_ROOT . '/loginverification.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'message'=>'Unauthorized']);
  exit;
}

$user_id = (int) $_SESSION['user_id'];
$in = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];

$email    = trim((string)($in['email']    ?? ''));
$name     = trim((string)($in['name']     ?? ''));
$status   = trim((string)($in['status']   ?? 'ACTIVE'));
$password = trim((string)($in['password'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Invalid email']);
  exit;
}
if (!in_array($status, ['ACTIVE','INACTIVE'], true)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Invalid status']);
  exit;
}

/* Unique email (exclude self) */
$chk = $conn->prepare('SELECT user_id FROM admin WHERE email=? AND user_id<>? LIMIT 1');
$chk->bind_param('si', $email, $user_id);
$chk->execute(); $chk->store_result();
if ($chk->num_rows > 0) {
  http_response_code(409);
  echo json_encode(['ok'=>false,'message'=>'Email already in use']);
  $chk->close(); exit;
}
$chk->close();

/* Update */
if ($password !== '') {
  if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>'Password must be at least 8 characters']);
    exit;
  }
  $hash = password_hash($password, PASSWORD_DEFAULT);
  $stmt = $conn->prepare('UPDATE admin SET email=?, name=?, status=?, password=? WHERE user_id=?');
  $stmt->bind_param('ssssi', $email, $name, $status, $hash, $user_id);
} else {
  $stmt = $conn->prepare('UPDATE admin SET email=?, name=?, status=? WHERE user_id=?');
  $stmt->bind_param('sssi', $email, $name, $status, $user_id);
}

$ok = $stmt->execute();
$err = $stmt->error;
$stmt->close();

if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Database error: '.$err]);
  exit;
}

/* Refresh session cache (optional) */
$_SESSION['email']    = $email;
$_SESSION['username'] = $name;

echo json_encode(['ok'=>true,'message'=>'Account updated successfully']);
