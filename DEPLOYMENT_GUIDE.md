# Deployment Guide - Path Configuration

This guide explains how the application automatically handles different deployment scenarios (root domain vs subdirectory).

## Automatic Path Detection

The application now automatically detects whether it's installed at:
- **Root domain**: `https://cms.christembassytemanewtown.org/`
- **Subdirectory**: `https://example.com/church/`

## How It Works

### PHP Configuration

The path configuration is handled by `frontend/config/paths.php` which:
1. Detects the installation directory
2. Sets constants for BASE_PATH, BACKEND_PATH, FRONTEND_PATH
3. Provides helper functions for generating URLs

### JavaScript Configuration

Two approaches are available:

#### Option 1: Dynamic PHP-Generated Config (Recommended)
Include this in your HTML `<head>` section:
```php
<?php require_once 'includes/config.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <?php outputApiConfig(); ?>
    <!-- Your other scripts -->
</head>
```

This will automatically generate the correct `window.API_BASE` based on your server environment.

#### Option 2: Auto-Detection in config.js
The `frontend/js/config.js` file automatically detects the correct API base URL by analyzing the current page path.

## Migration Steps

### For Existing Pages

1. **Add the configuration include** at the top of your PHP files:
```php
<?php
require_once __DIR__ . '/../includes/config.php';
// or adjust the path based on your file location
?>
```

2. **Use the dynamic config script** in your HTML head:
```php
<head>
    <?php outputApiConfig(); ?>
    <script src="<?php echo getScriptUrl('js/config.js'); ?>"></script>
    <!-- Other scripts -->
</head>
```

3. **Update hardcoded paths** in your JavaScript:
```javascript
// OLD - Hardcoded relative path
const API_BASE = '../../backend';

// NEW - Use the global variable
const API_BASE = window.API_BASE;
// or simply use window.API_BASE directly
```

### For New Pages

Simply include the configuration at the top:
```php
<?php
require_once __DIR__ . '/../includes/config.php';
?>
<!DOCTYPE html>
<html>
<head>
    <?php outputApiConfig(); ?>
    <script src="<?php echo getScriptUrl('js/config.js'); ?>"></script>
</head>
<body>
    <script>
        // Use window.API_BASE for all API calls
        fetch(`${window.API_BASE}/members/read.php`)
            .then(response => response.json())
            .then(data => console.log(data));
    </script>
</body>
</html>
```

## Helper Functions

### PHP Helpers

```php
// Get full API URL
$apiUrl = getApiUrl('members/read.php');
// Returns: https://cms.christembassytemanewtown.org/backend/members/read.php

// Get frontend URL
$pageUrl = getFrontendUrl('pages/members.php');
// Returns: https://cms.christembassytemanewtown.org/frontend/pages/members.php

// Get asset URL
$imageUrl = getAssetUrl('images/logo.png');
// Returns: https://cms.christembassytemanewtown.org/frontend/assets/images/logo.png
```

### JavaScript Helpers

```javascript
// Get API URL
const apiUrl = window.getApiUrl('members/read.php');
// Returns: https://cms.christembassytemanewtown.org/backend/members/read.php

// Get frontend URL
const pageUrl = window.getFrontendUrl('pages/members.php');
// Returns: https://cms.christembassytemanewtown.org/frontend/pages/members.php

// Get asset URL
const imageUrl = window.getAssetUrl('images/logo.png');
// Returns: https://cms.christembassytemanewtown.org/frontend/assets/images/logo.png
```

## Testing

### Test at Root Domain
1. Deploy to `https://cms.christembassytemanewtown.org/`
2. Open browser console and check:
```javascript
console.log(window.API_BASE);
// Should output: https://cms.christembassytemanewtown.org/backend
```

### Test in Subdirectory
1. Deploy to `https://example.com/church/`
2. Open browser console and check:
```javascript
console.log(window.API_BASE);
// Should output: https://example.com/church/backend
```

## Troubleshooting

### API calls return 404
- Check browser console for the actual URL being called
- Verify `window.API_BASE` is set correctly
- Ensure your `.htaccess` or server configuration allows the paths

### Paths still hardcoded
- Search for hardcoded paths: `const API_BASE = '../../backend'`
- Replace with: `const API_BASE = window.API_BASE`
- Or use the helper functions

### Mixed content warnings (HTTP/HTTPS)
- The configuration automatically detects HTTPS
- Check your server's `$_SERVER['HTTPS']` variable
- Verify reverse proxy headers if behind a proxy

## Quick Fix for Existing Pages

If you want a quick fix without modifying all files, add this to the top of your main layout/template:

```php
<?php
// Auto-detect and set base path
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptPath = dirname(dirname($_SERVER['SCRIPT_FILENAME']));
$docRoot = $_SERVER['DOCUMENT_ROOT'];
$basePath = str_replace($docRoot, '', $scriptPath);
$basePath = str_replace('\\', '/', $basePath);
$basePath = rtrim($basePath, '/');
$backendUrl = $protocol . '://' . $host . $basePath . '/backend';
?>
<script>
    window.API_BASE = <?php echo json_encode($backendUrl); ?>;
</script>
```

This will set the correct `window.API_BASE` for all pages that include this template.
