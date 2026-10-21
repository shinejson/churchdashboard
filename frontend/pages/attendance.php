<?php 
require_once '../../backend/config/security.php';
secure_session_start();

$user_role = $_SESSION['role'] ?? 'user';
$is_admin = $user_role === 'admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- API Configuration - MUST load first -->
    <?php require_once __DIR__ . '/../includes/config.php'; outputApiConfig(); ?>
    
    <!-- Restore saved theme preference -->
    <script>
        const saved = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const isDark = saved === 'dark' || (!saved && prefersDark);
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    </script>
    <script>
        window.isAdmin = <?= json_encode($is_admin) ?>;
        // Pass current user info to JavaScript
        window.currentUser = {
            id: <?= $_SESSION['user_id'] ?? 'null' ?>,
            role: <?= json_encode($user_role) ?>,
            fellowship_id: <?= $_SESSION['fellowship_id'] ? $_SESSION['fellowship_id'] : 'null' ?>,
            fellowship_name: <?= json_encode($_SESSION['fellowship_name'] ?? null) ?>
        };
    </script>
    <title>Attendance Management - Church Management System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/attendance.css">
    <!-- Add this in the head section of attendance.php -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <!-- Environment and Configuration - MUST load before other scripts -->
    <script src="../js/env.js"></script>
    <script src="../js/config.js"></script>
</head>
<body>
    <?php include '../topnav.php'; ?>
    
<div id="app">
    <?php include '../sidebar.php'; ?>
    
    <main class="content">
        <!-- Main Attendance Tab -->
        <div class="page-content active" id="attendance-tab">
            <!-- Page Header -->
            <div class="page-header">
                <h2>Attendance Management</h2>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                    <button id="record-attendance-btn" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14m-7-7h14"/>
                        </svg>
                        Record Attendance
                    </button>
                    <?php if ($is_admin): ?>
                    <button id="send-report-btn" class="btn btn-info">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        Send Report
                    </button>
                    <button id="manage-services-btn" class="btn btn-success">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14m-7-7h14"/>
                        </svg>
                        Manage Services
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-container">
                <div class="filters-row">
                    <div class="filter-group">
                        <label>Date Range</label>
                        <input type="date" id="start-date" class="filter-input">
                    </div>
                    <div class="filter-group">
                        <label>To</label>
                        <input type="date" id="end-date" class="filter-input">
                    </div>
                    <div class="filter-group">
                        <label>Fellowship</label>
                        <select id="fellowship-filter" class="filter-input">
                            <option value="">All Fellowships</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Service Type</label>
                        <div style="position:relative; display:flex; gap:0.5rem; align-items:center;">
                            <select id="service-filter" class="filter-input" style="flex:1;">
                                <option value="">All Services</option>
                            </select>
                            <?php if ($is_admin): ?>
                            <div id="service-filter-actions" class="dropdown-actions"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="filters-actions">
                    <button id="apply-filters" class="btn btn-secondary btn-sm">Apply Filters</button>
                    <button id="reset-filters" class="btn btn-outline btn-sm">Reset</button>
                    <button id="export-csv" class="btn btn-outline btn-sm">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                        </svg>
                        Export CSV
                    </button>
                </div>
            </div>

            <!-- Complete Stats Dashboard -->
            <div class="stats-dashboard">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon present" loading="eager">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 6L9 17l-5-5"/>
                            </svg>
                        </div>
                        <span class="stat-trend up">↑ 12%</span>
                    </div>
                    <div class="stat-content">
                        <h3>Present Today</h3>
                        <p class="stat-value" id="total-present">0</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon absent">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                        </div>
                        <span class="stat-trend down">↓ 8%</span>
                    </div>
                    <div class="stat-content">
                        <h3>Absent Today</h3>
                        <p class="stat-value" id="total-absent">0</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="8.5" cy="7" r="4"/>
                                <line x1="20" y1="8" x2="20" y2="14"/>
                                <line x1="23" y1="11" x2="17" y2="11"/>
                            </svg>
                        </div>
                        <span class="stat-trend up">New</span>
                    </div>
                    <div class="stat-content">
                        <h3>First Timers</h3>
                        <p class="stat-value" id="total-first-timers">0</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87m-4-12a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-content">
                        <h3>Total Members</h3>
                        <p class="stat-value" id="total-members">0</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon percentage">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 6v6l4 2"/>
                            </svg>
                        </div>
                        <span class="stat-trend up">↑ 5%</span>
                    </div>
                    <div class="stat-content">
                        <h3>Attendance Rate</h3>
                        <p class="stat-value" id="attendance-rate">0%</p>
                    </div>
                </div>
            </div>

            <!-- Table Section -->
            <div class="table-section">
                <div class="table-header">
                    <h3>Attendance Records</h3>
                    <div class="search-box">
                        <input type="text" id="table-search" placeholder="Search records...">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                    </div>
                </div>
                
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="sortable">Date</th>
                                <th class="sortable">Service Type</th>
                                <th class="sortable">Fellowship</th>
                                <th class="sortable">Present</th>
                                <th class="sortable">First Timers</th>
                                <th class="sortable">Absent</th>
                                <th class="sortable">Attendance Rate</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="attendance-tbody">
                            <!-- Data will be inserted here -->
                        </tbody>
                    </table>
                </div>

                <div class="pagination">
                    <div class="pagination-info">
                        Showing <span id="showing-start">0</span> to <span id="showing-end">0</span> of <span id="total-records">0</span> records
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn" id="first-page">First</button>
                        <button class="pagination-btn" id="prev-page">Previous</button>
                        <div class="page-numbers" id="page-numbers"></div>
                        <button class="pagination-btn" id="next-page">Next</button>
                        <button class="pagination-btn" id="last-page">Last</button>
                    </div>
                </div>
            </div>
        </div> <!-- Close attendance-tab -->

        <!-- Reports Tab -->
        <div class="page-content" id="reports-tab">
            <div class="page-header">
                <h2>Attendance Reports</h2>
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <button id="export-report-pdf" class="btn btn-outline">Export PDF</button>
                    <button id="export-report-csv" class="btn btn-outline">Export CSV</button>
                    <button id="back-to-main" class="btn btn-secondary">Back to Attendance</button>
                </div>
            </div>

            <div class="filters-container">
                <div class="filters-row">
                    <div class="filter-group">
                        <label>Report Type</label>
                        <select id="report-type" class="filter-input">
                            <option value="overview">Overview</option>
                            <option value="demographics">Member Demographics</option>
                            <option value="fellowship">Fellowship Analysis</option>
                            <option value="trends">Attendance Trends</option>
                        </select>
                    </div>

                    <div class="filter-group date-range-group">
                        <label>Date Range</label>
                        <div class="date-range-container">
                            <input type="date" id="report-start-date" class="filter-input">
                            <span class="date-separator">to</span>
                            <input type="date" id="report-end-date" class="filter-input">
                        </div>
                    </div>

                    <div class="filter-group">
                        <label>Fellowship</label>
                        <select id="report-fellowship-filter" class="filter-input">
                            <option value="">All Fellowships</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Service</label>
                        <select id="report-service-filter" class="filter-input">
                            <option value="">All Services</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Member Category</label>
                        <select id="report-category-filter" class="filter-input">
                            <option value="">All Categories</option>
                            <option value="adult">Adult</option>
                            <option value="youth">Youth</option>
                            <option value="children">Children</option>
                        </select>
                    </div>

                    <div class="filter-group generate-button-group">
                        <button id="generate-report" class="btn btn-primary" style="display:flex; align-items:center; gap:8px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 3v18h18"/>
                                <path d="M8 21V9h8v12"/>
                                <path d="M12 9v6"/>
                            </svg>
                            Generate Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Report Content -->
            <div id="report-content">
                <div class="stats-grid" id="report-stats"></div>
                
                <!-- Charts Section -->
                <div class="chart-grid">
                    <div class="chart-container">
                        <canvas id="trendChart" width="400" height="400"></canvas>
                    </div>
                    <div class="chart-container" id="demographics-chart-container" style="display: none;">
                        <canvas id="demographicsChart" width="400" height="400"></canvas>
                    </div>
                </div>

                <!-- Reports Table -->
                <div class="table-section" id="reports-table-section" style="margin-top: 2rem;">
                    <div class="table-header">
                        <h3>Report Data</h3>
                    </div>
                    <div class="table-wrapper">
                        <table class="data-table" id="reports-table">
                            <thead id="reports-table-head">
                                <!-- Dynamic headers -->
                            </thead>
                            <tbody id="reports-table-body">
                                <!-- Dynamic content -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div> <!-- Close reports-tab -->
    </main> <!-- Close main.content -->
</div> <!-- Close #app -->

    
    <!-- Attendance Modal -->
    <div id="attendance-modal" class="modal">
        <div class="modal-content" style="max-width: 800px; max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z"/>
                    </svg>
                    <span id="modal-title">Record Attendance</span>
                    <span id="attendance-taken-indicator" style="display:none; margin-left:12px; font-size:0.9rem; padding:4px 8px; border-radius:12px; background:var(--success); color:#fff; align-items:center; display:flex; gap:6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:white;"><path d="M20 6L9 17l-5-5"/></svg>
                        <span>Attendance Taken</span>
                    </span>
                </h3>
                <button class="modal-close" id="close-modal">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="attendance-form" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
                <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 1.5rem;">
                    <input type="hidden" id="attendance-id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date <span class="required">*</span></label>
                            <input type="date" id="attendance-date" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Fellowship <span class="required">*</span></label>
                            <select id="attendance-fellowship" class="form-control" required>
                                <option value="">Select Fellowship</option>
                            </select>
                        </div>

                    <div class="form-group">
    <label>Service Type <span class="required">*</span></label>
    <div style="position:relative; display:flex; gap:0.5rem; align-items:center;">
        <select id="attendance-service" required>
                <option value="">Select Service</option>
                <!-- Populated dynamically -->
            </select>
        <?php if ($is_admin): ?>
        <div style="display:flex; gap:0.25rem;">
            <button type="button" id="refresh-services-modal" class="btn-icon" title="Refresh Services" style="width:38px;height:38px; padding:0; border:1px solid var(--border); background:var(--bg-secondary);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 4v6h-6M1 20v-6h6"/>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                </svg>
            </button>
            <button type="button" id="add-service-quick-modal" class="btn-icon" title="Add New Service" style="width:38px;height:38px; padding:0; border:1px solid var(--border); background:var(--bg-secondary);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 5v14m-7-7h14"/>
                </svg>
            </button>
        </div>
        <?php endif; ?>
        <div id="attendance-service-actions" class="dropdown-actions"></div>
    </div>
</div>
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea id="attendance-notes" class="form-control" placeholder="Add any additional notes..." rows="3"></textarea>
                    </div>

                    <!-- First Timers Section -->
                    <div class="form-group">
                        <label>First Timers / Visitors <span style="color: var(--text-secondary); font-weight: normal;">(not in members list)</span></label>
                        <input type="number" id="first-timers-count" class="form-control" min="0" value="0" placeholder="Enter number of first-timers">
                        <small style="color: var(--text-secondary); display: block; margin-top: 0.5rem;">
                            Enter the number of visitors or first-timers who attended but are not registered members
                        </small>
                    </div>

                    <!-- Members Section -->
                    <div class="members-section">
                        <div class="members-header">
                            <h4>Mark Attendance (<span id="selected-count">0</span> present)</h4>
                            <div class="bulk-actions">
                                <button type="button" class="btn btn-success btn-sm" id="mark-all-present">
                                    Mark All Present
                                </button>
                                <button type="button" class="btn btn-outline btn-sm" id="mark-all-absent">
                                    Mark All Absent
                                </button>
                            </div>
                        </div>
                        <div class="members-list" id="members-list" style="max-height: 300px; overflow-y: auto;">
                            <!-- Members will be loaded here -->
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="flex-shrink: 0;">
                    <button type="button" id="cancel-modal" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Attendance</button>
                </div>
            </form>
        </div>

    </div>

<!-- Enhanced Service Modal -->
<div id="service-modal" class="modal">
    <div class="modal-content" style="max-width: 800px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header">
            <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 5v14m-7-7h14"/>
                </svg>
                <span id="service-modal-title">Manage Services</span>
            </h3>
            <button class="modal-close" id="close-service-modal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
            <!-- Add Service Form -->
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border);">
                <form id="service-form">
                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label>Service Name <span class="required">*</span></label>
                            <input type="text" id="service-name" name="name" class="form-control" 
                                   placeholder="e.g., Sunday Service, Bible Study" required>
                        </div>
                        <div class="form-group" style="margin-top: 1.75rem;">
                            <button type="submit" class="btn btn-primary" style="min-width: 120px;">
                                <span id="service-submit-text">Add Service</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Services Table -->
            <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 0;">
                <div id="services-table-container" style="height: 100%;">
                    <!-- Services table will be loaded here -->
                    <div style="display: flex; justify-content: center; align-items: center; height: 200px;">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal - Add this too if missing -->
<div id="view-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <span>Attendance Details</span>
            </h3>
            <button class="modal-close" onclick="attendanceManager.closeModal('view-modal')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" id="view-modal-body">
            <!-- Content will be dynamically inserted -->
        </div>
    </div>
</div>

<!-- Include Attendance Report Manager -->
<script src="../js/attendance-report.js"></script>
  <?php include '../footer.php'; ?>

    <script src="../js/attendance.js"></script>
    <script src="../js/main.js?v=<?php echo time(); ?>"></script>
</body>
</html>