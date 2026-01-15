<?php
// --- Database connection (VPS) ---
$DB_HOST = "127.0.0.1";
$DB_NAME = "mysite_db";
$DB_USER = "mysite_user";
$DB_PASS = "NewStr0ngPass!";

// Enable mysqli exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4'); // Ensure proper encoding
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die('Database connection error.'); // keep generic for security
}

// Optional logger for debug messages
if (!function_exists('log_event')) {
    function log_event($msg) {
        error_log($msg);
    }
}

// Email helper (uses PHP mail via msmtp/sendmail)
function send_notice(string $to, string $subject, string $body, string $from = 'admin@blockit.site'): bool {
    $headers = [
        "From: {$from}",
        "Reply-To: {$from}",
        "MIME-Version: 1.0",
        "Content-Type: text/plain; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit",
    ];
    $ok = @mail($to, $subject, $body, implode("\r\n", $headers));
    if (!$ok) log_event("mail() failed for {$to} / {$subject}");
    return $ok;
}
?>
