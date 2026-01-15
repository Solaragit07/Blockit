<?php
// Start or resume session
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

function logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Enforce login on protected pages.
 * Redirects to /login.php?next=<current-url>
 */
function require_login(): void {
    if (!logged_in()) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        $next   = urlencode("$scheme://$host$uri");
        header("Location: /login.php?next={$next}");
        exit;
    }
}

/** If already logged in and you’re on the login page, bounce to home. */
function login_redirect(): void {
    if (logged_in()) {
        header('Location: /index.php');
        exit;
    }
}
