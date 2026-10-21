<?php
// backend/auth/logout.php
header('Content-Type: application/json');
require_once '../../config/security.php';
allow_cors();

// Allow CORS for logout
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

secure_session_start();

// Only require CSRF for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

echo json_encode(['message' => 'Logged out successfully']);
?>