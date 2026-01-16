<?php
// Quick local test: send a Gmail SMTP email using env vars.
// Usage (PowerShell):
//   $env:BLOCKIT_SMTP_USER='your@gmail.com'
//   $env:BLOCKIT_SMTP_PASS='your_app_password'
//   $env:BLOCKIT_SMTP_FROM='your@gmail.com'  # optional
//   $env:BLOCKIT_SMTP_FROM_NAME='BlockIT System' # optional
//   php test_email_gmail.php you@recipient.com

require_once __DIR__ . '/includes/EmailConfig.php';

$to = $argv[1] ?? '';
if ($to === '') {
    echo "Usage: php test_email_gmail.php recipient@example.com\n";
    exit(2);
}

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid recipient email: {$to}\n";
    echo "Usage: php test_email_gmail.php recipient@example.com\n";
    exit(2);
}

$ok = EmailConfig::sendTestEmail($to);

echo $ok ? "OK: email sent\n" : "FAIL: email not sent (check logs/config)\n";
