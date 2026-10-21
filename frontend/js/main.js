// Main application functionality

// Close modal when clicking X
const closeBtn = document.querySelector('.close');
if (closeBtn) {
    closeBtn.addEventListener('click', function() {
        const modal = document.getElementById('modal');
        if (modal) {
            modal.style.display = 'none';
        }
    });
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('modal');
    if (modal && event.target === modal) {
        modal.style.display = 'none';
    }
});

// Format date for display
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

// Show notification
function showNotification(message, type = 'success') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#28a745' : '#dc3545'};
        color: white;
        border-radius: 5px;
        z-index: 1001;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    `;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Escape HTML to prevent XSS when inserting untrusted content into the DOM
function escapeHTML(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Page navigation function
function showPage(pageName) {
    // Hide all page contents
    const pageContents = document.querySelectorAll('.page-content');
    pageContents.forEach(content => {
        content.classList.remove('active');
    });
    
    // Show selected page
    const activePage = document.getElementById(pageName + '-content');
    if (activePage) {
        activePage.classList.add('active');
    }
    
    // Update active state in sidebar
    const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
    sidebarLinks.forEach(link => {
        link.classList.remove('active');
    });
    
    // Set active state for current page
    const currentLink = document.querySelector(`.sidebar-nav a[onclick="showPage('${pageName}')"]`);
    if (currentLink) {
        currentLink.classList.add('active');
    }
    
    // Close mobile sidebar if open
    if (window.innerWidth <= 768) {
        const sidebar = document.getElementById('app-sidebar');
        if (sidebar) {
            sidebar.classList.remove('mobile-open');
        }
    }
}

// Initialize dashboard as default page
function showDashboard() {
    showPage('dashboard');
}

// Mobile sidebar toggle
function toggleMobileSidebar() {
    const sidebar = document.getElementById('app-sidebar');
    if (sidebar) {
        sidebar.classList.toggle('mobile-open');
    }
}

// Close mobile sidebar when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('app-sidebar');
    const hamburger = document.getElementById('hamburger');
    
    if (window.innerWidth <= 768 && 
        sidebar &&
        hamburger &&
        !sidebar.contains(event.target) && 
        !hamburger.contains(event.target)) {
        sidebar.classList.remove('mobile-open');
    }
});

/* ============================================
   Church Management System - UI Enhancements
   Additional JavaScript features
   ============================================ */

// Initialize all enhancements when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Ensure one page is always visible on index layout.
    const pageContents = document.querySelectorAll('.page-content');
    const hasActivePage = Array.from(pageContents).some(page => page.classList.contains('active'));
    if (pageContents.length > 0 && !hasActivePage) {
        document.getElementById('dashboard-content')?.classList.add('active');
    }

    initTheme();
    // initSidebarToggle();  // Handled by sidebar.php
    // initProfileDropdown();  // Handled by topnav.php
    initSearch();
    // initNotifications();  // Handled by topnav.php
    // initTooltips();
    // initScrollEffects();
    // initTableFeatures();
    // initFormValidation();
});

/* ============================================
   Theme Management
   ============================================ */

function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);
    
    const darkModeToggle = document.getElementById('darkmode-toggle');
    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
            
            // Add transition effect
            document.body.style.transition = 'background-color 0.3s ease';
            setTimeout(() => {
                document.body.style.transition = '';
            }, 300);
        });
    }
}

function updateThemeIcon(theme) {
    const icon = document.getElementById('darkmode-icon');
    if (icon) {
        if (theme === 'dark') {
            icon.innerHTML = '<path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
        } else {
            icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
        }
    }
}

/* ============================================
   Sidebar Toggle
   ============================================ */

function initSidebarToggle() {
    const hamburger = document.getElementById('hamburger');
    const sidebar = document.getElementById('app-sidebar');
    
    if (hamburger && sidebar) {
        hamburger.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            
            // Save state
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebar-collapsed', isCollapsed);
            
            // Mobile specific
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
            }
        });
        
        // Restore saved state
        const savedState = localStorage.getItem('sidebar-collapsed');
        if (savedState === 'true' && window.innerWidth > 768) {
            sidebar.classList.add('collapsed');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !hamburger.contains(event.target)) {
                    sidebar.classList.remove('mobile-open');
                }
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
            }
        });
    }
}

/* ============================================
   Profile Dropdown
   ============================================ */

function initProfileDropdown() {
    const profileBtn = document.getElementById('profile-btn');
    const profileMenu = document.getElementById('profile-menu');
    const dropdown = document.getElementById('profile-dropdown');
    
    if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileMenu.classList.toggle('show');
            profileBtn.setAttribute('aria-expanded', 
                profileMenu.classList.contains('show'));
        });
        
        // Close when clicking outside
        document.addEventListener('click', function(event) {
            if (dropdown && !dropdown.contains(event.target)) {
                profileMenu.classList.remove('show');
                profileBtn.setAttribute('aria-expanded', 'false');
            }
        });
        
        // Close when pressing Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && profileMenu.classList.contains('show')) {
                profileMenu.classList.remove('show');
                profileBtn.setAttribute('aria-expanded', 'false');
                profileBtn.focus();
            }
        });
    }
}

/* ============================================
   Search Functionality
   ============================================ */

function initSearch() {
    const searchInput = document.getElementById('search-input');
    const searchForm = document.getElementById('topnav-search');
    
    if (searchInput) {
        // Add search icon animation
        searchInput.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        searchInput.addEventListener('blur', function() {
            if (!this.value) {
                this.parentElement.classList.remove('focused');
            }
        });
        
        // Handle search
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                performSearch(searchInput.value);
            });
        }
    }
}

function performSearch(query) {
    if (!query.trim()) return;
    
    // Show loading indicator
    showNotification('Searching...', 'info');
    
    // Implement your search logic here
    // This is a placeholder for actual search functionality
    
    setTimeout(() => {
        showNotification(`Search results for: ${query}`, 'success');
    }, 500);
}

/* ============================================
   Notifications
   ============================================ */

function initNotifications() {
    const notifBtn = document.getElementById('notif-btn');
    const notifMenu = document.getElementById('notifications-menu');
    const notifDropdown = document.getElementById('notifications-dropdown');
    
    if (notifBtn && notifMenu) {
        notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            // Remove any legacy full-page notification panels (defensive)
            document.querySelectorAll('.notification-panel').forEach(el => el.remove());

            // populate notifications list (placeholder) before showing
            populateNotifications();

            notifMenu.classList.toggle('show');
            const opened = notifMenu.classList.contains('show');
            notifBtn.setAttribute('aria-expanded', opened);

            // Move focus into menu for accessibility when opened
            if (opened) {
                const firstItem = notifMenu.querySelector('.notifications-item');
                if (firstItem) firstItem.focus();
            }
        });
        
        // Close when clicking outside
        document.addEventListener('click', function(event) {
            if (notifDropdown && !notifDropdown.contains(event.target)) {
                notifMenu.classList.remove('show');
                notifBtn.setAttribute('aria-expanded', 'false');
            }
        });
        
        // Close when pressing Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && notifMenu.classList.contains('show')) {
                notifMenu.classList.remove('show');
                notifBtn.setAttribute('aria-expanded', 'false');
                notifBtn.focus();
            }
        });
    }
    
    // Check for new notifications periodically
    setInterval(checkNotifications, 30000); // Every 30 seconds
}

// Fill the notifications list with sample or fetched notifications
function populateNotifications() {
    const list = document.getElementById('notifications-list');
    if (!list) return;

    // If real API exists, fetch notifications here. For now show placeholder items.
    list.innerHTML = '';

    const sample = [
        { title: 'Attendance recorded', body: 'Fellowship A — 24 present', time: '2m' },
        { title: 'New member added', body: 'John Doe joined members', time: '1h' }
    ];

    if (sample.length === 0) {
        list.innerHTML = '<div class="notifications-empty"><p>No new notifications</p></div>';
        return;
    }

    sample.forEach(item => {
        const el = document.createElement('div');
        el.className = 'notifications-item';
        el.tabIndex = 0;
        el.innerHTML = `
            <div style="flex:1">
                <div style="font-weight:600">${escapeHTML(item.title)}</div>
                <div style="font-size:0.9rem; color:var(--text-muted)">${escapeHTML(item.body)}</div>
            </div>
            <div style="font-size:0.8rem; color:var(--text-muted); margin-left:8px">${escapeHTML(item.time)}</div>
        `;
        list.appendChild(el);
    });
}

function checkNotifications() {
    // Implement your notification checking logic here
    // This is a placeholder
    const count = 0; // Get from your API
    updateNotificationBadge(count);
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notif-count');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `toast toast-${type}`;
    
    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };
    
    notification.innerHTML = `
        <span class="toast-icon">${icons[type]}</span>
        <span class="toast-message">${message}</span>
    `;
    
    // Add to document
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    container.appendChild(notification);
    
    // Animate in
    setTimeout(() => notification.classList.add('show'), 10);
    
    // Auto remove
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

/* ============================================
   Tooltips
   ============================================ */

function initTooltips() {
    const elements = document.querySelectorAll('[title]');
    
    elements.forEach(element => {
        const title = element.getAttribute('title');
        if (!title) return;
        
        // Remove native title to prevent default tooltip
        element.removeAttribute('title');
        element.setAttribute('data-tooltip', title);
        
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
        element.addEventListener('focus', showTooltip);
        element.addEventListener('blur', hideTooltip);
    });
}

function showTooltip(e) {
    const element = e.currentTarget;
    const text = element.getAttribute('data-tooltip');
    if (!text) return;
    
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();
    
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltipRect.width / 2) + 'px';
    tooltip.style.top = rect.top - tooltipRect.height - 8 + 'px';
    
    element._tooltip = tooltip;
    
    setTimeout(() => tooltip.classList.add('show'), 10);
}

function hideTooltip(e) {
    const element = e.currentTarget;
    if (element._tooltip) {
        element._tooltip.classList.remove('show');
        setTimeout(() => {
            if (element._tooltip) {
                element._tooltip.remove();
                delete element._tooltip;
            }
        }, 200);
    }
}

/* ============================================
   Scroll Effects
   ============================================ */

function initScrollEffects() {
    const topnav = document.getElementById('topnav');
    let lastScroll = 0;
    
    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset;
        
        if (topnav) {
            if (currentScroll > 100) {
                topnav.classList.add('scrolled');
            } else {
                topnav.classList.remove('scrolled');
            }
            
            // Hide/show on scroll
            if (currentScroll > lastScroll && currentScroll > 100) {
                topnav.style.transform = 'translateY(-100%)';
            } else {
                topnav.style.transform = 'translateY(0)';
            }
        }
        
        lastScroll = currentScroll;
    });
}

/* ============================================
   Table Features
   ============================================ */

function initTableFeatures() {
    // Add sorting capability
    const tables = document.querySelectorAll('table');
    
    tables.forEach(table => {
        const headers = table.querySelectorAll('thead th');
        
        headers.forEach((header, index) => {
            if (!header.classList.contains('no-sort')) {
                header.style.cursor = 'pointer';
                header.innerHTML += ' <span class="sort-icon">⇅</span>';
                
                header.addEventListener('click', function() {
                    sortTable(table, index);
                });
            }
        });
    });
}

function sortTable(table, columnIndex) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const header = table.querySelectorAll('thead th')[columnIndex];
    
    const isAscending = header.classList.contains('sort-asc');
    
    // Remove all sort classes
    table.querySelectorAll('thead th').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
    });
    
    // Add appropriate class
    header.classList.add(isAscending ? 'sort-desc' : 'sort-asc');
    
    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        // Try to parse as number
        const aNum = parseFloat(aValue.replace(/[^0-9.-]/g, ''));
        const bNum = parseFloat(bValue.replace(/[^0-9.-]/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAscending ? bNum - aNum : aNum - bNum;
        }
        
        return isAscending 
            ? bValue.localeCompare(aValue)
            : aValue.localeCompare(bValue);
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

/* ============================================
   Form Validation
   ============================================ */

function initFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('invalid')) {
                    validateField(this);
                }
            });
        });
        
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            inputs.forEach(input => {
                if (!validateField(input)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Please fix the errors in the form', 'error');
            }
        });
    });
}

function validateField(field) {
    const value = field.value.trim();
    let isValid = true;
    let errorMessage = '';
    
    // Required validation
    if (field.hasAttribute('required') && !value) {
        isValid = false;
        errorMessage = 'This field is required';
    }
    
    // Email validation
    if (field.type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email';
        }
    }
    
    // Phone validation
    if (field.type === 'tel' && value) {
        const phoneRegex = /^\+?[\d\s-()]+$/;
        if (!phoneRegex.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid phone number';
        }
    }
    
    // Min length validation
    if (field.hasAttribute('minlength') && value) {
        const minLength = parseInt(field.getAttribute('minlength'));
        if (value.length < minLength) {
            isValid = false;
            errorMessage = `Minimum ${minLength} characters required`;
        }
    }
    
    // Update field state
    if (isValid) {
        field.classList.remove('invalid');
        field.classList.add('valid');
        removeFieldError(field);
    } else {
        field.classList.remove('valid');
        field.classList.add('invalid');
        showFieldError(field, errorMessage);
    }
    
    return isValid;
}

function showFieldError(field, message) {
    removeFieldError(field);
    
    const error = document.createElement('div');
    error.className = 'field-error';
    error.textContent = message;
    
    field.parentElement.appendChild(error);
}

function removeFieldError(field) {
    const error = field.parentElement.querySelector('.field-error');
    if (error) {
        error.remove();
    }
}

/* ============================================
   Modal Helper Functions
   ============================================ */

function openModal(content) {
    const modal = document.getElementById('modal');
    const modalBody = document.getElementById('modal-body');
    
    if (modal && modalBody) {
        modalBody.innerHTML = content;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        // Close button
        const closeBtn = modal.querySelector('.close');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }
        
        // Click outside to close
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
        
        // Escape key to close
        document.addEventListener('keydown', handleEscapeKey);
    }
}

function closeModal() {
    const modal = document.getElementById('modal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
        document.removeEventListener('keydown', handleEscapeKey);
    }
}

function handleEscapeKey(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
}

/* ============================================
   Utility Functions
   ============================================ */

// Format currency
function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

// Format date
function formatDate(date, format = 'short') {
    if (!date) return '-';
    
    const dateObj = new Date(date);
    if (isNaN(dateObj.getTime())) return '-';
    
    const options = {
        short: { year: 'numeric', month: 'short', day: 'numeric' },
        long: { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' },
        time: { hour: '2-digit', minute: '2-digit' }
    };
    
    return new Intl.DateTimeFormat('en-US', options[format]).format(dateObj);
}

// Debounce function
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

// Export functions for global use
window.ChurchCMS = {
    showNotification,
    openModal,
    closeModal,
    formatCurrency,
    formatDate,
    debounce
};
