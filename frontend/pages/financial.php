<?php
/**
 * Financial Records Management Page
 * Church Management System
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Records - Church Management System</title>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/screens.css">
    <link rel="stylesheet" href="../css/icons.css">
    <link rel="stylesheet" href="../css/financial.css">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- API Configuration - MUST load first -->
    <?php require_once __DIR__ . '/../includes/config.php'; outputApiConfig(); ?>
    
    <!-- Environment and Configuration - MUST load before other scripts -->
    <script src="../js/env.js"></script>
    <script src="../js/config.js"></script>
</head>
<body>
    <!-- Restore saved theme preference -->
    <script>
        const saved = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const isDark = saved === 'dark' || (!saved && prefersDark);
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    </script>
    <!-- Top Navigation -->
    <?php include '../topnav.php'; ?>
   
    <div id="app">
        <!-- Sidebar -->
        <?php include '../sidebar.php'; ?>
       
        <!-- Main Content -->
        <main class="content">
            <div id="financial-content" class="page-content active">
                
                <!-- Page Header -->
                <div class="page-header">
                    <div class="header-content">
                        <h2>
                            <i class="fas fa-coins header-icon"></i> 
                            Financial Records
                        </h2>
                        <div class="header-actions">
                            <button id="record-financial-btn" class="btn-primary">
                                <i class="fas fa-plus"></i>
                                <span>Record Transaction</span>
                            </button>
                            <button id="add-partnership-arm-btn" class="btn-secondary">
                                <i class="fas fa-handshake"></i>
                                <span>Add Partnership Arm</span>
                            </button>
                            <button id="send-financial-report-btn" class="btn-secondary">
                                <i class="fas fa-paper-plane"></i>
                                <span>Send Report</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="filters-container">
                    <div class="filter-group">
                        <label for="date-from">
                            <i class="fas fa-calendar-alt"></i> From
                        </label>
                        <input type="date" id="date-from" class="filter-input">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date-to">
                            <i class="fas fa-calendar-alt"></i> To
                        </label>
                        <input type="date" id="date-to" class="filter-input">
                    </div>
                    
                    <div class="filter-group">
                        <label for="transaction-type">
                            <i class="fas fa-exchange-alt"></i> Type
                        </label>
                        <select id="transaction-type" class="filter-select">
                            <option value="">All Types</option>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="financial-category">
                            <i class="fas fa-tags"></i> Category
                        </label>
                        <select id="financial-category" class="filter-select">
                            <option value="">All Categories</option>
                            <option value="tithe">Tithe</option>
                            <option value="offering">Offering</option>
                            <option value="donation">Donation</option>
                            <option value="event">Event</option>
                            <option value="utility">Utility</option>
                            <option value="salary">Salary</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="tithe-filter">
                            <i class="fas fa-hand-holding-usd"></i> Show Tithes Only
                        </label>
                        <select id="tithe-filter" class="filter-select">
                            <option value="all">All Transactions</option>
                            <option value="tithe_only">Tithes Only</option>
                            <option value="no_tithe">Exclude Tithes</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button id="apply-financial-filters" class="btn-secondary">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <button id="reset-financial-filters" class="btn-outline">
                            <i class="fas fa-sync"></i> Reset
                        </button>
                    </div>
                </div>

                <!-- Financial Overview Stats -->
                <div class="stats-overview">
                    <div class="stat-card">
                        <div class="stat-icon income">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value amount-income" id="total-income">GHS 0.00</div>
                            <div class="stat-label">Total Income</div>
                            <div class="stat-trend">
                                <span class="trend-positive">
                                    <i class="fas fa-arrow-up"></i> +15%
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon expense">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value amount-expense" id="total-expenses">GHS 0.00</div>
                            <div class="stat-label">Total Expenses</div>
                            <div class="stat-trend">
                                <span class="trend-negative">
                                    <i class="fas fa-arrow-down"></i> -5%
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon balance">
                            <i class="fas fa-balance-scale"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" id="net-balance">GHS 0.00</div>
                            <div class="stat-label">Net Balance</div>
                            <div class="stat-trend">
                                <span id="balance-trend" class="trend-positive">
                                    <i class="fas fa-arrow-up"></i> +10%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Reports -->
                <div class="quick-actions">
                    <h3>
                        <i class="fas fa-chart-line"></i> Quick Reports
                    </h3>
                    <div class="action-buttons">
                        <button class="btn-outline" onclick="financialManager.generateReport('monthly')">
                            <i class="fas fa-calendar-alt"></i> Monthly Report
                        </button>
                        <button class="btn-outline" onclick="financialManager.generateReport('yearly')">
                            <i class="fas fa-calendar"></i> Yearly Report
                        </button>
                        <button class="btn-outline" onclick="financialManager.exportFinancialData()">
                            <i class="fas fa-file-export"></i> Export Data
                        </button>
                    </div>
                </div>

                <!-- Financial Records Table -->
                <div class="table-section">
                    <div class="table-header">
                        <h3>
                            <i class="fas fa-list"></i> Transaction Records
                        </h3>
                        <div class="summary-info">
                            <span>Showing <strong id="records-count">0</strong> transactions</span>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Recorded By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="financial-table-body">
                                    <!-- Dynamic rows populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Empty State -->
                        <div class="empty-state" id="table-empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <h3>No transactions found</h3>
                            <p>Try adjusting your filters or record your first transaction.</p>
                            <button class="btn-primary" id="record-from-empty">
                                <i class="fas fa-plus"></i> Record Transaction
                            </button>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="table-footer">
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

                <!-- Tithe Records Table -->
                <div class="table-section" id="tithe-table-section">
                    <div class="table-header">
                        <h3>
                            <i class="fas fa-hand-holding-usd"></i> Tithe Records
                        </h3>
                        <div class="summary-info">
                            <span>Showing <strong id="tithe-count">0</strong> tithe contributions</span>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Member</th>
                                        <th>Fellowship</th>
                                        <th>Amount</th>
                                        <th>Period</th>
                                        <th>Payment Method</th>
                                        <th>Type</th>
                                        <th>Recorded By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="tithe-table-body">
                                    <!-- Dynamic rows populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Empty State -->
                        <div class="empty-state" id="tithe-empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <h3>No tithe records found</h3>
                            <p>Start recording tithe contributions using the form above.</p>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Footer -->
    <?php include '../footer.php'; ?>

    <!-- ========================================
         MODALS SECTION
    ========================================= -->

    <!-- Financial Transaction Modal -->
    <div id="financial-modal" class="modal">
        <div class="modal-overlay" id="modal-overlay"></div>
        <div class="modal-content large-modal">
            
            <!-- Modal Header -->
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div>
                        <h3>Record Financial Transaction</h3>
                        <p class="subtitle">Multi-step transaction form (5 steps)</p>
                    </div>
                </div>
                <button class="close-btn" id="close-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="modal-body">
                
                <!-- Progress Steps Indicator -->
                <div class="form-steps-container">
                    <div class="form-steps">
                        <div class="step active" data-step="1">
                            <div class="step-number">1</div>
                            <div class="step-label">Basic Info</div>
                        </div>
                        <div class="step" data-step="2">
                            <div class="step-number">2</div>
                            <div class="step-label">Offering</div>
                        </div>
                        <div class="step" data-step="3">
                            <div class="step-number">3</div>
                            <div class="step-label">Tithe</div>
                        </div>
                        <div class="step" data-step="4">
                            <div class="step-number">4</div>
                            <div class="step-label">Partnership</div>
                        </div>
                        <div class="step" data-step="5">
                            <div class="step-number">5</div>
                            <div class="step-label">Review</div>
                        </div>
                    </div>
                </div>

                <!-- Form Container -->
                <div class="form-container">
                    
                    <!-- Step 1: Basic Information -->
                    <div class="form-step active" id="step-1">
                        <div class="form-step-content">
                            <h4>Basic Information</h4>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="transaction-date">
                                        <i class="fas fa-calendar"></i> Date *
                                    </label>
                                    <input type="date" id="transaction-date" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="fellowship">
                                        <i class="fas fa-users"></i> Fellowship *
                                    </label>
                                    <select id="fellowship" required>
                                        <option value="">Select Fellowship</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="service">
                                        <i class="fas fa-church"></i> Service *
                                    </label>
                                    <select id="service" required>
                                        <option value="">Select Service</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group full-width">
                                <h5>Attendance Overview</h5>
                                <div class="attendance-summary">
                                    <div class="attendance-category">
                                        <span class="category-label">Adults</span>
                                        <span class="category-count" id="adult-count">0</span>
                                    </div>
                                    <div class="attendance-category">
                                        <span class="category-label">Teens</span>
                                        <span class="category-count" id="teen-count">0</span>
                                    </div>
                                    <div class="attendance-category">
                                        <span class="category-label">Children</span>
                                        <span class="category-count" id="child-count">0</span>
                                    </div>
                                    <div class="attendance-category">
                                        <span class="category-label">First Timers</span>
                                        <span class="category-count" id="first-timers-count">0</span>
                                    </div>
                                    <div class="attendance-category total">
                                        <span class="category-label">Total</span>
                                        <span class="category-count" id="total-count">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Offering Information -->
                    <div class="form-step" id="step-2">
                        <div class="form-step-content">
                            <h4>Offering Details</h4>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="offering-amount">
                                        <i class="fas fa-money-bill"></i> Offering Amount (GHS) *
                                    </label>
                                    <input type="number" id="offering-amount" step="0.01" min="0" required placeholder="0.00">
                                </div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="offering-notes">
                                    <i class="fas fa-sticky-note"></i> Offering Notes
                                </label>
                                <textarea id="offering-notes" rows="3" placeholder="Additional notes about the offering..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Tithe Information -->
                    <div class="form-step" id="step-3">
                        <div class="form-step-content">
                            <h4>Tithe Contributions</h4>
                            
                            <div class="tithe-section">
                                <!-- Member Search and Selection -->
                                <div class="tithe-controls">
                                    <div class="form-group" style="flex: 2;">
                                        <label for="tithe-member-search">
                                            <i class="fas fa-search"></i> Search Members from Selected Fellowship
                                        </label>
                                        <div class="searchable-select">
                                            <input type="text" id="tithe-member-search" placeholder="Type to search members..." autocomplete="off">
                                            <div class="dropdown-icon">
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div class="dropdown-options" id="tithe-member-options"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group" style="flex: 1;">
                                        <label for="tithe-amount">
                                            <i class="fas fa-money-bill"></i> Tithe Amount (GHS)
                                        </label>
                                        <input type="number" id="tithe-amount" step="0.01" min="0" placeholder="0.00">
                                    </div>
                                    
                                    <div class="form-group" style="flex: 1; margin-top: 24px;">
                                        <button type="button" class="btn-primary add-tithe-member-btn" id="add-tithe-member" style="width: 100%;">
                                            <i class="fas fa-plus"></i> Add Tithe
                                        </button>
                                    </div>
                                </div>

                                <!-- Added Tithe Members List -->
                                <div class="member-list-container">
                                    <h5>Tithe Contributions</h5>
                                    <div class="member-list" id="tithe-members-list">
                                        <div class="empty-state" style="text-align: center; padding: 20px; color: var(--text-muted);">
                                            <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                            <p>No tithe contributions added yet</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tithe Total -->
                                <div class="form-group full-width">
                                    <label for="tithe-total">
                                        <i class="fas fa-calculator"></i> Total Tithe Contributions (GHS)
                                    </label>
                                    <input type="number" id="tithe-total" step="0.01" min="0" readonly placeholder="0.00" style="background-color: var(--background-color); font-weight: bold;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Partnership Information -->
                    <div class="form-step" id="step-4">
                        <div class="form-step-content">
                            <h4>Partnership Contributions</h4>
                            
                            <div class="partnership-section">
                                <!-- Partnership Arm Selection -->
                                <div class="partnership-arm-section">
                                    <h5>Select Partnership Arm</h5>
                                    <div class="form-group full-width">
                                        <select id="partnership-arms" required>
                                            <option value="">Select Partnership Arm</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Member Search and Selection -->
                                <div class="partnership-controls">
                                    <div class="form-group" style="flex: 2;">
                                        <label for="partnership-member-search">
                                            <i class="fas fa-search"></i> Search Members from Selected Fellowship
                                        </label>
                                        <div class="searchable-select">
                                            <input type="text" id="partnership-member-search" placeholder="Type to search members..." autocomplete="off">
                                            <div class="dropdown-icon">
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div class="dropdown-options" id="partnership-member-options"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group" style="flex: 1;">
                                        <label for="partnership-member-amount">
                                            <i class="fas fa-money-bill"></i> Partnership Amount (GHS)
                                        </label>
                                        <input type="number" id="partnership-member-amount" step="0.01" min="0" placeholder="0.00">
                                    </div>
                                    
                                    <div class="form-group" style="flex: 1; margin-top: 24px;">
                                        <button type="button" class="btn-primary add-member-btn" id="add-partnership-member" style="width: 100%;">
                                            <i class="fas fa-plus"></i> Add Member
                                        </button>
                                    </div>
                                </div>

                                <!-- Added Members List -->
                                <div class="member-list-container">
                                    <h5>Added Members for Current Partnership</h5>
                                    <div class="member-list" id="partnership-members-list">
                                        <div class="empty-state" style="text-align: center; padding: 20px; color: var(--text-muted);">
                                            <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                            <p>No members added yet</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Partnership Total and Add Button -->
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="partnership-total">
                                            <i class="fas fa-calculator"></i> Current Partnership Total (GHS)
                                        </label>
                                        <input type="number" id="partnership-total" step="0.01" min="0" readonly placeholder="0.00" style="background-color: var(--background-color);">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="button" class="btn-secondary" id="add-partnership-record" style="width: 100%; margin-top: 8px;">
                                            <i class="fas fa-plus-circle"></i> Add This Partnership
                                        </button>
                                    </div>
                                </div>

                                <!-- Partnership Records Container -->
                                <div id="partnership-records-container"></div>

                                <!-- Grand Total -->
                                <div class="form-group full-width">
                                    <label for="partnership-grand-total">
                                        <i class="fas fa-calculator"></i> Total Partnership Contributions (GHS)
                                    </label>
                                    <input type="number" id="partnership-grand-total" step="0.01" min="0" readonly placeholder="0.00" style="background-color: var(--background-color); font-weight: bold;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5: Review and Submit -->
                    <div class="form-step" id="step-5">
                        <div class="form-step-content">
                            <h4>Review Transaction</h4>
                            
                            <div class="review-section">
                                <div class="review-item">
                                    <span class="review-label">Date:</span>
                                    <span class="review-value" id="review-date">-</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Fellowship:</span>
                                    <span class="review-value" id="review-fellowship">-</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Service:</span>
                                    <span class="review-value" id="review-service">-</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Offering Amount:</span>
                                    <span class="review-value" id="review-offering">GHS 0.00</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Tithe Contributions:</span>
                                    <span class="review-value" id="review-tithe-count">0 members</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Tithe Total:</span>
                                    <span class="review-value" id="review-tithe">GHS 0.00</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Partnership Arm:</span>
                                    <span class="review-value" id="review-partnership-arm">-</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Partnership Members:</span>
                                    <span class="review-value" id="review-partnership-members">0</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Partnership Total:</span>
                                    <span class="review-value" id="review-partnership">GHS 0.00</span>
                                </div>
                                <div class="review-item total-item">
                                    <span class="review-label">Grand Total:</span>
                                    <span class="review-value" id="review-total">GHS 0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="modal-footer">
                <div class="form-actions">
                    <button type="button" id="prev-step" class="btn-secondary" style="display: none;">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="button" id="next-step" class="btn-primary">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="button" id="submit-transaction" class="btn-primary" style="display: none;">
                        <i class="fas fa-save"></i> Submit Transaction
                    </button>
                    <button type="button" id="cancel-financial" class="btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
            
        </div>
    </div>

    <!-- Report Modal -->
    <div id="report-modal" class="modal">
        <div class="modal-overlay"></div>
        <div class="modal-content large-modal">
            
            <div class="modal-header">
                <h3 id="report-title">Financial Report</h3>
                <button class="close-btn" id="close-report">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body" id="report-content">
                <!-- Report content populated by JavaScript -->
            </div>
            
            <div class="modal-footer">
                <button class="btn-outline" id="print-report">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="btn-primary" id="close-report-btn">Close</button>
            </div>
            
        </div>
    </div>

    <!-- Partnership Arm Management Modal -->
    <div id="partnership-arm-modal" class="modal">
        <div class="modal-overlay" id="partnership-arm-overlay"></div>
        <div class="modal-content large-modal">
            
            <!-- Modal Header -->
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div>
                        <h3>Manage Partnership Arms</h3>
                        <p class="subtitle">Add, edit, and manage partnership arms</p>
                    </div>
                </div>
                <button class="close-btn" id="close-partnership-arm-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="modal-body">
                
                <!-- Add New Partnership Arm Form -->
                <div class="form-section">
                    <h4>
                        <i class="fas fa-plus-circle"></i> Add New Partnership Arm
                    </h4>
                    
                    <form id="partnership-arm-form" class="partnership-arm-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="partnership-arm-name">
                                    <i class="fas fa-tag"></i> Arm Name *
                                </label>
                                <input type="text" id="partnership-arm-name" required placeholder="Enter partnership arm name">
                            </div>
                            
                            <div class="form-group">
                                <label for="partnership-arm-active">
                                    <i class="fas fa-toggle-on"></i> Status
                                </label>
                                <div class="form-checkbox">
                                    <input type="checkbox" id="partnership-arm-active" checked>
                                    <label for="partnership-arm-active">Active</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="partnership-arm-description">
                                <i class="fas fa-align-left"></i> Description
                            </label>
                            <textarea id="partnership-arm-description" placeholder="Enter description (optional)" rows="3"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" id="cancel-partnership-arm" class="btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="button" id="save-partnership-arm" class="btn-primary">
                                <i class="fas fa-save"></i> Save Partnership Arm
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Partnership Arms Table -->
                <div class="table-section" style="margin-top: 32px; background: transparent; box-shadow: none; padding: 0;">
                    <div class="table-header">
                        <h4>
                            <i class="fas fa-list"></i> Existing Partnership Arms
                        </h4>
                        <div class="summary-info">
                            <span>Showing <strong id="partnership-arms-count">0</strong> arms</span>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Created Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="partnership-arms-table-body">
                                    <!-- Dynamic rows populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Empty State -->
                        <div class="empty-state" id="partnership-arms-empty-state" style="margin: 20px 0;">
                            <div class="empty-icon">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <h4>No partnership arms found</h4>
                            <p>Get started by creating your first partnership arm using the form above.</p>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script>
        // Set global configuration for all pages
        // Use the current page's protocol (http or https) to avoid mixed-content issues
        (function () {
            const protocol = window.location.protocol; // "http:" or "https:"
            const host = window.location.host;         // includes hostname and port if any
            const basePath = '/church/';

            window.APP_CONFIG = {
                baseUrl: protocol + '//' + host + basePath,
                loginUrl: protocol + '//' + host + basePath + 'frontend/pages/login.php'
            };
        })();
    </script>
    <script src="../js/ui.js"></script>
    <script src="../js/financial.js"></script>
    <script src="../js/financial-report.js?v=<?php echo time(); ?>"></script>
    <script src="../js/main.js?v=<?php echo time(); ?>"></script>
 
</body>
</html>