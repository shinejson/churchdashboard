<?php
/**
 * Top Navigation Bar Component
 * Handles search, notifications, and user profile menu
 */

// Security & Session Management
require_once __DIR__ . '../../backend/config/security.php';
secure_session_start();

// Authentication Check
if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/frontend/pages/login.php');
    exit();
}

// Regenerate CSRF token
init_csrf();

// User Information
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'member';
$userId = $_SESSION['user_id'];

// Path Resolution
$scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$frontendPos = strpos($scriptPath, '/frontend');
$assetBase = $frontendPos !== false
    ? substr($scriptPath, 0, $frontendPos + strlen('/frontend'))
    : rtrim($scriptPath, '/') . '/frontend';

$logoSrc = $assetBase . '/assets/logo.svg';
$avatarSrc = $assetBase . '/assets/avatar-placeholder.png';
$pagesBase = $assetBase . '/pages';


// Fetch user avatar if exists
try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($userData && !empty($userData['avatar'])) {
        $avatarSrc = $assetBase . '/' . $userData['avatar'];
    }
} catch (Exception $e) {
    error_log("Topnav avatar fetch error: " . $e->getMessage());
}
?>

<header class="topnav" id="topnav" role="banner">
    
    <!-- Left Section: Hamburger Menu -->
    <div class="topnav-left">
        <button id="hamburger" 
                class="icon-btn" 
                type="button"
                aria-label="Toggle sidebar menu" 
                aria-controls="app-sidebar"
                aria-expanded="false">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>
    </div>

    <!-- Center Section: Search -->
    <div class="topnav-center" style="position: relative;">
        <form id="topnav-search" 
              class="topnav-search" 
              role="search"
              aria-label="Site search"
              autocomplete="off">
            <label for="search-input" class="sr-only">Search members and fellowships</label>
            <input type="search" 
                   id="search-input" 
                   name="q"
                   placeholder="Search members, fellowships..." 
                   aria-label="Search"
                   autocomplete="off">
            <button type="submit" 
                    id="search-btn"
                    class="icon-btn" 
                    aria-label="Submit search">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="11" cy="11" r="6" stroke="currentColor" stroke-width="2"/>
                </svg>
            </button>
            <button type="button" 
                    id="mobile-search-close" 
                    class="mobile-search-close" 
                    aria-label="Close search" 
                    style="display: none;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </form>
        
        <!-- Live Search Suggestions Dropdown -->
        <div id="search-suggestions" class="search-suggestions" style="display: none;">
            <div id="search-results-list"></div>
        </div>
    </div>

    <!-- Right Section: Actions & Profile -->
    <div class="topnav-right">
        
        <!-- Mobile Search Toggle -->
        <button id="mobile-search-toggle" 
                class="icon-btn mobile-search-toggle" 
                type="button"
                aria-label="Toggle search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="11" cy="11" r="6" stroke="currentColor" stroke-width="2"/>
            </svg>
        </button>

        <!-- Notifications Dropdown -->
        <div class="notifications-dropdown">
            <button id="notif-btn" 
                    class="icon-btn" 
                    type="button"
                    aria-label="Notifications" 
                    aria-haspopup="true" 
                    aria-expanded="false">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span id="notif-count" 
                      class="badgee" 
                      aria-label="Unread notifications">0</span>
            </button>
            
            <div id="notifications-menu" 
                 class="notifications-menu" 
                 role="menu"
                 aria-hidden="true">
                <div class="notifications-header">
                    <h3>Notifications</h3>
                    <button class="icon-btn" 
                            id="mark-all-read"
                            type="button"
                            aria-label="Mark all as read"
                            style="display:none">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <polyline points="20 6 9 17 4 12" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </button>
                </div>
                <div id="notifications-list" 
                     class="notifications-list" 
                     role="list">
                    <div class="notifications-empty">
                        <p>No new notifications</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Dropdown -->
        <div class="profile-dropdown">
            <button id="profile-btn" 
                    class="profile-btn" 
                    type="button"
                    aria-label="User menu" 
                    aria-haspopup="true" 
                    aria-expanded="false">
                <img src="<?= htmlspecialchars($avatarSrc) ?>" 
                     alt="<?= htmlspecialchars($username) ?> avatar" 
                     class="profile-avatar" 
                     loading="lazy"
                     onerror="this.src='<?= htmlspecialchars($logoSrc) ?>'">
                <div class="profile-info">
                    <span class="profile-name"><?= htmlspecialchars($username) ?></span>
                    <small class="profile-role"><?= htmlspecialchars(ucfirst($role)) ?></small>
                </div>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true" class="profile-chevron">
                    <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            
            <div id="profile-menu" 
                 class="profile-menu" 
                 role="menu"
                 aria-hidden="true">
                <a href="<?= htmlspecialchars($pagesBase) ?>/profile.php" 
                   role="menuitem"
                   data-action="profile">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                        <path d="M4 21v-2a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v2" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    My Profile
                </a>
                <a href="<?= htmlspecialchars($pagesBase) ?>/settings.php" 
                   role="menuitem"
                   data-action="settings">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                        <path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Settings
                </a>
                <a href="pages/permissions.php" 
                   role="menuitem"
                   data-action="permissions"
                   id="admin-permissions-link"
                   style="display:none">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M12 15v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/>
                        <path d="M4 20.5h16c1.105 0 2 .895 2 2v1.5H2v-1.5c0-1.105.895-2 2-2z" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Permissions
                </a>
                <hr class="menu-divider">
                <a href="#logout" 
                   id="logout-link"
                   role="menuitem"
                   data-action="logout">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke="currentColor" stroke-width="2"/>
                        <polyline points="16 17 21 12 16 7" stroke="currentColor" stroke-width="2"/>
                        <line x1="21" y1="12" x2="9" y2="12" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Logout
                </a>
            </div>
        </div>

    </div>
</header>

<?php set_csrf_meta_tag(); ?>

<script>
/**
 * Top Navigation Controller
 * Handles search, notifications, profile menu, and interactions
 */
(function() {
    'use strict';

    // DOM Elements
    const elements = {
        topnav: document.getElementById('topnav'),
        hamburger: document.getElementById('hamburger'),
        sidebar: document.getElementById('app-sidebar'),
        searchForm: document.getElementById('topnav-search'),
        searchInput: document.getElementById('search-input'),
        mobileSearchToggle: document.getElementById('mobile-search-toggle'),
        mobileSearchClose: document.getElementById('mobile-search-close'),
        notifBtn: document.getElementById('notif-btn'),
        notifMenu: document.getElementById('notifications-menu'),
        notifList: document.getElementById('notifications-list'),
        notifCount: document.getElementById('notif-count'),
        profileBtn: document.getElementById('profile-btn'),
        profileMenu: document.getElementById('profile-menu'),
        logoutLink: document.getElementById('logout-link')
    };

    // Configuration
    const config = {
        storageKeys: {
            sidebarCollapsed: 'sidebar-collapsed'
        },
        apiEndpoints: {
            logout: '/church/backend/auth/logout.php',
            notifications: '/church/backend/api/notifications.php',
            search: '/church/backend/api/search.php'
        }
    };

    /**
     * Topnav Position Controller
     */
    const TopnavController = {
        sidebarWarningShown: false,
        
        init() {
            // Restore sidebar collapsed state from localStorage on page load
            this.restoreSidebarState();
            
            this.attachHamburgerListener();
            this.adjustPosition();
            window.addEventListener('resize', () => this.adjustPosition());
            // Also adjust on page load after a small delay to ensure sidebar is ready
            setTimeout(() => this.adjustPosition(), 100);
        },

        restoreSidebarState() {
            const sidebar = document.getElementById('app-sidebar');
            if (!sidebar) return;
            
            elements.sidebar = sidebar;  // Cache it
            
            const wasPreviouslyCollapsed = localStorage.getItem(config.storageKeys.sidebarCollapsed);
            if (wasPreviouslyCollapsed === 'true') {
                sidebar.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
            }
        },

        attachHamburgerListener() {
            if (!elements.hamburger) return;
            
            elements.hamburger.addEventListener('click', (e) => {
                e.stopPropagation();
                
                // Get sidebar reference
                let sidebar = elements.sidebar;
                
                // If not cached, search DOM
                if (!sidebar) {
                    sidebar = document.getElementById('app-sidebar') || document.querySelector('.sidebar');
                    if (sidebar) {
                        elements.sidebar = sidebar;  // Cache it
                    } else {
                        // Avoid noisy console errors; this can happen on minimal pages
                        // where sidebar is intentionally not rendered.
                        if (!this.sidebarWarningShown) {
                            console.warn('Sidebar is not available on this page.');
                            this.sidebarWarningShown = true;
                        }
                        return;
                    }
                }
                
                const isMobile = window.innerWidth <= 768;
                
                if (isMobile) {
                    // Mobile: toggle open class
                    sidebar.classList.toggle('open');
                    const isOpen = sidebar.classList.contains('open');
                    elements.hamburger.setAttribute('aria-expanded', isOpen);
                } else {
                    // Desktop: toggle collapsed class
                    const wasCollapsed = sidebar.classList.contains('collapsed');
                    sidebar.classList.toggle('collapsed');
                    const isCollapsed = sidebar.classList.contains('collapsed');
                    
                    // Save state
                    localStorage.setItem(config.storageKeys.sidebarCollapsed, isCollapsed);
                    
                    // Update topnav position immediately
                    this.adjustPosition();
                    
                    // Update hamburger aria
                    elements.hamburger.setAttribute('aria-expanded', !isCollapsed);
                }
            });
        },

        adjustPosition() {
            if (!elements.topnav) return;
            
            // Make sure sidebar element exists
            let sidebar = elements.sidebar;
            if (!sidebar) {
                sidebar = document.getElementById('app-sidebar');
                if (!sidebar) {
                    // Sidebar not found, use default positioning
                    elements.topnav.style.marginLeft = '0';
                    elements.topnav.style.width = '100%';
                    return;
                }
                elements.sidebar = sidebar;
            }
            
            // Check actual sidebar class state, not just localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            const marginLeft = isCollapsed ? '70px' : '250px';
            const width = isCollapsed ? 'calc(100% - 70px)' : 'calc(100% - 250px)';
            
            // Only apply on desktop
            if (window.innerWidth > 768) {
                elements.topnav.style.marginLeft = marginLeft;
                elements.topnav.style.width = width;
            } else {
                elements.topnav.style.marginLeft = '0';
                elements.topnav.style.width = '100%';
            }
        }
    };

    /**
     * Search Controller
     */
    const SearchController = {
        debounceTimeout: null,
        
        init() {
            if (!elements.searchInput) return;
            
            elements.searchForm.addEventListener('submit', (e) => this.handleSearch(e));
            elements.searchInput.addEventListener('input', (e) => this.handleLiveSearch(e));
            
            // Mobile search toggle events
            if (elements.mobileSearchToggle) {
                elements.mobileSearchToggle.addEventListener('click', () => {
                    elements.topnav.classList.add('search-active');
                    elements.searchInput.focus();
                });
            }
            if (elements.mobileSearchClose) {
                elements.mobileSearchClose.addEventListener('click', () => {
                    elements.topnav.classList.remove('search-active');
                    elements.searchInput.value = '';
                    const suggestions = document.getElementById('search-suggestions');
                    if (suggestions) suggestions.style.display = 'none';
                });
            }
            
            // Hide suggestions when clicking outside
            document.addEventListener('click', (e) => {
                const suggestions = document.getElementById('search-suggestions');
                if (suggestions && !elements.searchForm.contains(e.target) && !suggestions.contains(e.target)) {
                    suggestions.style.display = 'none';
                }
            });
            
            // Show suggestions again on input focus if there is a query
            elements.searchInput.addEventListener('focus', () => {
                const q = elements.searchInput.value.trim();
                const suggestions = document.getElementById('search-suggestions');
                if (q.length >= 2 && suggestions && document.getElementById('search-results-list').innerHTML.trim() !== '') {
                    suggestions.style.display = 'block';
                }
            });
        },

        handleLiveSearch(e) {
            const query = elements.searchInput.value.trim();
            const suggestionsEl = document.getElementById('search-suggestions');
            const listEl = document.getElementById('search-results-list');
            
            if (query.length < 2) {
                suggestionsEl.style.display = 'none';
                listEl.innerHTML = '';
                return;
            }

            clearTimeout(this.debounceTimeout);
            this.debounceTimeout = setTimeout(() => {
                this.fetchAndRenderResults(query, suggestionsEl, listEl);
            }, 300); // 300ms debounce
        },
        
        async fetchAndRenderResults(query, suggestionsEl, listEl) {
            try {
                // Show loading state inline
                listEl.innerHTML = '<div class="search-message"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
                suggestionsEl.style.display = 'block';
                
                const response = await fetch(`${config.apiEndpoints.search}?q=${encodeURIComponent(query)}`);
                if (!response.ok) throw new Error('Network error');
                const data = await response.json();
                
                if (data.error) throw new Error(data.error);
                
                this.renderResults(data.data, listEl);
            } catch (err) {
                console.error(err);
                listEl.innerHTML = '<div class="search-message error">Failed to load results</div>';
            }
        },
        
        renderResults(results, listEl) {
            if (!results || results.length === 0) {
                listEl.innerHTML = '<div class="search-message">No records found.</div>';
                return;
            }
            
            // Generate HTML for each result properly linking to the specific module
            // Base pages path mapping logic based on current location
            const isRoot = window.location.pathname.endsWith('index.php') || window.location.pathname.endsWith('/');
            const pagesPrefix = isRoot ? 'frontend/pages/' : ''; // From pages directory it's just relative

            let html = '';
            results.forEach(item => {
                html += `
                    <a href="${pagesPrefix}${item.url}" class="search-result-item">
                        <div class="result-icon">
                            ${this.getIconForType(item.type)}
                        </div>
                        <div class="result-details">
                            <div class="result-title">${item.title}</div>
                            <div class="result-subtitle">${item.subtitle}</div>
                        </div>
                        <span class="result-type badge-type-${item.type.toLowerCase()}">${item.type}</span>
                    </a>
                `;
            });
            
            // Add view all link if 5 or more (a simplified limit check)
            if (results.length >= 5) {
                html += `
                    <a href="${pagesPrefix}members.php?search=${encodeURIComponent(elements.searchInput.value)}" class="search-view-all">
                        View all results <i class="fas fa-arrow-right"></i>
                    </a>
                `;
            }
            
            listEl.innerHTML = html;
        },
        
        getIconForType(type) {
            switch(type) {
                case 'Member': return '<i class="fas fa-user"></i>';
                case 'Fellowship': return '<i class="fas fa-church"></i>';
                case 'User': return '<i class="fas fa-user-shield"></i>';
                default: return '<i class="fas fa-search"></i>';
            }
        },

        async handleSearch(e) {
            e.preventDefault();
            const query = elements.searchInput?.value.trim();
            if (!query) return;

            // Basic redirect on enter to member page search
            const isRoot = window.location.pathname.endsWith('index.php') || window.location.pathname.endsWith('/');
            const targetUrl = (isRoot ? 'frontend/pages/members.php' : 'members.php') + `?search=${encodeURIComponent(query)}`;
            window.location.href = targetUrl;
        }
    };

    /**
     * Notifications Controller
     */
    const NotificationsController = {
        
        init() {
            if (!elements.notifBtn) return;
            
            elements.notifBtn.addEventListener('click', (e) => this.toggleMenu(e));
            document.addEventListener('click', (e) => this.handleOutsideClick(e));
            window.addEventListener('resize', () => this.updatePosition());
            
            // Load notifications on init
            this.loadNotifications();
            
            // Poll for new notifications every 30 seconds
            setInterval(() => this.loadNotifications(), 30000);
        },

        toggleMenu(e) {
            e.stopPropagation();
            
            const isOpen = elements.notifMenu.classList.contains('show');
            
            if (isOpen) {
                this.closeMenu();
            } else {
                this.openMenu();
            }
        },

        openMenu() {
            // Close profile menu if open
            ProfileController.closeMenu();
            
            this.updatePosition();
            elements.notifMenu.classList.add('show');
            elements.notifBtn.setAttribute('aria-expanded', 'true');
            elements.notifMenu.setAttribute('aria-hidden', 'false');
        },

        closeMenu() {
            elements.notifMenu.classList.remove('show');
            elements.notifBtn.setAttribute('aria-expanded', 'false');
            elements.notifMenu.setAttribute('aria-hidden', 'true');
        },

        updatePosition() {
            if (!elements.notifBtn || !elements.notifMenu) return;
            
            const rect = elements.notifBtn.getBoundingClientRect();
            const rightPos = window.innerWidth - rect.right;
            elements.notifMenu.style.right = rightPos + 'px';
        },

        handleOutsideClick(e) {
            if (!elements.notifBtn.contains(e.target) && 
                !elements.notifMenu.contains(e.target)) {
                this.closeMenu();
            }
        },

        async loadNotifications() {
            try {
                // Implement your notifications API call here
                // const response = await fetch(config.apiEndpoints.notifications);
                // const data = await response.json();
                // this.updateNotifications(data);
                
                // For now, don't update count on load - only when actual data comes in
                // this.updateCount(0);
                
            } catch (error) {
                console.error('Failed to load notifications:', error);
            }
        },

        updateNotifications(notifications) {
            if (!notifications || notifications.length === 0) {
                this.showEmptyState();
                return;
            }

            elements.notifList.innerHTML = notifications.map(notif => `
                <div class="notifications-item" role="listitem">
                    <div class="notif-icon">${this.getIcon(notif.type)}</div>
                    <div class="notif-content">
                        <p class="notif-message">${notif.message}</p>
                        <span class="notif-time">${notif.time}</span>
                    </div>
                </div>
            `).join('');

            this.updateCount(notifications.length);
        },

        showEmptyState() {
            elements.notifList.innerHTML = `
                <div class="notifications-empty">
                    <p>No new notifications</p>
                </div>
            `;
            this.updateCount(0);
        },

        updateCount(count) {
            if (count > 0) {
                elements.notifCount.textContent = count > 99 ? '99+' : count;
                elements.notifCount.style.display = 'block';
            } else {
                elements.notifCount.style.display = 'none';
            }
        },

        getIcon(type) {
            // Return appropriate icon based on notification type
            return '📢';
        }
    };

    /**
     * Profile Menu Controller
     */
    const ProfileController = {
        
        init() {
            if (!elements.profileBtn) return;
            
            elements.profileBtn.addEventListener('click', (e) => this.toggleMenu(e));
            document.addEventListener('click', (e) => this.handleOutsideClick(e));
            window.addEventListener('resize', () => this.updatePosition());
            
            // Show permissions link for admins
            this.updatePermissionsVisibility();
            
            // Handle menu item clicks
            elements.profileMenu?.querySelectorAll('[data-action]').forEach(item => {
                item.addEventListener('click', (e) => this.handleAction(e));
            });
        },

        updatePermissionsVisibility() {
            const permLink = document.getElementById('admin-permissions-link');
            if (!permLink) return;
            
            // Check if user is admin
            const isAdmin = <?= json_encode($_SESSION['role'] === 'pastor' || ($_SESSION['role'] ?? '') === 'admin') ?>;
            
            if (isAdmin) {
                permLink.style.display = 'flex';
                permLink.style.alignItems = 'center';
                permLink.style.gap = '10px';
            } else {
                permLink.style.display = 'none';
            }
        },

        toggleMenu(e) {
            e.stopPropagation();
            
            const isOpen = elements.profileMenu.classList.contains('show');
            
            if (isOpen) {
                this.closeMenu();
            } else {
                this.openMenu();
            }
        },

        openMenu() {
            // Close notifications menu if open
            NotificationsController.closeMenu();
            
            this.updatePosition();
            elements.profileMenu.classList.add('show');
            elements.profileBtn.setAttribute('aria-expanded', 'true');
            elements.profileMenu.setAttribute('aria-hidden', 'false');
        },

        closeMenu() {
            elements.profileMenu.classList.remove('show');
            elements.profileBtn.setAttribute('aria-expanded', 'false');
            elements.profileMenu.setAttribute('aria-hidden', 'true');
        },

        updatePosition() {
            if (!elements.profileBtn || !elements.profileMenu) return;
            
            const rect = elements.profileBtn.getBoundingClientRect();
            const rightPos = window.innerWidth - rect.right;
            elements.profileMenu.style.right = rightPos + 'px';
        },

        handleOutsideClick(e) {
            if (!elements.profileBtn.contains(e.target) && 
                !elements.profileMenu.contains(e.target)) {
                this.closeMenu();
            }
        },

        handleAction(e) {
            e.preventDefault();
            const action = e.currentTarget.dataset.action;
            
            switch(action) {
                case 'profile':
                    this.showProfile();
                    break;
                case 'settings':
                    this.showSettings();
                    break;
                case 'logout':
                    LogoutController.handleLogout();
                    break;
            }
            
            this.closeMenu();
        },

        showProfile() {
            // Navigate to profile page or show modal
            // window.location.href = 'profile.php';
        },

        showSettings() {
            // Navigate to settings page or show modal
            // window.location.href = 'settings.php';
        }
    };

    /**
     * Logout Controller
     */
    const LogoutController = {
        
        async handleLogout() {
            if (!confirm('Are you sure you want to log out?')) return;

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                
                if (!token) {
                    throw new Error('CSRF token not found');
                }

                const response = await fetch(config.apiEndpoints.logout, {
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
                    const loginUrl = window.APP_CONFIG?.loginUrl || '/church/frontend/pages/login.php';
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
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initControllers);
        } else {
            initControllers();
        }
    }

    function initControllers() {
        TopnavController.init();
        SearchController.init();
        NotificationsController.init();
        ProfileController.init();
    }

    // Start the application
    init();

})();
</script>

<style>
/* Profile Dropdown Styles */
.profile-info {
    display: flex;
    flex-direction: column;
    text-align: left;
    margin-left: 8px;
}

.profile-name {
    font-weight: 600;
    font-size: 0.95rem;
    line-height: 1.2;
}

.profile-role {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 2px;
}

.profile-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: var(--border-radius);
    transition: var(--transition);
}

.profile-btn:hover {
    background-color: var(--gray-light);
}

[data-theme="dark"] .profile-btn:hover {
    background-color: #333;
}

.profile-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border-color);
}

.profile-chevron {
    transition: transform 0.2s ease;
}

.profile-btn[aria-expanded="true"] .profile-chevron {
    transform: rotate(180deg);
}

/* Profile Menu Items */
.profile-menu a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
}

.profile-menu a svg {
    width: 16px;
    height: 16px;
    opacity: 0.7;
}

.profile-menu a:hover svg {
    opacity: 1;
}

.menu-divider {
    margin: 8px 0;
    border: none;
    border-top: 1px solid var(--border-color);
}

/* Notification Badge */
.badgee {
    position: absolute;
    top: -4px;
    right: -4px;
    background-color: var(--danger-color);
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 0.7rem;
    font-weight: bold;
    min-width: 18px;
    text-align: center;
}

/* Screen Reader Only */
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

/* Search Suggestions Styling */
.mobile-search-toggle {
    display: none;
}
.mobile-search-close {
    display: none;
}

@media (max-width: 1024px) {
    .topnav:not(.search-active) .topnav-center {
        display: none !important;
    }
    
    .mobile-search-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .topnav.search-active .topnav-left,
    .topnav.search-active .topnav-right {
        display: none !important;
    }
    
    .topnav.search-active .topnav-center {
        display: flex !important;
        flex: 1;
        width: 100%;
        align-items: center;
        padding-right: 15px;
    }
    
    .topnav.search-active .topnav-search {
        width: 100%;
        margin: 0;
        display: flex;
        align-items: center;
    }
    
    .topnav.search-active .topnav-search input[type="search"] {
        flex: 1;
        width: 100%;
    }
    
    .topnav.search-active .mobile-search-close {
        display: flex !important;
        align-items: center;
        justify-content: center;
        margin-left: 8px;
        color: var(--text-color, #1e293b);
        background: transparent;
        border: none;
        cursor: pointer;
        padding: 5px;
    }
}

.search-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    background: var(--surface-color, #ffffff);
    border: 1px solid var(--border-color, #cbd5e1);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    margin-top: 8px;
    z-index: 1000;
    max-height: 400px;
    overflow-y: auto;
}

.search-message {
    padding: 16px;
    text-align: center;
    color: var(--text-muted, #64748b);
    font-size: 0.9rem;
}

.search-message.error {
    color: var(--danger-color, #ef4444);
}

.search-result-item {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    text-decoration: none;
    color: var(--text-color, #1e293b);
    border-bottom: 1px solid var(--border-color, #f1f5f9);
    transition: background-color 0.2s;
    gap: 12px;
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-item:hover {
    background: var(--bg-color, #f8fafc);
}

[data-theme="dark"] .search-result-item:hover {
    background: #334155;
}

.result-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary-light, #eff6ff);
    color: var(--primary-color, #3b82f6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.result-details {
    flex: 1;
    min-width: 0;
}

.result-title {
    font-weight: 600;
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.result-subtitle {
    font-size: 0.75rem;
    color: var(--text-muted, #64748b);
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.result-type {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 500;
    flex-shrink: 0;
}

.badge-type-member { background: #dcfce7; color: #166534; }
.badge-type-fellowship { background: #fef3c7; color: #92400e; }
.badge-type-user { background: #f3e8ff; color: #6b21a8; }

.search-view-all {
    display: block;
    padding: 12px;
    text-align: center;
    color: var(--primary-color, #3b82f6);
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    background: var(--bg-color, #f8fafc);
    border-top: 1px solid var(--border-color, #cbd5e1);
}

[data-theme="dark"] .search-view-all {
    background: #1e293b;
    border-color: #334155;
}

.search-view-all:hover {
    text-decoration: underline;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .profile-info {
        display: none;
    }
    
    .profile-chevron {
        display: none;
    }
}
</style>