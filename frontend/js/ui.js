// Enhanced UI Manager with Dark Mode Support
class UIManager {
    constructor() {
        this.currentTheme = 'light';
        this.init();
    }

    init() {
        this.initializeTheme();
        this.initializeSidebar();
        this.initializeEventListeners();
        this.updateFooterWidth(); // Initial footer width
        this.updateTopnavWidth(); // Initial topnav width

        // Listen for sidebar toggle to update footer and topnav
        document.addEventListener('sidebarToggled', () => {
            // Small delay to ensure DOM has updated
            setTimeout(() => {
                this.updateFooterWidth();
                this.updateTopnavWidth();
            }, 50);
        });

        // Update footer and topnav on window resize
        window.addEventListener('resize', () => {
            this.updateFooterWidth();
            this.updateTopnavWidth();
        });

        // Watch for sidebar class changes with MutationObserver
        const sidebar = document.getElementById('app-sidebar');
        if (sidebar) {
            const observer = new MutationObserver(() => {
                this.updateFooterWidth();
                this.updateTopnavWidth();
            });
            observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
        }
    }

    // Theme Management
    initializeTheme() {
        // Get saved theme or default to light
        this.currentTheme = localStorage.getItem('theme') || 'light';
        this.applyTheme(this.currentTheme);

        // Update theme toggle button if exists
        this.updateThemeToggle();
    }

    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        this.currentTheme = theme;
        localStorage.setItem('theme', theme);

        // Update theme toggle button
        this.updateThemeToggle();

        // Dispatch theme change event for other components
        document.dispatchEvent(new CustomEvent('themeChanged', {
            detail: { theme: theme }
        }));
    }

    toggleTheme() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.applyTheme(newTheme);
    }

    updateThemeToggle() {
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            const icon = themeToggle.querySelector('i');
            const text = themeToggle.querySelector('span');

            if (this.currentTheme === 'dark') {
                if (icon) icon.className = 'fas fa-sun';
                if (text) text.textContent = 'Light Mode';
            } else {
                if (icon) icon.className = 'fas fa-moon';
                if (text) text.textContent = 'Dark Mode';
            }
        }
    }

    // Sidebar Management
    initializeSidebar() {
        // NOTE: Hamburger and sidebar toggle is now handled by topnav.php and sidebar.php
        // DO NOT add duplicate hamburger handlers here to avoid conflicts

        // Just watch for sidebar state changes to trigger UI updates
        const sidebar = document.getElementById('app-sidebar');
        if (sidebar) {
            // Listen for sidebar state changes to update footer and topnav
            const observer = new MutationObserver(() => {
                this.updateFooterWidth();
                this.updateTopnavWidth();
            });
            observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
        }
    }

    updateFooterWidth() {
        const footer = document.querySelector('.app-footer');
        const sidebar = document.getElementById('app-sidebar');

        if (!footer || !sidebar) return;

        const isCollapsed = sidebar.classList.contains('collapsed');
        const isMobile = window.innerWidth <= 768;

        if (isMobile) {
            // On mobile, footer spans full width
            footer.style.marginLeft = '0';
            footer.style.width = '100%';
        } else {
            // Desktop: adjust for sidebar based on collapsed state
            const marginLeft = isCollapsed ? '70px' : '250px';
            const width = isCollapsed ? 'calc(100% - 70px)' : 'calc(100% - 250px)';

            footer.style.marginLeft = marginLeft;
            footer.style.width = width;
        }
    }

    updateTopnavWidth() {
        const topnav = document.querySelector('.topnav');
        const sidebar = document.getElementById('app-sidebar');

        if (!topnav || !sidebar) return;

        const isCollapsed = sidebar.classList.contains('collapsed');
        const isMobile = window.innerWidth <= 768;

        if (isMobile) {
            // On mobile, topnav spans full width
            topnav.style.marginLeft = '0';
            topnav.style.width = '100%';
        } else {
            // Desktop: adjust for sidebar based on collapsed state
            const marginLeft = isCollapsed ? '70px' : '250px';
            const width = isCollapsed ? 'calc(100% - 70px)' : 'calc(100% - 250px)';

            topnav.style.marginLeft = marginLeft;
            topnav.style.width = width;
        }
    }

    // Event Listeners
    initializeEventListeners() {
        // Theme toggle
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => this.toggleTheme());
        }

        // Close modals on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });

        // Initialize tooltips if any
        this.initializeTooltips();
    }

    initializeTooltips() {
        // Add tooltip functionality to elements with data-tooltip
        document.querySelectorAll('[data-tooltip]').forEach(element => {
            element.addEventListener('mouseenter', this.showTooltip);
            element.addEventListener('mouseleave', this.hideTooltip);
        });
    }

    showTooltip(e) {
        const tooltipText = e.target.getAttribute('data-tooltip');
        if (!tooltipText) return;

        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = tooltipText;
        tooltip.style.cssText = `
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            z-index: 10000;
            white-space: nowrap;
            pointer-events: none;
            transform: translateY(-100%);
            margin-top: -8px;
        `;

        document.body.appendChild(tooltip);

        const rect = e.target.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top + 'px';

        e.target._currentTooltip = tooltip;
    }

    hideTooltip(e) {
        if (e.target._currentTooltip) {
            e.target._currentTooltip.remove();
            e.target._currentTooltip = null;
        }
    }

    closeAllModals() {
        document.querySelectorAll('.modal.show').forEach(modal => {
            modal.classList.remove('show');
        });
        document.body.style.overflow = '';
    }

    // Notification System
    showNotification(message, type = 'info', duration = 4000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;

        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };

        notification.innerHTML = `
            <div class="notification-content">
                <i class="${icons[type] || icons.info}"></i>
                <span>${message}</span>
            </div>
            <button class="notification-close">
                <i class="fas fa-times"></i>
            </button>
        `;

        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 20px;
            border-radius: 12px;
            color: white;
            z-index: 1000001;
            transform: translateX(400px);
            transition: transform 0.3s ease, opacity 0.3s ease;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            max-width: 400px;
        `;

        // Set background based on type
        const backgrounds = {
            success: 'linear-gradient(135deg, #06d6a0, #20d489)',
            error: 'linear-gradient(135deg, #ef476f, #ff6b7a)',
            warning: 'linear-gradient(135deg, #ffd166, #ffb347)',
            info: 'linear-gradient(135deg, #118ab2, #06b6d4)'
        };

        notification.style.background = backgrounds[type] || backgrounds.info;

        document.body.appendChild(notification);

        // Animate in
        requestAnimationFrame(() => {
            notification.style.transform = 'translateX(0)';
        });

        // Close button
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            this.hideNotification(notification);
        });

        // Auto hide
        if (duration > 0) {
            setTimeout(() => {
                this.hideNotification(notification);
            }, duration);
        }

        return notification;
    }

    hideNotification(notification) {
        notification.style.transform = 'translateX(400px)';
        notification.style.opacity = '0';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }
}

// Page Router Functions
window.showPage = function (pageName) {
    if (pageName === 'dashboard') {
        switchToDashboard();
        return;
    }

    loadPHPPage(pageName);
};

function switchToDashboard() {
    const target = document.getElementById('dashboard-content');

    document.querySelectorAll('.page-content').forEach(page => {
        page.style.opacity = '0';
        page.style.transform = 'translateY(20px)';
        setTimeout(() => {
            // Keep dashboard visible; only deactivate non-target pages.
            if (!target || page !== target) {
                page.classList.remove('active');
            }
            page.style.opacity = '';
            page.style.transform = '';
        }, 150);
    });

    if (target) {
        target.classList.add('active');
        setTimeout(() => {
            target.style.opacity = '1';
            target.style.transform = 'translateY(0)';
        }, 50);
    }

    updateActiveNav('dashboard');
    loadPageData('dashboard');
}

function loadPHPPage(pageName) {
    const pageFile = `frontend/pages/${pageName}.php`;
    showNotification(`Loading ${pageName}...`, 'info');
    window.location.href = pageFile;
}

function updateActiveNav(pageName) {
    document.querySelectorAll('.sidebar-nav a').forEach(a => {
        a.classList.remove('active');
        a.removeAttribute('aria-current');
    });

    const activeLink = document.querySelector(`.sidebar-nav a[data-page="${pageName}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
        activeLink.setAttribute('aria-current', 'page');
    }
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatDateTime(dateString) {
    return new Date(dateString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Global notification function
window.showNotification = function (message, type = 'info', duration = 4000) {
    if (window.uiManager) {
        return window.uiManager.showNotification(message, type, duration);
    }

    // Fallback notification
    const notification = document.createElement('div');
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        border-radius: 8px;
        z-index: 1000001;
        transform: translateX(400px);
        transition: transform 0.3s ease;
    `;

    document.body.appendChild(notification);
    requestAnimationFrame(() => notification.style.transform = 'translateX(0)');

    setTimeout(() => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => notification.remove(), 300);
    }, duration);
};

// Initialize UI Manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    window.uiManager = new UIManager();

    // Initialize any page-specific functionality
    if (typeof window.pageManager !== 'undefined') {
        window.pageManager.init();
    }
});

// Export for module usage if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { UIManager, debounce, formatDate, formatDateTime };
}

// main.js - Safe initialization
document.addEventListener('DOMContentLoaded', function () {
    // Initialize UI Manager if it exists
    if (typeof UIManager !== 'undefined') {
        window.uiManager = new UIManager();
    }

    // Initialize page-specific managers if they exist
    if (typeof UsersManager !== 'undefined') {
        window.usersManager = new UsersManager();
    }

    // Add any other safe initializations here
});