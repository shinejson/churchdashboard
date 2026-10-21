# Church Management System

A comprehensive web-based church management system for managing members, attendance, finances, fellowships, and generating reports.

## Features

### Core Modules
- **Dashboard**: Overview of church statistics and activities
- **Members Management**: Add, edit, and manage church members
- **Attendance Tracking**: Record and track service attendance
- **Financial Management**: Track tithes, offerings, donations, and expenses
- **Fellowship Management**: Manage fellowship groups and leaders
- **Reports & Analytics**: Generate detailed reports with charts and export options
- **User Management**: Role-based access control (Admin, Pastor, PCF, Secretary, Treasurer)
- **Settings**: System configuration for email, SMS, backups, and permissions

### Key Features
- **Dark Mode Support**: Toggle between light and dark themes
- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Email Notifications**: Send reports and notifications via email
- **SMS Integration**: Send SMS notifications (Arkesel, Hubtel, Twilio)
- **Data Export**: Export reports to PDF and CSV
- **Database Backups**: Automated and manual backup functionality
- **Security**: CSRF protection, secure sessions, role-based permissions

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Charts**: Chart.js
- **Icons**: Font Awesome 6
- **Email**: PHPMailer

## Requirements

- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx web server
- mod_rewrite enabled (Apache)
- PHP Extensions:
  - PDO
  - mysqli
  - mbstring
  - json
  - session

## Installation

### 1. Clone or Download
```bash
git clone <repository-url>
cd church-management-system
```

### 2. Database Setup
```bash
# Create database
mysql -u root -p
CREATE DATABASE church_management;
exit;

# Import schema
mysql -u root -p church_management < church_management.sql
```

### 3. Configuration
```bash
# Copy environment file
cp .env.example .env

# Edit configuration
nano backend/config/database.php
# Update database credentials
```

### 4. Set Permissions
```bash
chmod 775 uploads/
chmod 775 backend/logs/
chmod 775 backend/backups/
```

### 5. Access Application
```
http://localhost/church-management-system/
```

### 6. Default Login
```
Username: admin
Password: [check database or setup script]
```

**⚠️ IMPORTANT: Change the default password immediately after first login!**

## Configuration

### Email Setup
1. Login as admin
2. Go to Settings > Email
3. Configure SMTP settings:
   - Host: smtp.gmail.com (for Gmail)
   - Port: 587 (TLS) or 465 (SSL)
   - Username: your-email@gmail.com
   - Password: app-specific password
4. Test email functionality

### SMS Setup (Optional)
1. Go to Settings > SMS
2. Select provider (Arkesel, Hubtel, Twilio)
3. Enter API credentials
4. Test SMS functionality

### Permissions
1. Go to Settings > Permissions
2. Configure module access for each role
3. Save changes

## User Roles

- **Admin**: Full system access
- **Pastor**: Access to all modules except system settings
- **PCF**: Access to members, attendance, and reports
- **Secretary**: Access to members and attendance
- **Treasurer**: Access to financial records and reports

## Usage

### Adding Members
1. Navigate to Members
2. Click "Add New Member"
3. Fill in member details
4. Upload photo (optional)
5. Save

### Recording Attendance
1. Navigate to Attendance
2. Select service date and type
3. Mark members as present/absent
4. Record first-timers
5. Submit attendance

### Financial Transactions
1. Navigate to Financial
2. Click "Record Payment"
3. Select transaction type (Tithe, Offering, Donation)
4. Enter amount and details
5. Save transaction

### Generating Reports
1. Navigate to Reports
2. Select report type
3. Choose filters (date range, fellowship, service)
4. Click "Generate Report"
5. Export to PDF or CSV if needed

## Backup & Restore

### Creating Backups
1. Go to Settings > Backup
2. Click "Create Backup Now"
3. Download backup file

### Restoring Backups
```bash
mysql -u root -p church_management < backup_file.sql
```

## Security Best Practices

1. **Change Default Passwords**: Immediately after installation
2. **Use HTTPS**: Install SSL certificate
3. **Regular Backups**: Enable automatic backups
4. **Update Regularly**: Keep system and dependencies updated
5. **Secure Credentials**: Use strong passwords and app-specific passwords
6. **Limit Access**: Use role-based permissions appropriately
7. **Monitor Logs**: Regularly check error and security logs

## Troubleshooting

### Database Connection Error
- Verify database credentials in `backend/config/database.php`
- Check if MySQL service is running
- Ensure database exists

### Session Timeout
- Check PHP session settings
- Verify session directory is writable
- Clear browser cookies

### Email Not Sending
- Verify SMTP credentials
- Check firewall/port settings
- Use app-specific password for Gmail
- Check spam folder

### File Upload Issues
- Check `uploads/` directory permissions
- Verify PHP `upload_max_filesize` setting
- Check available disk space

## Support

For issues, questions, or feature requests:
1. Check the documentation
2. Review error logs in `backend/logs/`
3. Contact system administrator

## License

[Specify your license here]

## Credits

Developed for church management and administration.

## Version

Current Version: 1.0.0
Release Date: 2025-01-XX
