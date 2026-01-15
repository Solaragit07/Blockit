<?php

require_once __DIR__ . '/../vendor/autoload.php';
include 'connectMikrotik.php';
include 'connectMikrotik.php'; 

use RouterOS\Client;
use RouterOS\Query;

header('Content-Type: application/json');

$newEmail = $_GET['email'] ?? null;

// Validate email input
if (!$newEmail || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'status' => 'error',
        'message' => '❌ Invalid or missing email parameter in query string.'
    ]);
    exit;
}

$scriptName = 'check-blocked-access';

try {


    $query = (new Query('/system/script/print'))->where('name', $scriptName);
    $scripts = $client->query($query)->read();

    if (count($scripts) === 0) {
        echo json_encode(['status' => 'error', 'message' => '❌ Script not found.']);
        exit;
    }

    $scriptId = $scripts[0]['.id'];

$newSource = <<<EOT
:local logs [/log find where message~"BLOCKED-SITE"];
:if ([:len \$logs] > 0) do={
    :local msg "⚠️ Warning: Someone tried to visit a blocked website on your network.

Details of the attempts:
------------------------

";
    :foreach i in=\$logs do={
        :local time [/log get \$i time];
        :local message [/log get \$i message];
        :set msg (\$msg . "- Time: " . \$time . "\n  Description: " . \$message . "\n\n");
    };
    /tool e-mail send to="$newEmail" subject="Alert: Blocked Website Access Attempt Detected" body=\$msg;
    /log info "Blocked site access attempt email sent";
}
EOT;


    $updateQuery = (new Query('/system/script/set'))
        ->equal('.id', $scriptId)
        ->equal('source', $newSource);

    $client->query($updateQuery)->read();

    echo json_encode([
        'status' => 'success',
        'message' => '✅ Script updated with new email successfully.',
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => '❌ Failed to update script: ' . $e->getMessage(),
    ]);
}
