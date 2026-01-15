<?php
// paypal/_config.php

// ⚠️ In production: set these as environment variables and DO NOT hardcode
define('PAYPAL_CLIENT_ID',     getenv('PAYPAL_CLIENT_ID')     ?: 'AcfIQ1WFhu2xb0FKtc9CH7sh5cK6dD3wy1ZNkmigvIYdcB5GlxHU3oIVM4IIJPG9QupG3_N3VUCg7_wU');
define('PAYPAL_CLIENT_SECRET', getenv('PAYPAL_CLIENT_SECRET') ?: 'ECfIQdM0qKeTvCpiA1kkVr6mhKacCKtCnKf3Yo-RAhxksf3aBoHsRnWwCFm89o6Cp-L6U1TGh1FLxNNO');

// Sandbox for testing; switch to live for production
define('PAYPAL_API_BASE', getenv('PAYPAL_API_BASE') ?: 'https://api-m.sandbox.paypal.com');

function paypal_get_access_token() {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => PAYPAL_API_BASE . '/v1/oauth2/token',
        CURLOPT_RETURNTRANSFER => true,
        // ✅ Use the constants; concatenate strings properly
        CURLOPT_USERPWD => PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 20
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception('PayPal OAuth cURL error: ' . $err);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if ($status !== 200 || empty($data['access_token'])) {
        // Surface PayPal’s error for easier debugging
        $msg = isset($data['error']) ? ($data['error'] . ': ' . ($data['error_description'] ?? '')) : $response;
        throw new Exception('PayPal OAuth failed: ' . $msg);
    }
    return $data['access_token'];
}

function paypal_post($endpoint, $payload, $bearer) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => PAYPAL_API_BASE . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $bearer
        ],
        CURLOPT_TIMEOUT => 20
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception('PayPal API cURL error: ' . $err);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, json_decode($response, true)];
}
