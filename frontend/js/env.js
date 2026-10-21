/**
 * Environment Configuration
 * Auto-detects the environment and sets the correct API_BASE URL
 * This file should be loaded BEFORE config.js
 */

(function() {
    // Dynamically determine the base path by finding the 'frontend' folder
    const pathname = window.location.pathname;
    const pathParts = pathname.split('/').filter(p => p); // Remove empty parts
    const frontendIndex = pathParts.indexOf('frontend');
    
    let basePath = '';
    if (frontendIndex > 0) {
        // Everything before 'frontend' is the base path
        basePath = '/' + pathParts.slice(0, frontendIndex).join('/');
    }
    // If frontendIndex === 0, we're at root and basePath stays empty
    
    // Get the current domain
    const protocol = window.location.protocol; // http: or https:
    const hostname = window.location.hostname;
    const port = window.location.port; // Empty string if default port, otherwise the port number
    const portString = port ? ':' + port : '';
    
    // Construct API base
    const baseUrl = `${protocol}//${hostname}${portString}${basePath}`;
    const apiBase = `${protocol}//${hostname}${portString}${basePath}/backend`;
    
    // Set global variables
    window.BASE_PATH = basePath;
    window.BASE_URL = baseUrl;
    window.BACKEND_URL = apiBase;
    window.FRONTEND_URL = `${baseUrl}/frontend`;
    window.API_BASE = apiBase;
    window.DEV = true; // Default fallback - will be overridden by env.dev.js or env.prod.js
})();
