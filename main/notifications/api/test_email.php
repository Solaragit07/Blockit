<?php
// /main/notifications/api/test_email.php
// Browser-based email test (server-side). Requires login.

declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json');

function jexit(array $arr): void { echo json_encode($arr); exit; }

// Auto-detect app root to work across different server layouts.
// We define "app root" as the directory that contains loginverification.php.
$APP_ROOT = null;
for ($i = 0; $i <= 8; $i++) {
    $cand = dirname(__DIR__, $i);
    if (is_file($cand . '/loginverification.php')) { $APP_ROOT = $cand; break; }
}
if (!is_string($APP_ROOT)) {
    http_response_code(500);
    jexit([
        'ok' => false,
        'message' => 'Server misconfig: loginverification.php not found in parent directories',
        'hint' => 'Expected file under the web root. Ensure the app is deployed with loginverification.php.'
    ]);
}

$loginPath = $APP_ROOT . '/loginverification.php';
require_once $loginPath;

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (function_exists('logged_in') && !logged_in()) {
    http_response_code(401);
    jexit(['ok' => false, 'message' => 'Not authenticated']);
}

$svcPath = $APP_ROOT . '/includes/EmailNotificationService.php';
$cfgPath = $APP_ROOT . '/includes/EmailConfig.php';
if (!is_file($svcPath) || !is_file($cfgPath)) {
    http_response_code(500);
    jexit(['ok' => false, 'message' => 'Server misconfig: email includes missing', 'paths' => [$svcPath, $cfgPath]]);
}
require_once $svcPath;
require_once $cfgPath;

$to = trim((string)($_GET['to'] ?? ''));
if ($to !== '' && !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    jexit(['ok' => false, 'message' => 'Invalid email in ?to=']);
}

// Show minimal config state (no secrets)
$settings = EmailConfig::getSmtpSettings();
$cfg = [
    'host' => (string)($settings['host'] ?? ''),
    'port' => (int)($settings['port'] ?? 0),
    'from' => (string)($settings['from'] ?? ''),
    'fromName' => (string)($settings['fromName'] ?? ''),
    'username_set' => !empty($settings['username']),
    'password_set' => !empty($settings['password']),
];

$svc = new EmailNotificationService();
$adminEmail = (string)$svc->getAdminEmail();

$recipient = $to !== '' ? $to : $adminEmail;
if ($recipient === '') {
    jexit([
        'ok' => false,
        'message' => 'No recipient email available. Set admin email in DB (admin.user_id=1) or configure SMTP from address.',
        'smtp' => $cfg,
        'adminEmail' => $adminEmail,
    ]);
}

$ok = false;
try {
    $ok = (bool)$svc->sendTestEmail($recipient);
} catch (Throwable $e) {
    $ok = false;
}

jexit([
    'ok' => $ok,
    'message' => $ok ? 'Test email sent (check inbox/spam).' : 'Test email failed (check SMTP env vars + server outbound rules).',
    'recipient' => $recipient,
    'adminEmail' => $adminEmail,
    'smtp' => $cfg,
]);
