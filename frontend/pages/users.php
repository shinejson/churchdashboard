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
    <title>Users Management - Church Management System</title>
    
    <!-- API Configuration - MUST load first -->
    <?php require_once __DIR__ . '/../includes/config.php'; outputApiConfig(); ?>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/screens.css">
    <link rel="stylesheet" href="../css/users.css">
    <link rel="stylesheet" href="../css/icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
    <?php include '../topnav.php'; ?>
    
    <div id="app">
        <?php include '../sidebar.php'; ?>
        
        <main class="content">
            <div id="users-content" class="page-content active">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="header-content">
                        <h2><i class="fas fa-users-cog header-icon"></i> Users Management</h2>
                        <div class="header-actions">
                            <div class="search-container">
                                <input type="text" id="global-search" placeholder="Search users..." class="search-input">
                                <i class="fas fa-search search-icon"></i>
                            </div>
                            <button id="link-members-btn" class="btn-secondary" onclick="window.location.href='link_members.php'" title="Link user accounts to member records">
                                <i class="fas fa-link"></i> Link to Members
                            </button>
                            <button id="create-user-btn" class="btn-primary">
                                <i class="fas fa-user-plus"></i>
                                <span>Add New User</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Overview -->
                <div class="stats-overview">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" id="total-users-count">0</div>
                            <div class="stat-label">Total Users</div>
                            <div class="stat-trend">
                                <span class="trend-positive"><i class="fas fa-arrow-up"></i> +5%</span>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon active">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" id="active-users-count">0</div>
                            <div class="stat-label">Active Users</div>
                            <div class="stat-trend">
                                <span class="trend-positive"><i class="fas fa-arrow-up"></i> +3%</span>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon admin">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" id="admin-count">0</div>
                            <div class="stat-label">Administrators</div>
                            <div class="stat-trend">
                                <span class="trend-neutral"><i class="fas fa-minus"></i> 0%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Table Section -->
                <div class="table-section">
                    <div class="table-header">
                        <h3><i class="fas fa-list"></i> System Users</h3>
                        <div class="table-controls">
                            <div class="filter-group">
                               <select id="role-filter">
                                    <option value="">All Roles</option>
                                    <option value="admin">Administrator</option>
                                    <option value="pastor">Pastor</option>
                                    <option value="pcf">PCF Leader</option>
                                    <option value="leader">Fellowship Leader</option>
                                </select>
                                <select id="status-filter">
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                            <div class="action-buttons">
                                <button id="export-users" class="btn-secondary">
                                    <i class="fas fa-file-export"></i> Export
                                </button>
                                <button id="print-users" class="btn-secondary">
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
                                                <span>User</span>
                                                <i class="fas fa-sort sort-icon"></i>
                                            </div>
                                        </th>
                                        <th data-sort="email">
                                            <div class="th-content">
                                                <span>Email</span>
                                                <i class="fas fa-sort sort-icon"></i>
                                            </div>
                                        </th>
                                        <th data-sort="role">
                                            <div class="th-content">
                                                <span>Role</span>
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
                                        <th data-sort="last_login">
                                            <div class="th-content">
                                                <span>Last Login</span>
                                                <i class="fas fa-sort sort-icon"></i>
                                            </div>
                                        </th>
                                        <th data-sort="created_at">
                                            <div class="th-content">
                                                <span>Created</span>
                                                <i class="fas fa-sort sort-icon"></i>
                                            </div>
                                        </th>
                                        <th class="text-center">
                                            <div class="th-content" style="justify-content: center;">
                                                <span>Actions</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="users-table-body">
                                    <!-- Dynamic rows -->
                                </tbody>
                            </table>
                        </div>

                        <div class="empty-state" id="table-empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-users-slash"></i>
                            </div>
                            <h3>No users found</h3>
                            <p>Try adjusting your filters or add your first user to get started.</p>
                            <button class="btn-primary" id="create-from-empty">
                                <i class="fas fa-user-plus"></i> Add First User
                            </button>
                        </div>
                    </div>

                    <div class="table-footer">
                        <div class="table-info">
                            <span id="table-info">Showing 0-0 of 0 entries</span>
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

    <?php include '../footer.php'; ?>

    <!-- User Modal -->
    <div id="user-modal" class="modal">
        <div class="modal-overlay" id="modal-overlay"></div>
        <div class="modal-content large-modal">
            <!-- Modal Header -->
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <h3 id="modal-title">Add New User</h3>
                    <p id="modal-subtitle" class="subtitle">Create or update system user accounts</p>
                </div>
                <button class="close-btn" id="close-modal" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body (Scrollable Form) -->
            <form id="user-form" class="modal-form">
                <input type="hidden" id="user-id">
                
                <!-- Basic Information Grid -->
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-at"></i>
                            Username
                        </label>
                        <input 
                            type="text" 
                            id="username" 
                            required 
                            placeholder="e.g., john.doe" 
                            autocomplete="off"
                        >
                    </div>

                    <div class="form-group">
                        <label for="user-email">
                            <i class="fas fa-envelope"></i>
                            Email
                        </label>
                        <input 
                            type="email" 
                            id="user-email" 
                            required 
                            placeholder="user@church.org"
                        >
                    </div>
                </div>

                <!-- Name Grid -->
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first-name">
                            <i class="fas fa-user"></i>
                            First Name
                        </label>
                        <input 
                            type="text" 
                            id="first-name" 
                            required 
                            placeholder="John"
                        >
                    </div>

                    <div class="form-group">
                        <label for="last-name">
                            <i class="fas fa-user"></i>
                            Last Name
                        </label>
                        <input 
                            type="text" 
                            id="last-name" 
                            required 
                            placeholder="Doe"
                        >
                    </div>
                </div>

                <!-- Role and Fellowship Grid -->
                <div class="form-grid">
                    <div class="form-group">
                        <label for="user-role">
                            <i class="fas fa-user-tag"></i>
                            Role
                        </label>
                        <select id="user-role" required>
                            <option value="admin">Administrator</option>
                            <option value="pastor">Pastor</option>
                            <option value="pcf">PCF Leader</option>
                            <option value="leader">Fellowship Leader</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="user-fellowship">
                            <i class="fas fa-users"></i>
                            Fellowship
                        </label>
                        <select id="user-fellowship">
                            <option value="">-- No Fellowship --</option>
                            <!-- Populated by JavaScript from backend -->
                        </select>
                        <small class="form-hint">
                            <i class="fas fa-info-circle"></i> 
                            Required for PCF Leaders & Fellowship Leaders
                        </small>
                    </div>
                </div>

                <!-- Status -->
                <div class="form-grid">
                    <div class="form-group">
                        <label for="user-status">
                            <i class="fas fa-toggle-on"></i>
                            Status
                        </label>
                        <select id="user-status" required>
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="user-phone">
                            <i class="fas fa-phone"></i>
                            Phone Number
                        </label>
                        <input 
                            type="tel" 
                            id="user-phone" 
                            placeholder="+233 XX XXX XXXX"
                        >
                    </div>
                </div>

                <!-- Password Section - Make fields not required when hidden -->
                <div class="form-section" id="password-section">
                    <h4>
                        <i class="fas fa-key"></i>
                        Password
                    </h4>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="password" id="password-label">
                                <i class="fas fa-lock"></i>
                                Password
                            </label>
                            <input 
                                type="password" 
                                id="password" 
                                placeholder="••••••••"
                                data-required="true"
                            >
                            <i class="fas fa-eye" id="toggle-password"></i>
                            <div class="password-strength" id="password-strength-indicator">
                                <div class="strength-bar"><div></div></div>
                                <span class="strength-text">Weak</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm-password" id="confirm-password-label">
                                <i class="fas fa-lock"></i>
                                Confirm Password
                            </label>
                            <input 
                                type="password" 
                                id="confirm-password" 
                                placeholder="••••••••"
                                data-required="true"
                            >
                            <i class="fas fa-eye" id="toggle-confirm"></i>
                            <div id="password-match-indicator" style="margin-top: 0.5rem; font-size: 0.8125rem; display: none;"></div>
                        </div>
                    </div>
                    
                    <div class="password-hint">
                        <small>Use 8+ characters with letters, numbers, and symbols for a strong password</small>
                    </div>
                </div>

                <!-- Notes Section -->
                <div class="form-grid single-column">
                    <div class="form-group">
                        <label for="user-notes">
                            <i class="fas fa-sticky-note"></i>
                            Notes
                        </label>
                        <textarea 
                            id="user-notes" 
                            rows="4" 
                            maxlength="300"
                            placeholder="Optional notes about this user..."
                        ></textarea>
                        <div class="textarea-counter">
                            <span id="char-count">0</span>/300
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" id="cancel-user" class="btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary" id="submit-btn">
                        <i class="fas fa-save"></i>
                        <span id="submit-text">Save User</span>
                        <div id="submit-spinner" class="spinner" style="display: none;"></div>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../js/users.js"></script>
    <script src="../js/ui.js"></script>
    <script src="../js/main.js?v=<?php echo time(); ?>"></script>

</body>
</html>