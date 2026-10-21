# Member Profile Setup Guide

This guide explains how to set up the member profile feature that allows members to view their fellowship information, financial contributions (tithe and partnerships), and attendance records.

## Features

### For Members
- View personal information (name, email, phone, address, etc.)
- See fellowship, department, and home cell information
- View tithe contribution history and summary
- View partnership contribution history and summary
- Check attendance records and statistics
- Access restricted to profile page only

### For Administrators
- Full system access remains unchanged
- Ability to link user accounts to member records
- Manage member permissions

## Installation Steps

### Step 1: Run the Database Migration

Run the migration script to create the permissions table and set up member role permissions:

**Option A: Via Browser**
Navigate to:
```
http://your-domain.com/backend/migrations/apply_member_permissions.php
```

**Option B: Via Command Line**
```bash
php backend/migrations/apply_member_permissions.php
```

This will:
- Create the `permissions` table
- Set up permissions for all roles (admin, pastor, pcf, leader, member)
- Add `member_id` column to `users` table
- Add `user_id` column to `members` table
- Automatically link existing users to members where IDs match

### Step 2: Create Member User Accounts

For each member who needs access to their profile:

1. Go to the Users page in your admin panel
2. Create a new user with:
   - Username (e.g., member's email or unique identifier)
   - Password
   - Role: **member**
   - Fellowship: Select the member's fellowship

### Step 3: Link User Accounts to Member Records

After creating user accounts, you need to link them to member records:

**Option A: Automatic Linking (if IDs match)**
The migration script automatically links users and members with matching IDs.

**Option B: Manual Linking via API**

1. Get unlinked users and members:
```bash
GET http://your-domain.com/backend/users/link_member.php
```

2. Link a user to a member:
```bash
POST http://your-domain.com/backend/users/link_member.php
Content-Type: application/json

{
  "user_id": 123,
  "member_id": 456
}
```

**Option C: Direct Database Update**
```sql
-- Link user ID 123 to member ID 456
UPDATE users SET member_id = 456 WHERE id = 123;
UPDATE members SET user_id = 123 WHERE id = 456;
```

### Step 4: Test Member Login

1. Log out of your admin account
2. Log in with a member account
3. You should be redirected to the profile page automatically
4. Verify that the member can see:
   - Personal information
   - Fellowship details
   - Financial contributions
   - Attendance records

## User Roles and Permissions

### Member Role
- **Can Access**: Profile page only
- **Cannot Access**: Dashboard, Users, Fellowships, Attendance, Financial, Reports, Settings, Members management

### Other Roles
All other roles (admin, pastor, pcf, leader) retain their existing permissions and can also access the profile page.

## Security Features

1. **Authentication Required**: Members must log in to access their profile
2. **Data Isolation**: Members can only see their own data
3. **Fellowship Scoping**: Data is automatically filtered by fellowship
4. **Session Management**: Secure session handling with CSRF protection
5. **Permission Checks**: Role-based access control on all endpoints

## Troubleshooting

### Member sees "Profile not found" error

**Cause**: User account is not linked to a member record

**Solution**:
1. Check if the user has a `member_id` in the `users` table
2. Use the link_member.php endpoint to link the user to a member
3. Or manually update the database:
```sql
UPDATE users SET member_id = [member_id] WHERE id = [user_id];
UPDATE members SET user_id = [user_id] WHERE id = [member_id];
```

### Member can access other pages

**Cause**: Permissions not properly set

**Solution**:
1. Verify the user's role is set to 'member'
2. Re-run the migration script to reset permissions
3. Check the `permissions` table for the member role

### Financial data not showing

**Cause**: Member record not linked to financial transactions

**Solution**:
1. Verify that tithe_transactions and partnership_contributions tables have the correct `member_id`
2. Check that the member_id matches the linked member record

### Attendance data not showing

**Cause**: Attendance records not linked to member

**Solution**:
1. Verify that attendance_records table has the correct `member_id`
2. Ensure attendance is being recorded with member IDs

## Database Schema

### Users Table
```sql
- id (primary key)
- username
- password
- role (admin, pastor, pcf, leader, member)
- fellowship_id
- member_id (links to members.id) -- NEW
```

### Members Table
```sql
- id (primary key)
- user_id (links to users.id) -- NEW
- first_name
- last_name
- email
- phone
- fellowship_id
- cell_id
- department_id
- ... other fields
```

### Permissions Table
```sql
- id (primary key)
- role (admin, pastor, pcf, leader, member)
- module (dashboard, users, fellowships, etc.)
- allowed (0 or 1)
```

## API Endpoints

### Get Member Profile
```
GET /backend/profile/read.php
```
Returns complete member profile including:
- Personal information
- Fellowship, cell, and department details
- Tithe contributions and summary
- Partnership contributions and summary
- Attendance records and statistics

### Link User to Member (Admin only)
```
GET /backend/users/link_member.php
Returns unlinked users and members

POST /backend/users/link_member.php
Body: { "user_id": 123, "member_id": 456 }
Links a user account to a member record
```

## Frontend Pages

### Profile Page
Location: `frontend/pages/profile.php`

Features:
- Responsive design
- Real-time data loading
- Financial summaries with charts
- Transaction history
- Attendance statistics

## Customization

### Changing Currency Symbol
Edit `frontend/pages/profile.php` and update the `formatMoney()` function to change from ₦ (Naira) to your currency.

### Adding More Profile Sections
1. Update `backend/profile/read.php` to fetch additional data
2. Add new sections to `frontend/pages/profile.php`
3. Update the `renderProfile()` function to display the new data

### Customizing Permissions
Edit the permissions in `backend/config/security.php` or update the `permissions` table directly.

## Support

For issues or questions:
1. Check the error logs in `backend/logs/`
2. Verify database connections
3. Ensure all migrations have been run
4. Check browser console for JavaScript errors

## Next Steps

After setup, consider:
1. Creating a user guide for members
2. Setting up email notifications for new accounts
3. Adding password reset functionality
4. Implementing profile editing for members
5. Adding more detailed financial reports
