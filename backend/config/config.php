<?php
// backend/config/config.php

// ==============================================
// LOAD ENVIRONMENT VARIABLES FROM .ENV FILE
// ==============================================
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }
            // Remove quotes if present
            $value = trim($value, '"\'');
            // Only set if not already set
            // Load directly into $_ENV or server env
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
    }
}

// ==============================================
// APPLICATION CONFIGURATION
// ==============================================

// Environment Detection
$environment = getenv('APP_ENV') ?: 'production';
define('ENVIRONMENT', $environment);

// ==============================================
// DATABASE CONFIGURATION
// ==============================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'church_management');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT', getenv('DB_PORT') ?: 3306);

// =======================s=======================
// APPLICATION SETTINGS
// ==============================================
// Dynamically determine SITE_URL if not set in .env
if (!getenv('SITE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Detect if we are in a subdirectory (like /church or /church-management)
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($scriptName));
    $baseDir = ($dir === '/') ? '' : $dir;
    
    // If we are deep in backend/config, we need to go up to get the base URL
    if (strpos($baseDir, '/backend/config') !== false) {
        $baseDir = str_replace('/backend/config', '', $baseDir);
    } elseif (strpos($baseDir, '/backend') !== false) {
        $baseDir = str_replace('/backend', '', $baseDir);
    }
    
    $dynamic_url = rtrim($protocol . $host . $baseDir, '/');
    define('SITE_URL', $dynamic_url);
} else {
    define('SITE_URL', rtrim(getenv('SITE_URL'), '/'));
}
define('SITE_NAME', 'Church Management System');
define('TIMEZONE', getenv('TIMEZONE') ?: 'UTC');
date_default_timezone_set(TIMEZONE);

// ==============================================
// SECURITY SETTINGS
// ==============================================
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'CMS_SESSION');
define('CSRF_TOKEN_EXPIRY', 1800); // 30 minutes
define('PASSWORD_BCRYPT_COST', 12);

// ==============================================
// FILE UPLOADS
// ==============================================
define('UPLOAD_MAX_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx');
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');

// ==============================================
// DEBUG & LOGGING SETTINGS
// ==============================================
define('LOG_PATH', dirname(__DIR__) . '/logs/');
define('LOG_FILE', 'app.log');
define('LOG_LEVEL', ENVIRONMENT === 'development' ? 'DEBUG' : 'ERROR');

if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . 'php_errors.log');
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . 'php_errors.log');
}

// ==============================================
// ATTENDANCE SETTINGS
// ==============================================
define('ATTENDANCE_REPORT_LIMIT', 1000);
define('DEFAULT_REPORT_DAYS', 30);

// ==============================================
// EMAIL SETTINGS
// ==============================================
define('EMAIL_HOST', getenv('EMAIL_HOST') ?: 'smtp.gmail.com');
define('EMAIL_PORT', getenv('EMAIL_PORT') ?: 587);
define('EMAIL_USERNAME', getenv('EMAIL_USERNAME') ?: '');
define('EMAIL_PASSWORD', getenv('EMAIL_PASSWORD') ?: '');
define('EMAIL_FROM', getenv('EMAIL_FROM') ?: 'noreply@churchmanagement.com');

// ==============================================
// PATH CONSTANTS
// ==============================================
define('BASE_PATH', dirname(dirname(__DIR__)));
define('APP_PATH', BASE_PATH . '/app/');
define('PUBLIC_PATH', BASE_PATH . '/public/');
define('BACKEND_PATH', BASE_PATH . '/backend/');
define('FRONTEND_PATH', BASE_PATH . '/frontend/');

// ==============================================
// CORS SETTINGS
// ==============================================
define('ALLOWED_ORIGINS', [
    'http://localhost',
    'http://localhost:3000',
    'http://localhost:8080',
    SITE_URL
]);

// ==============================================
// VERSION
// ==============================================
define('APP_VERSION', '1.0.0');
define('APP_BUILD', '2024.12.01');

// ==============================================
// CREATE REQUIRED DIRECTORIES
// ==============================================
$required_dirs = [LOG_PATH, UPLOAD_PATH];
foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ==============================================
// SESSION CONFIGURATION
// ==============================================
// Session configuration is handled in backend/config/security.php
// to ensure consistency across the application.

// ==============================================
// AUTO-LOADER (if needed)
// ==============================================
spl_autoload_register(function ($class_name) {
    $class_path = BACKEND_PATH . str_replace('\\', '/', $class_name) . '.php';
    if (file_exists($class_path)) {
        require_once $class_path;
    }
});

// ==============================================
// ERROR HANDLER
// ==============================================
set_exception_handler(function ($exception) {
    $error_data = [
        'message' => $exception->getMessage(),
        'code' => $exception->getCode(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
        'time' => date('Y-m-d H:i:s')
    ];
    
    error_log("Uncaught Exception: " . json_encode($error_data));
    
    if (ENVIRONMENT === 'development') {
        echo json_encode([
            'success' => false,
            'error' => $error_data
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'An unexpected error occurred'
        ]);
    }
    
    exit(1);
});

// ==============================================
// SHUTDOWN FUNCTION
// ==============================================
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal Error: " . json_encode($error));
    }
});
?>
