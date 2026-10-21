<?php
/**
 * System Settings
 *
 * Admin-only Bootstrap 5 tabbed settings screen: church information, SMTP email,
 * SMS provider, the role => module permission matrix and database backups. All
 * behaviour is the inline jQuery script in $settingsScript, talking to
 * backend/settings/*.php and backend/permission/*.php.
 *
 * This page is deliberately the odd one out and stays that way:
 *   - it loads Bootstrap 5 CSS and Font Awesome **6.4.0** from CDN (every other
 *     page uses 6.6.0), and Bootstrap's sheet must come BEFORE style.css, so the
 *     CDN URLs are listed inline in 'css' to pin that cascade;
 *   - it loads the Bootstrap JS bundle and then jQuery 3.6.0 at the end of the
 *     body, and no ui.js or main.js at all;
 *   - its tabs are Bootstrap's (`data-bs-toggle` / `data-bs-target`), not the
 *     `.tab-navigation` component the other pages use;
 *   - it has no `.page-content` wrapper - `.settings-container` sits directly
 *     inside `<main class="content">`.
 *
 * Changes made while moving this onto the shared layout:
 *   - the inline <style> block became css/settings.css (verbatim rules);
 *   - the broken admin redirect is fixed. A non-admin was sent to
 *     `../pages/dashboard.php`, which resolves to frontend/pages/pages/
 *     dashboard.php and does not exist. The layout now redirects to the real
 *     application root;
 *   - the CSRF meta tag is emitted (the page never had one);
 *   - the hardcoded `const API_BASE = '../../backend'` became
 *     window.getApiUrl(), so the endpoints no longer depend on this file's
 *     directory depth.
 */

require_once __DIR__ . '/../layout/bootstrap.php';

$bootstrapCss = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css';
$bootstrapJs  = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js';
$jqueryJs     = 'https://code.jquery.com/jquery-3.6.0.min.js';
// 6.4.0 on purpose - this page predates the 6.6.0 bump the others got.
$fontAwesomeCss = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';

/**
 * Builds one row of the permissions matrix.
 *
 * Admin is always checked and disabled; the other four roles carry the
 * data-role / data-module pair the save handler collects.
 *
 * @param string $label  module display name
 * @param string $module data-module value
 */
$permissionRow = static function (string $label, string $module): string {
    $roles = ['pastor', 'pcf', 'secretary', 'treasurer'];
    $cells = '<td><input type="checkbox" class="form-check-input" checked disabled></td>';

    foreach ($roles as $role) {
        $cells .= '<td><input type="checkbox" class="form-check-input" data-role="' . $role
            . '" data-module="' . $module . '"></td>';
    }

    return '<tr>'
        . '<td><strong>' . htmlspecialchars($label, ENT_QUOTES) . '</strong></td>'
        . $cells
        . '</tr>';
};

/**
 * Builds one label + description + control row.
 *
 * @param string $id          control id, which is also the settings key the
 *                            loader writes to via getElementById(setting.key)
 * @param string $label       setting name
 * @param string $description helper text
 * @param string $control     the input/select/switch markup
 */
$settingItem = static function (string $label, string $description, string $control): string {
    return '<div class="setting-item">'
        . '<label class="setting-label">' . htmlspecialchars($label, ENT_QUOTES) . '</label>'
        . '<p class="setting-description">' . htmlspecialchars($description, ENT_QUOTES) . '</p>'
        . $control
        . '</div>';
};

/** Bootstrap text input bound to a settings key. */
$textControl = static function (string $id, string $name, string $type, string $placeholder, ?string $extra = null): string {
    return '<input type="' . $type . '" class="form-control" id="' . $id . '" name="' . $name
        . '" placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES) . '"'
        . ($extra !== null ? ' ' . $extra : '') . '>';
};

/** Bootstrap select bound to a settings key. */
$selectControl = static function (string $id, string $name, array $options, string $class = 'form-select'): string {
    $html = '<select class="' . $class . '" id="' . $id . '" name="' . $name . '">';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . htmlspecialchars((string)$value, ENT_QUOTES) . '">'
            . htmlspecialchars((string)$label, ENT_QUOTES) . '</option>';
    }
    return $html . '</select>';
};

/** Bootstrap switch bound to a settings key. */
$switchControl = static function (string $id, string $name, string $label): string {
    return '<div class="form-check form-switch">'
        . '<input class="form-check-input" type="checkbox" id="' . $id . '" name="' . $name . '">'
        . '<label class="form-check-label" for="' . $id . '">' . htmlspecialchars($label, ENT_QUOTES) . '</label>'
        . '</div>';
};

/**
 * Loads every settings category, wires the forms and manages backups.
 *
 * Kept inline because it is the whole behaviour of this page and is not shared
 * with any other screen. Endpoint URLs now go through getApiUrl() rather than a
 * relative `../../backend` constant.
 */
$settingsScript = <<<'JS'
<script>
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
            const response = await fetch(window.getApiUrl('settings/read.php?category=general'));
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
            const response = await fetch(window.getApiUrl('settings/update.php'), {
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
            const response = await fetch(window.getApiUrl('settings/read.php?category=email'));
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
            const response = await fetch(window.getApiUrl('settings/update.php'), {
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
            const response = await fetch(window.getApiUrl('settings/test_email.php'), {
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
            const response = await fetch(window.getApiUrl('settings/read.php?category=sms'));
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
            const response = await fetch(window.getApiUrl('settings/update.php'), {
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
            const response = await fetch(window.getApiUrl('settings/test_sms.php'), {
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
            const response = await fetch(window.getApiUrl('permission/read.php'));
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
            const response = await fetch(window.getApiUrl('permission/update.php'), {
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
            const response = await fetch(window.getApiUrl('settings/list_backups.php'));
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
            const response = await fetch(window.getApiUrl('settings/create_backup.php'), {
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
        window.location.href = window.getApiUrl(`settings/download_backup.php?file=${filename}`);
    });

    // Delete Backup
    $(document).on('click', '.delete-backup', async function() {
        const filename = $(this).data('file');

        if (!confirm(`Are you sure you want to delete ${filename}?`)) {
            return;
        }

        try {
            const response = await fetch(window.getApiUrl('settings/delete_backup.php'), {
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
JS;

$page = layout_page_init([
    'id' => 'settings',
    'title' => 'System Settings',
    'titleSuffix' => 'Church Management System',
    'permission' => 'admin_only',
    'roles' => ['admin'],
    // Bootstrap's sheet must precede style.css, and this page pins Font Awesome
    // 6.4.0 rather than the 6.6.0 the fontAwesome flag would emit - so both CDN
    // URLs are listed here to hold that exact cascade.
    'css' => [$bootstrapCss, $fontAwesomeCss, 'css/style.css', 'css/settings.css'],
    // Bootstrap bundle first, then jQuery, then this page's own script.
    'scripts' => [$bootstrapJs, $jqueryJs],
    'footer' => $settingsScript,
    // .settings-container sits directly inside <main class="content">.
    'contentWrapper' => false,
]);

require_once LAYOUT_DIR . '/head.php';
?>

<?php require_once LAYOUT_DIR . '/body-open.php'; ?>

            <div class="settings-container">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-cog text-primary me-2"></i>
                    <?= layout_e($page['title']) ?>
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
                            <?= $settingItem(
                                'Church Name',
                                'The official name of your church',
                                $textControl('church_name', 'church_name', 'text', 'Enter church name')
                            ) ?>

                            <?= $settingItem(
                                'Church Address',
                                'Physical address of the church',
                                '<textarea class="form-control" id="church_address" name="church_address" rows="2" placeholder="Enter church address"></textarea>'
                            ) ?>

                            <?= $settingItem(
                                'Church Phone',
                                'Main contact phone number',
                                $textControl('church_phone', 'church_phone', 'tel', '+233 XXX XXX XXX')
                            ) ?>

                            <?= $settingItem(
                                'Church Email',
                                'Official email address',
                                $textControl('church_email', 'church_email', 'email', 'info@church.com')
                            ) ?>

                            <?= $settingItem(
                                'Currency',
                                'Default currency for financial transactions',
                                $selectControl('currency', 'currency', [
                                    'GHS' => 'GHS - Ghanaian Cedi',
                                    'USD' => 'USD - US Dollar',
                                    'EUR' => 'EUR - Euro',
                                    'GBP' => 'GBP - British Pound',
                                ])
                            ) ?>

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
                            <?= $settingItem(
                                'SMTP Host',
                                'Your email server hostname',
                                $textControl('smtp_host', 'smtp_host', 'text', 'smtp.gmail.com')
                            ) ?>

                            <?= $settingItem(
                                'SMTP Port',
                                'SMTP port (usually 587 for TLS or 465 for SSL)',
                                $textControl('smtp_port', 'smtp_port', 'number', '587')
                            ) ?>

                            <?= $settingItem(
                                'SMTP Username',
                                'Your email address',
                                $textControl('smtp_username', 'smtp_username', 'email', 'your-email@gmail.com')
                            ) ?>

                            <?= $settingItem(
                                'SMTP Password',
                                'Your email password or app-specific password',
                                $textControl('smtp_password', 'smtp_password', 'password', '••••••••')
                            ) ?>

                            <?= $settingItem(
                                'From Name',
                                'Name that appears in sent emails',
                                $textControl('email_from_name', 'email_from_name', 'text', 'Church Name')
                            ) ?>

                            <?= $settingItem(
                                'Enable Email Notifications',
                                'Send automated email notifications',
                                $switchControl('email_enabled', 'email_enabled', 'Enable')
                            ) ?>

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
                            <?= $settingItem(
                                'SMS Provider',
                                'Select your SMS service provider',
                                $selectControl('sms_provider', 'sms_provider', [
                                    'arkesel' => 'Arkesel',
                                    'hubtel' => 'Hubtel',
                                    'twilio' => 'Twilio',
                                    'custom' => 'Custom API',
                                ])
                            ) ?>

                            <?= $settingItem(
                                'API Key',
                                'Your SMS provider API key',
                                $textControl('sms_api_key', 'sms_api_key', 'text', 'Enter API key')
                            ) ?>

                            <?= $settingItem(
                                'Sender ID',
                                'The name that appears as sender',
                                $textControl('sms_sender_id', 'sms_sender_id', 'text', 'ChurchName', 'maxlength="11"')
                            ) ?>

                            <?= $settingItem(
                                'Enable SMS Notifications',
                                'Send automated SMS notifications',
                                $switchControl('sms_enabled', 'sms_enabled', 'Enable')
                            ) ?>

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
                                    <?= $permissionRow('Dashboard', 'dashboard') ?>
                                    <?= $permissionRow('Members', 'members') ?>
                                    <?= $permissionRow('Attendance', 'attendance') ?>
                                    <?= $permissionRow('Financial', 'financial') ?>
                                    <?= $permissionRow('Reports', 'reports') ?>
                                    <?= $permissionRow('Users', 'users') ?>
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

                        <?= $settingItem(
                            'Automatic Backups',
                            'Enable automatic database backups',
                            $switchControl('auto_backup_enabled', 'auto_backup_enabled', 'Enable automatic backups')
                        ) ?>

                        <?= $settingItem(
                            'Backup Frequency',
                            'How often to create automatic backups',
                            $selectControl('backup_frequency', 'backup_frequency', [
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                            ])
                        ) ?>

                        <?= $settingItem(
                            'Manual Backup',
                            'Create a backup of your database now',
                            '<button type="button" class="btn btn-success" id="createBackupBtn">'
                                . '<i class="fas fa-download me-2"></i>Create Backup Now'
                                . '</button>'
                        ) ?>

                        <?= $settingItem(
                            'Recent Backups',
                            'List of recent database backups',
                            '<div id="backupsList" class="list-group">'
                                . '<div class="text-muted">Loading backups...</div>'
                                . '</div>'
                        ) ?>
                    </div>
                </div>
            </div>

<?php require_once LAYOUT_DIR . '/body-close.php'; ?>
