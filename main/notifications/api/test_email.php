<?php
// /main/notifications/api/test_email.php
// Browser-based email test (server-side). Requires login.

declare(strict_types=1);
header('Content-Type: application/json');

function jexit(array $arr): void { echo json_encode($arr); exit; }

$APP_ROOT = dirname(__DIR__, 3);

require_once $APP_ROOT . '/loginverification.php';
if (function_exists('logged_in') && !logged_in()) {
    http_response_code(401);
    jexit(['ok' => false, 'message' => 'Not authenticated']);
}

require_once $APP_ROOT . '/includes/EmailNotificationService.php';
require_once $APP_ROOT . '/includes/EmailConfig.php';

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
