// members.js - COMPLETELY FIXED VERSION
class MembersManager {
    constructor() {
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.totalPages = 1;
        this.sortColumn = 'first_name';
        this.sortDirection = 'asc';

        this.filters = {
            search: '',
            fellowship: '',
            status: '',
            category: '',
            department: '',
            cell: ''
        };

        this.membersData = [];
        this.fellowshipsData = [];
        this.departmentsData = [];
        this.cellsData = [];
        this.apiBase = '../../backend/members';
        this.fellowshipApi = '../../backend/fellowships/read.php';
        this.existingPhones = new Set();
        this.existingEmails = new Set();

        this.initializeEventListeners();
        this.loadInitialData();
    }

    initializeEventListeners() {
        // Main buttons
        document.getElementById('create-member-btn')?.addEventListener('click', () => this.openMemberModal());
        document.getElementById('create-from-empty')?.addEventListener('click', () => this.openMemberModal());

        // Form submission
        document.getElementById('member-form')?.addEventListener('submit', (e) => this.saveMember(e));

        // Filters
        document.getElementById('member-fellowship')?.addEventListener('change', () => {
            this.filters.fellowship = document.getElementById('member-fellowship').value;
            this.currentPage = 1;
            this.loadMembers();
        });

        document.getElementById('member-status')?.addEventListener('change', () => {
            this.filters.status = document.getElementById('member-status').value;
            this.currentPage = 1;
            this.loadMembers();
        });

        document.getElementById('member-category-filter')?.addEventListener('change', () => {
            this.filters.category = document.getElementById('member-category-filter').value;
            this.currentPage = 1;
            this.loadMembers();
        });

        document.getElementById('filter-member-department')?.addEventListener('change', () => {
            this.filters.department = document.getElementById('filter-member-department').value;
            this.currentPage = 1;
            this.loadMembers();
        });

        document.getElementById('filter-member-cell')?.addEventListener('change', () => {
            this.filters.cell = document.getElementById('filter-member-cell').value;
            this.currentPage = 1;
            this.loadMembers();
        });

        // Search with debounce
        document.getElementById('global-search')?.addEventListener('input',
            debounce(() => {
                this.filters.search = document.getElementById('global-search').value;
                this.currentPage = 1;
                this.loadMembers();
            }, 500)
        );

        // Apply/Reset filters
        document.getElementById('apply-member-filters')?.addEventListener('click', () => this.loadMembers());
        document.getElementById('reset-member-filters')?.addEventListener('click', () => this.resetFilters());

        // Live validation
        this.setupLiveValidation();

        // Export and other actions
        document.getElementById('export-members')?.addEventListener('click', () => this.exportToCSV());
        document.getElementById('print-members')?.addEventListener('click', () => this.printMembers());
        document.getElementById('download-template')?.addEventListener('click', () => this.downloadTemplate());
        document.getElementById('gsheet-import-btn')?.addEventListener('click', () => this.openImportModal('gsheet'));
        document.getElementById('schedule-import-btn')?.addEventListener('click', () => this.openImportModal('schedule'));
        document.getElementById('import-history-btn')?.addEventListener('click', () => this.openImportModal('history'));

        // Modal close events
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal') || e.target.closest('.close-modal')) {
                this.closeModal();
            }

            // Close fellowship dropdown
            const fellowshipInput = document.getElementById('fellowship-search');
            const fellowshipDropdown = document.getElementById('fellowship-dropdown');
            if (fellowshipDropdown && fellowshipInput && !fellowshipInput.contains(e.target) && !fellowshipDropdown.contains(e.target)) {
                fellowshipDropdown.style.display = 'none';
                fellowshipInput.closest('.searchable-select')?.classList.remove('active');
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.closeModal();
        });

        // Pagination
        document.getElementById('prev-page')?.addEventListener('click', () => this.previousPage());
        document.getElementById('next-page')?.addEventListener('click', () => this.nextPage());

        // Photo upload
        document.getElementById('photo-preview')?.addEventListener('click', () => document.getElementById('photo').click());
        document.getElementById('photo')?.addEventListener('change', (e) => this.handlePhotoUpload(e));

        // Auto-set category from birth date
        document.getElementById('birth-date')?.addEventListener('change', () => this.autoSetCategory());

        // Fellowship dropdown
        this.setupFellowshipDropdown();
    }

    setupLiveValidation() {
        // Real-time validation for form fields
        const fields = [
            { id: 'first-name', validator: () => this.validateRequired('first-name', 'First name is required') },
            { id: 'last-name', validator: () => this.validateRequired('last-name', 'Last name is required') },
            { id: 'phone', validator: () => this.validatePhone() },
            { id: 'email', validator: () => this.validateEmail() },
            { id: 'join-date', validator: () => this.validateRequired('join-date', 'Join date is required') },
            { id: 'member-status', validator: () => this.validateRequired('member-status', 'Status is required') },
            { id: 'member-category', validator: () => this.validateRequired('member-category', 'Category is required') }
        ];

        fields.forEach(field => {
            const element = document.getElementById(field.id);
            if (element) {
                element.addEventListener('input', field.validator);
                element.addEventListener('change', field.validator);
            }
        });
    }

    validateRequired(fieldId, message) {
        const field = document.getElementById(fieldId);
        const value = field?.value.trim();
        this.validateField(fieldId, !value, message);
        return !(!value);
    }

    validatePhone() {
        const field = document.getElementById('phone');
        const value = field?.value.trim();
        const isValid = this.isValidPhone(value);
        this.validateField('phone', value && !isValid, 'Enter valid Ghana phone (e.g., 0241234567)');
        return !(value && !isValid);
    }

    validateEmail() {
        const field = document.getElementById('email');
        const value = field?.value.trim();
        this.validateField('email', value && !this.isValidEmail(value), 'Enter a valid email address');
        return !(value && !this.isValidEmail(value));
    }

    validateField(fieldId, invalid, message) {
        const field = document.getElementById(fieldId);
        const formGroup = field?.closest('.form-group');
        const errorEl = formGroup?.querySelector('.error-text');

        if (!field || !errorEl) return;

        if (invalid) {
            field.classList.add('error');
            errorEl.textContent = message;
            errorEl.style.display = 'block';
        } else {
            field.classList.remove('error');
            errorEl.textContent = '';
            errorEl.style.display = 'none';
        }
    }

    autoSetCategory() {
        const birthDate = document.getElementById('birth-date')?.value;
        if (!birthDate) return;

        const today = new Date();
        const birth = new Date(birthDate);
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();

        // Adjust age if birthday hasn't occurred this year
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }

        let category = 'adult';
        if (age < 13) category = 'children';
        else if (age < 18) category = 'teen';

        document.getElementById('member-category').value = category;
    }

    setupFellowshipDropdown() {
        const input = document.getElementById('fellowship-search');
        const dropdown = document.getElementById('fellowship-dropdown');
        if (!input || !dropdown) return;

        input.addEventListener('input', () => {
            const query = input.value.trim();
            dropdown.style.display = 'block';
            input.closest('.searchable-select')?.classList.add('active');
            this.renderFellowshipDropdown(query);
        });

        input.addEventListener('focus', () => {
            dropdown.style.display = 'block';
            input.closest('.searchable-select')?.classList.add('active');
            this.renderFellowshipDropdown(input.value.trim());
        });

        input.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.style.display = 'block';
            input.closest('.searchable-select')?.classList.add('active');
            this.renderFellowshipDropdown(input.value.trim());
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }

    renderFellowshipDropdown(query = '') {
        const dropdown = document.getElementById('fellowship-dropdown');
        if (!dropdown) return;

        const filtered = this.fellowshipsData
            .filter(f => f.name.toLowerCase().includes(query.toLowerCase()))
            .slice(0, 10);

        dropdown.innerHTML = filtered.length
            ? filtered.map(f => `
                <div class="dropdown-item" data-id="${f.id}" data-name="${this.escapeHtml(f.name)}">
                    ${this.escapeHtml(f.name)}
                </div>
            `).join('')
            : '<div class="dropdown-item disabled">No fellowships found</div>';

        dropdown.querySelectorAll('.dropdown-item:not(.disabled)').forEach(item => {
            item.addEventListener('click', () => {
                document.getElementById('fellowship-search').value = item.dataset.name;
                document.getElementById('fellowship').value = item.dataset.id;
                dropdown.style.display = 'none';
                document.getElementById('fellowship-search')?.closest('.searchable-select')?.classList.remove('active');
                this.validateRequired('fellowship-search', 'Please select a fellowship');
            });
        });
    }

    async loadInitialData() {
        this.showLoading();
        await Promise.all([
            this.loadFellowships(),
            this.loadDepartments(),
            this.loadCells(),
            this.loadMembers()
        ]);
        this.hideLoading();
    }

    async loadDepartments() {
        try {
            const response = await fetch('../../backend/ajax/departments_crud.php');
            const result = await response.json();
            if (result.success) {
                this.departmentsData = result.data;
                const options = this.departmentsData.map(d => `<option value="${d.id}">${this.escapeHtml(d.name)}</option>`).join('');
                
                const modalSelect = document.getElementById('member-department');
                if (modalSelect) modalSelect.innerHTML = '<option value="">No Department</option>' + options;
                
                const filterSelect = document.getElementById('filter-member-department');
                if (filterSelect) filterSelect.innerHTML = '<option value="">All Departments</option>' + options;
            }
        } catch (error) {
            console.error('Error loading departments:', error);
        }
    }

    async loadCells() {
        try {
            const response = await fetch('../../backend/ajax/cells_crud.php');
            const result = await response.json();
            if (result.success) {
                this.cellsData = result.data;
                const options = this.cellsData.map(c => `<option value="${c.id}">${this.escapeHtml(c.name)}</option>`).join('');
                
                const modalSelect = document.getElementById('member-cell');
                if (modalSelect) modalSelect.innerHTML = '<option value="">No Cell</option>' + options;
                
                const filterSelect = document.getElementById('filter-member-cell');
                if (filterSelect) filterSelect.innerHTML = '<option value="">All Cells</option>' + options;
            }
        } catch (error) {
            console.error('Error loading cells:', error);
        }
    }

    async loadFellowships() {
        try {
            const response = await fetch(this.fellowshipApi);
            const data = await response.json();
            this.fellowshipsData = data.fellowships || data || [];
            this.renderFellowshipDropdown();
            this.populateFellowshipFilter();
        } catch (error) {
            console.error('Error loading fellowships:', error);
            this.showNotification('Failed to load fellowships', 'error');
        }
    }

    populateFellowshipFilter() {
        const dropdown = document.getElementById('member-fellowship');
        if (!dropdown) return;

        dropdown.innerHTML = '<option value="">All Fellowships</option>' +
            this.fellowshipsData
                .map(f => `<option value="${f.id}">${this.escapeHtml(f.name)}</option>`)
                .join('');
    }

    async loadMembers() {
        try {
            this.showLoading();
            const params = new URLSearchParams({
                page: this.currentPage,
                limit: this.itemsPerPage,
                sort: this.sortColumn,
                order: this.sortDirection,
                ...this.filters
            });

            const response = await fetch(`${this.apiBase}/read.php?${params}`, {
                credentials: 'include'
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();

            if (result && Array.isArray(result.data)) {
                this.membersData = result.data;
                this.totalPages = result.pagination?.total_pages || 1;
                this.totalRecords = result.pagination?.total_records || result.data.length;

                // Build duplicate check sets
                this.existingPhones.clear();
                this.existingEmails.clear();
                this.membersData.forEach(m => {
                    if (m.phone) this.existingPhones.add(m.phone.trim());
                    if (m.email) this.existingEmails.add(m.email.trim().toLowerCase());
                });
            }

            this.renderMembersTable();
            this.updatePagination(this.totalRecords);
            this.updateStats();
        } catch (error) {
            console.error('Error loading members:', error);
            this.showNotification('Failed to load members', 'error');
            this.membersData = [];
            this.renderMembersTable();
        } finally {
            this.hideLoading();
        }
    }
    openMemberModal(memberId = null) {
        const modal = document.getElementById('member-modal');
        const form = document.getElementById('member-form');

        // Reset form and clear errors
        form.reset();
        this.clearAllErrors();

        // Reset photo preview
        document.getElementById('preview-img').src = '../assets/avatar-placeholder.png';

        let member = null;

        if (memberId) {
            // EDIT MODE
            member = this.membersData.find(m => m.id === memberId);
            if (member) {
                form.dataset.mode = 'edit';
                form.dataset.memberId = member.id;
                document.getElementById('modal-title').textContent = 'Edit Member';
                document.getElementById('submit-text').textContent = 'Update Member';

                // Populate form immediately - no setTimeout needed
                this.populateForm(member);
            }
        } else {
            // ADD MODE
            form.dataset.mode = 'add';
            delete form.dataset.memberId;
            document.getElementById('modal-title').textContent = 'Add New Member';
            document.getElementById('submit-text').textContent = 'Save Member';

            // Set default values
            document.getElementById('join-date').value = new Date().toISOString().split('T')[0];
            document.getElementById('member-status').value = 'active';
            document.getElementById('member-category').value = 'adult';
        }

        // Show modal
        modal.style.display = 'flex';
        document.body.classList.add('modal-open');

        // Focus first field
        setTimeout(() => document.getElementById('first-name')?.focus(), 100);
    }
    populateForm(member) {
        if (!member) return;

        // Basic info
        document.getElementById('first-name').value = member.first_name || '';
        document.getElementById('last-name').value = member.last_name || '';
        document.getElementById('phone').value = member.phone || '';
        document.getElementById('email').value = member.email || '';
        document.getElementById('address').value = member.address || '';
        document.getElementById('notes').value = member.notes || '';

        // Dates
        document.getElementById('join-date').value = member.join_date || '';
        document.getElementById('birth-date').value = member.birth_date || '';

        // STATUS - FIXED: Set value directly
        const statusSelect = document.getElementById('member-status');
        if (statusSelect) {
            statusSelect.value = member.status || 'active';
        }

        // CATEGORY - FIXED: Set value directly
        const categorySelect = document.getElementById('member-category');
        if (categorySelect) {
            categorySelect.value = member.category || 'adult';
        }

        // Fellowship
        if (member.fellowship_id) {
            const fellowship = this.fellowshipsData.find(f => f.id == member.fellowship_id);
            if (fellowship) {
                document.getElementById('fellowship-search').value = fellowship.name;
                document.getElementById('fellowship').value = fellowship.id;
            }
        }

        // New fields
        const deptSelect = document.getElementById('member-department');
        if (deptSelect) deptSelect.value = member.department_id || '';

        const cellSelect = document.getElementById('member-cell');
        if (cellSelect) cellSelect.value = member.cell_id || '';

        const roleInput = document.getElementById('role-in-church');
        if (roleInput) roleInput.value = member.role_in_church || '';

        // Photo
        if (member.photo_url || member.photo) {
            const photoUrl = member.photo_url || `/church/${member.photo}`;
            document.getElementById('preview-img').src = photoUrl;
        }
    }

    clearAllErrors() {
        document.querySelectorAll('.error-text').forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });
        document.querySelectorAll('.error').forEach(el => {
            el.classList.remove('error');
        });
    }

    async saveMember(e) {
        e.preventDefault();

        const form = e.target;
        const isEdit = form.dataset.mode === 'edit';
        const memberId = form.dataset.memberId;

        // Validate all required fields
        const validations = [
            this.validateRequired('first-name', 'First name is required'),
            this.validateRequired('last-name', 'Last name is required'),
            this.validatePhone(),
            this.validateRequired('join-date', 'Join date is required'),
            this.validateRequired('member-status', 'Status is required'),
            this.validateRequired('member-category', 'Category is required'),
            this.validateRequired('fellowship-search', 'Please select a fellowship')
        ];

        if (validations.includes(false)) {
            this.showNotification('Please fix the validation errors above', 'error');
            return;
        }

        // Get form data
        const formData = new FormData(form);

        // Add ID for edit mode
        if (isEdit && memberId) {
            formData.set('id', memberId);
        }

        // Show loading state
        const submitBtn = document.getElementById('submit-member-btn');
        const submitText = document.getElementById('submit-text');
        const submitSpinner = document.getElementById('submit-spinner');

        submitBtn.disabled = true;
        submitText.textContent = isEdit ? 'Updating...' : 'Saving...';
        submitSpinner.style.display = 'inline-block';

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            if (!csrfToken) {
                throw new Error('CSRF token not found. Please refresh the page.');
            }

            const endpoint = isEdit
                ? `${this.apiBase}/update.php`
                : `${this.apiBase}/create.php`;

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken
                },
                credentials: 'include',
                body: formData
            });

            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text.substring(0, 300));
                throw new Error('Server returned invalid response');
            }

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error || result.message || `HTTP ${response.status}: Operation failed`);
            }

            this.showNotification(
                `Member ${isEdit ? 'updated' : 'added'} successfully!`,
                'success'
            );

            this.closeModal();
            await this.loadMembers();

        } catch (error) {
            console.error('❌ Save error:', error);
            this.showNotification('Error: ' + error.message, 'error');
        } finally {
            // Reset button
            submitBtn.disabled = false;
            submitText.textContent = isEdit ? 'Update Member' : 'Save Member';
            submitSpinner.style.display = 'none';
        }
    }

    deleteMember(id) {
        const member = this.membersData.find(m => m.id === id);
        if (!member) {
            this.showNotification('Member not found', 'error');
            return;
        }

        const modal = document.getElementById('delete-modal');
        const memberName = `${member.first_name} ${member.last_name}`;

        document.getElementById('delete-member-name').textContent = memberName;
        modal.style.display = 'flex';

        // Set up confirm delete
        const confirmBtn = document.getElementById('confirm-delete-btn');

        // Remove existing event listeners by cloning
        confirmBtn.replaceWith(confirmBtn.cloneNode(true));
        const newConfirmBtn = document.getElementById('confirm-delete-btn');

        newConfirmBtn.onclick = async () => {
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch(`${this.apiBase}/delete.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ id: id }),
                    credentials: 'include'
                });

                const result = await response.json();

                if (response.ok) {
                    this.showNotification('Member deleted successfully', 'success');
                    modal.style.display = 'none';
                    await this.loadMembers();
                } else {
                    throw new Error(result.error || 'Delete failed');
                }
            } catch (error) {
                console.error('Delete error:', error);
                this.showNotification('Error: ' + error.message, 'error');
            }
        };

        // Close modal events
        modal.querySelectorAll('.close-modal').forEach(btn => {
            btn.onclick = () => modal.style.display = 'none';
        });
    }

    closeModal() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.style.display = 'none';
        });
        document.body.classList.remove('modal-open');
        this.clearAllErrors();
    }

    resetFilters() {
        document.getElementById('global-search').value = '';
        document.getElementById('member-fellowship').value = '';
        document.getElementById('member-status').value = '';
        document.getElementById('member-category-filter').value = '';
        if(document.getElementById('filter-member-department')) document.getElementById('filter-member-department').value = '';
        if(document.getElementById('filter-member-cell')) document.getElementById('filter-member-cell').value = '';

        this.filters = { search: '', fellowship: '', status: '', category: '', department: '', cell: '' };
        this.currentPage = 1;
        this.loadMembers();
    }

    // Utility methods
    isValidPhone(phone) {
        const cleaned = phone.replace(/\s/g, '');
        return /^(\+233|0)(20|24|26|27|50|54|55|56|57|59|23)\d{7}$/.test(cleaned);
    }

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    sanitizeInput(input) {
        if (!input) return '';
        const div = document.createElement('div');
        div.textContent = input;
        return div.innerHTML;
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
            position: fixed; top: 20px; right: 20px; padding: 16px 24px; border-radius: 12px; 
            color: white; z-index: 10000; transform: translateX(400px); transition: all 0.4s ease; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.2); font-weight: 600; display: flex; 
            align-items: center; gap: 10px; min-width: 300px;
        `;
        notification.style.background = type === 'success' ? '#06d6a0' : type === 'error' ? '#ef476f' : '#118ab2';

        const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle';
        notification.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;

        document.body.appendChild(notification);

        requestAnimationFrame(() => {
            notification.style.transform = 'translateX(0)';
        });

        setTimeout(() => {
            notification.style.transform = 'translateX(420px)';
            setTimeout(() => notification.remove(), 400);
        }, 4000);
    }

    showLoading() {
        const tbody = document.getElementById('members-table-body');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 2rem;">
                        <div class="spinner"></div>
                        <p>Loading members...</p>
                    </td>
                </tr>
            `;
        }
    }

    hideLoading() {
        // Loading state is handled by renderMembersTable
    }

    sortTable(column) {
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'asc';
        }
        this.updateSortIcons();
        this.renderMembersTable();
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

    getFilteredAndSortedData() {
        return this.membersData
            .filter(member => {
                const matchesSearch = !this.filters.search ||
                    `${member.first_name} ${member.last_name}`.toLowerCase().includes(this.filters.search) ||
                    (member.email || '').toLowerCase().includes(this.filters.search) ||
                    (member.phone || '').includes(this.filters.search);
                const matchesFellowship = !this.filters.fellowship ||
                    member.fellowship_id == this.filters.fellowship;
                const matchesStatus = !this.filters.status || member.status === this.filters.status;
                const matchesDepartment = !this.filters.department || member.department_id == this.filters.department;
                const matchesCell = !this.filters.cell || member.cell_id == this.filters.cell;
                return matchesSearch && matchesFellowship && matchesStatus && matchesDepartment && matchesCell;
            })
            .sort((a, b) => {
                let aVal = a[this.sortColumn];
                let bVal = b[this.sortColumn];

                if (this.sortColumn === 'name') {
                    aVal = `${a.first_name} ${a.last_name}`;
                    bVal = `${b.first_name} ${b.last_name}`;
                }
                if (this.sortColumn === 'fellowship') {
                    aVal = a.fellowship_name || '';
                    bVal = b.fellowship_name || '';
                }

                if (typeof aVal === 'string') aVal = aVal.toLowerCase();
                if (typeof bVal === 'string') bVal = bVal.toLowerCase();

                return (aVal < bVal ? -1 : 1) * (this.sortDirection === 'asc' ? 1 : -1);
            });
    }

    renderMembersTable() {
        const tbody = document.getElementById('members-table-body');
        if (!tbody) return;

        // Clear existing content
        tbody.innerHTML = '';

        if (!this.membersData || this.membersData.length === 0) {
            tbody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-users-slash"></i>
                    </div>
                    <h3>No members found</h3>
                    <p>Try adjusting your filters or add your first member to get started.</p>
                    <button class="btn-primary" id="create-from-empty">
                        <i class="fas fa-user-plus"></i> Add First Member
                    </button>
                </td>
            </tr>
        `;

            // Re-bind the create from empty button
            const createFromEmptyBtn = document.getElementById('create-from-empty');
            if (createFromEmptyBtn) {
                createFromEmptyBtn.onclick = () => this.openMemberModal();
            }

            this.updateTableInfo(0, 0, 0);
            return;
        }

        // Category Badge function
        const getCategoryBadge = (category) => {
            const badges = {
                adult: '<span class="badge badge-adult">Adult</span>',
                teen: '<span class="badge badge-teen">Teen</span>',
                children: '<span class="badge badge-child">Child</span>'
            };
            return badges[category] || '<span class="badge badge-unknown">—</span>';
        };

        // Get filtered and sorted data
        const filteredData = this.getFilteredAndSortedData();
        const startIndex = (this.currentPage - 1) * this.itemsPerPage;
        const endIndex = Math.min(startIndex + this.itemsPerPage, filteredData.length);
        const pageData = filteredData.slice(startIndex, endIndex);

        // Generate table rows
        const rows = pageData.map((member, index) => {
            const fullName = `${this.escapeHtml(member.first_name || '')} ${this.escapeHtml(member.last_name || '')}`.trim();
            const email = member.email ? this.escapeHtml(member.email) : '—';
            const phone = member.phone ? this.escapeHtml(this.formatPhoneNumber(member.phone)) : '—';
            const fellowship = this.escapeHtml(member.fellowship_name || 'No Fellowship');
            const joinDate = this.formatDate(member.join_date);

            // Status badge
            const statusClass = member.status === 'active' ? 'status-active' :
                member.status === 'inactive' ? 'status-inactive' : 'status-visitor';
            const statusText = this.formatStatus(member.status);

            // Category badge
            const categoryBadge = getCategoryBadge(member.category);

            return `
            <tr class="member-row" data-member-id="${member.id}">
                <!-- Name & Category Column -->
                <td class="member-info-cell">
                    <div class="member-name-cell">
                        <div class="avatar-placeholder">
                            ${member.first_name ? member.first_name.charAt(0).toUpperCase() : 'U'}
                            ${member.last_name ? member.last_name.charAt(0).toUpperCase() : ''}
                            
                        </div>
                        <div class="member-details">
                            <div class="member-name">${fullName}</div>
                            <div class="member-meta">
                                ${categoryBadge}
                                <span class="fellowship-name">• ${fellowship}</span>
                                ${member.department_name ? `<span class="member-meta-item">• Dept: ${this.escapeHtml(member.department_name)}</span>` : ''}
                                ${member.cell_name ? `<span class="member-meta-item">• Cell: ${this.escapeHtml(member.cell_name)}</span>` : ''}
                                ${member.role_in_church ? `<span class="member-meta-item">• Role: ${this.escapeHtml(member.role_in_church)}</span>` : ''}
                                ${email ? `<span class="member-email">• ${this.escapeHtml(email)}</span>` : ''}
                            </div>
                        </div>
                    </div>
                </td>

                <!-- Phone Column -->
                <td class="phone-cell">
                    <div class="cell-content">
                        ${phone}
                    </div>
                </td>

                <!-- Fellowship Column -->
                <td class="fellowship-cell">
                    <div class="cell-content">
                        ${fellowship}
                    </div>
                </td>

                <!-- Status Column -->
                <td class="status-cell">
                    <span class="status-badge ${statusClass}">
                        ${statusText}
                    </span>
                </td>

                <!-- Join Date Column -->
                <td class="date-cell">
                    <div class="cell-content">
                        ${joinDate}
                    </div>
                </td>

                <!-- Actions Column -->
                <td class="actions-cell">
                    <div class="action-buttons">
                        <button class="btn-icon" onclick="membersManager.openMemberModal(${member.id})" 
                                title="Edit Member" aria-label="Edit member">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon btn-danger" 
                                onclick="membersManager.deleteMember(${member.id})" 
                                title="Delete Member" aria-label="Delete member">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button class="btn-icon" 
                                onclick="membersManager.printMemberCard(${JSON.stringify(member).replace(/"/g, '&quot;')})" 
                                title="Print ID Card" aria-label="Print ID card">
                            <i class="fas fa-id-card"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        }).join('');

        tbody.innerHTML = rows;

        // Update table info and pagination
        this.updateTableInfo(filteredData.length, startIndex + 1, endIndex);
        this.updatePagination(filteredData.length);
    }

    // Add this helper method for phone number formatting
    formatPhoneNumber(phone) {
        if (!phone) return '';

        // Format Ghana phone numbers
        const cleaned = phone.replace(/\D/g, '');

        if (cleaned.startsWith('233') && cleaned.length === 12) {
            return `+${cleaned.substring(0, 3)} ${cleaned.substring(3, 6)} ${cleaned.substring(6, 9)} ${cleaned.substring(9)}`;
        } else if (cleaned.startsWith('0') && cleaned.length === 10) {
            return `+233 ${cleaned.substring(1, 4)} ${cleaned.substring(4, 7)} ${cleaned.substring(7)}`;
        }

        return phone;
    }

    updateTableInfo(total, start, end) {
        const info = document.getElementById('table-info');
        if (info) {
            if (total === 0) {
                info.textContent = 'Showing 0-0 of 0 entries';
            } else {
                info.textContent = `Showing ${start}-${end} of ${total} entries`;
            }
        }
    }

    updatePagination(total) {
        const pages = Math.ceil(total / this.itemsPerPage);
        this.totalPages = pages;
        const container = document.getElementById('page-numbers');

        if (!container) return;

        container.innerHTML = '';

        if (pages <= 5) {
            for (let i = 1; i <= pages; i++) {
                container.appendChild(this.createPageButton(i));
            }
        } else {
            container.appendChild(this.createPageButton(1));
            if (this.currentPage > 3) {
                const ellipsis = document.createElement('span');
                ellipsis.textContent = '...';
                ellipsis.className = 'page-ellipsis';
                container.appendChild(ellipsis);
            }
            const start = Math.max(2, this.currentPage - 1);
            const end = Math.min(pages - 1, this.currentPage + 1);
            for (let i = start; i <= end; i++) {
                container.appendChild(this.createPageButton(i));
            }
            if (this.currentPage < pages - 2) {
                const ellipsis = document.createElement('span');
                ellipsis.textContent = '...';
                ellipsis.className = 'page-ellipsis';
                container.appendChild(ellipsis);
            }
            container.appendChild(this.createPageButton(pages));
        }

        const prevBtn = document.getElementById('prev-page');
        const nextBtn = document.getElementById('next-page');
        if (prevBtn) prevBtn.disabled = this.currentPage === 1;
        if (nextBtn) nextBtn.disabled = this.currentPage === pages || pages === 0;
    }

    createPageButton(page) {
        const btn = document.createElement('button');
        btn.className = `page-number ${page === this.currentPage ? 'active' : ''}`;
        btn.textContent = page;
        btn.onclick = () => this.goToPage(page);
        return btn;
    }

    goToPage(page) {
        this.currentPage = page;
        this.renderMembersTable();
    }

    previousPage() {
        if (this.currentPage > 1) {
            this.currentPage--;
            this.renderMembersTable();
        }
    }

    nextPage() {
        if (this.currentPage < this.totalPages) {
            this.currentPage++;
            this.renderMembersTable();
        }
    }

    updateStats() {
        const total = this.membersData.length;
        const active = this.membersData.filter(m => m.status === 'active').length;
        const visitors = this.membersData.filter(m => m.status === 'visitor').length;

        const totalEl = document.getElementById('total-members-count');
        const activeEl = document.getElementById('active-members-count');
        const visitorEl = document.getElementById('visitor-count');

        if (totalEl) totalEl.textContent = total.toLocaleString();
        if (activeEl) activeEl.textContent = active.toLocaleString();
        if (visitorEl) visitorEl.textContent = visitors.toLocaleString();
    }

    async loadFellowshipsForDropdown() {
        try {
            const resp = await fetch(this.fellowshipApi);
            const data = await resp.json();
            this.fellowshipsData = data.fellowships || data || [];
            this.renderFellowshipDropdown('');
        } catch (e) {
            console.error('Error loading fellowships:', e);
        }
    }


    // Print Member Card
    printMemberCard(member) {
        document.getElementById('print-name').textContent = `${member.first_name} ${member.last_name}`;
        document.getElementById('print-fellowship').textContent = member.fellowship_name || 'No Fellowship';
        document.getElementById('print-phone').textContent = member.phone;
        document.getElementById('print-join').textContent = this.formatDate(member.join_date);
        document.getElementById('print-photo').src = member.photo || '../assets/avatar-placeholder.png';

        window.print();
    }
    formatDate(d) {
        if (!d) return 'N/A';
        return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    formatDateTime(d) {
        if (!d) return 'N/A';
        return new Date(d).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    formatStatus(s) {
        return { active: 'Active', inactive: 'Inactive', visitor: 'Visitor' }[s] || s;
    }

    exportToCSV() {
        const data = this.getFilteredAndSortedData();
        if (data.length === 0) {
            this.showNotification('No data to export', 'warning');
            return;
        }

        const headers = ['First Name', 'Last Name', 'Email', 'Phone', 'Fellowship', 'Status', 'Category', 'Join Date'];
        const csv = [
            headers.join(','),
            ...data.map(m => [
                `"${(m.first_name || '').replace(/"/g, '""')}"`,
                `"${(m.last_name || '').replace(/"/g, '""')}"`,
                `"${(m.email || '')}"`,
                `"${(m.phone || '')}"`,
                `"${(m.fellowship_name || '').replace(/"/g, '""')}"`,
                m.status,
                m.category,
                m.join_date
            ].join(','))
        ].join('\n');

        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `members_export_${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        URL.revokeObjectURL(url);

        this.showNotification('Members exported successfully', 'success');
    }

    downloadTemplate() {
        const headers = ['first_name', 'last_name', 'email', 'phone', 'fellowship_name', 'status', 'category', 'join_date', 'birth_date', 'address', 'notes'];
        const example = ['John', 'Doe', 'john@example.com', '0241234567', 'Main Fellowship', 'active', 'adult', '2023-01-01', '1990-01-01', '123 Street Ave', 'New member'];

        const csv = [headers.join(','), example.join(',')].join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'member_import_template.csv';
        a.click();
        URL.revokeObjectURL(url);
    }

    printMembers() {
        window.print();
    }

    openImportModal(type) {
        let modalId = '';
        if (type === 'gsheet') modalId = 'bulk-import-modal';
        else if (type === 'schedule') modalId = 'schedule-modal';
        else if (type === 'history') modalId = 'import-history-modal';

        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.classList.add('modal-open');
        }
    }
}


// Debounce utility

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Initialize
let membersManager;
document.addEventListener('DOMContentLoaded', () => {
    membersManager = new MembersManager();
});