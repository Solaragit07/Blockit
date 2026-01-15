<?php
// Login entrypoint used by require_login() redirects.

declare(strict_types=1);

require_once __DIR__ . '/loginverification.php';
if (function_exists('login_redirect')) {
    login_redirect();
}

// Capture intended destination for post-login redirect
$next = (string)($_GET['next'] ?? '');
if ($next !== '') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Store raw, we will validate/sanitize on use.
    $_SESSION['login_next'] = $next;
}

require __DIR__ . '/index.php';
