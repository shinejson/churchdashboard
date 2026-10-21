<?php
require_once '../../backend/config/security.php';
secure_session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/frontend/pages/login.php');
    exit();
}

// Check if user is admin
$user_role = $_SESSION['role'] ?? 'guest';
if ($user_role !== 'admin') {
    header('Location: ../pages/dashboard.php');
    exit();
}

$page_title = "System Settings";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Church Management System</title>
    
    <!-- API Configuration - MUST load first -->
    <?php require_once __DIR__ . '/../includes/config.php'; outputApiConfig(); ?>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Main Styles -->
    <link rel="stylesheet" href="../css/style.css">
    
    <!-- Environment and Configuration - MUST load before other scripts -->
    <script src="../js/env.js"></script>
    <script src="../js/config.js"></script>
    
    <style>
        /* Settings page specific styles */
        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .settings-card {
            background: var(--bg-color);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .settings-card h3 {
            color: var(--text-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .setting-item {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .setting-item:last-child {
            border-bottom: none;
        }
        
        .setting-label {
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 5px;
        }
        
        .setting-description {
            font-size: 0.9em;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        
        .nav-tabs .nav-link {
            color: var(--text-muted);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .alert-success {
            display: none;
        }
        
        /* Dark mode adjustments */
        [data-theme="dark"] .settings-card {
            background: var(--surface-color);
        }
        
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background-color: var(--surface-color);
            color: var(--text-color);
            border-color: var(--border-color);
        }
        
        /* Placeholder text in dark mode */
        [data-theme="dark"] .form-control::placeholder,
        [data-theme="dark"] .form-select::placeholder,
        [data-theme="dark"] textarea::placeholder,
        [data-theme="dark"] input::placeholder {
            color: var(--text-muted);
            opacity: 0.6;
        }
        
        /* Focus state for inputs in dark mode */
        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background-color: var(--surface-color);
            color: var(--text-color);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), 0.25);
        }
        
        [data-theme="dark"] .table {
            color: var(--text-color);
        }
        
        [data-theme="dark"] .table-bordered {
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .table-light {
            background-color: var(--surface-color);
            color: var(--text-color);
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .settings-container {
                padding: 10px;
            }
            
            .settings-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include '../topnav.php'; ?>
    
    <div id="app">
        <?php include '../sidebar.php'; ?>
        
        <main class="content">
            <div class="settings-container">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-cog text-primary me-2"></i>
                    <?php echo htmlspecialchars($page_title); ?>
                </h1>
                <span class="badge bg-primary">Admin Only</span>
            </div>
            
            <!-- Success Alert -->
            <div class="alert alert-success" id="successAlert" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <span id="successMessage">Settings saved successfully!</span>
            </div>
            
            <!-- Settings Tabs -->
            <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                        <i class="fas fa-building me-2"></i>General
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button">
                        <i class="fas fa-envelope me-2"></i>Email
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sms-tab" data-bs-toggle="tab" data-bs-target="#sms" type="button">
                        <i class="fas fa-sms me-2"></i>SMS
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions" type="button">
                        <i class="fas fa-shield-alt me-2"></i>Permissions
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button">
                        <i class="fas fa-database me-2"></i>Backup
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content" id="settingsTabContent">
                <!-- General Settings -->
                <div class="tab-pane fade show active" id="general" role="tabpanel">
                    <div class="settings-card">
                        <h3><i class="fas fa-building me-2"></i>Church Information</h3>
                        <form id="generalSettingsForm">
                            <div class="setting-item">
                                <label class="setting-label">Church Name</label>
                                <p class="setting-description">The official name of your church</p>
                                <input type="text" class="form-control" id="church_name" name="church_name" placeholder="Enter church name">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Church Address</label>
                                <p class="setting-description">Physical address of the church</p>
                                <textarea class="form-control" id="church_address" name="church_address" rows="2" placeholder="Enter church address"></textarea>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Church Phone</label>
                                <p class="setting-description">Main contact phone number</p>
                                <input type="tel" class="form-control" id="church_phone" name="church_phone" placeholder="+233 XXX XXX XXX">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Church Email</label>
                                <p class="setting-description">Official email address</p>
                                <input type="email" class="form-control" id="church_email" name="church_email" placeholder="info@church.com">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Currency</label>
                                <p class="setting-description">Default currency for financial transactions</p>
                                <select class="form-select" id="currency" name="currency">
                                    <option value="GHS">GHS - Ghanaian Cedi</option>
                                    <option value="USD">USD - US Dollar</option>
                                    <option value="EUR">EUR - Euro</option>
                                    <option value="GBP">GBP - British Pound</option>
                                </select>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save General Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Email Settings -->
                <div class="tab-pane fade" id="email" role="tabpanel">
                    <div class="settings-card">
                        <h3><i class="fas fa-envelope me-2"></i>Email Configuration</h3>
                        <form id="emailSettingsForm">
                            <div class="setting-item">
                                <label class="setting-label">SMTP Host</label>
                                <p class="setting-description">Your email server hostname</p>
                                <input type="text" class="form-control" id="smtp_host" name="smtp_host" placeholder="smtp.gmail.com">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">SMTP Port</label>
                                <p class="setting-description">SMTP port (usually 587 for TLS or 465 for SSL)</p>
                                <input type="number" class="form-control" id="smtp_port" name="smtp_port" placeholder="587">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">SMTP Username</label>
                                <p class="setting-description">Your email address</p>
                                <input type="email" class="form-control" id="smtp_username" name="smtp_username" placeholder="your-email@gmail.com">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">SMTP Password</label>
                                <p class="setting-description">Your email password or app-specific password</p>
                                <input type="password" class="form-control" id="smtp_password" name="smtp_password" placeholder="••••••••">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">From Name</label>
                                <p class="setting-description">Name that appears in sent emails</p>
                                <input type="text" class="form-control" id="email_from_name" name="email_from_name" placeholder="Church Name">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Enable Email Notifications</label>
                                <p class="setting-description">Send automated email notifications</p>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="email_enabled" name="email_enabled">
                                    <label class="form-check-label" for="email_enabled">Enable</label>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Email Settings
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="testEmailBtn">
                                    <i class="fas fa-paper-plane me-2"></i>Send Test Email
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- SMS Settings -->
                <div class="tab-pane fade" id="sms" role="tabpanel">
                    <div class="settings-card">
                        <h3><i class="fas fa-sms me-2"></i>SMS Configuration</h3>
                        <form id="smsSettingsForm">
                            <div class="setting-item">
                                <label class="setting-label">SMS Provider</label>
                                <p class="setting-description">Select your SMS service provider</p>
                                <select class="form-select" id="sms_provider" name="sms_provider">
                                    <option value="arkesel">Arkesel</option>
                                    <option value="hubtel">Hubtel</option>
                                    <option value="twilio">Twilio</option>
                                    <option value="custom">Custom API</option>
                                </select>
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">API Key</label>
                                <p class="setting-description">Your SMS provider API key</p>
                                <input type="text" class="form-control" id="sms_api_key" name="sms_api_key" placeholder="Enter API key">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Sender ID</label>
                                <p class="setting-description">The name that appears as sender</p>
                                <input type="text" class="form-control" id="sms_sender_id" name="sms_sender_id" placeholder="ChurchName" maxlength="11">
                            </div>
                            
                            <div class="setting-item">
                                <label class="setting-label">Enable SMS Notifications</label>
                                <p class="setting-description">Send automated SMS notifications</p>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="sms_enabled" name="sms_enabled">
                                    <label class="form-check-label" for="sms_enabled">Enable</label>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save SMS Settings
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="testSmsBtn">
                                    <i class="fas fa-mobile-alt me-2"></i>Send Test SMS
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Permissions Settings -->
                <div class="tab-pane fade" id="permissions" role="tabpanel">
                    <div class="settings-card">
                        <h3><i class="fas fa-shield-alt me-2"></i>Role Permissions</h3>
                        <p class="text-muted mb-4">Configure what each role can access in the system</p>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered" id="permissionsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Module</th>
                                        <th>Admin</th>
                                        <th>Pastor</th>
                                        <th>PCF</th>
                                        <th>Secretary</th>
                                        <th>Treasurer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Dashboard</strong></td>
                                        <td><input type="checkbox" class="form-check-input" checked disabled></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="pastor" data-module="dashboard"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="pcf" data-module="dashboard"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="secretary" data-module="dashboard"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="treasurer" data-module="dashboard"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Members</strong></td>
                                        <td><input type="checkbox" class="form-check-input" checked disabled></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="pastor" data-module="members"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="pcf" data-module="members"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="secretary" data-module="members"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="treasurer" data-module="members"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Attendance</strong></td>
                                        <td><input type="checkbox" class="form-check-input" checked disabled></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="pastor" data-module="attendance"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="pcf" data-module="attendance"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="secretary" data-module="attendance"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="treasurer" data-module="attendance"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Financial</strong></td>
                                        <td><input type="checkbox" class="form-check-input" checked disabled></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="pastor" data-module="financial"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="pcf" data-module="financial"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="secretary" data-module="financial"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="treasurer" data-module="financial"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Reports</strong></td>
                                        <td><input type="checkbox" class="form-check-input" checked disabled></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="pastor" data-module="reports"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="pcf" data-module="reports"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="secretary" data-module="reports"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="treasurer" data-module="reports"></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Users</strong></td>
                                        <td><input type="checkbox" class="form-check-input" checked disabled></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="pastor" data-module="users"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="pcf" data-module="users"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="secretary" data-module="users"></td>
                                        <td><input type="checkbox" class="form-check-input" data-role="treasurer" data-module="users"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button type="button" class="btn btn-primary" id="savePermissionsBtn">
                                <i class="fas fa-save me-2"></i>Save Permissions
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Backup Settings -->
                <div class="tab-pane fade" id="backup" role="tabpanel">
                    <div class="settings-card">
                        <h3><i class="fas fa-database me-2"></i>Database Backup</h3>
                        
                        <div class="setting-item">
                            <label class="setting-label">Automatic Backups</label>
                            <p class="setting-description">Enable automatic database backups</p>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="auto_backup_enabled" name="auto_backup_enabled">
                                <label class="form-check-label" for="auto_backup_enabled">Enable automatic backups</label>
                            </div>
                        </div>
                        
                        <div class="setting-item">
                            <label class="setting-label">Backup Frequency</label>
                            <p class="setting-description">How often to create automatic backups</p>
                            <select class="form-select" id="backup_frequency" name="backup_frequency">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        
                        <div class="setting-item">
                            <label class="setting-label">Manual Backup</label>
                            <p class="setting-description">Create a backup of your database now</p>
                            <button type="button" class="btn btn-success" id="createBackupBtn">
                                <i class="fas fa-download me-2"></i>Create Backup Now
                            </button>
                        </div>
                        
                        <div class="setting-item">
                            <label class="setting-label">Recent Backups</label>
                            <p class="setting-description">List of recent database backups</p>
                            <div id="backupsList" class="list-group">
                                <div class="text-muted">Loading backups...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <?php include '../footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        const API_BASE = '../../backend';
        
        // Load settings on page load
        $(document).ready(function() {
            loadGeneralSettings();
            loadEmailSettings();
            loadSmsSettings();
            loadPermissions();
            loadBackups();
        });
        
        // Show success message
        function showSuccess(message) {
            $('#successMessage').text(message);
            $('#successAlert').fadeIn().delay(3000).fadeOut();
        }
        
        // Show error message
        function showError(message) {
            alert('Error: ' + message);
        }
        
        // Load General Settings
        async function loadGeneralSettings() {
            try {
                const response = await fetch(`${API_BASE}/settings/read.php?category=general`);
                const data = await response.json();
                
                if (data.success && data.settings) {
                    data.settings.forEach(setting => {
                        const input = document.getElementById(setting.key);
                        if (input) {
                            input.value = setting.value || '';
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading general settings:', error);
            }
        }
        
        // Save General Settings
        $('#generalSettingsForm').on('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const settings = {};
            
            for (let [key, value] of formData.entries()) {
                settings[key] = value;
            }
            
            try {
                const response = await fetch(`${API_BASE}/settings/update.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ category: 'general', settings })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('General settings saved successfully!');
                } else {
                    showError(data.message || 'Failed to save settings');
                }
            } catch (error) {
                showError('Error saving settings: ' + error.message);
            }
        });
        
        // Load Email Settings
        async function loadEmailSettings() {
            try {
                const response = await fetch(`${API_BASE}/settings/read.php?category=email`);
                const data = await response.json();
                
                if (data.success && data.settings) {
                    data.settings.forEach(setting => {
                        const input = document.getElementById(setting.key);
                        if (input) {
                            if (input.type === 'checkbox') {
                                input.checked = setting.value === '1' || setting.value === 'true';
                            } else {
                                input.value = setting.value || '';
                            }
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading email settings:', error);
            }
        }
        
        // Save Email Settings
        $('#emailSettingsForm').on('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const settings = {};
            
            for (let [key, value] of formData.entries()) {
                settings[key] = value;
            }
            
            settings.email_enabled = $('#email_enabled').is(':checked') ? '1' : '0';
            
            try {
                const response = await fetch(`${API_BASE}/settings/update.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ category: 'email', settings })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('Email settings saved successfully!');
                } else {
                    showError(data.message || 'Failed to save settings');
                }
            } catch (error) {
                showError('Error saving settings: ' + error.message);
            }
        });
        
        // Test Email
        $('#testEmailBtn').on('click', async function() {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Sending...');
            
            try {
                const response = await fetch(`${API_BASE}/settings/test_email.php`, {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('Test email sent successfully!');
                } else {
                    showError(data.message || 'Failed to send test email');
                }
            } catch (error) {
                showError('Error sending test email: ' + error.message);
            } finally {
                btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Send Test Email');
            }
        });
        
        // Load SMS Settings
        async function loadSmsSettings() {
            try {
                const response = await fetch(`${API_BASE}/settings/read.php?category=sms`);
                const data = await response.json();
                
                if (data.success && data.settings) {
                    data.settings.forEach(setting => {
                        const input = document.getElementById(setting.key);
                        if (input) {
                            if (input.type === 'checkbox') {
                                input.checked = setting.value === '1' || setting.value === 'true';
                            } else {
                                input.value = setting.value || '';
                            }
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading SMS settings:', error);
            }
        }
        
        // Save SMS Settings
        $('#smsSettingsForm').on('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const settings = {};
            
            for (let [key, value] of formData.entries()) {
                settings[key] = value;
            }
            
            settings.sms_enabled = $('#sms_enabled').is(':checked') ? '1' : '0';
            
            try {
                const response = await fetch(`${API_BASE}/settings/update.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ category: 'sms', settings })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('SMS settings saved successfully!');
                } else {
                    showError(data.message || 'Failed to save settings');
                }
            } catch (error) {
                showError('Error saving settings: ' + error.message);
            }
        });
        
        // Test SMS
        $('#testSmsBtn').on('click', async function() {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Sending...');
            
            try {
                const response = await fetch(`${API_BASE}/settings/test_sms.php`, {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('Test SMS sent successfully!');
                } else {
                    showError(data.message || 'Failed to send test SMS');
                }
            } catch (error) {
                showError('Error sending test SMS: ' + error.message);
            } finally {
                btn.prop('disabled', false).html('<i class="fas fa-mobile-alt me-2"></i>Send Test SMS');
            }
        });
        
        // Load Permissions
        async function loadPermissions() {
            try {
                const response = await fetch(`${API_BASE}/permission/read.php`);
                const data = await response.json();
                
                if (data.success && data.permissions) {
                    data.permissions.forEach(perm => {
                        const checkbox = $(`input[data-role="${perm.role}"][data-module="${perm.module}"]`);
                        if (checkbox.length) {
                            checkbox.prop('checked', perm.allowed == 1);
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading permissions:', error);
            }
        }
        
        // Save Permissions
        $('#savePermissionsBtn').on('click', async function() {
            const permissions = [];
            
            $('#permissionsTable input[data-role]').each(function() {
                const role = $(this).data('role');
                const module = $(this).data('module');
                const allowed = $(this).is(':checked') ? 1 : 0;
                
                permissions.push({ role, module, allowed });
            });
            
            try {
                const response = await fetch(`${API_BASE}/permission/update.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ permissions })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('Permissions saved successfully!');
                } else {
                    showError(data.message || 'Failed to save permissions');
                }
            } catch (error) {
                showError('Error saving permissions: ' + error.message);
            }
        });
        
        // Load Backups
        async function loadBackups() {
            try {
                const response = await fetch(`${API_BASE}/settings/list_backups.php`);
                const data = await response.json();
                
                if (data.success && data.backups) {
                    const backupsList = $('#backupsList');
                    backupsList.empty();
                    
                    if (data.backups.length === 0) {
                        backupsList.html('<div class="text-muted">No backups found</div>');
                    } else {
                        data.backups.forEach(backup => {
                            backupsList.append(`
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-file-archive me-2"></i>
                                        <strong>${backup.name}</strong>
                                        <br>
                                        <small class="text-muted">${backup.size} - ${backup.date}</small>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-outline-primary download-backup" data-file="${backup.name}">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger delete-backup" data-file="${backup.name}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            `);
                        });
                    }
                }
            } catch (error) {
                console.error('Error loading backups:', error);
                $('#backupsList').html('<div class="text-danger">Error loading backups</div>');
            }
        }
        
        // Create Backup
        $('#createBackupBtn').on('click', async function() {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Creating backup...');
            
            try {
                const response = await fetch(`${API_BASE}/settings/create_backup.php`, {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('Backup created successfully!');
                    loadBackups();
                } else {
                    showError(data.message || 'Failed to create backup');
                }
            } catch (error) {
                showError('Error creating backup: ' + error.message);
            } finally {
                btn.prop('disabled', false).html('<i class="fas fa-download me-2"></i>Create Backup Now');
            }
        });
        
        // Download Backup
        $(document).on('click', '.download-backup', function() {
            const filename = $(this).data('file');
            window.location.href = `${API_BASE}/settings/download_backup.php?file=${filename}`;
        });
        
        // Delete Backup
        $(document).on('click', '.delete-backup', async function() {
            const filename = $(this).data('file');
            
            if (!confirm(`Are you sure you want to delete ${filename}?`)) {
                return;
            }
            
            try {
                const response = await fetch(`${API_BASE}/settings/delete_backup.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ filename })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('Backup deleted successfully!');
                    loadBackups();
                } else {
                    showError(data.message || 'Failed to delete backup');
                }
            } catch (error) {
                showError('Error deleting backup: ' + error.message);
            }
        });
    </script>
</body>
</html>
