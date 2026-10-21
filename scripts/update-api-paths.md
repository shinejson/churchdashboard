# Script to Update API Paths in Existing Files

## Quick Fix for All Pages

Add this snippet to your main template or at the top of each page (before any JavaScript):

```php
<?php
// Auto-configuration for API paths
if (!defined('BASE_PATH')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                $_SERVER['SERVER_PORT'] == 443 || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') 
                ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptPath = dirname(dirname($_SERVER['SCRIPT_FILENAME']));
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $basePath = str_replace($docRoot, '', $scriptPath);
    $basePath = str_replace('\\', '/', $basePath);
    $basePath = rtrim($basePath, '/');
    $backendUrl = $protocol . '://' . $host . $basePath . '/backend';
}
?>
<script>
    // Set global API_BASE before any other scripts load
    window.API_BASE = <?php echo json_encode($backendUrl); ?>;
</script>
```

## Files That Need Updating

Search for these patterns and replace:

### Pattern 1: Hardcoded relative paths
```javascript
// FIND:
const API_BASE = '../../backend';

// REPLACE WITH:
const API_BASE = window.API_BASE || '../../backend';
```

### Pattern 2: Direct API calls
```javascript
// FIND:
fetch('../../backend/members/read.php')

// REPLACE WITH:
fetch(`${window.API_BASE}/members/read.php`)
```

### Pattern 3: In PHP files
```php
// FIND:
<script src="../js/config.js"></script>

// ADD BEFORE:
<?php require_once __DIR__ . '/../includes/config.php'; outputApiConfig(); ?>
<script src="../js/config.js"></script>
```

## Files to Update (Priority Order)

1. **Login/Auth pages** - Most critical
   - frontend/pages/login.php
   - frontend/js/auth.js

2. **Main pages with API calls**
   - frontend/pages/profile.php ✅ (Already updated)
   - frontend/pages/members.php
   - frontend/pages/financial.php
   - frontend/pages/attendance.php
   - frontend/pages/settings.php

3. **Dashboard and reports**
   - frontend/js/dashboard.js
   - frontend/pages/report.php

4. **Admin pages**
   - frontend/pages/link_members.php
   - frontend/pages/create_member_users.php

## Testing Checklist

After updating:

1. ✅ Visit `/frontend/test-config.php` to verify configuration
2. ✅ Check browser console for `window.API_BASE` value
3. ✅ Test login functionality
4. ✅ Test API calls on main pages
5. ✅ Verify both root and subdirectory installations

## Automated Search & Replace (Use with caution!)

If you want to do a bulk update, use these commands:

### Linux/Mac:
```bash
# Find all files with hardcoded API_BASE
grep -r "const API_BASE = '../../backend'" frontend/

# Replace in all JavaScript files
find frontend/ -name "*.js" -type f -exec sed -i "s/const API_BASE = '..\/..\/backend'/const API_BASE = window.API_BASE || '..\/..\/backend'/g" {} \;

# Replace in all PHP files
find frontend/ -name "*.php" -type f -exec sed -i "s/const API_BASE = '..\/..\/backend'/const API_BASE = window.API_BASE || '..\/..\/backend'/g" {} \;
```

### Windows PowerShell:
```powershell
# Find all files with hardcoded API_BASE
Get-ChildItem -Path frontend\ -Recurse -Include *.js,*.php | Select-String "const API_BASE = '../../backend'"

# Replace in all files
Get-ChildItem -Path frontend\ -Recurse -Include *.js,*.php | ForEach-Object {
    (Get-Content $_.FullName) -replace "const API_BASE = '../../backend'", "const API_BASE = window.API_BASE || '../../backend'" | Set-Content $_.FullName
}
```

## Manual Update Template

For each page that makes API calls:

```javascript
// At the top of your script section
<script>
    // Use global API_BASE (set by api-config.php)
    const API_BASE = window.API_BASE || '../../backend'; // Fallback for safety
    
    // Your existing code...
    async function loadData() {
        const response = await fetch(`${API_BASE}/your/endpoint.php`);
        // ...
    }
</script>
```

## Verification

After updating, verify each page:
1. Open browser DevTools (F12)
2. Go to Console tab
3. Type: `window.API_BASE`
4. Should show: `https://cms.christembassytemanewtown.org/backend` (or your domain)
5. Test the page functionality
