<?php 
require_once '../../backend/config/security.php';
secure_session_start();
init_csrf();
set_csrf_meta_tag(); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token']; ?>">

    <title>Members Management - Church Management System</title>
   <!-- In members.php <head> section: -->
<link rel="stylesheet" href="../css/members.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<!-- Before </body>: -->

    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/screens.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    
    <!-- API Configuration - MUST load first -->
    <?php require_once __DIR__ . '/../includes/config.php'; outputApiConfig(); ?>
    
    <!-- Environment and Configuration - MUST load before other scripts -->
    <script src="../js/env.js"></script>
    <script src="../js/config.js"></script>
   
</head>
<body>
    <script>
        const saved = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const isDark = saved === 'dark' || (!saved && prefersDark);
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    </script>
    <!-- Top Navigation Bar -->
    <?php include_once '../topnav.php'; ?>
    
    <div id="app">
           <?php include_once '../sidebar.php'; ?>
        
        <main class="content">
            <div id="members-content" class="page-content active">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="header-content">
                        <h2><i class="fas fa-users header-icon"></i> Members Management</h2>
                        <div class="header-actions">
                            <div class="search-container">
                                <input type="text" id="global-search" placeholder="Search members..." class="search-input">
                                <i class="fas fa-search search-icon"></i>
                            </div>
                            <button id="gsheet-import-btn" class="btn-secondary">
                                <i class="fab fa-google"></i> Import from Google Sheet
                            </button>
                            <button id="schedule-import-btn" class="btn-secondary">
                                <i class="fas fa-clock"></i> Schedule Import
                            </button>
                            <button id="download-template" class="btn-secondary">
                                <i class="fas fa-download"></i> Download Template
                            </button>
                            <button id="import-history-btn" class="btn-secondary">
                                <i class="fas fa-history"></i> Import History
                            </button>
                            <button id="create-user-accounts-btn" class="btn-secondary" onclick="window.location.href='create_member_users.php'" title="Create login accounts for members">
                                <i class="fas fa-user-lock"></i> Create User Accounts
                            </button>
                            <button id="link-members-btn" class="btn-secondary" onclick="window.location.href='link_members.php'" title="Link user accounts to member records">
                                <i class="fas fa-link"></i> Link Accounts
                            </button>
                            <button id="create-member-btn" class="btn-primary">
                                <i class="fas fa-user-plus"></i>
                                <span>Add New Member</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="filters-container">
                    <div class="filter-group">
                        <label for="member-fellowship"><i class="fas fa-church"></i> Fellowship</label>
                        <select id="member-fellowship" class="filter-select">
                            <option value="">All Fellowships</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="member-status"><i class="fas fa-toggle-on"></i> Status</label>
                        <select id="member-status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="visitor">Visitor</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filter-member-department"><i class="fas fa-building"></i> Department</label>
                        <select id="filter-member-department" class="filter-select">
                            <option value="">All Departments</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filter-member-cell"><i class="fas fa-home"></i> Home Cell</label>
                        <select id="filter-member-cell" class="filter-select">
                            <option value="">All Cells</option>
                        </select>
                    </div>

    <div class="form-group">
    <label for="member-category">Category</label>
    <select id="member-category" name="category">
        <option value="adult">Adult</option>
        <option value="teen">Teen</option>
        <option value="children">Children</option>
    </select>
</div>

                    <div class="filter-actions">
                        <button id="apply-member-filters" class="btn-secondary">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <button id="reset-member-filters" class="btn-outline">
                            <i class="fas fa-sync"></i> Reset
                        </button>
                    </div>
                </div>

                <!-- Stats Overview -->
                <div class="stats-overview">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" id="total-members-count">0</div>
                            <div class="stat-label">Total Members</div>
                            <div class="stat-trend">
                                <span class="trend-positive"><i class="fas fa-arrow-up"></i> +12%</span>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon active">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" id="active-members-count">0</div>
                            <div class="stat-label">Active Members</div>
                            <div class="stat-trend">
                                <span class="trend-positive"><i class="fas fa-arrow-up"></i> +8%</span>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon visitor">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" id="visitor-count">0</div>
                            <div class="stat-label">Visitors</div>
                            <div class="stat-trend">
                                <span class="trend-neutral"><i class="fas fa-minus"></i> 0%</span>
                            </div>
                        </div>
                    </div>
                </div>

 <!-- Members Table Section -->
<div class="table-section">
    <div class="table-header">
        <h3><i class="fas fa-list"></i> Members List</h3>
        <div class="table-controls">
            <div class="action-buttons">
                <button id="export-members" class="btn-secondary">
                    <i class="fas fa-file-export"></i> Export CSV
                </button>
                <button id="print-members" class="btn-secondary">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-wrapper">
            <table class="data-table sortable">
                <thead>
                    <tr>
                        <th data-sort="name">
                            <div class="th-content">
                                <span>Name & Category</span>
                                <i class="fas fa-sort sort-icon"></i>
                            </div>
                        </th>
                        <th data-sort="phone">
                            <div class="th-content">
                                <span>Phone</span>
                                <i class="fas fa-sort sort-icon"></i>
                            </div>
                        </th>
                        <th data-sort="fellowship">
                            <div class="th-content">
                                <span>Fellowship</span>
                                <i class="fas fa-sort sort-icon"></i>
                            </div>
                        </th>
                        <th data-sort="status">
                            <div class="th-content">
                                <span>Status</span>
                                <i class="fas fa-sort sort-icon"></i>
                            </div>
                        </th>
                        <th data-sort="join_date">
                            <div class="th-content">
                                <span>Join Date</span>
                                <i class="fas fa-sort sort-icon"></i>
                            </div>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="members-table-body">
                    <!-- Dynamic rows will be inserted here by JavaScript -->
                </tbody>
            </table>
        </div>

        <div class="empty-state" id="table-empty-state" style="display: none;">
            <div class="empty-icon">
                <i class="fas fa-users-slash"></i>
            </div>
            <h3>No members found</h3>
            <p>Try adjusting your filters or add your first member to get started.</p>
            <button class="btn-primary" id="create-from-empty">
                <i class="fas fa-user-plus"></i> Add First Member
            </button>
        </div>
    </div>

    <div class="table-footer">
        <div class="table-info">
            <span id="table-info">Loading...</span>
        </div>
        <div class="pagination-container">
            <button id="prev-page" class="pagination-btn" disabled>
                <i class="fas fa-chevron-left"></i> Previous
            </button>
            <div class="page-numbers" id="page-numbers"></div>
            <button id="next-page" class="pagination-btn" disabled>
                Next <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
</div>
            </div>
        </main>
    </div>

      <?php include_once '../footer.php'; ?>

    <!-- MEMBER MODAL -->
    <div id="member-modal" class="modal" aria-hidden="true">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h3 id="modal-title">Add New Member</h3>
                <button type="button" class="close-modal" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body">
 <!-- In your member modal form - REPLACE the entire form section -->
<form id="member-form" enctype="multipart/form-data">
    <input type="hidden" id="member-id" name="id">

    <!-- Photo Upload -->
    <div class="form-group text-center">
        <label>Member Photo</label>
        <div class="photo-preview" id="photo-preview">
            <img src="../assets/avatar-placeholder.png" alt="Preview" id="preview-img">
            <div class="photo-overlay">
                <i class="fas fa-camera"></i>
            </div>
        </div>
        <input type="file" id="photo" name="photo" accept="image/jpeg,image/png" style="display:none;">
        <small>Click photo to change (JPG/PNG, max 2MB)</small>
    </div>

    <!-- Name -->
    <div class="form-grid">
        <div class="form-group">
            <label for="first-name">First Name *</label>
            <input type="text" id="first-name" name="first_name" required>
            <span class="error-text"></span>
        </div>
        <div class="form-group">
            <label for="last-name">Last Name *</label>
            <input type="text" id="last-name" name="last_name" required>
            <span class="error-text"></span>
        </div>
    </div>

    <!-- Contact -->
    <div class="form-grid">
        <div class="form-group">
            <label for="phone">Phone Number *</label>
            <input type="tel" id="phone" name="phone" required placeholder="+233 24 123 4567">
            <span class="error-text"></span>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email">
            <span class="error-text"></span>
        </div>
    </div>

    <!-- Fellowship (Searchable) -->
    <div class="form-group">
        <label>Fellowship <span class="required">*</span></label>
        <div class="searchable-select">
            <input type="text" id="fellowship-search" placeholder="Search and select fellowship..." autocomplete="off">
            <i class="fas fa-chevron-down dropdown-icon"></i>
            <input type="hidden" id="fellowship" name="fellowship_id" required>
            <div id="fellowship-dropdown" class="dropdown-list"></div>
        </div>
        <small class="error-text"></small>
    </div>

    <!-- Status & Category -->
    <div class="form-grid">
        <div class="form-group">
            <label for="member-status">Status *</label>
            <select id="member-status" name="status" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="visitor">Visitor</option>
            </select>
            <span class="error-text"></span>
        </div>
        
        <div class="form-group">
            <label for="member-category">Category *</label>
            <select id="member-category" name="category" required>
                <option value="adult">Adult (18+)</option>
                <option value="teen">Teen (13-17)</option>
                <option value="children">Children (0-12)</option>
            </select>
            <span class="error-text"></span>
        </div>
    </div>

    <!-- Department & Role -->
    <div class="form-grid">
        <div class="form-group">
            <label for="member-department">Department</label>
            <select id="member-department" name="department_id">
                <option value="">No Department</option>
            </select>
        </div>
        <div class="form-group">
            <label for="role-in-church">Role in Church</label>
            <input type="text" id="role-in-church" name="role_in_church" placeholder="e.g. Deacon, Usher">
        </div>
    </div>

    <!-- Home Cell -->
    <div class="form-group">
        <label for="member-cell">Home Cell</label>
        <select id="member-cell" name="cell_id">
            <option value="">No Cell</option>
        </select>
    </div>

    <!-- Dates -->
    <div class="form-grid">
        <div class="form-group">
            <label for="join-date">Join Date *</label>
            <input type="date" id="join-date" name="join_date" required>
            <span class="error-text"></span>
        </div>
        
        <div class="form-group">
            <label for="birth-date">Birth Date</label>
            <input type="date" id="birth-date" name="birth_date">
            <span class="error-text"></span>
        </div>
    </div>

    <!-- Address & Notes -->
    <div class="form-group full-width">
        <label for="address">Address</label>
        <textarea id="address" name="address" rows="2"></textarea>
    </div>

    <div class="form-group full-width">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="3"></textarea>
    </div>

    <div class="form-actions">
        <button type="button" id="close-modal" class="btn-secondary close-modal">Cancel</button>
        <button type="submit" class="btn-primary" id="submit-member-btn">
            <span id="submit-text">Save Member</span>
            <span id="submit-spinner" class="spinner" style="display:none;"></span>
        </button>
    </div>
</form>
            </div>
        </div>
    </div>

    <!-- DELETE CONFIRMATION MODAL -->
<div id="delete-modal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:420px;text-align:center;">
        <div class="modal-header">
            <h3>Delete Member?</h3>
            <button class="close-modal">×</button>
        </div>
        <div class="modal-body">
            <i class="fas fa-exclamation-triangle fa-3x" style="color:#ef4444;margin:20px 0;"></i>
            <p style="font-size:1.1rem;margin:16px 0;">
                Are you sure you want to <strong>permanently delete</strong> this member?
            </p>
            <p id="delete-member-name" style="font-weight:600;color:#1f2937;"></p>
            <div style="margin-top:24px;display:flex;gap:12px;justify-content:center;">
                <button class="btn-secondary close-modal">Cancel</button>
                <button id="confirm-delete-btn" class="btn-primary" style="background:#ef4444;">
                    <i class="fas fa-trash"></i> Delete Forever
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Print Card Template (Hidden) -->
    <div id="print-card" style="display:none; padding:40px; background:white; font-family:Arial;">
        <div style="max-width:400px; margin:auto; border:2px solid #333; border-radius:16px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
            <div style="background:linear-gradient(135deg,#118ab2,#06d6a0); color:white; padding:20px; text-align:center;">
                <h2>Grace Community Church</h2>
                <p>Member ID Card</p>
            </div>
            <div style="padding:20px; text-align:center;">
                <img id="print-photo" src="<?= $assetBase ?>/assets/avatar-placeholder.png" width="120" height="120" style="border-radius:50%; border:4px solid #eee;">
                <h3 id="print-name" style="margin:15px 0 5px;"></h3>
                <p id="print-fellowship" style="color:#555; font-size:14px;"></p>
                <p id="print-phone" style="margin:10px 0; font-weight:bold;"></p>
                <small>Member since <span id="print-join"></span></small>
            </div>
            <div style="background:#f0f0f0; padding:15px; text-align:center; font-size:12px;">
                <p>www.gracecommunity.org | +233 30 255 1234</p>
            </div>
        </div>
    </div>

    <!-- BULK IMPORT MODAL -->
    <div id="bulk-import-modal" class="modal" aria-hidden="true">
        <div class="modal-content" style="max-width:800px;">
            <div class="modal-header">
                <h3>Bulk Import Members from Excel</h3>
                <button class="close-modal">×</button>
            </div>

            <div style="margin: 20px 0;">
                <label style="display:flex;align-items:center;gap:8px;font-weight:500;">
                    <input type="checkbox" id="send-welcome-sms" checked>
                    Send Welcome SMS to all new members
                </label>
            </div>
            <div class="modal-body">
                <div id="drop-zone" class="drop-zone">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="1.5">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    <p>Drag & drop your Excel file here</p>
                    <small>or <span id="browse-link" style="color:var(--primary-color);cursor:pointer;text-decoration:underline;">browse</span></small>
                    <input type="file" id="excel-file" accept=".xlsx,.xls" hidden>
                </div>

                <div id="mapping-section" style="display:none; margin-top:20px;">
                    <h4>Map Columns</h4>
                    <div id="column-mapping"></div>
                    <div style="margin-top:20px;">
                        <label><input type="checkbox" id="skip-first-row" checked> Skip first row (headers)</label>
                    </div>
                </div>

                <div id="preview-section" style="display:none; margin-top:20px;">
                    <h4>Preview (First 5 rows)</h4>
                    <div class="table-container" style="max-height:300px; overflow:auto;">
                        <table class="data-table" id="preview-table"></table>
                    </div>
                    <div style="margin-top:15px; color:var(--success); font-weight:600;">
                        <span id="row-count">0</span> members will be imported
                    </div>
                </div>

                <div id="progress-section" style="display:none; margin-top:20px;">
                    <div class="progress-bar">
                        <div id="progress-fill" style="width:0%;"></div>
                    </div>
                    <p id="progress-text" class="text-center mt-2">Preparing...</p>
                </div>

                <div class="form-actions" style="margin-top:24px;">
                    <button type="button" class="btn-secondary close-modal">Cancel</button>
                    <button type="button" id="start-import" class="btn-primary" disabled>Start Import</button>
                </div>
            </div>
        </div>
    </div>

    <!-- IMPORT HISTORY MODAL -->
    <div id="import-history-modal" class="modal" aria-hidden="true">
        <div class="modal-content" style="max-width:900px;">
            <div class="modal-header">
                <h3>Import History</h3>
                <button class="close-modal">×</button>
            </div>
            <div class="modal-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>File</th>
                                <th>Added</th>
                                <th>Failed</th>
                                <th>By User</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="import-log-body">
                            <!-- Filled by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- SCHEDULE MODAL -->
    <div id="schedule-modal" class="modal" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Schedule Recurring Import</h3>
                <button class="close-modal">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Google Sheet URL</label>
                    <input type="url" id="gsheet-url" placeholder="https://docs.google.com/spreadsheets/d/...">
                </div>
                <div class="form-group">
                    <label>Frequency</label>
                    <select id="import-frequency">
                        <option value="daily">Daily at 2:00 AM</option>
                        <option value="weekly">Every Monday at 2:00 AM</option>
                        <option value="monthly">1st of every month</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button class="btn-secondary close-modal">Cancel</button>
                    <button class="btn-primary" id="save-schedule">Save Schedule</button>
                </div>
            </div>
        </div>
    </div>

    <!-- CSRF Token -->
    <?php set_csrf_meta_tag(); ?>

    <script src="../js/members.js"></script>
    <script src="../js/ui.js"></script>
    <script src="../js/main.js?v=<?php echo time(); ?>"></script>
    <script>
        // Set global configuration for all pages
        window.APP_CONFIG = {
            baseUrl: 'http://' + window.location.host + '/church/',
            loginUrl: 'http://' + window.location.host + '/church/frontend/pages/login.php'
        };

        document.getElementById('birth-date')?.addEventListener('change', function() {
            const birthDate = this.value;
            if (!birthDate) return;

            const today = new Date();
            const birth = new Date(birthDate);
            const age = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();
            const isYounger = monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate());

            let category = 'adult';
            if (age < 13 || (age === 13 && isYounger)) {
                category = 'children';
            } else if (age < 18 || (age === 18 && isYounger)) {
                category = 'teen';
            }

            document.getElementById('member-category').value = category;
        });
    </script>  
</body>
</html>