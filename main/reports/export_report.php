<?php
include_once '../../connectMySql.php';
include_once '../../loginverification.php';
include_once 'reports_functions.php';

if (!logged_in()) {
    header('location:../../index.php');
    exit;
}

// Get parameters
$dateRange = $_GET['dateRange'] ?? '7days';
$device = $_GET['device'] ?? 'all';
$reportType = $_GET['reportType'] ?? 'all';
$format = $_GET['format'] ?? 'pdf';

// Set headers for file download
$filename = 'blockit_report_' . date('Y-m-d_H-i-s');

if ($format === 'csv') {
    exportToCSV();
} else {
    exportToPDF();
}

function exportToCSV() {
    global $filename, $conn;
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Get data
    $dateCondition = getDateCondition($GLOBALS['dateRange'], 'l');
    
    // Convert device ID for filtering if needed
    $deviceCondition = '';
    if ($GLOBALS['device'] !== 'all') {
        $deviceCondition = "AND d.id = '{$GLOBALS['device']}'";
    }
    
    // Export Overview Stats
    fputcsv($output, ['BlockIT Report - ' . date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    $overview = getOverviewStats($dateCondition, $deviceCondition);
    fputcsv($output, ['OVERVIEW STATISTICS']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Blocked Attempts', $overview['totalBlocked']]);
    fputcsv($output, ['Active Devices', $overview['activeDevices']]);
    fputcsv($output, ['Data Usage (MB)', $overview['dataUsage']]);
    fputcsv($output, ['Security Alerts', $overview['securityAlerts']]);
    fputcsv($output, []);
    
    // Export Blocking Events
    fputcsv($output, ['RECENT BLOCKING EVENTS']);
    fputcsv($output, ['Time', 'Device', 'Blocked Site', 'Category']);
    
    $events = getRecentBlockingEvents($dateCondition, $deviceCondition);
    foreach ($events as $event) {
        fputcsv($output, [
            $event['time'],
            $event['device'],
            $event['blockedSite'],
            $event['category']
        ]);
    }
    
    fputcsv($output, []);
    
    // Export Top Blocked Sites
    fputcsv($output, ['TOP BLOCKED SITES']);
    fputcsv($output, ['Site', 'Attempts', 'Category', 'Last Attempt']);
    
    $topBlocked = getTopBlockedSites($dateCondition, $deviceCondition);
    foreach ($topBlocked as $site) {
        fputcsv($output, [
            $site['site'],
            $site['attempts'],
            $site['category'],
            $site['lastAttempt']
        ]);
    }
    
    fputcsv($output, []);
    
    // Export Usage Statistics
    fputcsv($output, ['DEVICE USAGE STATISTICS']);
    fputcsv($output, ['Device', 'Total Usage (MB)', 'Blocked Attempts', 'Active Hours', 'Last Activity', 'Status']);
    
    $usageStats = getDetailedUsageStats($dateCondition, $deviceCondition);
    foreach ($usageStats as $stat) {
        fputcsv($output, [
            $stat['device'],
            $stat['totalUsage'],
            $stat['blockedAttempts'],
            $stat['activeHours'],
            $stat['lastActivity'],
            $stat['status']
        ]);
    }
    
    fclose($output);
}

function exportToPDF() {
    global $filename, $conn;
    
    // For PDF export, we'll create an HTML page that can be printed to PDF
    header('Content-Type: text/html');
    
    $dateCondition = getDateCondition($GLOBALS['dateRange'], 'l');
    
    // Convert device ID for filtering if needed
    $deviceCondition = '';
    if ($GLOBALS['device'] !== 'all') {
        $deviceCondition = "AND d.id = '{$GLOBALS['device']}'";
    }
    
    $overview = getOverviewStats($dateCondition, $deviceCondition);
    $events = getRecentBlockingEvents($dateCondition, $deviceCondition);
    $topBlocked = getTopBlockedSites($dateCondition, $deviceCondition);
    $usageStats = getDetailedUsageStats($dateCondition, $deviceCondition);
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>BlockIT Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #4e73df; padding-bottom: 20px; }
            .section { margin-bottom: 30px; }
            .section h2 { color: #4e73df; border-bottom: 1px solid #dee2e6; padding-bottom: 10px; }
            .overview-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
            .overview-card { border: 1px solid #dee2e6; padding: 15px; text-align: center; border-radius: 5px; }
            .overview-card h3 { margin: 0; color: #4e73df; font-size: 24px; }
            .overview-card p { margin: 5px 0 0 0; color: #858796; font-size: 12px; text-transform: uppercase; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { border: 1px solid #dee2e6; padding: 8px; text-align: left; }
            th { background-color: #f8f9fc; font-weight: bold; }
            .badge { padding: 3px 8px; border-radius: 10px; font-size: 11px; }
            .badge-success { background-color: #d4edda; color: #155724; }
            .badge-secondary { background-color: #e2e3e5; color: #383d41; }
            @media print { 
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>BlockIT Security Report</h1>
            <p>Generated on: <?= date('F j, Y \a\t g:i A') ?></p>
            <p>Report Period: <?= ucwords(str_replace('_', ' ', $GLOBALS['dateRange'])) ?></p>
        </div>
        
        <div class="no-print" style="text-align: right; margin-bottom: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; background: #4e73df; color: white; border: none; border-radius: 5px; cursor: pointer;">
                Print Report
            </button>
        </div>
        
        <div class="section">
            <h2>Overview Statistics</h2>
            <div class="overview-grid">
                <div class="overview-card">
                    <h3><?= $overview['totalBlocked'] ?></h3>
                    <p>Total Blocked Attempts</p>
                </div>
                <div class="overview-card">
                    <h3><?= $overview['activeDevices'] ?></h3>
                    <p>Active Devices</p>
                </div>
                <div class="overview-card">
                    <h3><?= $overview['dataUsage'] ?> MB</h3>
                    <p>Data Usage</p>
                </div>
                <div class="overview-card">
                    <h3><?= $overview['securityAlerts'] ?></h3>
                    <p>Security Alerts</p>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>Recent Blocking Events</h2>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Device</th>
                        <th>Blocked Site</th>
                        <th>Category</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($events, 0, 20) as $event): ?>
                    <tr>
                        <td><?= htmlspecialchars($event['time']) ?></td>
                        <td><?= htmlspecialchars($event['device']) ?></td>
                        <td><?= htmlspecialchars($event['blockedSite']) ?></td>
                        <td><?= htmlspecialchars($event['category']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h2>Top Blocked Sites</h2>
            <table>
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Attempts</th>
                        <th>Category</th>
                        <th>Last Attempt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topBlocked as $site): ?>
                    <tr>
                        <td><?= htmlspecialchars($site['site']) ?></td>
                        <td><?= $site['attempts'] ?></td>
                        <td><?= htmlspecialchars($site['category']) ?></td>
                        <td><?= htmlspecialchars($site['lastAttempt']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h2>Device Usage Statistics</h2>
            <table>
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Total Usage (MB)</th>
                        <th>Blocked Attempts</th>
                        <th>Active Hours</th>
                        <th>Last Activity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usageStats as $stat): ?>
                    <tr>
                        <td><?= htmlspecialchars($stat['device']) ?></td>
                        <td><?= $stat['totalUsage'] ?></td>
                        <td><?= $stat['blockedAttempts'] ?></td>
                        <td><?= $stat['activeHours'] ?></td>
                        <td><?= htmlspecialchars($stat['lastActivity']) ?></td>
                        <td>
                            <span class="badge badge-<?= $stat['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($stat['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section" style="margin-top: 50px; text-align: center; color: #858796; font-size: 12px;">
            <p>This report was generated by BlockIT Security System</p>
            <p>For more information, visit your BlockIT dashboard</p>
        </div>
    </body>
    </html>
    <?php
}
?>
