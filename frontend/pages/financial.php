<?php
/**
 * Financial Records
 *
 * Offerings, tithes and partnership contributions: filters, overview stats,
 * quick reports, the transaction and tithe tables, and a five-step recording
 * wizard. Behaviour lives in js/financial.js and js/financial-report.js, so
 * every id and class below is a hook those files bind to and is preserved
 * exactly.
 *
 * Structural notes:
 *   - this page has NO footer include, unlike most others, so showFooter is off;
 *   - its icons come from css/icons.css (the project's dependency-free emoji
 *     stand-in for Font Awesome), NOT from the Font Awesome CDN. Several classes
 *     used here (fa-coins, fa-money-bill-wave, fa-hand-holding-usd, fa-receipt,
 *     fa-chevron-left/right, ...) have no mapping in icons.css and therefore
 *     render as nothing. That is how the page ships today and is deliberately
 *     left alone;
 *   - `report-content` is the id of the report modal's body here, which is why
 *     the layout must never put an id on <main>.
 *
 * Changes made while moving this onto the shared layout:
 *   - the page had NO session handling whatsoever: it never called
 *     secure_session_start() and performed no access check, so any visitor could
 *     load it by URL. It now requires an authenticated session via
 *     'requireLogin'. That is deliberately not a role gate, so every role that
 *     can reach it today still can;
 *   - a CSRF meta tag is now emitted (the page had none, while financial.js
 *     posts to the backend);
 *   - the trailing inline `window.APP_CONFIG` block was dropped. It hard-coded a
 *     '/church/' install path and then overwrote the richer APP_CONFIG the
 *     layout publishes (pageId, role, isAdmin, csrfToken) with only two keys.
 *     The layout derives its base URL from the real request path, so this also
 *     fixes a wrong URL on any non-/church/ install;
 *   - the `<!-- MODALS SECTION -->` banner comment was removed.
 */

require_once __DIR__ . '/../layout/bootstrap.php';

/**
 * The five-step transaction recording wizard.
 *
 * Non-standard on purpose: it has a `.modal-overlay`, a `.modal-header-content`
 * with an icon, a `.form-steps` progress indicator, five `.form-step` panels of
 * which only the active one shows, and a `.modal-footer` holding the
 * prev/next/submit/cancel controls. financial.js drives all of it by id, so the
 * structure is reproduced verbatim.
 */
$financialModal = <<<'HTML'
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
HTML;

/** Rendered financial report. Its body id is `report-content`. */
$reportModal = <<<'HTML'
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
HTML;

/** Add / edit partnership arms. */
$partnershipArmModal = <<<'HTML'
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
HTML;

$page = layout_page_init([
    'id' => 'financial',
    'title' => 'Financial Records',
    'titleSuffix' => 'Church Management System',
    // Was completely unguarded - it never even started a session.
    'requireLogin' => true,
    'contentId' => 'financial-content',
    'fontWeights' => '300;400;500;600;700',
    'css' => ['css/style.css', 'css/screens.css', 'css/icons.css', 'css/financial.css'],
    'scripts' => ['js/ui.js', 'js/financial.js', 'js/financial-report.js', 'js/main.js'],
    'overlays' => $financialModal . "\n\n" . $reportModal . "\n\n" . $partnershipArmModal,
    // This page ships without the shared footer.
    'showFooter' => false,
]);

require_once LAYOUT_DIR . '/head.php';
?>

<?php require_once LAYOUT_DIR . '/body-open.php'; ?>

                <!-- Page Header -->
                <?= layout_page_header([
                    'layout' => 'content',
                    'titleHtml' => '<i class="fas fa-coins header-icon"></i> Financial Records',
                    'actions' => [
                        layout_button([
                            'id' => 'record-financial-btn',
                            'classes' => 'btn-primary',
                            'icon' => 'fa-plus',
                            'label' => 'Record Transaction',
                            'wrapLabel' => true,
                        ]),
                        layout_button([
                            'id' => 'add-partnership-arm-btn',
                            'classes' => 'btn-secondary',
                            'icon' => 'fa-handshake',
                            'label' => 'Add Partnership Arm',
                            'wrapLabel' => true,
                        ]),
                        layout_button([
                            'id' => 'send-financial-report-btn',
                            'classes' => 'btn-secondary',
                            'icon' => 'fa-paper-plane',
                            'label' => 'Send Report',
                            'wrapLabel' => true,
                        ]),
                    ],
                ]) ?>

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
                        <?= layout_button(['id' => 'apply-financial-filters', 'classes' => 'btn-secondary', 'icon' => 'fa-filter', 'label' => 'Apply']) ?>
                        <?= layout_button(['id' => 'reset-financial-filters', 'classes' => 'btn-outline', 'icon' => 'fa-sync', 'label' => 'Reset']) ?>
                    </div>
                </div>

                <!-- Financial Overview Stats -->
                <?= layout_stats_overview([
                    layout_stat_card([
                        'icon' => 'fa-arrow-up',
                        'iconModifier' => 'income',
                        'contentWrapper' => true,
                        'valueId' => 'total-income',
                        'valueClass' => 'amount-income',
                        'value' => 'GHS 0.00',
                        'label' => 'Total Income',
                        'trend' => ['html' => '<span class="trend-positive">'
                            . '<i class="fas fa-arrow-up"></i> +15%'
                            . '</span>'],
                    ]),
                    layout_stat_card([
                        'icon' => 'fa-arrow-down',
                        'iconModifier' => 'expense',
                        'contentWrapper' => true,
                        'valueId' => 'total-expenses',
                        'valueClass' => 'amount-expense',
                        'value' => 'GHS 0.00',
                        'label' => 'Total Expenses',
                        'trend' => ['html' => '<span class="trend-negative">'
                            . '<i class="fas fa-arrow-down"></i> -5%'
                            . '</span>'],
                    ]),
                    layout_stat_card([
                        'icon' => 'fa-balance-scale',
                        'iconModifier' => 'balance',
                        'contentWrapper' => true,
                        'valueId' => 'net-balance',
                        'value' => 'GHS 0.00',
                        'label' => 'Net Balance',
                        // The trend span carries its own id here, which
                        // financial-report.js updates.
                        'trend' => ['html' => '<span id="balance-trend" class="trend-positive">'
                            . '<i class="fas fa-arrow-up"></i> +10%'
                            . '</span>'],
                    ]),
                ]) ?>

                <!-- Quick Reports -->
                <div class="quick-actions">
                    <h3>
                        <i class="fas fa-chart-line"></i> Quick Reports
                    </h3>
                    <div class="action-buttons">
                        <!-- financialManager is the global financial.js publishes,
                             so these inline handlers stay as shipped. -->
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

<?php require_once LAYOUT_DIR . '/body-close.php'; ?>
