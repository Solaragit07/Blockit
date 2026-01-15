<?php
include '../../connectMySql.php';
include '../../loginverification.php';

header('Content-Type: application/json');

// Simple activity detection based on HTTP referrer and user agent
function detectBrowserActivity() {
    $activity = [
        'activity' => 'WEB_BROWSING',
        'details' => 'Web browsing activity',
        'icon' => 'fas fa-globe'
    ];
    
    // Check HTTP referrer for specific sites
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (strpos($referrer, 'facebook.com') !== false || strpos($referrer, 'fb.com') !== false) {
        return [
            'activity' => 'SOCIAL_MEDIA',
            'details' => 'Using Facebook',
            'icon' => 'fab fa-facebook'
        ];
    }
    
    if (strpos($referrer, 'chat.openai.com') !== false || strpos($referrer, 'chatgpt.com') !== false) {
        return [
            'activity' => 'PRODUCTIVITY',
            'details' => 'Using ChatGPT',
            'icon' => 'fas fa-robot'
        ];
    }
    
    if (strpos($referrer, 'youtube.com') !== false || strpos($referrer, 'youtu.be') !== false) {
        return [
            'activity' => 'VIDEO_STREAMING',
            'details' => 'Watching YouTube',
            'icon' => 'fab fa-youtube'
        ];
    }
    
    // Check for mobile devices
    if (strpos($userAgent, 'Mobile') !== false || strpos($userAgent, 'Android') !== false || strpos($userAgent, 'iPhone') !== false) {
        return [
            'activity' => 'COMMUNICATION',
            'details' => 'Mobile device activity',
            'icon' => 'fas fa-mobile-alt'
        ];
    }
    
    return $activity;
}

// Get current user's activity
$currentActivity = detectBrowserActivity();

// For now, simulate activity data since we can't get real network data
$response = [
    'success' => true,
    'activity_counts' => [
        $currentActivity['activity'] => 1
    ],
    'peak_activity' => ucwords(str_replace('_', ' ', strtolower($currentActivity['activity']))),
    'total_devices' => 1,
    'current_user_activity' => $currentActivity,
    'debug' => [
        'referrer' => $_SERVER['HTTP_REFERER'] ?? 'none',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'none',
        'detected_activity' => $currentActivity
    ]
];

echo json_encode($response);
?>
