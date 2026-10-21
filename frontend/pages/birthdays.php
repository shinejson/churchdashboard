<?php
/**
 * Birthdays
 *
 * Today's / upcoming / monthly birthdays, rendered as cards by
 * js/birthdays.js, with a "send a wish" email modal.
 *
 * Rendered through the shared layout. The wish modal keeps its original markup
 * verbatim: its close control is an inline SVG (not the &times; glyph) and the
 * submit button holds two state spans (#wish-submit-text / #wish-spinner) that
 * birthdays.js swaps between.
 */

require_once __DIR__ . '/../layout/bootstrap.php';

$wishModal = layout_modal([
    'id' => 'wish-modal',
    'class' => 'modal',
    'modalContent' => '
        <div class="modal-content wish-modal">
            <div class="modal-header">
                <h3>Send Birthday Wish</h3>
                <button type="button" class="close-modal" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body">
                <form id="wish-form">
                    <input type="hidden" id="wish-member-id" name="member_id">

                    <div style="margin-bottom: 20px;">
                        <p>Send a birthday notification to <strong id="wish-member-name"></strong>.</p>
                        <p style="font-size: 0.85rem; color: var(--text-muted);">This will send an HTML email directly to their inbox.</p>
                    </div>

                    <div class="form-group full-width">
                        <label for="wish-message">Message</label>
                        <textarea id="wish-message" name="message" rows="5">Happy Birthday! Wishing you abundant blessings and joy today and always. From Grace Community Church.</textarea>
                    </div>

                    <div class="form-actions" style="margin-top: 24px;">
                        <button type="button" class="btn-secondary close-modal">Cancel</button>
                        <button type="submit" class="btn-primary" id="submit-wish-btn" style="background:var(--success-color);">
                            <span id="wish-submit-text"><i class="fas fa-paper-plane"></i> Send Wish</span>
                            <span id="wish-spinner" class="spinner" style="display:none;"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>',
]);

$page = layout_page_init([
    'id' => 'birthdays',
    'title' => 'Birthdays',
    'permission' => 'admin_only',
    'contentId' => 'birthdays-content',
    // This page is the one that loads Inter 300-700 rather than 400-800.
    'fontWeights' => '300;400;500;600;700',
    'fontAwesome' => true,
    'css' => ['css/style.css', 'css/screens.css', 'css/birthdays.css'],
    'scripts' => ['js/ui.js', 'js/birthdays.js'],
    'overlays' => $wishModal,
]);

require_once LAYOUT_DIR . '/head.php';
?>

<?php require_once LAYOUT_DIR . '/body-open.php'; ?>

                <?= layout_page_header([
                    'layout' => 'content',
                    'title' => 'Birthdays',
                    // The icon carries an inline colour and an extra class, so
                    // the heading is supplied as markup rather than options.
                    'titleHtml' => '<i class="fas fa-birthday-cake header-icon" style="color:#f59e0b;"></i> Birthdays',
                ]) ?>

                <div class="filters-container">
                    <div class="filter-group" id="birthday-filters">
                        <button class="filter-btn active" data-filter="today">Today's Birthdays</button>
                        <button class="filter-btn" data-filter="upcoming">Upcoming (7 Days)</button>
                        <button class="filter-btn" data-filter="monthly">This Month</button>
                    </div>
                </div>

                <!-- Loader: shown by birthdays.js while a filter is fetching -->
                <div id="loadingIndicator" style="display:none; text-align:center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin fa-3x" style="color:var(--primary-color);"></i>
                    <p style="margin-top: 10px; color:var(--text-muted);">Loading birthdays...</p>
                </div>

                <!-- Grid: cards populated by birthdays.js -->
                <div class="birthdays-grid" id="birthdaysGrid"></div>

<?php require_once LAYOUT_DIR . '/body-close.php'; ?>
