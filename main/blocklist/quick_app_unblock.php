<?php
// Quick Unblock Application Endpoint
error_reporting(0);
ini_set('display_errors', 0);
ini_set('max_execution_time', 60);
ob_start();

header('Content-Type: application/json');

require_once '../../connectMySql.php';
require_once '../../loginverification.php';
require_once '../../includes/fast_api_helper.php';

if (!logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests allowed']);
    exit;
}

$appName = trim($_POST['app_name'] ?? '');
if ($appName === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing application name']);
    exit;
}

try {
    // Find active blocks for this application (global blocks only for quick flow)
    $stmt = $conn->prepare("SELECT id, application_category, domains FROM application_blocks WHERE application_name = ? AND status = 'active' AND device_id IS NULL");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param('s', $appName);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        echo json_encode(['status' => 'info', 'message' => "$appName is not currently blocked."]);
        exit;
    }

    $domainsAll = [];
    $blockIds = [];
    while ($row = $res->fetch_assoc()) {
        $blockIds[] = intval($row['id']);
        if (!empty($row['domains'])) {
            foreach (explode(',', $row['domains']) as $d) {
                $d = trim($d);
                if ($d !== '') $domainsAll[$d] = true;
            }
        }
    }

    // Delete from application_blocks
    if (!empty($blockIds)) {
        $in = implode(',', array_fill(0, count($blockIds), '?'));
        $types = str_repeat('i', count($blockIds));
        $del = $conn->prepare("DELETE FROM application_blocks WHERE id IN ($in)");
        $del->bind_param($types, ...$blockIds);
        $del->execute();
    }

    // Remove related domains from blocklist (best-effort). This assumes those came from quick block.
    if (!empty($domainsAll)) {
        foreach (array_keys($domainsAll) as $domain) {
            $stmtDel = $conn->prepare('DELETE FROM blocklist WHERE website = ?');
            if ($stmtDel) {
                $stmtDel->bind_param('s', $domain);
                $stmtDel->execute();
            }
        }
    }

    // Propagate changes to devices (drop method)
    try {
        // Use a higher deviceLimit to ensure most devices refresh promptly after global unblock
        $updateResult = FastApiHelper::backgroundUpdateAllDevices($conn, false, 50);
        if (!$updateResult['success']) {
            // Still consider unblock ok, but report background issue
            echo json_encode(['status' => 'success', 'message' => "$appName unblocked. Router update queued: " . ($updateResult['error'] ?? 'Unknown')]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'success', 'message' => "$appName unblocked. Router update will apply shortly."]);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => "$appName unblocked and devices updated."]);
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Failed to unblock: ' . $e->getMessage()]);
}

ob_end_flush();
?>
