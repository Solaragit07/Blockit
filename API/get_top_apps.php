<?php
// Returns Top Apps aggregated from recent activity cache
header('Content-Type: application/json');
session_start();

// Lightweight auth (mirror get_recent_activity.php approach)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../connectMySql.php';
require_once '../includes/app_catalog.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username_server, $password_server);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Params
    $limit = isset($_GET['limit']) ? max(1, min(20, intval($_GET['limit']))) : 8;
    $period = $_GET['period'] ?? '1d'; // 1h, 6h, 1d, 7d
    $periodMap = [
        '1h' => '1 HOUR',
        '6h' => '6 HOUR',
        '1d' => '1 DAY',
        '7d' => '7 DAY',
        '30d'=> '30 DAY'
    ];
    $interval = $periodMap[$period] ?? '1 DAY';

    // Ensure table exists (same as in get_recent_activity.php writer)
    $pdo->exec("CREATE TABLE IF NOT EXISTS recent_activity_cache (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mac_address VARCHAR(17) NOT NULL,
        recent_sites JSON,
        recent_apps JSON,
        bandwidth_usage VARCHAR(20),
        connections INT,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_mac (mac_address),
        INDEX idx_updated (last_updated)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Fetch recent cached activity within window
    $stmt = $pdo->prepare("SELECT recent_sites, recent_apps FROM recent_activity_cache WHERE last_updated >= DATE_SUB(NOW(), INTERVAL $interval)");
    $stmt->execute();

    $appCounts = [];
    $categoryCounts = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sites = json_decode($row['recent_sites'] ?? '[]', true) ?: [];
        $apps = json_decode($row['recent_apps'] ?? '[]', true) ?: [];

        // Use explicit app names first
        foreach ($apps as $appName) {
            $key = trim($appName);
            if ($key === '') continue;
            $appCounts[$key] = ($appCounts[$key] ?? 0) + 1;
        }

        // Also infer from domains
        foreach ($sites as $site) {
            $info = map_domain_to_app($site);
            if ($info) {
                $app = $info['app'];
                $cat = $info['category'];
                $appCounts[$app] = ($appCounts[$app] ?? 0) + 1;
                $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
            }
        }
    }

    // Convert to array and sort desc
    arsort($appCounts);
    $topApps = array_slice($appCounts, 0, $limit, true);

    // Build response entries with category via catalog best-effort
    $results = [];
    foreach ($topApps as $appName => $count) {
        $category = null;
        // Try to find a category from domain map by scanning keys that map to this app
        foreach ($APP_DOMAIN_MAP as $domain => $info) {
            if ($info['app'] === $appName) { $category = $info['category']; break; }
        }
        $results[] = [
            'app' => $appName,
            'category' => $category ?: 'Unknown',
            'count' => $count
        ];
    }

    echo json_encode([
        'success' => true,
        'period' => $period,
        'limit' => $limit,
        'apps' => $results,
        'categories' => $categoryCounts
    ]);

} catch (Exception $e) {
    error_log('get_top_apps error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
