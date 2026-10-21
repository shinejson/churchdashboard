# KingsChat OAuth Setup Checklist

Quick reference guide for setting up KingsChat OAuth authentication.

## Prerequisites
- [ ] Church Management System installed and running
- [ ] HTTPS enabled on your domain (required for OAuth)
- [ ] Access to KingsChat Developer Portal
- [ ] Database access

## Setup Steps

### 1. Database Migration
- [ ] Navigate to: `https://yourdomain.com/backend/migrations/add_kingschat_fields.php`
- [ ] Verify all columns added successfully:
  - [ ] `kingschat_id` column
  - [ ] `kingschat_token` column
  - [ ] `email` column
  - [ ] `avatar` column
  - [ ] Unique index on `kingschat_id`
  - [ ] Index on `email`

### 2. KingsChat App Registration
- [ ] Go to KingsChat Developer Portal
- [ ] Create new OAuth application
- [ ] Configure application:
  - [ ] App Name: "Church Management System" (or your church name)
  - [ ] Redirect URI: `https://yourdomain.com/backend/kingschat/callback.php`
  - [ ] Scopes: `profile email`
- [ ] Copy Client ID
- [ ] Copy Client Secret

### 3. Environment Configuration
- [ ] Copy `.env.example` to `backend/config/.env` (if not already done)
- [ ] Add KingsChat credentials to `.env`:
  ```env
  KINGSCHAT_CLIENT_ID=your_client_id_here
  KINGSCHAT_CLIENT_SECRET=your_client_secret_here
  KINGSCHAT_REDIRECT_URI=https://yourdomain.com/backend/kingschat/callback.php
  ```
- [ ] Replace `yourdomain.com` with your actual domain
- [ ] Save the file

### 4. File Verification
Verify these files exist:
- [ ] `backend/kingschat/config.php`
- [ ] `backend/kingschat/authorize.php`
- [ ] `backend/kingschat/callback.php`
- [ ] `backend/users/complete_kingschat_registration.php`
- [ ] `frontend/pages/kingschat_register.php`
- [ ] `frontend/pages/login.php` (updated)
- [ ] `frontend/css/login.css` (updated)

### 5. Testing

#### Test 1: Login Page
- [ ] Navigate to login page
- [ ] Verify "Continue with KingsChat" button appears
- [ ] Button has purple gradient styling
- [ ] Divider shows "OR" between login methods

#### Test 2: OAuth Flow (Existing User)
- [ ] Click "Continue with KingsChat"
- [ ] Redirects to KingsChat authorization page
- [ ] Authorize the application
- [ ] Redirects back to your site
- [ ] Logs in successfully
- [ ] Redirects to appropriate page (dashboard or profile)

#### Test 3: OAuth Flow (New User)
- [ ] Use a KingsChat account not in the system
- [ ] Click "Continue with KingsChat"
- [ ] Authorize the application
- [ ] Redirects to registration page
- [ ] Verify pre-filled information:
  - [ ] Name displayed
  - [ ] Email displayed
  - [ ] Avatar displayed (if available)
- [ ] Complete registration form:
  - [ ] Enter username
  - [ ] Verify first/last name
  - [ ] Select fellowship
  - [ ] Enter phone (optional)
- [ ] Submit registration
- [ ] Verify account created
- [ ] Logs in automatically
- [ ] Redirects to profile page

#### Test 4: Account Linking
- [ ] Create a user manually with an email
- [ ] Use KingsChat with the same email
- [ ] Click "Continue with KingsChat"
- [ ] Verify account links automatically
- [ ] Logs in successfully
- [ ] Check database: `kingschat_id` populated

#### Test 5: Error Handling
- [ ] Test with invalid OAuth credentials
- [ ] Verify error message displays on login page
- [ ] Test with duplicate username
- [ ] Verify appropriate error message
- [ ] Test with invalid fellowship selection
- [ ] Verify validation works

### 6. Security Verification
- [ ] HTTPS enabled and working
- [ ] Session security configured in `security.php`
- [ ] CSRF protection active
- [ ] Error logs not exposing sensitive data
- [ ] OAuth tokens stored securely
- [ ] SQL injection prevention (prepared statements)

### 7. User Experience
- [ ] Login button clearly visible
- [ ] Button styling matches theme
- [ ] Dark mode support working
- [ ] Mobile responsive design
- [ ] Error messages user-friendly
- [ ] Success messages clear
- [ ] Loading states visible
- [ ] Redirects work smoothly

## Post-Setup Tasks

### Documentation
- [ ] Share login instructions with users
- [ ] Document OAuth setup for team
- [ ] Update user manual if applicable

### Monitoring
- [ ] Check error logs regularly: `backend/logs/php_errors.log`
- [ ] Monitor OAuth success/failure rates
- [ ] Track new user registrations

### Maintenance
- [ ] Set up token refresh mechanism (future)
- [ ] Plan for OAuth token expiration handling
- [ ] Consider backup authentication method

## Troubleshooting Quick Reference

### Issue: Button not appearing
- Check `frontend/pages/login.php` updated
- Verify CSS file loaded
- Clear browser cache

### Issue: OAuth redirect fails
- Verify redirect URI matches exactly
- Check HTTPS is enabled
- Confirm KingsChat app settings

### Issue: Token exchange fails
- Verify client ID and secret
- Check `.env` file loaded correctly
- Review error logs

### Issue: Registration fails
- Run database migration
- Check required fields
- Verify fellowship exists

### Issue: Avatar not displaying
- Check image URL from KingsChat
- Verify CORS settings
- Use fallback placeholder

## Success Criteria

Your KingsChat OAuth integration is successful when:
- ✓ Users can click "Continue with KingsChat" on login page
- ✓ OAuth authorization flow completes without errors
- ✓ Existing users log in automatically
- ✓ New users complete registration smoothly
- ✓ Accounts link correctly by email
- ✓ User and member records created properly
- ✓ Permissions set up correctly
- ✓ Error messages display appropriately
- ✓ All redirects work as expected
- ✓ No security vulnerabilities present

## Next Steps After Setup

1. **User Communication**
   - Announce new login method to congregation
   - Provide instructions for first-time users
   - Offer support for account linking

2. **Feature Enhancements**
   - Consider adding profile sync
   - Implement token refresh
   - Add account unlinking option

3. **Analytics**
   - Track OAuth login usage
   - Monitor registration completion rate
   - Identify common issues

## Support Resources

- **Documentation**: `KINGSCHAT_OAUTH_GUIDE.md`
- **Error Logs**: `backend/logs/php_errors.log`
- **KingsChat Docs**: https://kingsch.at/developers
- **OAuth 2.0 Spec**: https://oauth.net/2/

---

**Setup Date**: _________________
**Completed By**: _________________
**Status**: [ ] In Progress  [ ] Complete  [ ] Issues Found

**Notes**:
_____________________________________________
_____________________________________________
_____________________________________________
