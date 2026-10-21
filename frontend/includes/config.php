<?php
/**
 * Frontend Configuration Include
 * Include this file at the top of your PHP pages to ensure correct path configuration
 */

// Include path configuration if not already loaded
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../config/paths.php';
}

/**
 * Output the API configuration script tag
 * Call this function in your HTML <head> section
 */
function outputApiConfig() {
    // Ensure constants are defined
    if (!defined('BASE_URL') || !defined('BASE_PATH') || !defined('BACKEND_URL') || !defined('FRONTEND_URL')) {
        // If constants not defined, output fallback
        ?>
        <script>
        // Fallback configuration
        (function() {
            var protocol = window.location.protocol;
            var host = window.location.host;
            var pathname = window.location.pathname;
            
            // Try to detect base path from current URL
            var basePath = '';
            if (pathname.includes('/church/')) {
                basePath = '/church';
            } else if (pathname.includes('/frontend/')) {
                var parts = pathname.split('/frontend/');
                basePath = parts[0];
            }
            
            window.BASE_URL = protocol + '//' + host + basePath;
            window.BASE_PATH = basePath;
            window.BACKEND_URL = protocol + '//' + host + basePath + '/backend';
            window.FRONTEND_URL = protocol + '//' + host + basePath + '/frontend';
            window.API_BASE = window.BACKEND_URL;
            
            console.log('API Configuration loaded (fallback):', {
                BASE_URL: window.BASE_URL,
                BASE_PATH: window.BASE_PATH,
                BACKEND_URL: window.BACKEND_URL,
                API_BASE: window.API_BASE
            });
        })();
        </script>
        <?php
        return;
    }
    
    // Output configuration inline
    ?>
    <script>
    /**
     * Auto-generated API Configuration
     * This configuration is dynamically generated based on your server environment
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
    </script>
    <?php
}

/**
 * Get the correct script path with cache busting
 */
function getScriptUrl($path, $cacheBust = false) {
    $url = getFrontendUrl($path);
    if ($cacheBust) {
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . FRONTEND_PATH . '/' . ltrim($path, '/');
        if (file_exists($fullPath)) {
            $mtime = filemtime($fullPath);
            $url .= '?v=' . $mtime;
        }
    }
    return $url;
}
