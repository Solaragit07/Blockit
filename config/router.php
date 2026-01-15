<?php
/**
 * Router + API config.
 *
 * This file is intentionally safe to commit: it prefers environment variables.
 * For production, set the env vars (recommended) or replace the defaults here.
 */

declare(strict_types=1);

$env = static function (string $key, $default = null) {
    $v = getenv($key);
    if ($v === false || $v === '') return $default;
    return $v;
};

return [
    // MikroTik RouterOS
    'host'     => (string)$env('BLOCKIT_ROUTER_HOST', '10.10.20.10'),
    'api_port' => (int)$env('BLOCKIT_ROUTER_PORT', 8729),
    'api_tls'  => filter_var($env('BLOCKIT_ROUTER_TLS', 'true'), FILTER_VALIDATE_BOOLEAN),
    'user'     => (string)$env('BLOCKIT_ROUTER_USER', 'api-dashboard'),
    'pass'     => (string)$env('BLOCKIT_ROUTER_PASS', '5NAJS4GLW3'),
    'timeout'  => (int)$env('BLOCKIT_ROUTER_TIMEOUT', 8),

    // App/API auth
    'api_key'  => (string)$env('BLOCKIT_API_KEY', ''),
];
