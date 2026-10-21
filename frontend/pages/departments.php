<?php
require_once '../../backend/config/security.php';
secure_session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo get_csrf_token(); ?>">
    <title>Departments • Church Management System</title>
    
    <!-- API Configuration - MUST load first -->
    <?php require_once __DIR__ . '/../includes/config.php'; outputApiConfig(); ?>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/fellowships.css">
    <link rel="stylesheet" href="../css/icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    
    <!-- Environment and Configuration -->
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
            <div class="page-content active">
                <!-- Page Header -->
                <div class="page-header">
                    <h2><i class="fas fa-building"></i> Departments Management</h2>
                    <button id="create-dept-btn" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Department
                    </button>
                </div>

                <!-- Stats Overview -->
                <div class="stats-overview">
                    <div class="stat-card">
                        <div class="stat-icon total">
                            <i class="fas fa-building"></i>
                        </div>
                        <span class="stat-value" id="total-depts">0</span>
                        <span class="stat-label">Total Departments</span>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon active">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <span class="stat-value" id="active-depts">0</span>
                        <span class="stat-label">Active Departments</span>
                    </div>
                </div>

                <!-- Search and Filter Bar -->
                <div class="search-export-bar">
                    <div class="search-box">
                        <input type="text" id="search-input" placeholder="Search departments by name or description...">
                        <i class="fas fa-search"></i>
                    </div>
                    <button class="btn btn-outline" id="export-btn">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>

                <!-- Table Container -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">
                                    <span style="cursor: pointer;" data-sort="name">
                                        Name <i class="fas fa-sort"></i>
                                    </span>
                                </th>
                                <th style="width: 50%;">Description</th>
                                <th style="width: 20%; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dept-table-body">
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 2rem;">
                                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                                    <p style="margin-top: 1rem; color: var(--text-muted);">Loading departments...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div id="empty-state" style="display: none; text-align: center; padding: 3rem;">
                    <i class="fas fa-building" style="font-size: 4rem; color: var(--text-muted); opacity: 0.3;"></i>
                    <h3 style="margin-top: 1rem; color: var(--text-color);">No Departments Found</h3>
                    <p style="color: var(--text-muted); margin-top: 0.5rem;">Get started by creating your first department.</p>
                    <button class="btn btn-primary" style="margin-top: 1.5rem;" onclick="document.getElementById('create-dept-btn').click()">
                        <i class="fas fa-plus"></i> Create Department
                    </button>
                </div>
            </div>
        </main>
    </div>

    <!-- Department Modal -->
    <div id="dept-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title"><i class="fas fa-building"></i> Create New Department</h3>
                <button class="close" type="button">&times;</button>
            </div>
            <div class="modal-body">
                <form id="dept-form" class="modal-form">
                    <input type="hidden" id="dept-id">
                    
                    <div class="form-group">
                        <label for="dept-name">
                            Department Name <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="dept-name" 
                            required 
                            placeholder="e.g. Choir, Ushering, Media Team"
                            autocomplete="off"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="dept-description">Description</label>
                        <textarea 
                            id="dept-description" 
                            rows="4" 
                            placeholder="Describe the purpose and responsibilities of this department..."
                        ></textarea>
                        <span class="form-hint">Optional: Provide details about what this department does</span>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline close-modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../footer.php'; ?>
    <script src="../js/ui.js"></script>
    <script src="../js/main.js"></script>
    <script src="../js/departments.js"></script>
</body>
</html>
