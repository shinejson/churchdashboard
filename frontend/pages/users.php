<?php
/**
 * Users
 *
 * System user accounts (roles, fellowships, status) managed by js/users.js.
 * Rendered through the shared layout.
 *
 * Structures kept as page-local markup because they are unique to this screen
 * and forcing them through a shared component would change the DOM the CSS and
 * users.js bind to:
 *   - the sortable table header (.th-content + .sort-icon, with a centre-aligned
 *     Actions column),
 *   - the .empty-icon empty state,
 *   - the prev/next pagination bar,
 *   - the user modal, which nests .modal-overlay and .modal-header-content
 *     inside #user-modal and puts <form id="user-form"> as a direct child of
 *     .modal-content rather than inside .modal-body.
 *
 * Script order is preserved exactly: users.js loads BEFORE ui.js and main.js
 * here, unlike every other page.
 */

require_once __DIR__ . '/../layout/bootstrap.php';

/**
 * Build one sortable <th> for the users table.
 *
 * @param string      $sort  value for the data-sort attribute
 * @param string      $label column heading
 * @param string|null $style optional inline style for the <th>
 * @param string|null $contentStyle optional inline style for the .th-content div
 */
$sortableTh = static function (string $sort, string $label, ?string $style = null, ?string $contentStyle = null): string {
    return '<th data-sort="' . htmlspecialchars($sort, ENT_QUOTES) . '"'
        . ($style !== null ? ' style="' . htmlspecialchars($style, ENT_QUOTES) . '"' : '')
        . ' class="' . ($sort === '' ? 'text-center' : '') . '">'
        . '<div class="th-content"' . ($contentStyle !== null ? ' style="' . htmlspecialchars($contentStyle, ENT_QUOTES) . '"' : '') . '>'
        . '<span>' . htmlspecialchars($label, ENT_QUOTES) . '</span>'
        // The Actions column is not sortable and carries no sort icon.
        . ($sort !== '' ? '<i class="fas fa-sort sort-icon"></i>' : '')
        . '</div>'
        . '</th>';
};

$userModal = <<<'HTML'
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
                            Required for PCF Leaders &amp; Fellowship Leaders
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

                <!-- Password Section - fields are not required while hidden -->
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
HTML;

$page = layout_page_init([
    'id' => 'users',
    'title' => 'Users Management',
    'permission' => 'users',
    'contentId' => 'users-content',
    'css' => ['css/style.css', 'css/screens.css', 'css/icons.css', 'css/users.css'],
    // users.js first - matching the original load order on this page.
    'scripts' => ['js/users.js', 'js/ui.js', 'js/main.js'],
    'overlays' => $userModal,
]);

require_once LAYOUT_DIR . '/head.php';
?>

<?php require_once LAYOUT_DIR . '/body-open.php'; ?>

                <!-- Page Header -->
                <?= layout_page_header([
                    'layout' => 'content',
                    'titleHtml' => '<i class="fas fa-users-cog header-icon"></i> Users Management',
                    'search' => [
                        'variant' => 'container',
                        'id' => 'global-search',
                        'placeholder' => 'Search users...',
                    ],
                    'actions' => [
                        // users.js does not bind this button, so the navigation
                        // stays inline exactly as the original page had it.
                        '<button id="link-members-btn" class="btn-secondary" onclick="window.location.href=\'link_members.php\'" title="Link user accounts to member records">'
                            . '<i class="fas fa-link"></i> Link to Members'
                            . '</button>',
                        layout_button([
                            'id' => 'create-user-btn',
                            'classes' => 'btn-primary',
                            'icon' => 'fa-user-plus',
                            'label' => 'Add New User',
                            'wrapLabel' => true,
                        ]),
                    ],
                ]) ?>

                <!-- Stats Overview -->
                <?= layout_stats_overview([
                    layout_stat_card([
                        'icon' => 'fa-users',
                        'contentWrapper' => true,
                        'valueId' => 'total-users-count',
                        'label' => 'Total Users',
                        'trend' => ['html' => '<span class="trend-positive"><i class="fas fa-arrow-up"></i> +5%</span>'],
                    ]),
                    layout_stat_card([
                        'icon' => 'fa-check-circle',
                        'iconModifier' => 'active',
                        'contentWrapper' => true,
                        'valueId' => 'active-users-count',
                        'label' => 'Active Users',
                        'trend' => ['html' => '<span class="trend-positive"><i class="fas fa-arrow-up"></i> +3%</span>'],
                    ]),
                    layout_stat_card([
                        'icon' => 'fa-user-shield',
                        'iconModifier' => 'admin',
                        'contentWrapper' => true,
                        'valueId' => 'admin-count',
                        'label' => 'Administrators',
                        'trend' => ['html' => '<span class="trend-neutral"><i class="fas fa-minus"></i> 0%</span>'],
                    ]),
                ]) ?>

                <!-- Users Table Section -->
                <div class="table-section">
                    <div class="table-header">
                        <h3><i class="fas fa-list"></i> System Users</h3>
                        <div class="table-controls">
                            <div class="filter-group">
                                <?= layout_select([
                                    'id' => 'role-filter',
                                    'class' => null,
                                    'options' => [
                                        ['value' => '', 'label' => 'All Roles'],
                                        ['value' => 'admin', 'label' => 'Administrator'],
                                        ['value' => 'pastor', 'label' => 'Pastor'],
                                        ['value' => 'pcf', 'label' => 'PCF Leader'],
                                        ['value' => 'leader', 'label' => 'Fellowship Leader'],
                                    ],
                                ]) ?>
                                <?= layout_select([
                                    'id' => 'status-filter',
                                    'class' => null,
                                    'options' => [
                                        ['value' => '', 'label' => 'All Status'],
                                        ['value' => 'active', 'label' => 'Active'],
                                        ['value' => 'inactive', 'label' => 'Inactive'],
                                        ['value' => 'suspended', 'label' => 'Suspended'],
                                    ],
                                ]) ?>
                            </div>
                            <div class="action-buttons">
                                <?= layout_button(['id' => 'export-users', 'classes' => 'btn-secondary', 'icon' => 'fa-file-export', 'label' => 'Export']) ?>
                                <?= layout_button(['id' => 'print-users', 'classes' => 'btn-secondary', 'icon' => 'fa-print', 'label' => 'Print']) ?>
                            </div>
                        </div>
                    </div>

                    <div class="table-container">
                        <div class="table-wrapper">
                            <table class="data-table sortable">
                                <thead>
                                    <tr>
                                        <?= $sortableTh('name', 'User') ?>
                                        <?= $sortableTh('email', 'Email') ?>
                                        <?= $sortableTh('role', 'Role') ?>
                                        <?= $sortableTh('fellowship', 'Fellowship') ?>
                                        <?= $sortableTh('status', 'Status') ?>
                                        <?= $sortableTh('last_login', 'Last Login') ?>
                                        <?= $sortableTh('created_at', 'Created') ?>
                                        <?= $sortableTh('', 'Actions', null, 'justify-content: center;') ?>
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

<?php require_once LAYOUT_DIR . '/body-close.php'; ?>
