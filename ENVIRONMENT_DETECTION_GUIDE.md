# Environment Detection Guide

## 🎯 How It Works

The configuration system **automatically detects** your environment and sets the correct paths. You don't need to change anything when deploying!

## 📍 Detection Logic

The system looks at:
1. **Document Root**: Where your web server's root is
2. **Script Path**: Where your application files are located
3. **HTTP Host**: The domain name being accessed
4. **Protocol**: HTTP or HTTPS

### Example Detection:

#### Localhost Development
```
Document Root: C:/xampp/htdocs/
Script Path: C:/xampp/htdocs/church/
Result: BASE_PATH = "/church"
```

#### Production (Root Domain)
```
Document Root: /var/www/html/
Script Path: /var/www/html/
Result: BASE_PATH = ""
```

#### Production (Subdirectory)
```
Document Root: /var/www/html/
Script Path: /var/www/html/myapp/
Result: BASE_PATH = "/myapp"
```

## 🚀 Deployment Steps

### Step 1: Develop on Localhost
Work on: `http://localhost/church/`

Your configuration will be:
```javascript
BASE_URL: "http://localhost/church"
BASE_PATH: "/church"
BACKEND_URL: "http://localhost/church/backend"
```

### Step 2: Deploy to Production
Upload files to: `https://cms.christembassytemanewtown.org/`

**No code changes needed!** Configuration automatically becomes:
```javascript
BASE_URL: "https://cms.christembassytemanewtown.org"
BASE_PATH: ""
BACKEND_URL: "https://cms.christembassytemanewtown.org/backend"
```

### Step 3: Verify
Visit: `https://cms.christembassytemanewtown.org/frontend/test-config.php`

Check that:
- ✅ Installation Type shows "ROOT DOMAIN"
- ✅ BACKEND_URL is correct
- ✅ API connection test passes

## 🔍 Testing Your Current Setup

### On Localhost Right Now:

1. **Open your browser console** (F12)
2. **Navigate to**: `http://localhost/church/frontend/pages/login.php`
3. **Check the console output**:
   ```javascript
   API Configuration loaded: {
       BASE_URL: "http://localhost/church",
       BASE_PATH: "/church",
       BACKEND_URL: "http://localhost/church/backend",
       API_BASE: "http://localhost/church/backend"
   }
   ```

4. **Visit test page**: `http://localhost/church/frontend/test-config.php`
   - Should show: "Installed in SUBDIRECTORY: /church"

### When You Deploy to Production:

1. **Upload all files** to your server
2. **Navigate to**: `https://cms.christembassytemanewtown.org/frontend/pages/login.php`
3. **Check the console output**:
   ```javascript
   API Configuration loaded: {
       BASE_URL: "https://cms.christembassytemanewtown.org",
       BASE_PATH: "",
       BACKEND_URL: "https://cms.christembassytemanewtown.org/backend",
       API_BASE: "https://cms.christembassytemanewtown.org/backend"
   }
   ```

4. **Visit test page**: `https://cms.christembassytemanewtown.org/frontend/test-config.php`
   - Should show: "Installed at ROOT DOMAIN"

## 📝 What Gets Detected Automatically

| Environment | URL | BASE_PATH | BACKEND_URL |
|-------------|-----|-----------|-------------|
| **Localhost** | http://localhost/church/ | /church | http://localhost/church/backend |
| **Production (Root)** | https://cms.christembassytemanewtown.org/ | "" | https://cms.christembassytemanewtown.org/backend |
| **Production (Subdirectory)** | https://example.com/myapp/ | /myapp | https://example.com/myapp/backend |

## ⚙️ How the Detection Works

The magic happens in `frontend/config/paths.php`:

```php
// Get the document root and current script path
$documentRoot = $_SERVER['DOCUMENT_ROOT'];
$scriptPath = dirname(dirname(__DIR__)); // Application root

// Calculate the base path
$basePath = str_replace($documentRoot, '', $scriptPath);
$basePath = str_replace('\\', '/', $basePath); // Normalize paths
$basePath = rtrim($basePath, '/');

// Check if at root
$isRoot = empty($basePath) || $basePath === '';

if ($isRoot) {
    define('BASE_PATH', '');
    define('BACKEND_PATH', '/backend');
} else {
    define('BASE_PATH', $basePath);
    define('BACKEND_PATH', $basePath . '/backend');
}
```

## 🎨 Benefits

✅ **Zero Configuration** - Works automatically in any environment
✅ **No Manual Changes** - Same code works everywhere
✅ **Easy Testing** - Test locally, deploy without changes
✅ **Multiple Environments** - Works in root, subdirectory, or any setup
✅ **Protocol Detection** - Automatically uses HTTP or HTTPS
✅ **Windows & Linux** - Path normalization works on both

## 🐛 Troubleshooting

### If paths are wrong on localhost:

1. Check your XAMPP/WAMP installation path
2. Verify the application is in: `C:/xampp/htdocs/church/`
3. Access via: `http://localhost/church/`
4. Visit test page to see detected paths

### If paths are wrong on production:

1. Check where files are uploaded (should be in web root)
2. Verify `.htaccess` is uploaded
3. Check file permissions (755 for directories, 644 for files)
4. Visit test page to see detected paths

### Force a specific configuration (not recommended):

If you absolutely need to override, edit `frontend/config/paths.php`:

```php
// Force specific paths (NOT RECOMMENDED - breaks auto-detection)
define('BASE_PATH', '');  // Force root
define('BACKEND_PATH', '/backend');
define('FRONTEND_PATH', '/frontend');
```

But this defeats the purpose of automatic detection!

## 📦 Deployment Checklist

When deploying to production:

- [ ] Upload all files to server
- [ ] Ensure `.htaccess` files are uploaded
- [ ] Set correct file permissions
- [ ] Update database credentials in `backend/config/.env`
- [ ] Visit test page: `/frontend/test-config.php`
- [ ] Check browser console on login page
- [ ] Test login functionality
- [ ] Verify API calls work

**That's it!** No path configuration needed.

## 🎓 Summary

**The key point**: You develop on `http://localhost/church/` and deploy to `https://cms.christembassytemanewtown.org/` **without changing any code**. The system automatically detects the environment and sets the correct paths.

This is the beauty of the automatic detection system! 🚀
