# Configuration Update Summary

## ✅ Completed Updates

All PHP pages have been updated to use the new dynamic API configuration system that automatically detects whether the application is installed at the root domain or in a subdirectory.

### Pages Updated (16 total):

1. ✅ **frontend/pages/attendance.php**
2. ✅ **frontend/pages/birthdays.php**
3. ✅ **frontend/pages/cells.php**
4. ✅ **frontend/pages/create_member_users.php**
5. ✅ **frontend/pages/departments.php**
6. ✅ **frontend/pages/fellowships.php**
7. ✅ **frontend/pages/financial.php**
8. ✅ **frontend/pages/kingschat_register.php**
9. ✅ **frontend/pages/link_members.php**
10. ✅ **frontend/pages/login.php**
11. ✅ **frontend/pages/members.php**
12. ✅ **frontend/pages/permissions.php**
13. ✅ **frontend/pages/profile.php**
14. ✅ **frontend/pages/report.php**
15. ✅ **frontend/pages/settings.php**
16. ✅ **frontend/pages/users.php**

### What Was Added to Each Page:

In the `<head>` section, right after the title and before other scripts:

```php
<!-- API Configuration - MUST load first -->
<?php require_once __DIR__ . '/../includes/config.php'; outputApiConfig(); ?>
```

This line:
1. Includes the configuration helper functions
2. Outputs a dynamic JavaScript file that sets `window.API_BASE` correctly
3. Automatically detects if you're at root or in a subdirectory

## How It Works

### For Root Domain Installation
When deployed at: `https://cms.christembassytemanewtown.org/`

The system sets:
- `window.API_BASE = "https://cms.christembassytemanewtown.org/backend"`
- `window.BASE_PATH = ""`
- `window.BACKEND_URL = "https://cms.christembassytemanewtown.org/backend"`

### For Subdirectory Installation
When deployed at: `https://example.com/church/`

The system sets:
- `window.API_BASE = "https://example.com/church/backend"`
- `window.BASE_PATH = "/church"`
- `window.BACKEND_URL = "https://example.com/church/backend"`

## Testing Your Installation

### Step 1: Visit the Test Page
Navigate to: `https://cms.christembassytemanewtown.org/frontend/test-config.php`

This page will show you:
- ✅ Current PHP configuration
- ✅ Current JavaScript configuration
- ✅ Installation type (root or subdirectory)
- ✅ Test API connectivity button

### Step 2: Check Browser Console
On any page, open the browser console (F12) and type:
```javascript
console.log(window.API_BASE);
```

You should see:
```
https://cms.christembassytemanewtown.org/backend
```

### Step 3: Test Functionality
1. Try logging in
2. Navigate to Members page
3. Try loading data on Financial page
4. Check if API calls are working

## Files Created

### Configuration Files:
1. **frontend/config/paths.php** - PHP path detection and configuration
2. **frontend/js/api-config.php** - Dynamic JavaScript configuration generator
3. **frontend/includes/config.php** - Helper functions for including configuration
4. **frontend/test-config.php** - Test page to verify configuration

### Documentation Files:
1. **DEPLOYMENT_GUIDE.md** - Complete deployment and usage guide
2. **scripts/update-api-paths.md** - Migration guide for updating paths
3. **CONFIGURATION_UPDATE_SUMMARY.md** - This file

### Updated Files:
1. **frontend/js/config.js** - Improved auto-detection logic
2. **backend/payments/record_payment.php** - Fixed database column names
3. All 16 PHP pages listed above

## JavaScript Usage

In your JavaScript code, you can now use:

```javascript
// Use the global API_BASE (automatically set)
const API_BASE = window.API_BASE || '../../backend'; // Fallback for safety

// Make API calls
fetch(`${API_BASE}/members/read.php`)
    .then(response => response.json())
    .then(data => console.log(data));

// Or use helper functions
const apiUrl = window.getApiUrl('members/read.php');
fetch(apiUrl).then(/* ... */);
```

## Troubleshooting

### If API calls return 404:
1. Check `window.API_BASE` in browser console
2. Visit `/frontend/test-config.php` to see configuration
3. Verify your `.htaccess` or server configuration

### If paths are incorrect:
1. Clear browser cache
2. Hard refresh (Ctrl+F5 or Cmd+Shift+R)
3. Check that `frontend/config/paths.php` exists
4. Verify file permissions

### If you see "outputApiConfig not found":
1. Ensure `frontend/includes/config.php` exists
2. Check the require path is correct: `__DIR__ . '/../includes/config.php'`
3. Verify file permissions on the server

## Benefits

✅ **No more hardcoded paths** - Works automatically in any environment
✅ **Single deployment** - Same code works at root or subdirectory
✅ **Easy testing** - Test page shows current configuration
✅ **Automatic detection** - No manual configuration needed
✅ **Backward compatible** - Fallback to relative paths if needed

## Next Steps

1. ✅ All pages updated
2. ✅ Configuration system in place
3. ✅ Test page available
4. 🔄 **Test on your live server**: `https://cms.christembassytemanewtown.org/`
5. 🔄 **Verify all functionality works**
6. 🔄 **Check browser console for any errors**

## Support

If you encounter any issues:
1. Check the test page: `/frontend/test-config.php`
2. Review browser console for errors
3. Check `DEPLOYMENT_GUIDE.md` for detailed instructions
4. Verify all files were uploaded correctly

---

**Status**: ✅ All pages updated and ready for deployment
**Date**: 2026-04-23
**Version**: 1.0
