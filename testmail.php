<?php
$to = 'zxcdlareg@gmail.com';
$subject = 'PHP Mail Test';
$message = 'This is a test message. test test test';
$headers = 'From: admin@blockit.site';

if(mail($to, $subject, $message, $headers)) {
    echo "Mail sent successfully!";
} else {
    echo "Mail failed to send.";
}
?>
