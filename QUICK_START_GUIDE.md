# Quick Start Guide - Member Profile Setup

## 🚀 Getting Started

Your member profile system is now fully set up! Follow these simple steps to create member accounts.

## 📍 Where to Find Everything

### Option 1: From the Members Page (Recommended)
1. Go to **Members** in the sidebar
2. You'll see two new buttons at the top:
   - **"Create User Accounts"** - Creates login accounts for members
   - **"Link Accounts"** - Links existing users to members

### Option 2: From the Users Page
1. Go to **Users** in the sidebar
2. You'll see a new button:
   - **"Link to Members"** - Links user accounts to member records

### Option 3: Direct URLs
- Create Member Users: `frontend/pages/create_member_users.php`
- Link Accounts: `frontend/pages/link_members.php`

## 📝 Step-by-Step Setup

### Step 1: Create Member User Accounts

1. **Navigate to Members page**
2. **Click "Create User Accounts" button**
3. You'll see a list of all members without user accounts
4. For each member:
   - Username is auto-generated (firstname.lastname)
   - Default password is "password123" (you can change it)
   - Click "Create Account"
5. The account is created and automatically linked!

**What happens:**
- A new user account is created with role = "member"
- The user is automatically linked to the member record
- The member can now log in

### Step 2: Link Existing Users (If Needed)

If you already have user accounts that need to be linked to members:

1. **Navigate to Members page**
2. **Click "Link Accounts" button**
3. You'll see:
   - Summary of linked/unlinked accounts
   - List of already linked accounts
   - Side-by-side lists of unlinked users and members
4. Click "Link" next to any user or member
5. Select the corresponding member/user from the dropdown
6. Click "Link Accounts"

### Step 3: Test Member Login

1. **Log out** of your admin account
2. **Log in** with a member account:
   - Username: (the one you created, e.g., john.doe)
   - Password: (the one you set, default: password123)
3. You should be **automatically redirected** to the profile page
4. Verify the member can see:
   - ✅ Personal information
   - ✅ Fellowship, cell, and department details
   - ✅ Tithe contributions
   - ✅ Partnership contributions
   - ✅ Attendance records

## 🎯 What Members Can Do

When a member logs in, they can:
- ✅ View their complete profile
- ✅ See their fellowship and ministry involvement
- ✅ Check their financial contributions (tithe & partnerships)
- ✅ View their attendance history and statistics
- ❌ Cannot access dashboard
- ❌ Cannot access admin pages
- ❌ Cannot see other members' data

## 🔐 Security Features

- Members are automatically redirected to their profile page
- Members cannot access the dashboard or admin pages
- Each member can only see their own data
- Fellowship data isolation is enforced
- Session-based authentication with CSRF protection

## 💡 Tips

### Creating Multiple Accounts Quickly
1. Go to "Create User Accounts" page
2. The page shows all members without accounts
3. Usernames are auto-generated
4. You can use the same password for all (they can change it later)
5. Click through and create accounts quickly

### Password Best Practices
- Default password: "password123"
- Recommend members change their password after first login
- Consider implementing password reset functionality later

### Bulk Operations
- You can create accounts for multiple members at once
- Each account is created and linked automatically
- No need to manually link after creation

## 🐛 Troubleshooting

### "Profile not found" error
**Cause:** User account is not linked to a member record  
**Solution:** Use the "Link Accounts" page to connect them

### Member can access dashboard
**Cause:** User role is not set to 'member'  
**Solution:** Edit the user and change role to 'member'

### No financial data showing
**Cause:** Member record not linked to transactions  
**Solution:** Ensure tithe_transactions and partnership_contributions have correct member_id

### Button not showing on Members page
**Cause:** You may need to refresh the page  
**Solution:** Hard refresh (Ctrl+F5 or Cmd+Shift+R)

## 📊 Monitoring

### Check Setup Status
Visit: `backend/migrations/test_member_setup.php`

This shows:
- ✅ Permissions configured
- ✅ Database columns added
- ✅ Number of member users
- ✅ Linked accounts count
- ⚠️ Unlinked accounts

### Quick Status Check
Visit: `backend/migrations/check_setup.php`

Returns JSON with:
- Permissions count
- Column existence
- Member users count
- Linked users count
- Required tables status

## 🎨 Customization

### Change Default Password
Edit `frontend/pages/create_member_users.php`, line with:
```javascript
value="password123"
```

### Change Username Format
Edit `frontend/pages/create_member_users.php`, function:
```javascript
function generateUsername(member) {
    // Customize username generation here
}
```

### Customize Profile Page
Edit `frontend/pages/profile.php` to:
- Add more sections
- Change layout
- Modify styling
- Add additional data

## 📞 Support

If you encounter issues:
1. Check error logs: `backend/logs/`
2. Run test script: `backend/migrations/test_member_setup.php`
3. Verify database connections
4. Check browser console for JavaScript errors

## ✅ Checklist

- [ ] Migration completed successfully
- [ ] Permissions table created
- [ ] Database columns added (member_id, user_id)
- [ ] "Create User Accounts" button visible on Members page
- [ ] "Link Accounts" button visible on Members page
- [ ] Created at least one member user account
- [ ] Tested member login
- [ ] Member redirected to profile page
- [ ] Profile displays all data correctly
- [ ] Member cannot access admin pages

## 🎉 You're Done!

Your member profile system is now fully operational. Members can log in and view their complete church profile including fellowship information, financial contributions, and attendance records.

---

**Need Help?** Check the detailed documentation in `MEMBER_PROFILE_SETUP.md`
