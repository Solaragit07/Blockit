<?php
require_once '../../connectMySql.php';
require_once '../../loginverification.php';
header('Content-Type: application/json');
if (!logged_in()) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }

// ensure table exists and count
$conn->query("CREATE TABLE IF NOT EXISTS blocklist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  website VARCHAR(255) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$res = $conn->query('SELECT COUNT(*) AS c FROM blocklist');
$row = $res ? $res->fetch_assoc() : ['c'=>0];
echo json_encode(['success'=>true,'count'=>intval($row['c'])]);
<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    require_once '../../connectMySql.php';
    
    // Check if connection is successful
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Get blocklist count from different possible tables
    $blocklistCount = 0;
    
    // Check if there's a dedicated blocklist table
    $tableExists = $conn->query("SHOW TABLES LIKE 'blocklist'");
    if ($tableExists && $tableExists->num_rows > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM blocklist WHERE status = 'active' OR status IS NULL");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $blocklistCount = (int)$row['count'];
    } else {
        // Check for blocked websites in other tables
        $tables = ['blocked_sites', 'website_blocks', 'url_blocks', 'domain_blocks'];
        
        foreach ($tables as $table) {
            $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM `$table`");
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $blocklistCount += (int)$row['count'];
                break; // Use first found table
            }
        }
        
        // If no dedicated tables found, check device table for blocked devices
        if ($blocklistCount == 0) {
            $deviceCheck = $conn->query("SHOW TABLES LIKE 'device'");
            if ($deviceCheck && $deviceCheck->num_rows > 0) {
                // Check if device table has internet column for blocked devices
                $columnCheck = $conn->query("SHOW COLUMNS FROM device LIKE 'internet'");
                if ($columnCheck && $columnCheck->num_rows > 0) {
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM device WHERE internet = 'Yes' OR internet = 'blocked'");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $blocklistCount = (int)$row['count'];
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'count' => $blocklistCount,
        'message' => 'Blocklist count retrieved successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_blocklist_count.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'count' => 0,
        'message' => 'Error retrieving blocklist count: ' . $e->getMessage()
    ]);
}
?>
