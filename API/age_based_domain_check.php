<?php
// Age-Based Domain Access Check API
// This endpoint checks if a domain should be blocked or allowed based on user age

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../connectMySql.php';
require_once '../includes/AgeBasedFilterEngine.php';

// Initialize the age-based filter engine
$ageFilter = new AgeBasedFilterEngine($conn);

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // GET: Check domain access for specific age
    $domain = $_GET['domain'] ?? '';
    $age = $_GET['age'] ?? '';
    
    if (empty($domain) || empty($age)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Domain and age parameters are required'
        ]);
        exit;
    }
    
    $access = $ageFilter->checkDomainAccess($domain, $age);
    
    echo json_encode([
        'success' => true,
        'domain' => $domain,
        'age' => (int)$age,
        'access' => $access,
        'message' => getAccessMessage($access, $domain, $age)
    ]);
    
} elseif ($method === 'POST') {
    // POST: Bulk check multiple domains or get filtered lists
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action'])) {
        switch ($input['action']) {
            case 'bulk_check':
                handleBulkCheck($ageFilter, $input);
                break;
                
            case 'get_blocked_domains':
                handleGetBlockedDomains($ageFilter, $input);
                break;
                
            case 'get_allowed_domains':
                handleGetAllowedDomains($ageFilter, $input);
                break;
                
            case 'export_rules':
                handleExportRules($ageFilter, $input);
                break;
                
            default:
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid action'
                ]);
        }
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Action parameter is required'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}

function getAccessMessage($access, $domain, $age) {
    switch ($access) {
        case 'blocked':
            return "Access to $domain is blocked for age $age due to age restrictions";
        case 'allowed':
            return "Access to $domain is explicitly allowed for age $age";
        case 'neutral':
            return "No specific age-based rules found for $domain (default policy applies)";
        default:
            return "Unknown access status";
    }
}

function handleBulkCheck($ageFilter, $input) {
    $domains = $input['domains'] ?? [];
    $age = $input['age'] ?? '';
    
    if (empty($domains) || empty($age)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Domains array and age are required'
        ]);
        return;
    }
    
    $results = [];
    foreach ($domains as $domain) {
        $access = $ageFilter->checkDomainAccess($domain, $age);
        $results[] = [
            'domain' => $domain,
            'access' => $access,
            'message' => getAccessMessage($access, $domain, $age)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'age' => (int)$age,
        'results' => $results
    ]);
}

function handleGetBlockedDomains($ageFilter, $input) {
    $age = $input['age'] ?? '';
    
    if (empty($age)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Age parameter is required'
        ]);
        return;
    }
    
    $blocked_domains = $ageFilter->getBlockedDomainsForAge($age);
    
    echo json_encode([
        'success' => true,
        'age' => (int)$age,
        'blocked_domains' => $blocked_domains,
        'count' => count($blocked_domains)
    ]);
}

function handleGetAllowedDomains($ageFilter, $input) {
    $age = $input['age'] ?? '';
    
    if (empty($age)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Age parameter is required'
        ]);
        return;
    }
    
    $allowed_domains = $ageFilter->getAllowedDomainsForAge($age);
    
    echo json_encode([
        'success' => true,
        'age' => (int)$age,
        'allowed_domains' => $allowed_domains,
        'count' => count($allowed_domains)
    ]);
}

function handleExportRules($ageFilter, $input) {
    $age = $input['age'] ?? null;
    $format = $input['format'] ?? 'json';
    
    $rules = $ageFilter->exportForRouter($age);
    
    if ($format === 'mikrotik') {
        // Export in MikroTik script format
        $script = generateMikroTikScript($rules, $age);
        echo json_encode([
            'success' => true,
            'format' => 'mikrotik',
            'age' => $age,
            'script' => $script,
            'rules' => $rules
        ]);
    } else {
        // Default JSON format
        echo json_encode([
            'success' => true,
            'format' => 'json',
            'age' => $age,
            'rules' => $rules
        ]);
    }
}

function generateMikroTikScript($rules, $age = null) {
    $script = "# Age-Based Content Filter Rules\n";
    $script .= "# Generated on " . date('Y-m-d H:i:s') . "\n";
    if ($age !== null) {
        $script .= "# For age: $age\n";
    }
    $script .= "\n";
    
    // Add blacklist rules
    if (!empty($rules['blacklist'])) {
        $script .= "# Blacklisted Domains\n";
        foreach ($rules['blacklist'] as $rule) {
            $comment = $age !== null ? $rule['category'] : "Ages {$rule['min_age']}-{$rule['max_age']}: {$rule['category']}";
            $script .= "/ip dns static add name={$rule['domain']} address=127.0.0.1 comment=\"$comment\"\n";
        }
        $script .= "\n";
    }
    
    // Add whitelist rules (remove from blacklist if exists)
    if (!empty($rules['whitelist'])) {
        $script .= "# Whitelisted Domains (remove blocks)\n";
        foreach ($rules['whitelist'] as $rule) {
            $comment = $age !== null ? $rule['category'] : "Min age {$rule['min_age']}: {$rule['category']}";
            $script .= "/ip dns static remove [find name=\"{$rule['domain']}\"]\n";
        }
    }
    
    return $script;
}

// Example usage in comments:
/*
GET Examples:
- /api/age_based_domain_check.php?domain=facebook.com&age=15
- /api/age_based_domain_check.php?domain=khanacademy.org&age=8

POST Examples:
{
    "action": "bulk_check",
    "domains": ["facebook.com", "youtube.com", "khanacademy.org"],
    "age": 12
}

{
    "action": "get_blocked_domains",
    "age": 15
}

{
    "action": "get_allowed_domains", 
    "age": 10
}

{
    "action": "export_rules",
    "age": 12,
    "format": "mikrotik"
}
*/
?>
