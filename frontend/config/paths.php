<?php
/**
 * Path Configuration
 * Automatically detects the correct base paths for the application
 * Works for both subdirectory installations and root domain installations
 */

// Suppress any output during configuration
ob_start();

try {
    // Get the document root and current script path
    $documentRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/\\'));
    $scriptPath = str_replace('\\', '/', dirname(dirname(__DIR__))); // Go up two levels from frontend/config

    // Calculate the base path relative to document root
    $basePath = str_replace($documentRoot, '', $scriptPath);
    $basePath = rtrim($basePath, '/');

    // Determine if we're in a subdirectory or at root
    $isRoot = empty($basePath) || $basePath === '';

    // Set the base URL path
    if ($isRoot) {
        // Installed at root domain (e.g., https://cms.christembassytemanewtown.org/)
        define('BASE_PATH', '');
        define('BACKEND_PATH', '/backend');
        define('FRONTEND_PATH', '/frontend');
    } else {
        // Installed in subdirectory (e.g., https://example.com/church/)
        define('BASE_PATH', $basePath);
        define('BACKEND_PATH', $basePath . '/backend');
        define('FRONTEND_PATH', $basePath . '/frontend');
    }

    // Get the protocol (http or https)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                $_SERVER['SERVER_PORT'] == 443 || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') 
                ? 'https' : 'http';

    // Get the host
    $host = $_SERVER['HTTP_HOST'];

    // Full URLs
    define('BASE_URL', $protocol . '://' . $host . BASE_PATH);
    define('BACKEND_URL', $protocol . '://' . $host . BACKEND_PATH);
    define('FRONTEND_URL', $protocol . '://' . $host . FRONTEND_PATH);

} catch (Exception $e) {
    // If there's an error, define fallback constants
    if (!defined('BASE_PATH')) define('BASE_PATH', '');
    if (!defined('BACKEND_PATH')) define('BACKEND_PATH', '/backend');
    if (!defined('FRONTEND_PATH')) define('FRONTEND_PATH', '/frontend');
    if (!defined('BASE_URL')) define('BASE_URL', 'http://localhost');
    if (!defined('BACKEND_URL')) define('BACKEND_URL', 'http://localhost/backend');
    if (!defined('FRONTEND_URL')) define('FRONTEND_URL', 'http://localhost/frontend');
}

// Clear any output that might have occurred
ob_end_clean();

/**
 * Helper function to generate API URL
 */
function getApiUrl($endpoint = '') {
    return BACKEND_URL . ($endpoint ? '/' . ltrim($endpoint, '/') : '');
}

/**
 * Helper function to generate frontend URL
 */
function getFrontendUrl($path = '') {
    return FRONTEND_URL . ($path ? '/' . ltrim($path, '/') : '');
}

/**
 * Helper function to generate asset URL
 */
function getAssetUrl($path = '') {
    return FRONTEND_URL . '/assets/' . ltrim($path, '/');
}
