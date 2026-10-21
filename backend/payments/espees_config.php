<?php
/**
 * Espees Payment Gateway Configuration
 * Configure your Espees API credentials here
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

// Espees Configuration
define('ESPEES_API_KEY', $_ENV['ESPEES_API_KEY'] ?? 'your_espees_api_key');
define('ESPEES_SECRET_KEY', $_ENV['ESPEES_SECRET_KEY'] ?? 'your_espees_secret_key');
define('ESPEES_MERCHANT_ID', $_ENV['ESPEES_MERCHANT_ID'] ?? 'your_merchant_id');
define('ESPEES_BASE_URL', $_ENV['ESPEES_BASE_URL'] ?? 'https://api.espees.com/v1');
define('ESPEES_CALLBACK_URL', $_ENV['ESPEES_CALLBACK_URL'] ?? 'https://yourdomain.com/backend/payments/espees_callback.php');
define('ESPEES_SUCCESS_URL', $_ENV['ESPEES_SUCCESS_URL'] ?? 'https://yourdomain.com/profile.html?payment=success');
define('ESPEES_FAIL_URL', $_ENV['ESPEES_FAIL_URL'] ?? 'https://yourdomain.com/profile.html?payment=failed');

// Payment Configuration
define('PAYMENT_CURRENCY', 'GHS'); // Ghana Cedis
define('PAYMENT_COUNTRY', 'GH'); // Ghana

/**
 * Initialize Espees Payment
 */
function initializeEspeesPayment($data) {
    $payload = [
        'merchant_id' => ESPEES_MERCHANT_ID,
        'amount' => $data['amount'],
        'currency' => PAYMENT_CURRENCY,
        'reference' => $data['reference'],
        'customer_email' => $data['email'],
        'customer_name' => $data['name'],
        'customer_phone' => $data['phone'] ?? '',
        'callback_url' => ESPEES_CALLBACK_URL,
        'success_url' => ESPEES_SUCCESS_URL,
        'fail_url' => ESPEES_FAIL_URL,
        'metadata' => [
            'payment_type' => $data['payment_type'],
            'member_id' => $data['member_id'],
            'fellowship_id' => $data['fellowship_id'],
            'partnership_arm_id' => $data['partnership_arm_id'] ?? null
        ]
    ];
    
    $ch = curl_init(ESPEES_BASE_URL . '/payments/initialize');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . ESPEES_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return ['success' => false, 'message' => 'Payment initialization failed'];
}

/**
 * Verify Espees Payment
 */
function verifyEspeesPayment($reference) {
    $ch = curl_init(ESPEES_BASE_URL . '/payments/verify/' . $reference);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . ESPEES_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return ['success' => false, 'message' => 'Payment verification failed'];
}
