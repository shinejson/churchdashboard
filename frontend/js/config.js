// Configuration file
// Define a single global API_BASE for all frontend scripts
// Default to a relative path if not already defined (important for hosted environments)
if (!window.API_BASE) {
    // Detect if we're at root or in a subdirectory
    const pathParts = window.location.pathname.split('/').filter(p => p);
    
    // Check if 'frontend' is in the path
    const frontendIndex = pathParts.indexOf('frontend');
    
    let basePath = '';
    if (frontendIndex !== -1) {
        // We're in a frontend path, get everything before 'frontend'
        basePath = '/' + pathParts.slice(0, frontendIndex).join('/');
    } else {
        // We might be at root or in a different structure
        // Check if there's a common parent directory
        const currentPath = window.location.pathname;
        if (currentPath.includes('/pages/') || currentPath.includes('/frontend/')) {
            // Extract base path
            const match = currentPath.match(/^(.*?)\/(frontend|pages)/);
            basePath = match ? match[1] : '';
        }
    }
    
    // Remove trailing slash
    basePath = basePath.replace(/\/$/, '');
    
    // Build the API_BASE URL
    window.API_BASE = window.location.origin + basePath + '/backend';
    
    console.log('Auto-detected API_BASE:', window.API_BASE);
}

// Dev flag: if not provided on the page or env.js, default to false so cache-busting is opt-in
if (typeof window.DEV === 'undefined') {
    window.DEV = false;
}

// Check if user is already logged in
document.addEventListener('DOMContentLoaded', function () {
    if (localStorage.getItem('user_role')) {
        // Only auto-show dashboard on pages that actually contain it.
        // Otherwise this hides the current page content (e.g. financial.php) and results in a blank page.
        const hasDashboard = document.getElementById('dashboard-content');
        if (hasDashboard && typeof showDashboard === 'function') {
            showDashboard();
        }
    }
});

// Helper to read a named cookie value (used for CSRF double-submit)
window.getCookie = function (name) {
    const match = document.cookie.split('; ').find(row => row.startsWith(name + '='));
    if (!match) return '';
    return decodeURIComponent(match.split('=')[1]);
};