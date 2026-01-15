<?php
// Age-Based Content Filter Engine
// This file handles the core logic for age-based domain filtering

class AgeBasedFilterEngine {
    private $conn;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        $this->createTablesIfNotExist();
    }
    
    private function createTablesIfNotExist() {
        // Create age-based blacklist table
        $create_blacklist = "CREATE TABLE IF NOT EXISTS age_based_blacklist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain VARCHAR(255) NOT NULL,
            min_age INT NOT NULL,
            max_age INT NOT NULL,
            category VARCHAR(100) DEFAULT NULL,
            reason TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_domain (domain),
            INDEX idx_age_range (min_age, max_age),
            INDEX idx_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        // Create age-based whitelist table
        $create_whitelist = "CREATE TABLE IF NOT EXISTS age_based_whitelist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain VARCHAR(255) NOT NULL,
            min_age INT NOT NULL,
            category VARCHAR(100) DEFAULT NULL,
            reason TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_domain (domain),
            INDEX idx_min_age (min_age),
            INDEX idx_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->conn->query($create_blacklist);
        $this->conn->query($create_whitelist);
        
        // Populate initial data if tables are empty
        $this->populateInitialData();
    }
    
    private function populateInitialData() {
        // Check if we need to populate initial data
        $check = $this->conn->query("SELECT COUNT(*) as count FROM age_based_blacklist");
        $blacklist_count = $check->fetch_assoc()['count'];
        
        $check = $this->conn->query("SELECT COUNT(*) as count FROM age_based_whitelist");
        $whitelist_count = $check->fetch_assoc()['count'];
        
        if ($blacklist_count == 0) {
            $this->insertInitialBlacklist();
        }
        
        if ($whitelist_count == 0) {
            $this->insertInitialWhitelist();
        }
    }
    
    private function insertInitialBlacklist() {
        $blacklist_data = [
            // Adult Content - blocked for ages 1-17
            ['pornhub.com', 1, 17, 'Adult Content', 'Adult content inappropriate for minors'],
            ['xvideos.com', 1, 17, 'Adult Content', 'Adult content inappropriate for minors'],
            ['xnxx.com', 1, 17, 'Adult Content', 'Adult content inappropriate for minors'],
            ['redtube.com', 1, 17, 'Adult Content', 'Adult content inappropriate for minors'],
            ['youporn.com', 1, 17, 'Adult Content', 'Adult content inappropriate for minors'],
            ['brazzers.com', 1, 17, 'Adult Content', 'Adult content inappropriate for minors'],
            ['onlyfans.com', 1, 17, 'Adult Content', 'Adult content inappropriate for minors'],
            ['chaturbate.com', 1, 17, 'Adult Content', 'Adult content inappropriate for minors'],
            
            // Gambling - blocked for ages 1-20
            ['bet365.com', 1, 20, 'Gambling', 'Gambling sites inappropriate for minors'],
            ['1xbet.com', 1, 20, 'Gambling', 'Gambling sites inappropriate for minors'],
            ['pinnacle.com', 1, 20, 'Gambling', 'Gambling sites inappropriate for minors'],
            ['draftkings.com', 1, 20, 'Gambling', 'Gambling sites inappropriate for minors'],
            ['fanduel.com', 1, 20, 'Gambling', 'Gambling sites inappropriate for minors'],
            ['888casino.com', 1, 20, 'Gambling', 'Gambling sites inappropriate for minors'],
            ['betfair.com', 1, 20, 'Gambling', 'Gambling sites inappropriate for minors'],
            
            // Social Media - limited access for young children
            ['facebook.com', 1, 12, 'Social Media', 'Social media platform with age restrictions'],
            ['instagram.com', 1, 12, 'Social Media', 'Social media platform with age restrictions'],
            ['tiktok.com', 1, 12, 'Social Media', 'Social media platform with age restrictions'],
            ['snapchat.com', 1, 12, 'Social Media', 'Social media platform with age restrictions'],
        ];
        
        foreach ($blacklist_data as $item) {
            $domain = mysqli_real_escape_string($this->conn, $item[0]);
            $min_age = $item[1];
            $max_age = $item[2];
            $category = mysqli_real_escape_string($this->conn, $item[3]);
            $reason = mysqli_real_escape_string($this->conn, $item[4]);
            
            $insert = "INSERT INTO age_based_blacklist (domain, min_age, max_age, category, reason) 
                      VALUES ('$domain', $min_age, $max_age, '$category', '$reason')";
            $this->conn->query($insert);
        }
    }
    
    private function insertInitialWhitelist() {
        $whitelist_data = [
            // Educational sites - accessible from young ages
            ['khanacademy.org', 5, 'Educational', 'Free educational content for all ages'],
            ['codecademy.com', 10, 'Educational', 'Programming education platform'],
            ['coursera.org', 13, 'Educational', 'Online university courses'],
            ['edx.org', 13, 'Educational', 'University-level courses'],
            ['duolingo.com', 8, 'Educational', 'Language learning platform'],
            ['udemy.com', 13, 'Educational', 'Skill development courses'],
            ['w3schools.com', 10, 'Educational', 'Web development tutorials'],
            
            // News & Media - age-appropriate access
            ['bbc.com', 12, 'News & Media', 'Reliable international news source'],
            ['cnn.com', 12, 'News & Media', 'International news coverage'],
            ['reuters.com', 14, 'News & Media', 'Professional news agency'],
            
            // Health & Wellness
            ['webmd.com', 16, 'Health & Wellness', 'Medical information resource'],
            ['mayoclinic.org', 16, 'Health & Wellness', 'Medical information from Mayo Clinic'],
            ['healthline.com', 16, 'Health & Wellness', 'Health information and advice'],
            
            // Essential services
            ['google.com', 5, 'Search Engine', 'Primary search engine'],
            ['wikipedia.org', 8, 'Educational', 'Educational encyclopedia'],
            ['youtube.com', 13, 'Entertainment', 'Video platform with parental controls'],
        ];
        
        foreach ($whitelist_data as $item) {
            $domain = mysqli_real_escape_string($this->conn, $item[0]);
            $min_age = $item[1];
            $category = mysqli_real_escape_string($this->conn, $item[2]);
            $reason = mysqli_real_escape_string($this->conn, $item[3]);
            
            $insert = "INSERT INTO age_based_whitelist (domain, min_age, category, reason) 
                      VALUES ('$domain', $min_age, '$category', '$reason')";
            $this->conn->query($insert);
        }
    }
    
    /**
     * Check if a domain should be blocked for a specific age
     * Returns: 'blocked', 'allowed', or 'neutral'
     */
    public function checkDomainAccess($domain, $user_age) {
        $domain = strtolower(trim($domain));
        $user_age = (int)$user_age;
        
        // First check blacklist (higher priority)
        $blacklist_query = "SELECT * FROM age_based_blacklist 
                           WHERE LOWER(domain) = '$domain' 
                           AND $user_age >= min_age 
                           AND $user_age <= max_age";
        
        $blacklist_result = $this->conn->query($blacklist_query);
        
        if ($blacklist_result && $blacklist_result->num_rows > 0) {
            return 'blocked';
        }
        
        // Then check whitelist
        $whitelist_query = "SELECT * FROM age_based_whitelist 
                           WHERE LOWER(domain) = '$domain' 
                           AND $user_age >= min_age";
        
        $whitelist_result = $this->conn->query($whitelist_query);
        
        if ($whitelist_result && $whitelist_result->num_rows > 0) {
            return 'allowed';
        }
        
        // Not found in either list
        return 'neutral';
    }
    
    /**
     * Get all domains that would be blocked for a specific age
     */
    public function getBlockedDomainsForAge($age) {
        $age = (int)$age;
        $query = "SELECT domain, category, min_age, max_age, reason 
                 FROM age_based_blacklist 
                 WHERE $age >= min_age AND $age <= max_age 
                 ORDER BY category, domain";
        
        $result = $this->conn->query($query);
        $domains = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $domains[] = $row;
            }
        }
        
        return $domains;
    }
    
    /**
     * Get all domains that would be allowed for a specific age
     */
    public function getAllowedDomainsForAge($age) {
        $age = (int)$age;
        $query = "SELECT domain, category, min_age, reason 
                 FROM age_based_whitelist 
                 WHERE $age >= min_age 
                 ORDER BY category, domain";
        
        $result = $this->conn->query($query);
        $domains = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $domains[] = $row;
            }
        }
        
        return $domains;
    }
    
    /**
     * Add domain to blacklist
     */
    public function addToBlacklist($domain, $min_age, $max_age, $category, $reason = '') {
        $domain = mysqli_real_escape_string($this->conn, strtolower(trim($domain)));
        $min_age = (int)$min_age;
        $max_age = (int)$max_age;
        $category = mysqli_real_escape_string($this->conn, $category);
        $reason = mysqli_real_escape_string($this->conn, $reason);
        
        // Remove from whitelist if exists
        $this->conn->query("DELETE FROM age_based_whitelist WHERE LOWER(domain) = '$domain'");
        
        // Add to blacklist
        $insert = "INSERT INTO age_based_blacklist (domain, min_age, max_age, category, reason) 
                  VALUES ('$domain', $min_age, $max_age, '$category', '$reason')
                  ON DUPLICATE KEY UPDATE 
                  min_age = VALUES(min_age), 
                  max_age = VALUES(max_age), 
                  category = VALUES(category), 
                  reason = VALUES(reason),
                  updated_at = CURRENT_TIMESTAMP";
        
        return $this->conn->query($insert);
    }
    
    /**
     * Add domain to whitelist
     */
    public function addToWhitelist($domain, $min_age, $category, $reason = '') {
        $domain = mysqli_real_escape_string($this->conn, strtolower(trim($domain)));
        $min_age = (int)$min_age;
        $category = mysqli_real_escape_string($this->conn, $category);
        $reason = mysqli_real_escape_string($this->conn, $reason);
        
        // Remove from blacklist if exists
        $this->conn->query("DELETE FROM age_based_blacklist WHERE LOWER(domain) = '$domain'");
        
        // Add to whitelist
        $insert = "INSERT INTO age_based_whitelist (domain, min_age, category, reason) 
                  VALUES ('$domain', $min_age, '$category', '$reason')
                  ON DUPLICATE KEY UPDATE 
                  min_age = VALUES(min_age), 
                  category = VALUES(category), 
                  reason = VALUES(reason),
                  updated_at = CURRENT_TIMESTAMP";
        
        return $this->conn->query($insert);
    }
    
    /**
     * Remove domain from both lists
     */
    public function removeDomain($domain) {
        $domain = mysqli_real_escape_string($this->conn, strtolower(trim($domain)));
        
        $delete_blacklist = $this->conn->query("DELETE FROM age_based_blacklist WHERE LOWER(domain) = '$domain'");
        $delete_whitelist = $this->conn->query("DELETE FROM age_based_whitelist WHERE LOWER(domain) = '$domain'");
        
        return $delete_blacklist || $delete_whitelist;
    }
    
    /**
     * Get statistics
     */
    public function getStatistics() {
        $stats = [
            'total_blacklist' => 0,
            'total_whitelist' => 0,
            'categories' => []
        ];
        
        // Get blacklist count
        $result = $this->conn->query("SELECT COUNT(*) as count FROM age_based_blacklist");
        if ($result) {
            $stats['total_blacklist'] = $result->fetch_assoc()['count'];
        }
        
        // Get whitelist count
        $result = $this->conn->query("SELECT COUNT(*) as count FROM age_based_whitelist");
        if ($result) {
            $stats['total_whitelist'] = $result->fetch_assoc()['count'];
        }
        
        // Get categories
        $result = $this->conn->query("SELECT DISTINCT category FROM 
                                     (SELECT category FROM age_based_blacklist 
                                      UNION 
                                      SELECT category FROM age_based_whitelist) as combined
                                     WHERE category IS NOT NULL
                                     ORDER BY category");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $stats['categories'][] = $row['category'];
            }
        }
        
        return $stats;
    }
    
    /**
     * Export age-based rules for router configuration
     */
    public function exportForRouter($device_age = null) {
        $rules = [
            'blacklist' => [],
            'whitelist' => []
        ];
        
        if ($device_age !== null) {
            // Export for specific age
            $rules['blacklist'] = $this->getBlockedDomainsForAge($device_age);
            $rules['whitelist'] = $this->getAllowedDomainsForAge($device_age);
        } else {
            // Export all rules
            $blacklist_query = "SELECT * FROM age_based_blacklist ORDER BY min_age, domain";
            $result = $this->conn->query($blacklist_query);
            while ($row = $result->fetch_assoc()) {
                $rules['blacklist'][] = $row;
            }
            
            $whitelist_query = "SELECT * FROM age_based_whitelist ORDER BY min_age, domain";
            $result = $this->conn->query($whitelist_query);
            while ($row = $result->fetch_assoc()) {
                $rules['whitelist'][] = $row;
            }
        }
        
        return $rules;
    }
}

// Example usage:
/*
include 'connectMySql.php';
$filter_engine = new AgeBasedFilterEngine($conn);

// Check if a 12-year-old can access Facebook
$access = $filter_engine->checkDomainAccess('facebook.com', 12);
echo "Facebook access for 12-year-old: $access\n";

// Get all blocked domains for a 15-year-old
$blocked = $filter_engine->getBlockedDomainsForAge(15);
print_r($blocked);

// Add a new rule
$filter_engine->addToBlacklist('example-bad.com', 1, 16, 'Inappropriate Content', 'Not suitable for children');
*/
?>
