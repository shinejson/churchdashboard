<?php
/**
 * Sidebar Navigation Component
 * Handles user authentication, permissions, and navigation menu
 */

// Security & Session Management
require_once __DIR__ . '../../backend/config/security.php';
secure_session_start();

// Authentication Check
if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/frontend/pages/login.php');
    exit();
}

// User Data & Permissions
$role = $_SESSION['role'] ?? 'guest';
$perms = get_db_permissions();

// Fetch Current User Details
try {
    $db = (new Database())->getConnection();
    
    // First, update last_seen for current user to ensure they show as online
    $updateStmt = $db->prepare("UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = ?");
    $updateStmt->execute([$_SESSION['user_id']]);
    
    // Then fetch user details
    $stmt = $db->prepare("SELECT username, role, avatar, last_seen FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    // Fetch Church Name from Settings
    $settingsStmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'church_name' AND category = 'general'");
    $settingsStmt->execute();
    $churchSetting = $settingsStmt->fetch(PDO::FETCH_ASSOC);
    $churchName = $churchSetting['value'] ?? 'Grace Community';
    
} catch (Exception $e) {
    error_log("Sidebar data fetch error: " . $e->getMessage());
    $churchName = 'Grace Community';
    $user = [
        'username' => $_SESSION['username'] ?? 'User',
        'role' => $role,
        'avatar' => 'assets/avatar-placeholder.png',
        'last_seen' => date('Y-m-d H:i:s')
    ];
}

// User Status & Activity
$avatar = $user['avatar'] ?? 'assets/avatar-placeholder.png';

// Check if user is online - if last_seen is NULL or empty, consider them online (just logged in)
if (empty($user['last_seen'])) {
    $isOnline = true;
    $lastActivity = 'Just now';
} else {
    $lastSeenTimestamp = strtotime($user['last_seen']);
    $timeDiff = time() - $lastSeenTimestamp;
    $isOnline = $timeDiff < 300; // 5 minutes
    
    if ($timeDiff < 60) {
        $lastActivity = 'Just now';
    } else {
        $lastActivity = date('g:i A', $lastSeenTimestamp);
    }
}

// Path Resolution
$scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$frontendPos = strpos($scriptPath, '/frontend');
$assetBase = $frontendPos !== false
    ? substr($scriptPath, 0, $frontendPos + strlen('/frontend'))
    : rtrim($scriptPath, '/') . '/frontend';

$pagesBase = $assetBase . '/pages';
$rootIndex = ($assetBase === '/') ? '/index.php' : $assetBase . '../../index.php';
$logoSrc = $assetBase . '/assets/images/logo.png';
$avatarSrc = $assetBase . '/' . $avatar;

// Navigation Menu Items
$menuItems = [
    [
        'id' => 'dashboard',
        'label' => 'Dashboard',
        'href' => $rootIndex,
        'icon' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="currentColor" stroke-width="2"/><polyline points="9 22 9 12 15 12 15 22" stroke="currentColor" stroke-width="2"/>',
        'permission' => 'dashboard'
    ],
    [
        'id' => 'users',
        'label' => 'Users',
        'href' => $pagesBase . '/users.php',
        'icon' => '<circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 21v-2a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v2" stroke="currentColor" stroke-width="2"/>',
        'permission' => 'users'
    ],
    [
        'id' => 'fellowships',
        'label' => 'Fellowships',
        'href' => $pagesBase . '/fellowships.php',
        'icon' => '<path d="M12 2L2 7v10c0 5.55 4.92 9.25 9.75 11.25.1.05.21.05.3 0C16.83 26.25 22 22.55 22 17V7l-10-5z" stroke="currentColor" stroke-width="2"/>',
        'permission' => 'fellowships'
    ],
    [
        'id' => 'departments',
        'label' => 'Departments',
        'href' => $pagesBase . '/departments.php',
        'icon' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" stroke="currentColor" stroke-width="2"/>',
        'permission' => 'fellowships'
    ],
    [
        'id' => 'cells',
        'label' => 'Home Cells',
        'href' => $pagesBase . '/cells.php',
        'icon' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="currentColor" stroke-width="2"/><polyline points="9 22 9 12 15 12 15 22" stroke="currentColor" stroke-width="2"/>',
        'permission' => 'fellowships'
    ],
    [
        'id' => 'attendance',
        'label' => 'Attendance',
        'href' => $pagesBase . '/attendance.php',
        'icon' => '<rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="2"/><path d="M3 8h18M8 12h2M8 16h2M14 12h2M14 16h2" stroke="currentColor" stroke-width="2"/>',
        'permission' => 'attendance'
    ],
    [
        'id' => 'financial',
        'label' => 'Financial',
        'href' => $pagesBase . '/financial.php',
        'icon' => '<rect x="2" y="6" width="20" height="12" rx="2" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="2" stroke="currentColor" stroke-width="2"/><path d="M6 12h.01M18 12h.01" stroke="currentColor" stroke-width="2"/>',
        'permission' => 'financial'
    ],
    [
        'id' => 'reports',
        'label' => 'Reports',
        'href' => $pagesBase . '/report.php',
        'icon' => '<rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke="currentColor" stroke-width="2"/><line x1="8" y1="21" x2="16" y2="21" stroke="currentColor" stroke-width="2"/><line x1="12" y1="17" x2="12" y2="21" stroke="currentColor" stroke-width="2"/>',
        'permission' => 'reports'
    ],
    [
        'id' => 'members',
        'label' => 'Members',
        'href' => $pagesBase . '/members.php',
        'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2"/><path d="M23 21v-2a4 4 0 0 0-3-3.87m-4-12a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="2"/>',
        'permission' => 'admin_only'
    ],
    [
        'id' => 'birthdays',
        'label' => 'Birthdays',
        'href' => $pagesBase . '/birthdays.php',
        'icon' => '<rect x="3" y="8" width="18" height="4" rx="1" ry="1" stroke="currentColor" stroke-width="2"/><path d="M12 8v13" stroke="currentColor" stroke-width="2"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7" stroke="currentColor" stroke-width="2"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5A4.8 8 0 0 1 12 8a4.8 8 0 0 1 4.5-5 2.5 2.5 0 0 1 0 5" stroke="currentColor" stroke-width="2"/>',
        'permission' => 'admin_only'
    ],
    [
        'id' => 'settings',
        'label' => 'Settings',
        'href' => $pagesBase . '/settings.php',
        'icon' => '<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/><path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24" stroke="currentColor" stroke-width="2"/>',
        'permission' => 'admin_only'
    ],
    [
        'id' => 'profile',
        'label' => 'My Profile',
        'href' => $pagesBase . '/profile.php',
        'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>',
        'permission' => 'profile'
    ]
    // Permissions removed from main menu - moved to user profile dropdown
];

/**
 * Check if user has permission to view menu item
 */
function hasPermission($item, $role, $perms) {
    // Admins have access to everything
    if ($role === 'admin') {
        return true;
    }
    
    // Non-admin: check specific permission
    if ($item['permission'] === 'admin_only') {
        return false;
    }
    
    // Check database permissions
    return $perms[$role][$item['permission']] ?? false;
}

/**
 * Render SVG icon
 */
function renderIcon($pathData, $width = 20, $height = 20) {
    return sprintf(
        '<svg class="sidebar-icon" width="%d" height="%d" viewBox="0 0 24 24" fill="none">%s</svg>',
        $width, $height, $pathData
    );
}
?>

<nav class="sidebar" id="app-sidebar" role="navigation" aria-label="Main navigation">
    
    <!-- Mobile Close Button -->
    <button id="sidebar-close" class="icon-btn mobile-only" type="button" aria-label="Close sidebar">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
    </button>

    <!-- SCROLLABLE NAVIGATION -->
    <div class="sidebar-scroll">
        
        <!-- Header -->
        <div class="sidebar-top">
            <img src="<?= htmlspecialchars($logoSrc) ?>" alt="<?= htmlspecialchars($churchName) ?> Logo" class="sidebar-logo" loading="lazy">
            <h3 class="sidebar-title"><?= htmlspecialchars($churchName) ?></h3>
        </div>

        <!-- Navigation Menu -->
        <ul class="sidebar-nav" role="menu">
            <?php foreach ($menuItems as $item): ?>
                <?php if (hasPermission($item, $role, $perms)): ?>
                    <li role="none">
                        <a href="<?= htmlspecialchars($item['href']) ?>" 
                           data-page="<?= htmlspecialchars($item['id']) ?>" 
                           class="nav-link" 
                           role="menuitem"
                           aria-label="<?= htmlspecialchars($item['label']) ?>">
                            <?= renderIcon($item['icon']) ?>
                            <span><?= htmlspecialchars($item['label']) ?></span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
        
    </div>

    <!-- SIDEBAR FOOTER -->
    <div class="sidebar-footer">
        <div class="sidebar-footer-content">
            
            <!-- User Profile -->
            <div class="footer-user-profile">
                <div class="avatar-wrapper">
                    <img src="<?= htmlspecialchars($avatarSrc) ?>" 
                         alt="<?= htmlspecialchars($user['username']) ?> avatar" 
                         class="sidebar-avatar" 
                         loading="lazy"
                         onerror="this.src='<?= htmlspecialchars($assetBase) ?>/assets/avatar-placeholder.png'">
                    <span class="status-dot <?= $isOnline ? 'online' : 'offline' ?>" 
                          aria-label="<?= $isOnline ? 'Online' : 'Offline' ?>"></span>
                </div>
                <div class="footer-user-info">
                    <div class="footer-user-name"><?= htmlspecialchars($user['username']) ?></div>
                    <div class="footer-user-role">
                        <?= htmlspecialchars(ucfirst($user['role'])) ?>
                        <span class="activity" id="sidebar-activity-time" aria-label="Last active">Active <?= htmlspecialchars($lastActivity) ?></span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="footer-actions">
                <!-- Theme Toggle -->
                <button id="theme-toggle-sidebar" 
                        class="icon-btn" 
                        type="button"
                        aria-label="Toggle dark mode">
                    <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="5" stroke="currentColor" stroke-width="2"/>
                        <path d="M12 2v2m0 16v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M2 12h2m16 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </button>

                <!-- Logout Button -->
                <button id="logout-sidebar" 
                        class="icon-btn" 
                        type="button"
                        aria-label="Logout">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke="currentColor" stroke-width="2"/>
                        <polyline points="16 17 21 12 16 7" stroke="currentColor" stroke-width="2"/>
                        <line x1="21" y1="12" x2="9" y2="12" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </button>
            </div>

            <!-- Expand Toggle (Collapsed Mode Only) -->
            <button id="sidebar-expand-toggle" 
                    class="icon-btn footer-toggle-btn" 
                    type="button"
                    aria-label="Show sidebar actions"
                    aria-expanded="false">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            
        </div>
    </div>
    
</nav>

<?php set_csrf_meta_tag(); ?>

<script>
/**
 * Sidebar Navigation Controller
 * Handles sidebar state, theme switching, and user interactions
 */
(function() {
    'use strict';

    // DOM Elements
    const elements = {
        sidebar: document.getElementById('app-sidebar'),
        hamburger: document.getElementById('hamburger'),
        closeBtn: document.getElementById('sidebar-close'),
        expandToggle: document.getElementById('sidebar-expand-toggle'),
        footerActions: document.querySelector('.footer-actions'),
        topnav: document.getElementById('topnav'),
        themeToggle: document.getElementById('theme-toggle-sidebar'),
        logoutBtn: document.getElementById('logout-sidebar'),
        navLinks: document.querySelectorAll('.nav-link')
    };

    // Configuration
    const config = {
        mobileBreakpoint: 768,
        storageKeys: {
            sidebarCollapsed: 'sidebar-collapsed',
            theme: 'theme'
        }
    };

    /**
     * Sidebar State Management
     */
    const SidebarController = {
        
        init() {
            this.attachEventListeners();
            this.restoreState();
            this.highlightActivePage();
        },

        attachEventListeners() {
            // Note: Hamburger toggle is handled by topnav.php
            
            // Close button
            elements.closeBtn?.addEventListener('click', () => this.closeSidebar());
            
            // Expand toggle (collapsed mode)
            elements.expandToggle?.addEventListener('click', (e) => this.toggleActions(e));
            
            // Close actions on outside click
            document.addEventListener('click', (e) => this.handleOutsideClick(e));
            
            // Window resize handler
            window.addEventListener('resize', () => this.handleResize());
        },

        toggleSidebar() {
            // This method is kept for backwards compatibility but hamburger is handled by topnav.php
            console.warn('toggleSidebar called - use topnav hamburger handler instead');
        },

        closeSidebar() {
            elements.sidebar.classList.remove('open');
            elements.hamburger?.setAttribute('aria-expanded', 'false');
        },

        toggleActions(e) {
            e.stopPropagation();
            elements.footerActions?.classList.toggle('show');
            
            // Update ARIA
            const isExpanded = elements.footerActions?.classList.contains('show');
            elements.expandToggle?.setAttribute('aria-expanded', isExpanded);
        },

        handleOutsideClick(e) {
            // Close sidebar on mobile
            if (window.innerWidth <= config.mobileBreakpoint && 
                elements.sidebar.classList.contains('open') &&
                !elements.sidebar.contains(e.target) && 
                !elements.hamburger?.contains(e.target)) {
                this.closeSidebar();
            }

            // Close footer actions popover
            if (elements.expandToggle && elements.footerActions &&
                !elements.expandToggle.contains(e.target) && 
                !elements.footerActions.contains(e.target)) {
                elements.footerActions.classList.remove('show');
                elements.expandToggle.setAttribute('aria-expanded', 'false');
            }
        },

        handleResize() {
            if (window.innerWidth > config.mobileBreakpoint) {
                elements.sidebar.classList.remove('open');
                elements.footerActions?.classList.remove('show');
            }
        },

        updateTopnavPosition(isCollapsed) {
            if (elements.topnav) {
                if (isCollapsed) {
                    elements.topnav.style.marginLeft = '70px';
                    elements.topnav.style.width = 'calc(100% - 70px)';
                } else {
                    elements.topnav.style.marginLeft = '250px';
                    elements.topnav.style.width = 'calc(100% - 250px)';
                }
            }
        },

        restoreState() {
            if (window.innerWidth > config.mobileBreakpoint) {
                const isCollapsed = localStorage.getItem(config.storageKeys.sidebarCollapsed) === 'true';
                
                if (isCollapsed) {
                    elements.sidebar.classList.add('collapsed');
                    elements.hamburger?.setAttribute('aria-expanded', 'false');
                    this.updateTopnavPosition(true);
                } else {
                    elements.sidebar.classList.remove('collapsed');
                    elements.hamburger?.setAttribute('aria-expanded', 'true');
                    this.updateTopnavPosition(false);
                }
            }
        },

        highlightActivePage() {
            const currentPage = window.location.pathname.split('/').pop() || 'index.php';
            
            elements.navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.includes(currentPage)) {
                    link.classList.add('active');
                    link.setAttribute('aria-current', 'page');
                }
            });
        }
    };

    /**
     * Theme Management
     */
    const ThemeController = {
        
        init() {
            this.applyTheme();
            this.attachEventListeners();
        },

        attachEventListeners() {
            elements.themeToggle?.addEventListener('click', () => this.toggleTheme());
        },

        getCurrentTheme() {
            const saved = localStorage.getItem(config.storageKeys.theme);
            if (saved) return saved;
            
            // Use system preference
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        },

        applyTheme() {
            const theme = this.getCurrentTheme();
            document.documentElement.setAttribute('data-theme', theme);
        },

        toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem(config.storageKeys.theme, next);

            // Announce to screen readers
            const announcement = `Theme switched to ${next} mode`;
            this.announceToScreenReader(announcement);
        },

        announceToScreenReader(message) {
            const announcement = document.createElement('div');
            announcement.setAttribute('role', 'status');
            announcement.setAttribute('aria-live', 'polite');
            announcement.className = 'sr-only';
            announcement.textContent = message;
            document.body.appendChild(announcement);
            
            setTimeout(() => announcement.remove(), 1000);
        }
    };

    /**
     * Logout Handler
     */
    const LogoutController = {
        
        init() {
            elements.logoutBtn?.addEventListener('click', () => this.handleLogout());
        },

        async handleLogout() {
            if (!confirm('Are you sure you want to log out?')) return;

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                
                if (!token) {
                    throw new Error('CSRF token not found');
                }

                const baseUrl = window.APP_CONFIG?.baseUrl || 'http://' + window.location.host + '/church/';
                const logoutUrl = baseUrl + 'backend/auth/logout.php';

                const response = await fetch(logoutUrl, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': token
                    }
                });

                if (response.ok) {
                    // Clear local storage
                    localStorage.clear();
                    sessionStorage.clear();
                    
                    // Redirect to login using global config
                    const loginUrl = window.APP_CONFIG?.loginUrl || 'http://' + window.location.host + '/church/frontend/pages/login.php';
                    window.location.href = loginUrl;
                } else {
                    throw new Error('Logout request failed');
                }
                
            } catch (error) {
                console.error('Logout error:', error);
                alert('Logout failed. Please try again or close your browser.');
            }
        }
    };

    /**
     * Initialize Application
     */
    function init() {
        // Check if DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initControllers);
        } else {
            initControllers();
        }
    }

    function initControllers() {
        SidebarController.init();
        ThemeController.init();
        LogoutController.init();
        initLiveActivity();
    }

    /**
     * Live Activity Clock
     */
    function initLiveActivity() {
        const activityEl = document.getElementById('sidebar-activity-time');
        const isOnline = <?= $isOnline ? 'true' : 'false' ?>;
        
        if (!activityEl || !isOnline) return;

        function updateSidebarActivityTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit'
            });
            activityEl.textContent = `Active ${timeStr}`;
        }

        // Update every minute (60000ms)
        updateSidebarActivityTime();
        setInterval(updateSidebarActivityTime, 60000);
    }

    // Start the application
    init();

})();
</script>

<style>
/* Screen reader only utility class */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}
</style>