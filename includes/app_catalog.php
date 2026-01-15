<?php
/**
 * Centralized App Catalog and domain mapping utilities
 * Provides domain -> app/category mapping for analytics and blocking.
 */

if (!defined('APP_CATALOG_LOADED')) {
    define('APP_CATALOG_LOADED', true);

    // Minimal helper to extract base domain (naive eTLD+1)
    function app_base_domain($host) {
        $host = strtolower(trim($host));
        // Strip protocol and path if present
        $host = preg_replace('#^https?://#', '', $host);
        $host = explode('/', $host)[0];
        $host = preg_replace('/^www\./', '', $host);
        $parts = explode('.', $host);
        if (count($parts) <= 2) {
            return $host;
        }
        // Return last 2 labels (naive). Good enough for our catalogs.
        $last = array_slice($parts, -2);
        return implode('.', $last);
    }

    // Domain suffix to application map (use base domains as keys)
    // Keep keys lowercase and focused on canonical bases.
    $APP_DOMAIN_MAP = [
        // Social Media
        'facebook.com'   => ['app' => 'Facebook',  'category' => 'Social Media'],
        'fb.com'         => ['app' => 'Facebook',  'category' => 'Social Media'],
        'fbcdn.net'      => ['app' => 'Facebook',  'category' => 'Social Media'],
        'messenger.com'  => ['app' => 'Facebook',  'category' => 'Social Media'],
        'instagram.com'  => ['app' => 'Instagram', 'category' => 'Social Media'],
        'cdninstagram.com'=>['app' => 'Instagram', 'category' => 'Social Media'],
        'twitter.com'    => ['app' => 'Twitter/X', 'category' => 'Social Media'],
        'x.com'          => ['app' => 'Twitter/X', 'category' => 'Social Media'],
        'twimg.com'      => ['app' => 'Twitter/X', 'category' => 'Social Media'],
        't.co'           => ['app' => 'Twitter/X', 'category' => 'Social Media'],
        'tiktok.com'     => ['app' => 'TikTok',    'category' => 'Social Media'],
        'musically.com'  => ['app' => 'TikTok',    'category' => 'Social Media'],
        'musical.ly'     => ['app' => 'TikTok',    'category' => 'Social Media'],
        'tiktokcdn.com'  => ['app' => 'TikTok',    'category' => 'Social Media'],
        'snapchat.com'   => ['app' => 'Snapchat',  'category' => 'Social Media'],
        'sc-cdn.net'     => ['app' => 'Snapchat',  'category' => 'Social Media'],
        'reddit.com'     => ['app' => 'Reddit',    'category' => 'Social Media'],
        'redd.it'        => ['app' => 'Reddit',    'category' => 'Social Media'],
        'discord.com'    => ['app' => 'Discord',   'category' => 'Communication'],
        'discordapp.com' => ['app' => 'Discord',   'category' => 'Communication'],
        'discord.gg'     => ['app' => 'Discord',   'category' => 'Communication'],
        'telegram.org'   => ['app' => 'Telegram',  'category' => 'Communication'],
        't.me'           => ['app' => 'Telegram',  'category' => 'Communication'],
        'whatsapp.com'   => ['app' => 'WhatsApp',  'category' => 'Communication'],
        'whatsapp.net'   => ['app' => 'WhatsApp',  'category' => 'Communication'],

        // Entertainment / Streaming
        'youtube.com'    => ['app' => 'YouTube',   'category' => 'Entertainment'],
        'youtu.be'       => ['app' => 'YouTube',   'category' => 'Entertainment'],
        'googlevideo.com'=> ['app' => 'YouTube',   'category' => 'Entertainment'],
        'ytimg.com'      => ['app' => 'YouTube',   'category' => 'Entertainment'],
        'netflix.com'    => ['app' => 'Netflix',   'category' => 'Entertainment'],
        'nflxso.net'     => ['app' => 'Netflix',   'category' => 'Entertainment'],
        'nflxext.com'    => ['app' => 'Netflix',   'category' => 'Entertainment'],
        'nflximg.net'    => ['app' => 'Netflix',   'category' => 'Entertainment'],
        'spotify.com'    => ['app' => 'Spotify',   'category' => 'Entertainment'],
        'scdn.co'        => ['app' => 'Spotify',   'category' => 'Entertainment'],
        'spoti.fi'       => ['app' => 'Spotify',   'category' => 'Entertainment'],
        'twitch.tv'      => ['app' => 'Twitch',    'category' => 'Entertainment'],
        'ttvnw.net'      => ['app' => 'Twitch',    'category' => 'Entertainment'],
        'jtvnw.net'      => ['app' => 'Twitch',    'category' => 'Entertainment'],
        'disneyplus.com' => ['app' => 'Disney+',   'category' => 'Entertainment'],
        'disney.com'     => ['app' => 'Disney+',   'category' => 'Entertainment'],

        // E-commerce
        'amazon.com'     => ['app' => 'Amazon',    'category' => 'E-commerce'],
        'ebay.com'       => ['app' => 'eBay',      'category' => 'E-commerce'],
        'shopee.ph'      => ['app' => 'Shopee',    'category' => 'E-commerce'],
        'shopee.com'     => ['app' => 'Shopee',    'category' => 'E-commerce'],
        'lazada.com'     => ['app' => 'Lazada',    'category' => 'E-commerce'],
        'lazada.ph'      => ['app' => 'Lazada',    'category' => 'E-commerce'],
    ];

    /**
     * Map a domain/host to an application name and category from the catalog.
     * Returns [app, category] or null if unknown.
     */
    function map_domain_to_app($domain) {
        global $APP_DOMAIN_MAP;
        if (!$domain) return null;
        $base = app_base_domain($domain);
        if (isset($APP_DOMAIN_MAP[$base])) {
            return $APP_DOMAIN_MAP[$base];
        }
        // Try suffix match (handles subdomain-heavy hosts in cache)
        foreach ($APP_DOMAIN_MAP as $suffix => $info) {
            $len = strlen($suffix);
            if ($len === 0) continue;
            if (strlen($base) >= $len && substr($base, -$len) === $suffix) {
                return $info;
            }
        }
        return null;
    }
}

?>
