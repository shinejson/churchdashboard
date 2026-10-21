<?php
/**
 * KingsChat OAuth Configuration
 */

// Load environment variables
if (file_exists(__DIR__ . '/../config/.env')) {
    $env = parse_ini_file(__DIR__ . '/../config/.env');
    foreach ($env as $key => $value) {
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
    }
}

// KingsChat OAuth Configuration
define('KINGSCHAT_CLIENT_ID', $_ENV['KINGSCHAT_CLIENT_ID'] ?? 'your_kingschat_client_id');
define('KINGSCHAT_CLIENT_SECRET', $_ENV['KINGSCHAT_CLIENT_SECRET'] ?? 'your_kingschat_client_secret');
define('KINGSCHAT_REDIRECT_URI', $_ENV['KINGSCHAT_REDIRECT_URI'] ?? 'https://yourdomain.com/backend/kingschat/callback.php');
define('KINGSCHAT_AUTH_URL', 'https://kingsch.at/oauth/authorize');
define('KINGSCHAT_TOKEN_URL', 'https://kingsch.at/oauth/token');
define('KINGSCHAT_API_URL', 'https://kingsch.at/api/v1');

/**
 * Get KingsChat Authorization URL
 */
function getKingsChatAuthUrl() {
    $params = [
        'client_id' => KINGSCHAT_CLIENT_ID,
        'redirect_uri' => KINGSCHAT_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'profile email'
    ];
    
    return KINGSCHAT_AUTH_URL . '?' . http_build_query($params);
}

/**
 * Exchange authorization code for access token
 */
function getKingsChatAccessToken($code) {
    $data = [
        'client_id' => KINGSCHAT_CLIENT_ID,
        'client_secret' => KINGSCHAT_CLIENT_SECRET,
        'redirect_uri' => KINGSCHAT_REDIRECT_URI,
        'code' => $code,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init(KINGSCHAT_TOKEN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return null;
}

/**
 * Get KingsChat user profile
 */
function getKingsChatUserProfile($accessToken) {
    $ch = curl_init(KINGSCHAT_API_URL . '/user');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return null;
}
