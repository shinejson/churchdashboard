# Quick Deployment Reference Card

## 🎯 The Simple Truth

**You don't need to change ANYTHING when deploying!**

The system automatically detects:
- ✅ Localhost vs Production
- ✅ Root domain vs Subdirectory
- ✅ HTTP vs HTTPS
- ✅ Correct API paths

## 📍 Current Setup

### Development (Localhost)
```
URL: http://localhost/church/
Files: C:/xampp/htdocs/church/
```

### Production (Live Server)
```
URL: https://cms.christembassytemanewtown.org/
Files: /var/www/html/ (or wherever your host puts files)
```

## 🚀 Deployment Process

### Step 1: Develop Locally
```bash
# Work on your local machine
http://localhost/church/frontend/pages/login.php

# Console shows:
API_BASE: "http://localhost/church/backend"
```

### Step 2: Deploy to Production
```bash
# Upload files via FTP/SFTP/Git
# Upload to: /public_html/ or /var/www/html/

# NO CODE CHANGES NEEDED!
```

### Step 3: Test Production
```bash
# Visit your site
https://cms.christembassytemanewtown.org/frontend/pages/login.php

# Console shows:
API_BASE: "https://cms.christembassytemanewtown.org/backend"
```

## ✅ Verification Checklist

After deploying, check these:

1. **Test Configuration Page**
   ```
   https://cms.christembassytemanewtown.org/frontend/test-config.php
   ```
   Should show: "✅ Installed at ROOT DOMAIN"

2. **Browser Console**
   ```javascript
   // Should show correct production URL
   console.log(window.API_BASE);
   // "https://cms.christembassytemanewtown.org/backend"
   ```

3. **Login Test**
   - Try logging in
   - Check Network tab (F12 → Network)
   - API calls should go to: `https://cms.christembassytemanewtown.org/backend/...`

## 🔧 Files to Update on Deployment

### Files that NEED updating:
- ✅ `backend/config/.env` - Database credentials only

### Files that DON'T need updating:
- ❌ Any path configurations (automatic!)
- ❌ API URLs (automatic!)
- ❌ Frontend paths (automatic!)
- ❌ JavaScript files (automatic!)

## 📋 Deployment Methods

### Method 1: FTP/SFTP (Most Common)
```
1. Connect to your server via FileZilla/WinSCP
2. Upload entire 'church' folder contents to web root
3. Done! No configuration needed.
```

### Method 2: cPanel File Manager
```
1. Login to cPanel
2. Go to File Manager
3. Navigate to public_html
4. Upload all files
5. Extract if zipped
6. Done!
```

### Method 3: Git (Advanced)
```bash
# On server
cd /var/www/html
git clone your-repo-url .
# or
git pull origin main

# Update .env file only
nano backend/config/.env
```

## 🎨 What Happens Automatically

| Aspect | Localhost | Production | Action Required |
|--------|-----------|------------|-----------------|
| **Domain** | localhost | cms.christembassytemanewtown.org | None - Auto-detected |
| **Protocol** | http:// | https:// | None - Auto-detected |
| **Base Path** | /church | / (root) | None - Auto-detected |
| **API URL** | http://localhost/church/backend | https://cms.../backend | None - Auto-detected |
| **Database** | localhost DB | Production DB | ✅ Update .env file |

## 🔍 Quick Debug Commands

### Check current configuration:
```javascript
// In browser console
console.log({
    BASE_URL: window.BASE_URL,
    BASE_PATH: window.BASE_PATH,
    API_BASE: window.API_BASE
});
```

### Test API connectivity:
```javascript
// In browser console
fetch(window.API_BASE + '/auth/login.php', {method: 'OPTIONS'})
    .then(r => console.log('API reachable:', r.ok))
    .catch(e => console.error('API error:', e));
```

## 💡 Pro Tips

1. **Always test locally first**
   - Make sure everything works on localhost
   - Check console for errors
   - Test all major features

2. **Use the test page**
   - Visit `/frontend/test-config.php` on both environments
   - Verify configuration is correct
   - Test API connectivity

3. **Check browser console**
   - Look for the "API Configuration loaded" message
   - Verify the URLs are correct for your environment
   - Check for any JavaScript errors

4. **Database credentials**
   - Only thing you need to update manually
   - Edit `backend/config/.env` on production
   - Use production database details

## 🎯 Common Scenarios

### Scenario 1: Moving from localhost to production
```
Action: Upload files
Result: Automatically works at root domain
Changes needed: Update .env file only
```

### Scenario 2: Testing on staging subdirectory
```
URL: https://staging.example.com/test/
Action: Upload files to /test/ folder
Result: Automatically detects /test/ as base path
Changes needed: Update .env file only
```

### Scenario 3: Multiple developers
```
Dev 1: http://localhost/church/
Dev 2: http://localhost/cms/
Dev 3: http://localhost:8080/
Result: Each automatically detects their own setup
Changes needed: None!
```

## 📞 Need Help?

If something doesn't work:

1. Visit test page: `/frontend/test-config.php`
2. Check browser console for errors
3. Verify file permissions (755/644)
4. Check `.htaccess` is uploaded
5. Verify database credentials in `.env`

## 🎉 Summary

**Remember**: The system is smart! It automatically detects your environment and sets the correct paths. You just need to:

1. ✅ Develop on localhost
2. ✅ Upload files to production
3. ✅ Update database credentials
4. ✅ Test and enjoy!

**No path configuration needed!** 🚀
