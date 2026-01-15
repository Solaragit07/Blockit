<?php
// Enhanced Age-Based Content Filters
include '../../connectMySql.php';
include '../../loginverification.php';
include '../../includes/fast_api_helper.php';
include '../../includes/AgeBasedFilterEngine.php';

if (!logged_in()) {
    header('location:../../index.php');
    exit;
}

// Initialize the age-based filter engine
$filter_engine = new AgeBasedFilterEngine($conn);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_to_blacklist':
                    $domain = trim($_POST['domain']);
                    $min_age = (int)$_POST['min_age'];
                    $max_age = (int)$_POST['max_age'];
                    $category = $_POST['category'];
                    $reason = $_POST['reason'] ?? '';
                    
                    if (empty($domain) || $min_age < 1 || $max_age < 1 || $min_age >= $max_age) {
                        throw new Exception('Invalid input parameters');
                    }
                    
                    if ($filter_engine->addToBlacklist($domain, $min_age, $max_age, $category, $reason)) {
                        $response['success'] = true;
                        $response['message'] = "Domain '{$domain}' added to blacklist for ages {$min_age}-{$max_age}";
                        
                        // Update router rules
                        FastApiHelper::backgroundUpdateAllDevices($conn);
                    } else {
                        throw new Exception('Failed to add domain to blacklist');
                    }
                    break;
                    
                case 'add_to_whitelist':
                    $domain = trim($_POST['domain']);
                    $min_age = (int)$_POST['min_age'];
                    $category = $_POST['category'];
                    $reason = $_POST['reason'] ?? '';
                    
                    if (empty($domain) || $min_age < 1) {
                        throw new Exception('Invalid input parameters');
                    }
                    
                    if ($filter_engine->addToWhitelist($domain, $min_age, $category, $reason)) {
                        $response['success'] = true;
                        $response['message'] = "Domain '{$domain}' added to whitelist for ages {$min_age}+";
                        
                        // Update router rules
                        FastApiHelper::backgroundUpdateAllDevices($conn);
                    } else {
                        throw new Exception('Failed to add domain to whitelist');
                    }
                    break;
                    
                case 'remove_domain':
                    $domain = trim($_POST['domain']);
                    
                    if ($filter_engine->removeDomain($domain)) {
                        $response['success'] = true;
                        $response['message'] = "Domain '{$domain}' removed from age-based filters";
                        
                        // Update router rules
                        FastApiHelper::backgroundUpdateAllDevices($conn);
                    } else {
                        throw new Exception('Failed to remove domain');
                    }
                    break;
                    
                case 'get_filtered_domains':
                    $age = (int)$_POST['age'];
                    
                    if ($age < 1 || $age > 99) {
                        throw new Exception('Invalid age');
                    }
                    
                    $allowed_domains = $filter_engine->getAllowedDomainsForAge($age);
                    $blocked_domains = $filter_engine->getBlockedDomainsForAge($age);
                    
                    $response['success'] = true;
                    $response['allowed'] = $allowed_domains;
                    $response['blocked'] = $blocked_domains;
                    $response['age'] = $age;
                    break;
                    
                case 'update_category_filter':
                    $category = $_POST['category'];
                    $from_age = (int)$_POST['from_age'];
                    $to_age = (int)$_POST['to_age'];
                    $filter_type = $_POST['filter_type']; // 'whitelist' or 'blacklist'
                    
                    // Get predefined domains for this category
                    $predefined_domains = getPredefinedDomainsForCategory($category);
                    
                    $updated_count = 0;
                    foreach ($predefined_domains as $domain) {
                        if ($filter_type === 'whitelist') {
                            if ($filter_engine->addToWhitelist($domain, $from_age, $category)) {
                                $updated_count++;
                            }
                        } else {
                            if ($filter_engine->addToBlacklist($domain, $from_age, $to_age, $category)) {
                                $updated_count++;
                            }
                        }
                    }
                    
                    if ($updated_count > 0) {
                        $response['success'] = true;
                        $response['message'] = "Updated {$updated_count} domains in {$category} category";
                        
                        // Update router rules
                        FastApiHelper::backgroundUpdateAllDevices($conn);
                    } else {
                        throw new Exception('No domains were updated');
                    }
                    break;
            }
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Function to get predefined domains for categories
function getPredefinedDomainsForCategory($category) {
    $domains = [
        'Educational' => [
            'khanacademy.org', 'coursera.org', 'edx.org', 'udemy.com', 'w3schools.com',
            'tutorialspoint.com', 'academia.edu', 'quizlet.com', 'brilliant.org',
            'futurelearn.com', 'skillshare.com', 'alison.com', 'codecademy.com',
            'open.edu', 'wikiversity.org', 'duolingo.com'
        ],
        'News & Media' => [
            'cnn.com', 'bbc.com', 'nytimes.com', 'reuters.com', 'foxnews.com',
            'aljazeera.com', 'news.yahoo.com', 'time.com', 'nbcnews.com',
            'guardian.com', 'washingtonpost.com', 'bloomberg.com'
        ],
        'Health & Wellness' => [
            'webmd.com', 'mayoclinic.org', 'healthline.com', 'medlineplus.gov',
            'who.int', 'cdc.gov', 'medicalnewstoday.com', 'nhs.uk',
            'clevelandclinic.org', 'verywellhealth.com', 'drugs.com',
            'everydayhealth.com', 'psychologytoday.com'
        ],
        'Adult Content' => [
            'pornhub.com', 'xvideos.com', 'xnxx.com', 'redtube.com', 'youporn.com',
            'brazzers.com', 'onlyfans.com', 'fansly.com', 'adultfriendfinder.com',
            'cam4.com', 'livejasmin.com', 'chaturbate.com', 'fapello.com',
            'rule34.xxx', 'tnaflix.com'
        ],
        'Gambling' => [
            'bet365.com', '1xbet.com', 'pinnacle.com', 'draftkings.com',
            'fanduel.com', '888casino.com', 'betfair.com', 'leovegas.com',
            'stake.com', 'betway.com', '22bet.com', 'ladbrokes.com',
            'williamhill.com', 'ggbet.com', 'dafabet.com'
        ],
        'Gaming' => [
            'steampowered.com', 'epicgames.com', 'roblox.com', 'minecraft.net',
            'playstation.com', 'xbox.com', 'nintendo.com', 'riotgames.com',
            'blizzard.com', 'ea.com', 'ubisoft.com', 'twitch.tv'
        ],
        'Social Media' => [
            'facebook.com', 'instagram.com', 'twitter.com', 'x.com', 'tiktok.com',
            'snapchat.com', 'linkedin.com', 'pinterest.com', 'reddit.com',
            'tumblr.com', 'discord.com', 'whatsapp.com', 'telegram.org'
        ],
        'Entertainment' => [
            'youtube.com', 'netflix.com', 'spotify.com', 'disneyplus.com',
            'primevideo.com', 'hulu.com', 'hbomax.com', 'paramountplus.com',
            'appletv.com', 'peacocktv.com'
        ]
    ];
    
    return $domains[$category] ?? [];
}

// Get statistics for display
$stats = $filter_engine->getStatistics();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Age-Based Content Filters - BlockIt</title>
    <link rel="icon" type="image/x-icon" href="../../img/logo1.png" />
    
    <!-- Custom fonts for this template-->
    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    
    <!-- Custom styles for this template-->
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../../css/custom-color-palette.css" rel="stylesheet">
    
    <style>
        /* Enhanced Dashboard Design System */
        body {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%) !important;
            min-height: 100vh;
        }

        .card {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb) !important;
            border: 1px solid #b3e5fc !important;
            border-radius: 15px !important;
            box-shadow: 0 8px 32px rgba(13, 202, 240, 0.1) !important;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(13, 202, 240, 0.15) !important;
        }

        .card-header {
            background: #b6effb !important;
            color: #0f3460 !important;
            border-bottom: 2px solid #87ceeb !important;
            border-radius: 15px 15px 0 0 !important;
        }

        .filter-panel {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 16px rgba(13, 202, 240, 0.1);
            backdrop-filter: blur(10px);
        }

        .age-selector {
            background: linear-gradient(135deg, #0dcaf0, #17a2b8);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .age-selector:hover {
            background: linear-gradient(135deg, #17a2b8, #0dcaf0);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(13, 202, 240, 0.3);
        }

        .domain-list {
            max-height: 400px;
            overflow-y: auto;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 15px;
        }

        .domain-item {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid #b3e5fc;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 8px;
            transition: all 0.2s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .domain-item:hover {
            background: rgba(183, 239, 251, 0.3);
            border-color: #0dcaf0;
            transform: translateX(5px);
        }

        .category-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            background: #0dcaf0;
            color: white;
        }

        .age-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            border-radius: 8px;
            background: #6c757d;
            color: white;
        }

        .btn-custom {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-whitelist {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white;
        }

        .btn-whitelist:hover {
            background: linear-gradient(135deg, #20c997, #28a745);
            transform: translateY(-2px);
            color: white;
        }

        .btn-blacklist {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
            border: none;
            color: white;
        }

        .btn-blacklist:hover {
            background: linear-gradient(135deg, #fd7e14, #dc3545);
            transform: translateY(-2px);
            color: white;
        }

        .stats-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(227, 242, 253, 0.9));
            border: 1px solid #b3e5fc;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 32px rgba(13, 202, 240, 0.2);
        }

        /* Fix for dropdown select visibility */
        select.form-control {
            position: relative !important;
            z-index: 1000 !important;
            background-color: white !important;
            border: 1px solid #ced4da !important;
            appearance: auto !important;
            -webkit-appearance: menulist !important;
            -moz-appearance: menulist !important;
            height: auto !important;
            min-height: 38px !important;
        }

        select.form-control option {
            background-color: white !important;
            color: #212529 !important;
            padding: 8px 12px !important;
            display: block !important;
            visibility: visible !important;
        }

        select.form-control:focus {
            border-color: #80bdff !important;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
            outline: none !important;
        }

        /* Ensure dropdown container doesn't clip */
        .filter-panel {
            overflow: visible !important;
            position: relative !important;
        }

        /* Force dropdown to show */
        #categorySelect {
            -webkit-appearance: menulist !important;
            -moz-appearance: menulist !important;
            appearance: menulist !important;
            background-image: none !important;
            background: white !important;
            border: 2px solid #007bff !important;
            font-size: 16px !important;
            padding: 10px !important;
        }

        #categorySelect option {
            background: white !important;
            color: black !important;
            padding: 10px !important;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #0dcaf0;
        }

        .filter-controls {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .form-control {
            border: 1px solid #b3e5fc;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: #0dcaf0;
            box-shadow: 0 0 0 0.2rem rgba(13, 202, 240, 0.25);
            background: rgba(255, 255, 255, 0.95);
        }

        .alert-custom {
            border: none;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .alert-info-custom {
            background: linear-gradient(135deg, rgba(13, 202, 240, 0.1), rgba(23, 162, 184, 0.1));
            color: #0f3460;
            border-left: 4px solid #0dcaf0;
        }
    </style>
</head>

<body id="page-top">
    <div id="wrapper">
        <?php include '../sidebar.php'; ?>
        
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include '../nav.php'; ?>
                
                <div class="container-fluid">
                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-filter text-primary"></i> Age-Based Content Filters
                        </h1>
                        <div class="d-flex">
                            <a href="index.php" class="btn btn-outline-secondary btn-sm mr-2">
                                <i class="fas fa-arrow-left"></i> Back to Blocklist
                            </a>
                        </div>
                    </div>

                    <!-- Statistics Row -->
                    <div class="row mb-4">
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="stats-card">
                                <div class="stats-number"><?php echo $stats['total_blacklist']; ?></div>
                                <div class="text-muted">Blacklisted Domains</div>
                                <i class="fas fa-ban fa-2x text-danger mt-2"></i>
                            </div>
                        </div>
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="stats-card">
                                <div class="stats-number"><?php echo $stats['total_whitelist']; ?></div>
                                <div class="text-muted">Whitelisted Domains</div>
                                <i class="fas fa-check-circle fa-2x text-success mt-2"></i>
                            </div>
                        </div>
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="stats-card">
                                <div class="stats-number"><?php echo $stats['categories_count']; ?></div>
                                <div class="text-muted">Active Categories</div>
                                <i class="fas fa-layer-group fa-2x text-info mt-2"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Age-Based Content Filter Interface -->
                    <div class="card shadow-sm mb-5">
                        <div class="card-body">
                            <!-- Header -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="fw-bold mb-1">
                                        <i class="fas fa-filter text-primary"></i> Age-Based Content Filters
                                    </h5>
                                    <small class="text-muted">Customize content filters based on age group</small>
                                </div>
                            </div>

                            <!-- Active Content Categories Display -->
                            <div class="mb-4">
                                <h6 class="fw-semibold mb-2">Active Content Categories</h6>
                                <div class="d-flex flex-wrap">
                                    <span class="badge bg-success text-white me-2 p-2 mr-2">
                                        <i class="fas fa-check"></i> Educational ✓
                                    </span>
                                    <span class="badge bg-success text-white me-2 p-2 mr-2">
                                        <i class="fas fa-check"></i> Health & Wellness ✓
                                    </span>
                                    <span class="badge bg-success text-white me-2 p-2 mr-2">
                                        <i class="fas fa-check"></i> News & Media ✓
                                    </span>
                                    <span class="badge bg-secondary text-white me-2 p-2 mr-2">
                                        <i class="fas fa-ban"></i> Adult Content ✗
                                    </span>
                                    <span class="badge bg-secondary text-white me-2 p-2 mr-2">
                                        <i class="fas fa-ban"></i> Gambling ✗
                                    </span>
                                </div>
                            </div>

                            <!-- Category Keyword Filters Section -->
                            <div class="mb-4">
                                <form method="post" id="categoryFilterForm">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="fw-semibold mb-0">Category Keyword Filters</h6>
                                        <div>
                                            <button type="button" class="btn btn-outline-success btn-sm me-2" onclick="toggleCategoryFilter('whitelist')">
                                                <i class="fas fa-check"></i> Whitelist
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleCategoryFilter('blacklist')">
                                                <i class="fas fa-ban"></i> Blacklist
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Category Select Dropdown -->
                                    <div class="mb-3">
                                        <label for="categorySelect" class="form-label">Select Category:</label>
                                        <select class="form-control" id="categorySelect" name="content_keyword_id" required>
                                            <option value="">---SELECT CATEGORY---</option>
                                            <option value="Educational">Educational</option>
                                            <option value="News & Media">News & Media</option>
                                            <option value="Health & Wellness">Health & Wellness</option>
                                            <option value="Adult Content">Adult Content</option>
                                            <option value="Gambling">Gambling</option>
                                            <option value="Gaming">Gaming</option>
                                            <option value="Social Media">Social Media</option>
                                            <option value="Entertainment">Entertainment</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Alternative dropdown for testing -->
                                    <div class="mb-3" style="background: yellow; padding: 10px; border-radius: 5px;">
                                        <label><strong>Test Dropdown (if above doesn't work):</strong></label><br>
                                        <select id="testCategorySelect" style="width: 100%; padding: 10px; font-size: 16px; background: white; border: 2px solid red;">
                                            <option value="">Choose Category...</option>
                                            <option value="Educational">📚 Educational</option>
                                            <option value="News & Media">📰 News & Media</option>
                                            <option value="Health & Wellness">🏥 Health & Wellness</option>
                                            <option value="Adult Content">🔞 Adult Content</option>
                                            <option value="Gambling">🎰 Gambling</option>
                                            <option value="Gaming">🎮 Gaming</option>
                                            <option value="Social Media">📱 Social Media</option>
                                            <option value="Entertainment">🎬 Entertainment</option>
                                        </select>
                                    </div>

                                    <div class="row">
                                        <div class="col-6 mb-2">
                                            <label for="from_age" class="form-label">From Age</label>
                                            <input type="number" class="form-control" id="from_age" name="from_age" min="0" placeholder="Enter minimum age" required>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <label for="to_age" class="form-label">To Age</label>
                                            <input type="number" class="form-control" id="to_age" name="to_age" min="0" placeholder="Enter maximum age" required>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Impact Preview Section -->
                            <div class="bg-light rounded p-4">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="bg-white rounded shadow-sm p-3 h-100">
                                            <h6 class="text-success fw-bold">
                                                <i class="fas fa-check-circle"></i> Currently Allowed
                                            </h6>
                                            <div style="max-height: 200px; overflow-y: auto;" id="allowedList">
                                                <ul class="list-unstyled mb-0">
                                                    <li class="mb-1">
                                                        <i class="fas fa-check-circle text-success me-1"></i>
                                                        <strong>Educational:</strong> codecademy.com
                                                    </li>
                                                    <li class="mb-1">
                                                        <i class="fas fa-check-circle text-success me-1"></i>
                                                        <strong>Educational:</strong> coursera.org
                                                    </li>
                                                    <li class="mb-1">
                                                        <i class="fas fa-check-circle text-success me-1"></i>
                                                        <strong>Educational:</strong> duolingo.com
                                                    </li>
                                                    <li class="mb-1">
                                                        <i class="fas fa-check-circle text-success me-1"></i>
                                                        <strong>Educational:</strong> edx.org
                                                    </li>
                                                    <li class="mb-1">
                                                        <i class="fas fa-check-circle text-success me-1"></i>
                                                        <strong>Educational:</strong> khanacademy.org
                                                    </li>
                                                    <li class="mb-1">
                                                        <i class="fas fa-check-circle text-success me-1"></i>
                                                        <strong>Educational:</strong> udemy.com
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="bg-white rounded shadow-sm p-3 h-100">
                                            <h6 class="text-danger fw-bold">
                                                <i class="fas fa-ban"></i> Currently Blocked
                                            </h6>
                                            <div style="max-height: 200px; overflow-y: auto;" id="blockedList">
                                                <ul class="list-unstyled mb-0">
                                                    <li class="mb-1">
                                                        <i class="fas fa-ban text-danger me-1"></i>
                                                        <strong>Adult Content:</strong> brazzers.com
                                                    </li>
                                                    <li class="mb-1">
                                                        <i class="fas fa-ban text-danger me-1"></i>
                                                        <strong>Adult Content:</strong> chaturbate.com
                                                    </li>
                                                    <li class="mb-1">
                                                        <i class="fas fa-ban text-danger me-1"></i>
                                                        <strong>Adult Content:</strong> onlyfans.com
                                                    </li>
                                                    <li class="mb-1">
                                                        <i class="fas fa-ban text-danger me-1"></i>
                                                        <strong>Adult Content:</strong> pornhub.com
                                                    </li>
                                                    <li class="mb-1">
                                                        <i class="fas fa-ban text-danger me-1"></i>
                                                        <strong>Adult Content:</strong> redtube.com
                                                    </li>
                                                    <li class="mb-1">
                                                        <i class="fas fa-ban text-danger me-1"></i>
                                                        <strong>Adult Content:</strong> youporn.com
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rules Explanation -->
                    <div class="alert alert-info-custom alert-custom mb-4">
                        <h6><i class="fas fa-info-circle"></i> Age-Based Filter Rules</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="mb-0">
                                    <li><strong>Blacklist:</strong> Blocks access for users within age range (min_age to max_age)</li>
                                    <li><strong>Whitelist:</strong> Allows access for users at or above minimum age</li>
                                    <li><strong>Priority:</strong> Blacklist rules override whitelist rules</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="mb-0">
                                    <li><strong>Dynamic:</strong> Changes apply instantly to all devices</li>
                                    <li><strong>Age Override:</strong> Age rules override category settings</li>
                                    <li><strong>Profile Based:</strong> Uses device profile age for enforcement</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Results -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold text-success">
                                        <i class="fas fa-check-circle"></i> Currently Allowed
                                    </h6>
                                    <span class="badge badge-success" id="allowedCount">0</span>
                                </div>
                                <div class="card-body">
                                    <div class="domain-list" id="allowedDomains">
                                        <div class="text-center text-muted">
                                            <i class="fas fa-search fa-2x mb-2"></i>
                                            <p>Select an age above to preview allowed domains</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold text-danger">
                                        <i class="fas fa-ban"></i> Currently Blocked
                                    </h6>
                                    <span class="badge badge-danger" id="blockedCount">0</span>
                                </div>
                                <div class="card-body">
                                    <div class="domain-list" id="blockedDomains">
                                        <div class="text-center text-muted">
                                            <i class="fas fa-search fa-2x mb-2"></i>
                                            <p>Select an age above to preview blocked domains</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include '../footer.php'; ?>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="../../vendor/jquery/jquery.min.js"></script>
    <script src="../../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../js/sb-admin-2.min.js"></script>
    <script src="../../js/sweetalert2.all.min.js"></script>
    <script src="../../js/sidebar.js"></script>

    <script>
        // Enhanced Age-Based Filter Management
        
        function toggleCategoryFilter(filterType) {
            const category = document.getElementById('categorySelect').value;
            const fromAge = document.getElementById('from_age').value;
            const toAge = document.getElementById('to_age').value;
            
            if (!category || !fromAge || !toAge) {
                Swal.fire({
                    title: 'Missing Information',
                    text: 'Please select a category and enter age range',
                    icon: 'warning'
                });
                return;
            }
            
            if (parseInt(fromAge) >= parseInt(toAge)) {
                Swal.fire({
                    title: 'Invalid Age Range',
                    text: 'To Age must be greater than From Age',
                    icon: 'warning'
                });
                return;
            }
            
            const actionText = filterType === 'whitelist' ? 'whitelist' : 'blacklist';
            
            Swal.fire({
                title: `${actionText.charAt(0).toUpperCase() + actionText.slice(1)} Category`,
                text: `Add all ${category} domains to ${actionText} for ages ${fromAge}-${toAge}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: filterType === 'whitelist' ? '#28a745' : '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Yes, ${actionText}!`
            }).then((result) => {
                if (result.isConfirmed) {
                    updateCategoryFilter(category, fromAge, toAge, filterType);
                }
            });
        }
        
        function updateCategoryFilter(category, fromAge, toAge, filterType) {
            Swal.fire({
                title: 'Updating Category Filter...',
                text: `Processing ${category} domains...`,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            const formData = new FormData();
            formData.append('action', 'update_category_filter');
            formData.append('category', category);
            formData.append('from_age', fromAge);
            formData.append('to_age', toAge);
            formData.append('filter_type', filterType);
            
            fetch('age_based_filters.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success'
                    });
                    
                    // Clear form
                    document.getElementById('categoryFilterForm').reset();
                    
                    // Update display
                    updateAgeFilters(12); // Default age preview
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message,
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to update category filter: ' + error.message,
                    icon: 'error'
                });
            });
        }
        
        function updateAgeFilters(age = null) {
            if (!age) {
                age = 12; // Default age for preview
            }
            
            // Show loading
            document.getElementById('allowedList').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            document.getElementById('blockedList').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            
            const formData = new FormData();
            formData.append('action', 'get_filtered_domains');
            formData.append('age', age);
            
            fetch('age_based_filters.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayFilteredDomains(data.allowed, 'allowedList', 'allowed');
                    displayFilteredDomains(data.blocked, 'blockedList', 'blocked');
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('allowedList').innerHTML = '<div class="text-danger">Error loading data</div>';
                document.getElementById('blockedList').innerHTML = '<div class="text-danger">Error loading data</div>';
            });
        }
        
        function displayFilteredDomains(domains, containerId, type) {
            const container = document.getElementById(containerId);
            
            if (domains.length === 0) {
                container.innerHTML = `
                    <ul class="list-unstyled mb-0">
                        <li class="text-center text-muted">No ${type} domains for this age</li>
                    </ul>
                `;
                return;
            }
            
            let html = '<ul class="list-unstyled mb-0">';
            domains.forEach(domain => {
                const iconClass = type === 'allowed' ? 'fas fa-check-circle text-success' : 'fas fa-ban text-danger';
                const ageInfo = type === 'blocked' ? 
                    ` (${domain.min_age}-${domain.max_age})` : 
                    ` (${domain.min_age}+)`;
                
                html += `
                    <li class="mb-1 d-flex justify-content-between align-items-center">
                        <span>
                            <i class="${iconClass} me-1"></i>
                            <strong>${domain.category}:</strong> ${domain.domain}${ageInfo}
                        </span>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeDomainFromFilter('${domain.domain}')" title="Remove">
                            <i class="fas fa-times"></i>
                        </button>
                    </li>
                `;
            });
            html += '</ul>';
            
            container.innerHTML = html;
        }
        
        function removeDomainFromFilter(domain) {
            Swal.fire({
                title: 'Remove Domain?',
                text: `Remove "${domain}" from age-based filters?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, remove it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'remove_domain');
                    formData.append('domain', domain);
                    
                    fetch('age_based_filters.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Removed!',
                                text: data.message,
                                icon: 'success'
                            });
                            
                            // Refresh display
                            updateAgeFilters(12);
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.message,
                                icon: 'error'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to remove domain: ' + error.message,
                            icon: 'error'
                        });
                    });
                }
            });
        }
        
        // Initialize display on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateAgeFilters(12); // Default preview for 12-year-olds
            
            // Fix dropdown visibility issue
            const categorySelect = document.getElementById('categorySelect');
            if (categorySelect) {
                console.log('Dropdown found, ensuring visibility...');
                
                // Force dropdown to be visible
                categorySelect.style.zIndex = '9999';
                categorySelect.style.position = 'relative';
                categorySelect.style.backgroundColor = 'white';
                categorySelect.style.border = '2px solid #007bff';
                
                // Test dropdown functionality
                categorySelect.addEventListener('click', function() {
                    console.log('Dropdown clicked, options should be visible');
                });
                
                categorySelect.addEventListener('change', function() {
                    console.log('Category selected:', this.value);
                });
                
                // Ensure all options are visible
                const options = categorySelect.querySelectorAll('option');
                options.forEach((option, index) => {
                    option.style.backgroundColor = 'white';
                    option.style.color = 'black';
                    option.style.display = 'block';
                    console.log(`Option ${index}: ${option.value} - ${option.textContent}`);
                });
            } else {
                console.error('Category select dropdown not found!');
            }
            
            // Also handle test dropdown
            const testSelect = document.getElementById('testCategorySelect');
            if (testSelect) {
                testSelect.addEventListener('change', function() {
                    console.log('Test dropdown selected:', this.value);
                    // Copy selection to main dropdown
                    if (categorySelect) {
                        categorySelect.value = this.value;
                        console.log('Copied selection to main dropdown');
                    }
                });
            }
        });
        
        // Auto-refresh every 30 seconds to show latest data
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                updateAgeFilters(12);
            }
        }, 30000);
    </script>
</body>
</html>
