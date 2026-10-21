// Auto-inject CSRF + credentials
const originalFetch = window.fetch;
window.fetch = async (input, init = {}) => {
    const url = typeof input === 'string' ? input : input.url;

    if (url.includes('/backend/') && init.method && init.method !== 'GET') {
        const token = await usersManager.getCsrfToken();
        init.headers = init.headers || {};
        init.headers['X-CSRF-Token'] = token;
        init.credentials = 'include';
    }
    return originalFetch(input, init);
};
// Enhanced Users Manager with Backend API Integration
class UsersManager {
    constructor() {
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.totalPages = 1;
        this.sortColumn = 'created_at';
        this.sortDirection = 'desc';
        this.filters = { search: '', role: '', status: '' };
        this.usersData = [];
         this.fellowships = [];          // <-- NEW
        this.apiBase = '../../backend/users';
        this.fellowshipApi = '../../backend/fellowships/read.php';

        this.initializeEventListeners();
        this.loadInitialData();
        this.loadFellowships();         // <-- NEW
    }

    initializeEventListeners() {
        // Buttons
        this.safeAddEventListener('create-user-btn', 'click', () => this.openUserModal());
        this.safeAddEventListener('create-from-empty', 'click', () => this.openUserModal());
        this.safeAddEventListener('confirm-password', 'input', () => this.checkPasswordMatch());
        this.safeAddEventListener('export-users', 'click', () => this.exportUsers());

        // Search & Filters
        this.safeAddEventListener('global-search', 'input', debounce((e) => {
            this.filters.search = e.target.value;
            this.currentPage = 1;
            this.loadUsers();
        }, 500));

        this.safeAddEventListener('role-filter', 'change', () => this.applyFilters());
        this.safeAddEventListener('status-filter', 'change', () => this.applyFilters());

        // Table sorting
        document.querySelectorAll('.data-table th[data-sort]').forEach(th => {
            th.addEventListener('click', () => this.sortTable(th.dataset.sort));
        });

        // Pagination
        this.safeAddEventListener('prev-page', 'click', () => this.previousPage());
        this.safeAddEventListener('next-page', 'click', () => this.nextPage());

        // Modal
        this.safeAddEventListener('user-form', 'submit', (e) => this.saveUser(e));
        this.safeAddEventListener('cancel-user', 'click', () => this.closeModal());
        this.safeAddEventListener('close-modal', 'click', () => this.closeModal());
        this.safeAddEventListener('modal-overlay', 'click', () => this.closeModal());

        // Password
        this.safeAddEventListener('toggle-password', 'click', () => this.togglePassword('password'));
        this.safeAddEventListener('toggle-confirm', 'click', () => this.togglePassword('confirm-password'));
        this.safeAddEventListener('password', 'input', () => this.checkPasswordStrength());
        this.safeAddEventListener('user-notes', 'input', (e) => {
            document.getElementById('char-count').textContent = e.target.value.length;
        });

        // Uniqueness checks
   // === UNIQUENESS CHECKS ===
this.safeAddEventListener('username', 'input', debounce(() => this.checkUsernameAvailability(), 600));
this.safeAddEventListener('username', 'blur', () => this.checkUsernameAvailability());

this.safeAddEventListener('user-email', 'input', debounce(() => this.checkEmailUniqueness(), 600));
this.safeAddEventListener('user-email', 'blur', () => this.checkEmailUniqueness());
    }

    safeAddEventListener(id, event, handler) {
        const el = document.getElementById(id);
        if (el) el.addEventListener(event, handler);
    }

    async loadInitialData() {
        await this.loadUsers();
        this.updateStats();
    }

  // In the loadUsers method, replace with this:
async loadUsers() {
    try {
        this.showLoading();
        
        const params = new URLSearchParams({
            page: this.currentPage,
            limit: this.itemsPerPage,
            sort: this.sortColumn,
            order: this.sortDirection
        });

        if (this.filters.search) params.append('search', this.filters.search);
        if (this.filters.role) params.append('role', this.filters.role);
        if (this.filters.status) params.append('status', this.filters.status);

                const response = await fetch(`${this.apiBase}/read.php?${params}`);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Server response:', errorText);
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        
                if (data.users) {
            this.usersData = data.users;
            this.totalPages = data.pagination.total_pages;
            this.renderUsersTable();
            this.updateStats();
        } else {
            throw new Error('Invalid response format: users array missing');
        }
    } catch (error) {
        console.error('Error loading users:', error);
        this.showNotification('Failed to load users. Please check backend connection.', 'error');
        this.usersData = [];
        this.renderUsersTable();
    } finally {
        this.hideLoading();
    }
}

async loadFellowships() {
    try {
        const resp = await fetch(this.fellowshipApi);
        if (!resp.ok) throw new Error('Failed to load fellowships');
        this.fellowships = await resp.json();

        const sel = document.getElementById('user-fellowship');
        if (!sel) return;

        sel.innerHTML = '<option value="">-- No Fellowship --</option>';
        this.fellowships.forEach(f => {
            const opt = document.createElement('option');
            opt.value = f.id;
            opt.textContent = f.name;
            sel.appendChild(opt);
        });
    } catch (e) {
        console.error(e);
        this.showNotification('Could not load fellowships', 'error');
    }
}

    applyFilters() {
        this.filters.role = document.getElementById('role-filter')?.value || '';
        this.filters.status = document.getElementById('status-filter')?.value || '';
        this.currentPage = 1;
        this.loadUsers();
    }

    sortTable(column) {
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'asc';
        }
        this.updateSortIcons();
        this.loadUsers();
    }

    updateSortIcons() {
        document.querySelectorAll('.sort-icon').forEach(icon => {
            icon.className = 'fas fa-sort sort-icon';
            icon.style.color = 'var(--text-muted)';
        });
        const active = document.querySelector(`[data-sort="${this.sortColumn}"] .sort-icon`);
        if (active) {
            active.className = `fas fa-sort-${this.sortDirection === 'asc' ? 'up' : 'down'} sort-icon`;
            active.style.color = 'var(--primary-color)';
        }
    }

    renderUsersTable() {
        const tbody = document.getElementById('users-table-body');
        const emptyState = document.getElementById('table-empty-state');

        if (!tbody) return;

        if (this.usersData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-muted);">No users found</td></tr>';
            if (emptyState) emptyState.style.display = 'flex';
            this.updatePagination(0);
            this.updateTableInfo(0, 0, 0);
            return;
        }

        if (emptyState) emptyState.style.display = 'none';

        tbody.innerHTML = this.usersData.map((user, i) => `
            <tr class="fade-in-up" style="animation-delay: ${i * 0.05}s">
                <td>
                    <div class="user-cell">
                        <div class="user-avatar">${user.first_name?.[0] || ''}${user.last_name?.[0] || ''}</div>
                        <div>
                            <div class="user-name">${user.first_name || ''} ${user.last_name || ''}</div>
                            <div class="user-username">@${user.username || ''}</div>
                        </div>
                    </div>
                </td>
                <td>${user.email || ''}</td>
                <td><span class="role-badge role-${user.role}">${this.formatRole(user.role)}</span></td>
                <td>${this.escapeHtml(user.fellowship_display)}</td>
                <td><span class="status-badge status-${user.status}">${this.formatStatus(user.status)}</span></td>
                <td>${this.formatDateTime(user.last_login)}</td>
                <td>${this.formatDate(user.created_at)}</td>
                <td class="text-center">
                    <div class="action-buttons-wrapper">
                        <button class="btn-action edit" onclick="usersManager.openUserModal(${user.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-action delete" onclick="usersManager.deleteUser(${user.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button class="btn-action reset" onclick="usersManager.resetPassword(${user.id})" title="Reset Password">
                            <i class="fas fa-key"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        this.updatePagination(this.usersData.length);
        const start = (this.currentPage - 1) * this.itemsPerPage + 1;
        const end = Math.min(this.currentPage * this.itemsPerPage, this.usersData.length);
        this.updateTableInfo(start, end, this.usersData.length);
    }

    updatePagination(total) {
        const container = document.getElementById('page-numbers');
        if (!container) return;

        container.innerHTML = '';
        
        const prevBtn = document.getElementById('prev-page');
        const nextBtn = document.getElementById('next-page');

        if (this.totalPages <= 1) {
            if (prevBtn) prevBtn.disabled = true;
            if (nextBtn) nextBtn.disabled = true;
            return;
        }

        // Show limited page numbers
        const startPage = Math.max(1, this.currentPage - 2);
        const endPage = Math.min(this.totalPages, startPage + 4);

        for (let i = startPage; i <= endPage; i++) {
            const btn = document.createElement('button');
            btn.className = `page-number ${i === this.currentPage ? 'active' : ''}`;
            btn.textContent = i;
            btn.onclick = () => this.goToPage(i);
            container.appendChild(btn);
        }

        if (prevBtn) prevBtn.disabled = this.currentPage === 1;
        if (nextBtn) nextBtn.disabled = this.currentPage === this.totalPages;
    }

    updateTableInfo(start, end, total) {
        const info = document.getElementById('table-info');
        if (info) info.textContent = `Showing ${start}–${end} of ${total} entries`;
    }

    goToPage(page) { 
        this.currentPage = page; 
        this.loadUsers(); 
    }
    
    previousPage() { 
        if (this.currentPage > 1) { 
            this.currentPage--; 
            this.loadUsers(); 
        } 
    }
    
    nextPage() { 
        if (this.currentPage < this.totalPages) { 
            this.currentPage++; 
            this.loadUsers(); 
        } 
    }

    updateStats() {
        const total = this.usersData.length;
        const active = this.usersData.filter(u => u.status === 'active').length;
        const admins = this.usersData.filter(u => u.role === 'admin').length;

        ['total-users-count', 'active-users-count', 'admin-count'].forEach((id, i) => {
            const el = document.getElementById(id);
            if (el) el.textContent = [total, active, admins][i];
        });
    }

openUserModal(id = null) {
    const modal = document.getElementById('user-modal');
    const title = document.getElementById('modal-title');
    const subtitle = document.getElementById('modal-subtitle');
    

    if (!modal) return;

    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    this.clearAllErrors();

    const isEditMode = !!id;
    
    if (isEditMode) {
        const user = this.usersData.find(u => u.id === id);
        if (!user) return;

        title.textContent = 'Edit User';
        subtitle.textContent = 'Update user account details';

        document.getElementById('user-id').value = user.id;
        document.getElementById('username').value = user.username || '';
        document.getElementById('user-email').value = user.email || '';
        document.getElementById('first-name').value = user.first_name || '';
        document.getElementById('last-name').value = user.last_name || '';
        document.getElementById('user-role').value = user.role || 'leader';  // Changed default
        document.getElementById('user-status').value = user.status || 'active';
        document.getElementById('user-notes').value = user.notes || '';
        document.getElementById('char-count').textContent = (user.notes || '').length;
        document.getElementById('user-fellowship').value = user.fellowship_id || '';
        // Hide password fields for editing
        const passwordSection = document.getElementById('password-section');
        if (passwordSection) {
            passwordSection.style.display = 'none';
        }
        
        const passwordInputs = document.querySelectorAll('#password, #confirm-password');
        passwordInputs.forEach(input => {
            input.removeAttribute('required');
            input.removeAttribute('data-required');
        });

    } else {
        title.textContent = 'Add New User';
        subtitle.textContent = 'Create a new system user account';
        
        // Reset form
        document.getElementById('user-form').reset();
        document.getElementById('user-id').value = '';
        document.getElementById('char-count').textContent = '0';
        document.getElementById('user-role').value = 'pastor';  // Changed default to match database
        document.getElementById('user-status').value = 'active';

        // Show password fields
        const passwordSection = document.getElementById('password-section');
        if (passwordSection) {
            passwordSection.style.display = 'block';
        }
        
        const passwordInputs = document.querySelectorAll('#password, #confirm-password');
        passwordInputs.forEach(input => {
            input.setAttribute('required', 'true');
            input.setAttribute('data-required', 'true');
        });
    }

    this.checkPasswordStrength();
    this.resetPasswordStrength();
    
    setTimeout(() => {
        document.getElementById('username').focus();
    }, 150);
}

closeModal() {
    const modal = document.getElementById('user-modal');
    if (modal) modal.classList.remove('show');
    document.body.style.overflow = '';
    this.resetUserForm();
}

resetUserForm() {
    const form = document.getElementById('user-form');
    if (form) {
        form.reset();
        document.getElementById('user-id').value = '';
        document.getElementById('char-count').textContent = '0';
        
        // Always show password fields when resetting
        const passwordSection = document.getElementById('password-section');
        if (passwordSection) {
            passwordSection.style.display = 'block';
        }
        
        // Set required for password fields
        const passwordInputs = document.querySelectorAll('#password, #confirm-password');
        passwordInputs.forEach(input => {
            input.setAttribute('required', 'true');
            input.setAttribute('data-required', 'true');
        });
    }
    
    this.clearAllErrors();
    this.resetPasswordStrength();
}

    togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = document.getElementById(fieldId === 'password' ? 'toggle-password' : 'toggle-confirm');
        if (!field || !icon) return;
        const isText = field.type === 'text';
        field.type = isText ? 'password' : 'text';
        icon.classList.toggle('fa-eye', isText);
        icon.classList.toggle('fa-eye-slash', !isText);
    }

    checkPasswordStrength() {
        const password = document.getElementById('password').value;
        const strengthEl = document.getElementById('password-strength-indicator');
        if (!strengthEl) return;

        const bar = strengthEl.querySelector('.strength-bar > div');
        const text = strengthEl.querySelector('.strength-text');

        let strength = 0;
        
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;

        strengthEl.className = 'password-strength';
        if (bar) bar.style.width = '0%';
        if (text) text.textContent = 'Weak';

        if (password.length === 0) {
            strengthEl.style.display = 'none';
            return;
        } else {
            strengthEl.style.display = 'block';
        }

        if (strength === 1) {
            strengthEl.classList.add('strength-weak');
            if (bar) bar.style.width = '25%';
            if (text) text.textContent = 'Weak';
        } else if (strength === 2) {
            strengthEl.classList.add('strength-fair');
            if (bar) bar.style.width = '50%';
            if (text) text.textContent = 'Fair';
        } else if (strength === 3) {
            strengthEl.classList.add('strength-good');
            if (bar) bar.style.width = '75%';
            if (text) text.textContent = 'Good';
        } else if (strength === 4) {
            strengthEl.classList.add('strength-strong');
            if (bar) bar.style.width = '100%';
            if (text) text.textContent = 'Strong';
        }
    }

    checkPasswordMatch() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm-password').value;
        const matchIndicator = document.getElementById('password-match-indicator');
        
        if (!matchIndicator) return;

        if (confirmPassword.length === 0) {
            matchIndicator.style.display = 'none';
            return;
        }

        matchIndicator.style.display = 'block';
        
        if (password === confirmPassword) {
            matchIndicator.innerHTML = '<i class="fas fa-check-circle" style="color: var(--success-color);"></i> Passwords match';
            matchIndicator.style.color = 'var(--success-color)';
            document.getElementById('confirm-password').classList.remove('error');
        } else {
            matchIndicator.innerHTML = '<i class="fas fa-exclamation-circle" style="color: var(--error-color);"></i> Passwords do not match';
            matchIndicator.style.color = 'var(--error-color)';
            document.getElementById('confirm-password').classList.add('error');
        }
    }

    resetPasswordStrength() {
        const el = document.getElementById('password-strength-indicator');
        if (el) {
            el.className = 'password-strength';
            el.style.display = 'none';
            const bar = el.querySelector('.strength-bar > div');
            const text = el.querySelector('.strength-text');
            if (bar) bar.style.width = '0%';
            if (text) text.textContent = 'Weak';
        }
        
        const matchIndicator = document.getElementById('password-match-indicator');
        if (matchIndicator) {
            matchIndicator.style.display = 'none';
            document.getElementById('confirm-password').classList.remove('error');
        }
    }

// === UNIQUENESS CHECKS ===
async checkUsernameAvailability() {
    const input = document.getElementById('username');
    const value = input.value.trim();
    const currentId = document.getElementById('user-id').value;
    const feedback = this.getOrCreateFeedback(input);

    if (!value) {
        this.setFeedback(feedback, '', '');
        return;
    }

    try {
        // Check against backend for real-time validation
        const response = await fetch(`${this.apiBase}/check_availability.php?field=username&value=${encodeURIComponent(value)}&current_id=${currentId}`);
        
        if (response.ok) {
            const result = await response.json();
            
            if (result.available) {
                this.setFeedback(feedback, 'Username available', 'success');
                input.classList.remove('error');
            } else {
                this.setFeedback(feedback, 'Username already taken', 'error');
                input.classList.add('error');
            }
        } else {
            // Fallback to frontend check if backend fails
            const exists = this.usersData.some(u => 
                u.username.toLowerCase() === value.toLowerCase() && u.id != currentId
            );

            if (exists) {
                this.setFeedback(feedback, 'Username already taken', 'error');
                input.classList.add('error');
            } else {
                this.setFeedback(feedback, 'Username available', 'success');
                input.classList.remove('error');
            }
        }
    } catch (error) {
        console.error('Error checking username:', error);
        // Fallback to frontend check
        const exists = this.usersData.some(u => 
            u.username.toLowerCase() === value.toLowerCase() && u.id != currentId
        );

        if (exists) {
            this.setFeedback(feedback, 'Username already taken', 'error');
            input.classList.add('error');
        } else {
            this.setFeedback(feedback, 'Username available', 'success');
            input.classList.remove('error');
        }
    }
}

async checkEmailUniqueness() {
    const input = document.getElementById('user-email');
    const value = input.value.trim();
    const currentId = document.getElementById('user-id').value;
    const feedback = this.getOrCreateFeedback(input);

    if (!value) {
        this.setFeedback(feedback, '', '');
        return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        this.setFeedback(feedback, 'Invalid email format', 'error');
        input.classList.add('error');
        return;
    }

    try {
        // Check against backend for real-time validation
        const response = await fetch(`${this.apiBase}/check_availability.php?field=email&value=${encodeURIComponent(value)}&current_id=${currentId}`);
        
        if (response.ok) {
            const result = await response.json();
            
            if (result.available) {
                this.setFeedback(feedback, 'Email available', 'success');
                input.classList.remove('error');
            } else {
                this.setFeedback(feedback, 'Email already in use', 'error');
                input.classList.add('error');
            }
        } else {
            // Fallback to frontend check if backend fails
            const exists = this.usersData.some(u => 
                u.email.toLowerCase() === value.toLowerCase() && u.id != currentId
            );

            if (exists) {
                this.setFeedback(feedback, 'Email already in use', 'error');
                input.classList.add('error');
            } else {
                this.setFeedback(feedback, 'Email available', 'success');
                input.classList.remove('error');
            }
        }
    } catch (error) {
        console.error('Error checking email:', error);
        // Fallback to frontend check
        const exists = this.usersData.some(u => 
            u.email.toLowerCase() === value.toLowerCase() && u.id != currentId
        );

        if (exists) {
            this.setFeedback(feedback, 'Email already in use', 'error');
            input.classList.add('error');
        } else {
            this.setFeedback(feedback, 'Email available', 'success');
            input.classList.remove('error');
        }
    }
}

    getOrCreateFeedback(input) {
        let feedback = input.parentNode.querySelector('.field-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'field-feedback';
            input.parentNode.appendChild(feedback);
        }
        return feedback;
    }

    setFeedback(el, message, type) {
        el.textContent = message;
        el.className = `field-feedback feedback-${type}`;
        el.style.display = message ? 'flex' : 'none';
    }

    clearAllErrors() {
        document.querySelectorAll('.field-feedback').forEach(el => el.remove());
        document.querySelectorAll('.form-group input.error').forEach(el => el.classList.remove('error'));
    }

// Update the saveUser method to handle password validation only for new users
// Replace your loadUsers method with this improved version
async loadUsers() {
    try {
        this.showLoading();
        
        const params = new URLSearchParams({
            page: this.currentPage,
            limit: this.itemsPerPage,
            sort: this.sortColumn,
            order: this.sortDirection
        });

        if (this.filters.search) params.append('search', this.filters.search);
        if (this.filters.role) params.append('role', this.filters.role);
        if (this.filters.status) params.append('status', this.filters.status);

        const url = `${this.apiBase}/read.php?${params}`;
                const response = await fetch(url);
        
        // IMPORTANT: Get raw text first to see what's being returned
        const responseText = await response.text();
        
        if (!response.ok) {
            console.error('❌ HTTP error! status:', response.status);
            console.error('Response length:', responseText.length);
            throw new Error(`HTTP ${response.status}: Server error`);
        }

        // Check if response looks like JSON
        if (!responseText.trim().startsWith('{') && !responseText.trim().startsWith('[')) {
            console.error('❌ Response doesn\'t look like JSON!');
            console.error('Response length:', responseText.length);
            console.error('First 50 characters:', responseText.substring(0, 50));
            throw new Error('Server returned non-JSON response. Check read.php for errors or whitespace.');
        }

        // Try to parse JSON
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('❌ JSON Parse Error!');
            console.error('Error message:', jsonError.message);
            console.error('Response length:', responseText.length);
            console.error('First 100 chars:', responseText.substring(0, 100));
            
            throw new Error(`Server returned invalid JSON. Check console for details. Error: ${jsonError.message}`);
        }
        
        // Validate response structure
        if (!data.users) {
            console.error('❌ Invalid response format - users array missing');
            throw new Error('Invalid response format: users array missing');
        }

        if (!data.pagination) {
            console.warn('⚠️ Pagination data missing, using defaults');
            data.pagination = {
                current_page: 1,
                total_pages: 1,
                total_users: data.users.length,
                users_per_page: this.itemsPerPage
            };
        }
        
                this.usersData = data.users;
        this.totalPages = data.pagination.total_pages;
        this.renderUsersTable();
        this.updateStats();
        
    } catch (error) {
        console.error('❌ Error loading users:', error);
        console.error('Error stack:', error.stack);
        
        // Show user-friendly error message
        let errorMessage = 'Failed to load users. ';
        
        if (error.message.includes('JSON')) {
            errorMessage += 'The server returned invalid data. Check the browser console and read.php file.';
        } else if (error.message.includes('HTTP')) {
            errorMessage += 'Server error. Check that backend/users/read.php exists and database is connected.';
        } else {
            errorMessage += error.message;
        }
        
        this.showNotification(errorMessage, 'error');
        this.usersData = [];
        this.renderUsersTable();
        
    } finally {
        this.hideLoading();
    }
}

// Also add this helper method to escape HTML in the renderUsersTable
escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async deleteUser(id) {
    if (!confirm('Are you sure you want to delete this user? This cannot be undone.')) return;

    try {
        const csrfToken = await this.getCsrfToken(); // Reuse from saveUser()
        if (!csrfToken) throw new Error('CSRF token missing');

        const response = await fetch(`${this.apiBase}/delete.php`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken  // CRITICAL: Missing before
            },
            credentials: 'include',  // Required for session cookie
            body: JSON.stringify({ id })
        });

        const result = await response.json();

        if (response.ok) {
            this.showNotification(result.message || 'User deleted successfully', 'success');
            this.loadUsers(); // Refresh list
        } else {
            let errorMsg = result.message || 'Delete failed';
            if (response.status === 403) {
                errorMsg = 'Invalid CSRF token. Please refresh the page.';
            } else if (response.status === 404) {
                errorMsg = 'User not found';
            } else if (response.status === 400) {
                errorMsg = result.message;
            }
            throw new Error(errorMsg);
        }
    } catch (error) {
        console.error('Delete error:', error);
        this.showNotification('Error: ' + error.message, 'error');
    }
}

    resetPassword(id) {
        if (confirm('Send password reset email to this user?')) {
            // Implement password reset functionality
            this.showNotification('Password reset email sent!', 'success');
        }
    }
// IMPROVED CSRF TOKEN HANDLING
// Replace your existing getCsrfToken method with this:

async getCsrfToken() {
    // First, try to get from meta tag
    let token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    if (token) {
        return token;
    }

    // If not in meta tag, fetch from backend
        try {
        const resp = await fetch('../../backend/users/get_csrf.php', {
            credentials: 'include'
        });
        
        if (!resp.ok) {
            throw new Error(`HTTP ${resp.status}`);
        }
        
        const data = await resp.json();
        
        if (!data.csrf_token) {
            throw new Error('No CSRF token in response');
        }
        
        // Store in meta tag for future use
        let metaTag = document.querySelector('meta[name="csrf-token"]');
        if (!metaTag) {
            metaTag = document.createElement('meta');
            metaTag.name = 'csrf-token';
            document.head.appendChild(metaTag);
        }
        metaTag.setAttribute('content', data.csrf_token);
        
        return data.csrf_token;
        
    } catch (e) {
        console.error('❌ CSRF fetch failed:', e);
        throw new Error('Failed to get CSRF token. Please refresh the page.');
    }
}

// ALSO UPDATE YOUR saveUser METHOD - Replace the formData section with this:

async saveUser(e) {
    e.preventDefault();

    // Debug: Log form data before validation
        const debugData = {
        username: document.getElementById('username').value,
        email: document.getElementById('user-email').value,
        first_name: document.getElementById('first-name').value,
        last_name: document.getElementById('last-name').value,
        role: document.getElementById('user-role').value,
        status: document.getElementById('user-status').value,
        fellowship_id: document.getElementById('user-fellowship').value,
        user_id: document.getElementById('user-id').value
    };
        // Run final uniqueness checks
    await this.checkUsernameAvailability();
    await this.checkEmailUniqueness();

    // Wait for validation to complete
    await new Promise(resolve => setTimeout(resolve, 200));

    // Check for validation errors
    const errorFields = document.querySelectorAll('.feedback-error');
    const errorInputs = document.querySelectorAll('.form-group input.error');
    
    if (errorFields.length > 0 || errorInputs.length > 0) {
        this.showNotification('Please fix the validation errors before saving', 'error');
        return;
    }

    // Check for password match if creating new user
    const isNewUser = !document.getElementById('user-id').value;
    if (isNewUser) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm-password').value;
        
        if (password !== confirmPassword) {
            this.showNotification('Passwords do not match', 'error');
            return;
        }
        
        if (password.length < 8) {
            this.showNotification('Password must be at least 8 characters long', 'error');
            return;
        }
    }

    const submitBtn = e.target.querySelector('button[type="submit"]');
    const text = submitBtn.querySelector('#submit-text');
    const spinner = document.getElementById('submit-spinner');

    submitBtn.disabled = true;
    if (text) text.style.display = 'none';
    if (spinner) spinner.style.display = 'inline-block';

    try {
        // Get CSRF token FIRST
        const csrfToken = await this.getCsrfToken();

        if (!csrfToken) {
            throw new Error('CSRF token is missing. Please refresh the page.');
        }

        // Get form data with proper validation
        const formData = {
            username: document.getElementById('username').value.trim(),
            email: document.getElementById('user-email').value.trim(),
            first_name: document.getElementById('first-name').value.trim(),
            last_name: document.getElementById('last-name').value.trim(),
            role: document.getElementById('user-role').value,
            status: document.getElementById('user-status').value,
            notes: document.getElementById('user-notes').value.trim()
        };

        // Handle fellowship_id - convert empty string to null
        const fellowshipValue = document.getElementById('user-fellowship').value;
        formData.fellowship_id = fellowshipValue && fellowshipValue !== '' ? parseInt(fellowshipValue) : null;

        // Validate role value
        const VALID_ROLES = ['admin', 'pastor', 'pcf', 'leader'];
        if (!VALID_ROLES.includes(formData.role)) {
            throw new Error(`Invalid role value: ${formData.role}. Allowed values: ${VALID_ROLES.join(', ')}`);
        }

        // Add password only for new users
        if (isNewUser) {
            formData.password = document.getElementById('password').value;
        }

        const userId = document.getElementById('user-id').value;
        const url = isNewUser ? `${this.apiBase}/create.php` : `${this.apiBase}/update.php`;
        const method = isNewUser ? 'POST' : 'PUT';

        if (!isNewUser) {
            formData.id = parseInt(userId);
        }

                        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'include',
            body: JSON.stringify(formData)
        });

        const result = await response.json();
                if (response.ok) {
            this.closeModal();
            this.showNotification(result.message || 'User saved successfully!', 'success');
            await this.loadUsers();
        } else {
            // Handle specific error cases
            let errorMessage = result.message || `Failed to save user: ${response.status}`;
            
            if (response.status === 400) {
                if (result.message && result.message.includes('role')) {
                    errorMessage = 'Invalid role selected. Please choose a valid role.';
                }
            } else if (response.status === 409) {
                errorMessage = 'Username or email already exists. Please choose different values.';
            } else if (response.status === 403) {
                errorMessage = 'CSRF token invalid. Refreshing page...';
                setTimeout(() => location.reload(), 2000);
            }
            
            throw new Error(errorMessage);
        }
    } catch (error) {
        console.error('❌ Error saving user:', error);
        this.showNotification('Error: ' + error.message, 'error');
    } finally {
        submitBtn.disabled = false;
        if (text) text.style.display = 'inline';
        if (spinner) spinner.style.display = 'none';
    }
}

    showLoading() {
        // Add loading indicator to table
        const tbody = document.getElementById('users-table-body');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;"><div class="spinner"></div><p>Loading users...</p></td></tr>';
        }
    }

    hideLoading() {
        // Remove loading indicator
    }

formatRole(role) { 
    const m = { 
        admin: 'Administrator', 
        pastor: 'Pastor', 
        pcf: 'PCF Leader', 
        leader: 'Fellowship Leader' 
    }; 
    return m[role] || role; 
}
    
    formatStatus(status) { 
        const m = { active: 'Active', inactive: 'Inactive', suspended: 'Suspended' }; 
        return m[status] || status; 
    }
    
    formatDate(date) { 
        if (!date) return 'N/A';
        return new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }); 
    }
    
    formatDateTime(datetime) { 
        if (!datetime) return 'Never'; 
        const d = new Date(datetime); 
        return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) + ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }); 
    }

    exportUsers() {
        this.showNotification('Export feature coming soon!', 'info');
    }

    showNotification(message, type = 'info') {
        if (window.showNotification) { 
            window.showNotification(message, type); 
            return; 
        }
        
        const n = document.createElement('div');
        n.className = `notification notification-${type}`;
        n.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i> ${message}`;
        n.style.cssText = `position:fixed;top:20px;right:20px;padding:12px 20px;border-radius:8px;color:white;z-index:10000;transform:translateX(400px);transition:transform .3s ease;box-shadow:0 4px 15px rgba(0,0,0,.2);font-weight:500;display:flex;gap:8px;align-items:center;background:${type === 'success' ? 'linear-gradient(135deg,#06d6a0,#20d489)' : type === 'error' ? 'linear-gradient(135deg,#ef476f,#ff6b7a)' : 'linear-gradient(135deg,#118ab2,#06d6a0)'};`;
        document.body.appendChild(n);
        requestAnimationFrame(() => n.style.transform = 'translateX(0)');
        setTimeout(() => { 
            n.style.transform = 'translateX(400px)'; 
            setTimeout(() => n.remove(), 300); 
        }, 3000);
    }
}

function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

document.addEventListener('DOMContentLoaded', () => {
    window.usersManager = new UsersManager();
});
