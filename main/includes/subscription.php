<?php
// main/includes/subscription.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DB
require_once $_SERVER['DOCUMENT_ROOT'] . '/connectMySql.php'; // exposes $conn (mysqli)

// Defaults
$plan = 'free';
$isPremium = false;
$expiresAt = null;

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId && $conn instanceof mysqli) {
    // Treat premium as valid only if not expired
    $sql = "
        SELECT plan, expires_at
          FROM subscriptions
         WHERE user_id = ?
           AND status = 'active'
         ORDER BY updated_at DESC
         LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($dbPlan, $dbExp);
        if ($stmt->fetch()) {
            $plan = strtolower(trim($dbPlan ?: 'free'));
            $expiresAt = $dbExp;
        }
        $stmt->close();
    }
    // premium only if expiry is null or future
    if ($plan === 'premium') {
        if (!$expiresAt || strtotime($expiresAt) > time()) {
            $isPremium = true;
        }
    }
}
