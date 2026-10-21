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

    <title>Birthdays - Church Management System</title>
    
    <!-- API Configuration - MUST load first -->
    <?php require_once __DIR__ . '/../includes/config.php'; outputApiConfig(); ?>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/screens.css">
    <link rel="stylesheet" href="../css/birthdays.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
    
    <?php include_once '../topnav.php'; ?>
    
    <div id="app">
        <?php include_once '../sidebar.php'; ?>
        
        <main class="content">
            <div id="birthdays-content" class="page-content active">
                
                <div class="page-header">
                    <div class="header-content">
                        <h2><i class="fas fa-birthday-cake header-icon" style="color:#f59e0b;"></i> Birthdays</h2>
                    </div>
                </div>

                <div class="filters-container">
                    <div class="filter-group" id="birthday-filters">
                        <button class="filter-btn active" data-filter="today">Today's Birthdays</button>
                        <button class="filter-btn" data-filter="upcoming">Upcoming (7 Days)</button>
                        <button class="filter-btn" data-filter="monthly">This Month</button>
                    </div>
                </div>

                <!-- Loader -->
                <div id="loadingIndicator" style="display:none; text-align:center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin fa-3x" style="color:var(--primary-color);"></i>
                    <p style="margin-top: 10px; color:var(--text-muted);">Loading birthdays...</p>
                </div>

                <!-- Grid -->
                <div class="birthdays-grid" id="birthdaysGrid">
                    <!-- Cards will be populated here -->
                </div>

            </div>
        </main>
    </div>

    <?php include_once '../footer.php'; ?>

    <!-- WISH MODAL -->
    <div id="wish-modal" class="modal" aria-hidden="true">
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
        </div>
    </div>

    <script src="../js/ui.js"></script>
    <script src="../js/birthdays.js?v=<?php echo time(); ?>"></script>
</body>
</html>
