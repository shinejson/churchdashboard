<?php
require_once 'backend/config/security.php';
secure_session_start();

// Check if user is logged in
error_log("INDEX.PHP: Session ID: " . session_id());
error_log("INDEX.PHP: Session User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("INDEX.PHP: HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'unknown'));

if (!isset($_SESSION['user_id'])) {
    error_log("INDEX.PHP: Redirecting to login because user_id is not set");
    header('Location: ' . SITE_URL . '/frontend/pages/login.php');
    exit();
}

// Redirect members to their profile page
$user_role = $_SESSION['role'] ?? 'guest';
if ($user_role === 'member') {
    error_log("INDEX.PHP: Member detected, redirecting to profile");
    header('Location: ' . SITE_URL . '/frontend/pages/profile.php');
    exit();
}

error_log("INDEX.PHP: User is logged in, proceeding to app");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Church Management System</title>
    <link rel="stylesheet" href="frontend/css/style.css">
    <link rel="stylesheet" href="frontend/css/screens.css">
    <link rel="stylesheet" href="frontend/css/icons.css">
    <link rel="stylesheet" href="frontend/css/dashboard.css?v=<?= time() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Restore theme before page renders to avoid flash
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
</head>
<body>
    <?php include __DIR__ . '/frontend/topnav.php'; ?>

    <!-- MAIN APP LAYOUT -->
    <div id="app">
        <!-- SIDEBAR (now sibling of .content) -->
        <?php include __DIR__ . '/frontend/sidebar.php'; ?>

        <!-- MAIN CONTENT AREA -->
        <main class="content">
            <!-- Dashboard Page -->
          <!-- Dashboard Page -->
<!-- Modern Dashboard Page -->
<div id="dashboard-content" class="page-content active">
    <div class="dashboard-header">
        <h2>Church Dashboard</h2>
        <div class="date-range-info">
            <span id="current-date">Tuesday, December 16, 2025</span>
        </div>
    </div>

    <!-- Key Stats Grid -->
 <!-- Key Stats Grid -->
<div class="stats-grid modern">
    <div class="stat-card primary">
        <div class="stat-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
        </div>
        <h3>Total Members</h3>
        <p id="total-members">0</p>
        <small>Active: <span id="active-members">0</span></small>
    </div>

    <div class="stat-card success">
        <div class="stat-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
                <path d="M8 14l4 4 8-8"></path>
            </svg>
        </div>
        <h3>This Week's Attendance</h3>
        <p id="weekly-attendance">0</p>
        <small>Average per service</small>
    </div>

    <div class="stat-card info">
        <div class="stat-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="8.5" cy="7" r="4"></circle>
                <path d="M20 8v8"></path>
                <path d="M24 12h-4"></path>
                <path d="M16 2v4"></path>
                <path d="M20 2v4"></path>
            </svg>
        </div>
        <h3>Active Fellowships</h3>
        <p id="active-fellowships">0</p>
        <small>Out of <span id="total-fellowships">0</span></small>
    </div>

    <div class="stat-card warning">
        <div class="stat-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
        </div>
        <h3>Total Tithes (Current Period)</h3>
        <p id="current-tithe-total">$0.00</p>
        <small id="current-period-name">No active period</small>
    </div>
</div>

    <!-- Main Content: Charts + Widgets -->
    <div class="dashboard-grid">
        <!-- Left: Charts -->
        <div class="charts-container">
            <!-- Attendance Trend -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Attendance Trend (Last 12 Weeks)</h3>
                    <a href="frontend/pages/attendance.php">View Details →</a>
                </div>
                <div style="height: 300px;">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>

            <!-- Financial Trend -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Financial Overview (Tithes vs Expenses)</h3>
                    <a href="frontend/pages/financial.php">View Details →</a>
                </div>
                <div style="height: 300px;">
                    <canvas id="financialChart"></canvas>
                </div>
            </div>

            <!-- Member Distribution -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Member Category Distribution</h3>
                    <a href="frontend/pages/members.php">View Details →</a>
                </div>
                <div style="height: 300px;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>

        </div>

        <!-- Right: Quick Insights -->
    <!-- Right: Quick Insights -->
<div class="widgets-container">
    <!-- Recent Attendance Services -->
    <div class="widget-card">
        <div class="widget-header-with-icon">
            <div class="widget-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </div>
            <h3>Recent Services Attendance</h3>
        </div>
        <div id="recent-services-list">
            <p class="empty">No recent services</p>
        </div>
    </div>

    <!-- Active Fellowships -->
    <div class="widget-card">
        <div class="widget-header-with-icon">
            <div class="widget-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <h3>Active Fellowships</h3>
        </div>
        <div id="fellowships-list">
            <p class="empty">Loading...</p>
        </div>
    </div>

    <!-- Upcoming Tithe Period -->
    <div class="widget-card">
        <div class="widget-header-with-icon">
            <div class="widget-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <h3>Current Tithe Period</h3>
        </div>
        <div id="tithe-period-info">
            <p class="empty">No active period</p>
        </div>
    </div>
</div>
    </div>
</div> 

            <!-- Fellowships Page -->
            <div id="fellowships-content" class="page-content">
                <div class="page-header">
                    <h2>Fellowships Management</h2>
                    <button id="create-fellowship-btn" class="btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 5v14m-7-7h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        Create New Fellowship
                    </button>
                </div>
                <div id="fellowships-list" class="table-container">
                    <div class="loading">
                        <div class="loading-spinner"></div>
                        Loading fellowships...
                    </div>
                </div>
            </div>

            <!-- Users Page -->
            <div id="users-content" class="page-content">
                <div class="page-header">
                    <h2>Users Management</h2>
                    <button id="create-user-btn" class="btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 5v14m-7-7h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        Add New User
                    </button>
                </div>
                <div id="users-list" class="table-container">
                    <div class="empty-state">
                        <div class="empty-state-icon">People</div>
                        <h3>No users found</h3>
                        <p>Get started by adding your first user to the system.</p>
                        <button class="btn-primary" onclick="document.getElementById('create-user-btn').click()">
                            Add First User
                        </button>
                    </div>
                </div>
            </div>

            <!-- Members Page -->
            <div id="members-content" class="page-content">
                <div class="page-header">
                    <h2>Members Management</h2>
                    <button id="create-member-btn" class="btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 5v14m-7-7h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        Add New Member
                    </button>
                </div>
                <div id="members-list" class="table-container">
                    <div class="loading">
                        <div class="loading-spinner"></div>
                        Loading members...
                    </div>
                </div>
            </div>

            <!-- Attendance Page -->
            <div id="attendance-content" class="page-content">
                <div class="page-header">
                    <h2>Attendance Records</h2>
                </div>
                <div id="attendance-form-container" class="attendance-form-card">
                    <h3>Record Attendance</h3>
                    <p>Attendance recording form will appear here.</p>
                </div>
                <div id="attendance-list" class="table-container">
                    <div class="empty-state">
                        <div class="empty-state-icon">Calendar</div>
                        <h3>No attendance records</h3>
                        <p>Start recording attendance to see data here.</p>
                    </div>
                </div>
            </div>

            <!-- Financial Page -->
            <div id="financial-content" class="page-content">
                <div class="page-header">
                    <h2>Financial Records</h2>
                    <button id="record-financial-btn" class="btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 5v14m-7-7h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        Record Payment
                    </button>
                </div>
                <div id="financial-filters" class="filter-section">
                    <h3>Filters</h3>
                    <p>Filter controls will appear here.</p>
                </div>
                <div id="financial-list" class="table-container">
                    <div class="loading">
                        <div class="loading-spinner"></div>
                        Loading financial records...
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- FOOTER -->
    <?php include __DIR__ . '/frontend/footer.php'; ?>

    <!-- MODAL -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Modal Title</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body" id="modal-body">
                <p>Modal content will appear here.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="modal-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="modal-submit">Submit</button>
            </div>
        </div>
    </div>

    <script src="frontend/js/ui.js"></script>
    <script src="frontend/js/main.js?v=<?php echo time(); ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="frontend/js/dashboard.js?v=<?php echo time(); ?>"></script>

    <script>
        // Set global configuration for all pages
        const currentPath = window.location.pathname;
        const appRoot = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
        window.APP_CONFIG = {
            baseUrl: window.location.origin + appRoot,
            loginUrl: window.location.origin + appRoot + 'frontend/pages/login.php'
        };
    </script>
</body>
</html>