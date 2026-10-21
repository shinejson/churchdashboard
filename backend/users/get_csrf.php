<?php
// backend/users/get_csrf.php
header('Content-Type: application/json');

require_once '../config/security.php';

// Allow CORS
allow_cors();

// Start secure session
secure_session_start();

// Initialize CSRF token if not exists
init_csrf();

// Get the CSRF token
$csrf_token = $_SESSION['csrf_token'] ?? '';

if (empty($csrf_token)) {
    // Generate a new one if missing
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;
}

// Return the token
echo json_encode([
    'csrf_token' => $csrf_token,
    'session_id' => session_id()
]);
?>