<?php
/**
 * Dynamic JavaScript Configuration
 * This PHP file generates JavaScript configuration based on the server environment
 * Include this file in your HTML pages to automatically set the correct API paths
 */

// Include the path configuration
require_once __DIR__ . '/../config/paths.php';

// Set the content type to JavaScript
header('Content-Type: application/javascript');

// Prevent caching in development
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
?>
/**
 * Auto-generated API Configuration
 * This file is dynamically generated based on your server environment
 */

// Base paths
window.BASE_URL = <?php echo json_encode(BASE_URL); ?>;
window.BASE_PATH = <?php echo json_encode(BASE_PATH); ?>;
window.BACKEND_URL = <?php echo json_encode(BACKEND_URL); ?>;
window.FRONTEND_URL = <?php echo json_encode(FRONTEND_URL); ?>;

// API Base (for backward compatibility)
window.API_BASE = window.BACKEND_URL;

// Helper function to build API URLs
window.getApiUrl = function(endpoint) {
    endpoint = endpoint || '';
    if (endpoint.startsWith('/')) {
        endpoint = endpoint.substring(1);
    }
    return window.BACKEND_URL + (endpoint ? '/' + endpoint : '');
};

// Helper function to build frontend URLs
window.getFrontendUrl = function(path) {
    path = path || '';
    if (path.startsWith('/')) {
        path = path.substring(1);
    }
    return window.FRONTEND_URL + (path ? '/' + path : '');
};

// Helper function to build asset URLs
window.getAssetUrl = function(path) {
    path = path || '';
    if (path.startsWith('/')) {
        path = path.substring(1);
    }
    return window.FRONTEND_URL + '/assets/' + path;
};

// Development mode flag
if (typeof window.DEV === 'undefined') {
    window.DEV = false;
}

console.log('API Configuration loaded:', {
    BASE_URL: window.BASE_URL,
    BASE_PATH: window.BASE_PATH,
    BACKEND_URL: window.BACKEND_URL,
    API_BASE: window.API_BASE
});
