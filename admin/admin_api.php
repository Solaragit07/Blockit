<?php
// admin/admin_api.php
// Pure JSON API for the minimal panel

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../connectMySql.php'; // must set $conn = new mysqli(...)

function json_out($arr, $code=200){
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
  case 'list_users':   list_users(); break;
  case 'upgrade':      upgrade_user(); break;
  case 'downgrade':    downgrade_user(); break;
  case 'payments':     payments_user(); break;
  case 'payments_all': payments_all(); break;
  case 'delete_user':  delete_user(); break; // <-- added
  default: json_out(['success'=>false,'message'=>'Invalid action'], 400);
}

/** List all users joined with their subscription (if any) */
function list_users(){
  global $conn;
  $sql = "
    SELECT
      a.user_id,
      a.name,
      a.email,
      COALESCE(s.plan,'free')       AS plan,
      COALESCE(s.status,'inactive') AS status,
      s.expires_at
    FROM admin a
    LEFT JOIN subscriptions s
      ON s.user_id = a.user_id
    ORDER BY a.user_id DESC
  ";
  $rows = [];
  if ($res = $conn->query($sql)) {
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $res->close();
  } else {
    json_out(['success'=>false,'message'=>'DB error: '.$conn->error], 500);
  }
  json_out(['success'=>true,'data'=>$rows]);
}

/** Upgrade: plan=premium, status=active, +30 days */
function upgrade_user(){
  global $conn;
  $user_id = (int)($_POST['user_id'] ?? 0);
  if (!$user_id) json_out(['success'=>false,'message'=>'Missing user_id'], 400);

  // Ensure admin row exists
  $chk = $conn->prepare("SELECT 1 FROM admin WHERE user_id=?");
  $chk->bind_param('i', $user_id);
  $chk->execute(); $chk->store_result();
  if (!$chk->num_rows) json_out(['success'=>false,'message'=>'User not found'], 404);
  $chk->close();

  $sql = "INSERT INTO subscriptions (user_id,plan,status,order_id,started_at,expires_at,updated_at)
          VALUES (?, 'premium', 'active', NULL, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
          ON DUPLICATE KEY UPDATE
            plan='premium',
            status='active',
            started_at=NOW(),
            expires_at=DATE_ADD(NOW(), INTERVAL 30 DAY),
            updated_at=NOW()";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $user_id);
  $ok = $stmt->execute(); $err = $stmt->error; $stmt->close();

  if (!$ok) json_out(['success'=>false,'message'=>"DB error: $err"], 500);
  json_out(['success'=>true,'message'=>'Upgraded to Premium (+30 days)']);
}

/** Downgrade: plan=free, status=inactive, expires_at=NULL, canceled_at=NOW() */
function downgrade_user(){
  global $conn;
  $user_id = (int)($_POST['user_id'] ?? 0);
  if (!$user_id) json_out(['success'=>false,'message'=>'Missing user_id'], 400);

  $sql = "INSERT INTO subscriptions (user_id,plan,status,order_id,started_at,expires_at,canceled_at,updated_at)
          VALUES (?, 'free', 'inactive', NULL, NOW(), NULL, NOW(), NOW())
          ON DUPLICATE KEY UPDATE
            plan='free',
            status='inactive',
            expires_at=NULL,
            canceled_at=NOW(),
            updated_at=NOW()";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $user_id);
  $ok = $stmt->execute(); $err = $stmt->error; $stmt->close();

  if (!$ok) json_out(['success'=>false,'message'=>"DB error: $err"], 500);
  json_out(['success'=>true,'message'=>'Downgraded to Free (inactive)']);
}

/** Payments for a single user (derived from subscriptions, price ₱499) */
function payments_user(){
  global $conn;
  $user_id = (int)($_GET['user_id'] ?? 0);
  if (!$user_id) json_out(['success'=>false,'message'=>'Missing user_id'], 400);

  $stmt = $conn->prepare("SELECT plan,status,started_at,expires_at,order_id FROM subscriptions WHERE user_id=?");
  $stmt->bind_param('i', $user_id);
  $stmt->execute();
  $stmt->bind_result($plan,$status,$started,$expires,$order);
  $rows = [];
  while ($stmt->fetch()){
    if (strtolower($plan)==='premium'){
      $rows[] = [
        'order_id'   => $order ?: ('SUB-'.$user_id),
        'amount'     => 499,
        'currency'   => 'PHP',
        'status'     => $status,
        'created_at' => $started,
        'expires_at' => $expires,
      ];
    }
  }
  $stmt->close();
  json_out(['success'=>true,'data'=>$rows]);
}

/** All “payments” (all premium subs) */
function payments_all(){
  global $conn;
  $sql = "SELECT a.user_id, a.name, a.email, s.status, s.started_at, s.expires_at, s.order_id
          FROM admin a
          JOIN subscriptions s ON s.user_id = a.user_id
          WHERE s.plan='premium'
          ORDER BY s.started_at DESC
          LIMIT 500";
  $rows = [];
  if ($res = $conn->query($sql)) {
    while ($r = $res->fetch_assoc()){
      $rows[] = [
        'user_id'    => $r['user_id'],
        'name'       => $r['name'],
        'email'      => $r['email'],
        'order_id'   => $r['order_id'] ?: ('SUB-'.$r['user_id']),
        'amount'     => 499,
        'currency'   => 'PHP',
        'status'     => $r['status'],
        'created_at' => $r['started_at'],
        'expires_at' => $r['expires_at'],
      ];
    }
    $res->close();
  } else {
    json_out(['success'=>false,'message'=>'DB error: '.$conn->error], 500);
  }
  json_out(['success'=>true,'data'=>$rows]);
}

/** NEW: Delete user (hard delete admin row + related subscriptions) */
function delete_user(){
  global $conn;

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['success'=>false,'message'=>'POST required'], 405);
  }

  $user_id = (int)($_POST['user_id'] ?? 0);
  if (!$user_id) json_out(['success'=>false,'message'=>'Missing user_id'], 400);

  // Confirm user exists
  $chk = $conn->prepare("SELECT 1 FROM admin WHERE user_id=?");
  $chk->bind_param('i', $user_id);
  $chk->execute(); $chk->store_result();
  if (!$chk->num_rows) {
    $chk->close();
    json_out(['success'=>false,'message'=>'User not found'], 404);
  }
  $chk->close();

  // Transaction: delete subscriptions then admin
  $conn->begin_transaction();
  try {
    // Optional cleanup if table exists
    if ($stmt = $conn->prepare("DELETE FROM subscriptions WHERE user_id=?")) {
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $stmt->close();
    }

    $stmt2 = $conn->prepare("DELETE FROM admin WHERE user_id=? LIMIT 1");
    $stmt2->bind_param('i', $user_id);
    $stmt2->execute();
    $affected = $stmt2->affected_rows;
    $stmt2->close();

    if ($affected < 1) {
      $conn->rollback();
      json_out(['success'=>false,'message'=>'Delete failed or already removed'], 409);
    }

    $conn->commit();
    json_out(['success'=>true,'message'=>'User deleted']);
  } catch (Throwable $e){
    $conn->rollback();
    json_out(['success'=>false,'message'=>'DB error: '.$e->getMessage()], 500);
  }
}
