// Use global API_BASE defined in config.js

// Wait for DOM to be fully loaded before attaching event listeners
document.addEventListener('DOMContentLoaded', function() {
    console.log('AUTH: DOMContentLoaded fired');
    const inlineError = document.getElementById('login-inline-error');
    
    // Only attach login handler if the login form exists on the current page
    const loginForm = document.getElementById('login-form');
    console.log('AUTH: Login form found?', !!loginForm);
    console.log('AUTH: API_BASE is:', window.API_BASE);

    if (loginForm) {
        console.log('AUTH: Attaching submit handler to login form');
        
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log('AUTH: Form submitted, preventing default');
            if (inlineError) {
                inlineError.style.display = 'none';
                inlineError.textContent = '';
            }

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            console.log('AUTH: Username:', username, 'Password length:', password.length);

            try {
                const loginUrl = `${window.API_BASE}/auth/login.php`;
                console.log('AUTH: Calling login endpoint:', loginUrl);
                
                const response = await fetch(loginUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',  // Include cookies in cross-origin requests
                    body: JSON.stringify({ username, password })
                });

                console.log('AUTH: Response status:', response.status, 'ok:', response.ok);
                console.log('AUTH: Response headers:', response.headers);

                const data = await response.json();
                console.log('AUTH: Response data:', data);

                if (response.ok) {
                    console.log('AUTH: Login successful, setting localStorage');
                    localStorage.setItem('user_role', data.role);
                    localStorage.setItem('fellowship_id', data.fellowship_id);
                    if (data.username) {
                        localStorage.setItem('username', data.username);
                    }
                    console.log('AUTH: localStorage set, waiting before redirect');
                    
                    // Wait a moment to ensure session is set on server
                    setTimeout(() => {
                        let redirectUrl = '';
                        
                        // Try to use window.BASE_URL first (most reliable, set by config.php)
                        if (window.BASE_URL) {
                            // Remove trailing slash to avoid double slashes
                            const baseUrl = window.BASE_URL.replace(/\/$/, '');
                            if (data.role === 'member') {
                                redirectUrl = baseUrl + '/frontend/pages/profile.php';
                            } else {
                                redirectUrl = baseUrl + '/index.php';
                            }
                        } else {
                            // Fallback: calculate base path from current location
                            const pathParts = window.location.pathname.split('/').filter(Boolean);
                            const frontendIndex = pathParts.indexOf('frontend');
                            let basePath = '/';
                            if (frontendIndex > 0) {
                                basePath = '/' + pathParts.slice(0, frontendIndex).join('/');
                            }
                            
                            if (data.role === 'member') {
                                redirectUrl = window.location.origin + basePath + '/frontend/pages/profile.php';
                            } else {
                                redirectUrl = window.location.origin + basePath + '/index.php';
                            }
                        }
                        
                        console.log('AUTH: Calculated redirect URL:', redirectUrl);
                        console.log('AUTH: Role:', data.role);
                        window.location.href = redirectUrl;
                    }, 500);
                } else {
                    console.log('AUTH: Login failed with message:', data.message);
                    if (inlineError) {
                        inlineError.textContent = data.message || 'Login failed.';
                        inlineError.style.display = 'flex';
                    } else {
                        alert(data.message || 'Login failed.');
                    }
                }
            } catch (error) {
                console.error('AUTH: Login error:', error);
                console.error('AUTH: Error stack:', error.stack);
                if (inlineError) {
                    inlineError.textContent = 'Login failed. Please try again.';
                    inlineError.style.display = 'flex';
                } else {
                    alert('Login failed: ' + error.message);
                }
            }
        });
    } else {
        console.log('AUTH: Login form NOT found, login handler not attached');
    }
});

function showDashboard() {
    document.getElementById('login-page').classList.remove('active');
    document.getElementById('dashboard-page').classList.add('active');
    
    // Hide fellowships nav for fellowship leaders
    if(localStorage.getItem('user_role') === 'fellowship_leader') {
        document.getElementById('fellowships-nav').style.display = 'none';
    }
    
    loadDashboardStats();
}

function logout() {
    // Call server to destroy session (include CSRF token)
    fetch(`${window.API_BASE}/auth/logout.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.getCookie ? window.getCookie('csrf_token') : ''
        },
        body: JSON.stringify({})
    }).finally(() => {
        localStorage.clear();
        document.getElementById('dashboard-page').classList.remove('active');
        document.getElementById('login-page').classList.add('active');
    });
}
// Delegate showing dashboard to central router if available
function showDashboard() {
    if (typeof window.showPage === 'function') {
        window.showPage('dashboard');
        // ensure role-specific UI adjustments
        if(localStorage.getItem('user_role') === 'fellowship_leader') {
            document.getElementById('fellowships-nav').style.display = 'none';
        }
    } else {
        // Fallback behavior
        document.getElementById('login-page')?.classList.remove('active');
        document.getElementById('dashboard-page')?.classList.add('active');
        if(localStorage.getItem('user_role') === 'fellowship_leader') {
            document.getElementById('fellowships-nav').style.display = 'none';
        }
        if (typeof loadDashboardStats === 'function') loadDashboardStats();
    }
}



class AttendanceManager {
    constructor() {
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.totalRecords = 0;
        this.attendanceData = [];
        this.filteredData = [];
        this.fellowships = [];
        this.members = [];
        this.services = [];
        this.sortColumn = 'date';
        this.sortDirection = 'desc';
        
        this.init();
    }

    init() {
        // Only initialize if this is an attendance management page
        if (!document.getElementById('record-attendance-btn')) {
            console.log('AttendanceManager: Not on attendance management page, skipping init');
            return;
        }
        
        this.setupEventListeners();
        this.loadFellowships();
        this.loadServices();
        this.loadAttendanceData();
        this.setDefaultDate();
    }

    setupEventListeners() {
        // Helper function to safely add event listener
        const addListener = (id, event, handler) => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener(event, handler);
            } else {
                console.warn(`Element not found: ${id}`);
            }
        };

        // Modal controls
        addListener('record-attendance-btn', 'click', () => this.openModal());
        addListener('close-modal', 'click', () => this.closeModal('attendance-modal'));
        addListener('cancel-modal', 'click', () => this.closeModal('attendance-modal'));
        
        // Form submission
        addListener('attendance-form', 'submit', (e) => this.saveAttendance(e));

        // Fellowship, service and date change -> reload members and check existing session
        addListener('attendance-fellowship', 'change', () => { this.loadMembers(); this.checkExistingAttendance(); });
        addListener('attendance-service', 'change', () => { this.loadMembers(); this.checkExistingAttendance(); });
        addListener('attendance-date', 'change', () => { this.checkExistingAttendance(); });

        // Bulk actions
        addListener('mark-all-present', 'click', () => this.markAll('present'));
        addListener('mark-all-absent', 'click', () => this.markAll('absent'));

        // Filters
        addListener('apply-filters', 'click', () => this.applyFilters());
        addListener('reset-filters', 'click', () => this.resetFilters());
        addListener('export-csv', 'click', () => this.exportCSV());

        // Search
        addListener('table-search', 'input', (e) => this.searchTable(e.target.value));

        // Pagination
        addListener('first-page', 'click', () => this.goToPage(1));
        addListener('prev-page', 'click', () => this.goToPage(this.currentPage - 1));
        addListener('next-page', 'click', () => this.goToPage(this.currentPage + 1));
        addListener('last-page', 'click', () => this.goToPage(Math.ceil(this.totalRecords / this.itemsPerPage)));

        // Close modal on outside click
        const modal = document.getElementById('attendance-modal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target.id === 'attendance-modal') this.closeModal('attendance-modal');
            });
        }
    }

    async loadFellowships() {
        try {
            const response = await fetch('../../backend/attendance/read.php?action=fellowships');
            const data = await response.json();
            
            if (data.success) {
                this.fellowships = data.data;
                this.populateFellowshipDropdowns();
            } else {
                console.error('Failed to load fellowships:', data.message);
                this.showNotification('Failed to load fellowships', 'error');
            }
        } catch (error) {
            console.error('Error loading fellowships:', error);
            this.showNotification('Error loading fellowships', 'error');
        }
    }

    async loadServices() {
        try {
            const response = await fetch('../../backend/ajax/services_crud.php');
            const data = await response.json();
            
            if (data.success) {
                this.services = data.data;
                this.populateServiceDropdowns();
            } else {
                console.error('Failed to load services:', data.message);
            }
        } catch (error) {
            console.error('Error loading services:', error);
        }
    }

    async loadAttendanceData() {
        try {
            const response = await fetch('../../backend/attendance/read.php');
            const data = await response.json();
            
            if (data.records) {
                this.attendanceData = data.records;
                this.filteredData = [...this.attendanceData];
                this.totalRecords = this.filteredData.length;
                this.renderTable();
                this.updateStats();
            }
        } catch (error) {
            console.error('Error loading attendance data:', error);
        }
    }

    populateFellowshipDropdowns() {
        const dropdowns = ['fellowship-filter', 'attendance-fellowship'];
        
        dropdowns.forEach(id => {
            const select = document.getElementById(id);
            if (!select) return;
            
            select.innerHTML = id === 'fellowship-filter' 
                ? '<option value="">All Fellowships</option>' 
                : '<option value="">Select Fellowship</option>';
            
            this.fellowships.forEach(fellowship => {
                const option = document.createElement('option');
                option.value = fellowship.id;
                option.textContent = fellowship.name;
                select.appendChild(option);
            });
        });
    }

    populateServiceDropdowns() {
        const selects = ['service-filter', 'attendance-service'];
        
        selects.forEach(id => {
            const select = document.getElementById(id);
            if (!select) return;
            
            select.innerHTML = id === 'service-filter' 
                ? '<option value="">All Services</option>' 
                : '<option value="">Select Service Type</option>';
            
            this.services.forEach(service => {
                const option = document.createElement('option');
                option.value = service.id;
                option.textContent = service.name;
                select.appendChild(option);
            });
        });
    }

    setDefaultDate() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('attendance-date').value = today;
        
        // Set default filter dates to current month
        const firstDay = new Date();
        firstDay.setDate(1);
        document.getElementById('start-date').value = firstDay.toISOString().split('T')[0];
        document.getElementById('end-date').value = today;
    }

    openModal(recordId = null) {
        const modal = document.getElementById('attendance-modal');
        const title = document.getElementById('modal-title');
        const dateInput = document.getElementById('attendance-date');
        
        // Always set date to today when opening modal
        const today = new Date().toISOString().split('T')[0];
        if (dateInput) dateInput.value = today;
        
        if (recordId) {
            title.textContent = 'Edit Attendance';
            this.loadRecord(recordId);
        } else {
            title.textContent = 'Record Attendance';
            document.getElementById('attendance-form').reset();
            // Re-set date after reset
            if (dateInput) dateInput.value = today;
            this.loadMembers(); // Load default members
        }
        
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        // Ensure date is populated when modal opens
        if (dateInput) dateInput.value = dateInput.value || today;
        // Check if attendance already exists for default selection
        this.checkExistingAttendance();
    }

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }

    async loadMembers() {
        const fellowshipId = document.getElementById('attendance-fellowship').value;
        
        if (!fellowshipId) {
            document.getElementById('members-list').innerHTML = 
                '<p style="text-align:center; padding: 2rem; color: var(--text-muted);">Please select a fellowship first</p>';
            return;
        }

        try {
            const response = await fetch(`../../backend/attendance/read.php?action=members&fellowship_id=${fellowshipId}`);
            const data = await response.json();
            
            if (data.success) {
                // Default to present, but if there is an existing session we'll overwrite statuses later
                this.members = data.data.map(member => ({
                    id: member.id,
                    name: member.name,
                    category: member.category || 'member',
                    status: 'present'
                }));
                this.renderMembersList();
                // After rendering members, check if there's an existing session to populate statuses
                this.checkExistingAttendance();
            } else {
                // Fallback to sample data
                this.loadSampleMembers();
            }
        } catch (error) {
            console.error('Error loading members:', error);
            this.loadSampleMembers();
        }
    }

    loadSampleMembers() {
        // Sample members data as fallback
        this.members = [
            { id: 1, name: "John Adebayo", category: "adult", status: "present" },
            { id: 2, name: "Sarah Okafor", category: "adult", status: "present" },
            { id: 3, name: "David Chukwu", category: "teen", status: "present" },
            { id: 4, name: "Grace Emmanuel", category: "teen", status: "present" }
        ];
        this.renderMembersList();
    }

    renderMembersList() {
        const container = document.getElementById('members-list');
        if (!container) return;

        if (this.members.length === 0) {
            container.innerHTML = '<p style="text-align:center; padding: 2rem; color: var(--text-muted);">No members found</p>';
            return;
        }

        container.innerHTML = this.members.map(member => `
            <div class="member-item" data-member-id="${member.id}">
                <div class="member-info">
                    <div class="member-avatar">${this.getInitials(member.name)}</div>
                    <div class="member-details">
                        <h5>${member.name}</h5>
                        <p>${member.category || 'Member'}</p>
                    </div>
                </div>
                <div class="attendance-toggle">
                    <button type="button" class="toggle-option ${member.status === 'present' ? 'active' : ''}" 
                            data-status="present" data-member-id="${member.id}">
                        Present
                    </button>
                    <button type="button" class="toggle-option absent ${member.status === 'absent' ? 'active' : ''}" 
                            data-status="absent" data-member-id="${member.id}">
                        Absent
                    </button>
                </div>
            </div>
        `).join('');

        // Add event listeners
        container.querySelectorAll('.toggle-option').forEach(btn => {
            btn.addEventListener('click', (e) => this.toggleAttendance(e.target));
        });

        this.updateSelectedCount();
    }

    toggleAttendance(button) {
        const memberId = button.dataset.memberId;
        const status = button.dataset.status;
        const memberItem = button.closest('.member-item');
        const buttons = memberItem.querySelectorAll('.toggle-option');
        
        buttons.forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');

        const member = this.members.find(m => m.id == memberId);
        if (member) {
            member.status = status;
        }

        this.updateSelectedCount();
    }

    markAll(status) {
        this.members.forEach(member => {
            member.status = status;
        });

        document.querySelectorAll('.toggle-option').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.status === status) {
                btn.classList.add('active');
            }
        });

        this.updateSelectedCount();
    }

    updateSelectedCount() {
        const presentCount = this.members.filter(m => m.status === 'present').length;
        const countElement = document.getElementById('selected-count');
        if (countElement) {
            countElement.textContent = presentCount;
        }
    }

    async checkExistingAttendance() {
        const date = document.getElementById('attendance-date')?.value;
        const fellowshipId = document.getElementById('attendance-fellowship')?.value;
        const serviceId = document.getElementById('attendance-service')?.value;
        const indicator = document.getElementById('attendance-taken-indicator');

        // Hide indicator by default
        if (indicator) indicator.style.display = 'none';
        document.getElementById('attendance-id').value = '';

        if (!date || !fellowshipId || !serviceId) return;

        // Look for an aggregated attendance session matching date/fellowship/service
        const found = (this.attendanceData || []).find(r => r.date === date && String(r.fellowship_id) === String(fellowshipId) && String(r.service_id) === String(serviceId));

        if (!found) {
            // reset modal title when session not found
            const titleEl = document.getElementById('modal-title');
            if (titleEl) titleEl.textContent = 'Record Attendance';
            return;
        }

        // Show indicator
        if (indicator) indicator.style.display = 'inline-flex';

        // Use the sample_id to load members statuses for this session
        const recordId = found.sample_id || found.id || null;
        if (!recordId) return;

        // Set attendance-id so save can update instead of create
        document.getElementById('attendance-id').value = recordId;

        try {
            const res = await fetch(`../../backend/attendance/read.php?action=record_members&record_id=${recordId}`);
            const data = await res.json();
            if (data.success) {
                // Map statuses to members list
                const membersData = data.data || [];
                // If members are already loaded, map statuses; otherwise set members based on response
                if (this.members && this.members.length > 0) {
                    this.members = this.members.map(m => {
                        const match = membersData.find(md => String(md.member_id) === String(m.id));
                        return match ? { ...m, status: match.status } : m;
                    });
                } else {
                    this.members = membersData.map(md => ({ id: md.member_id, name: md.member_name, category: md.category, status: md.status }));
                }

                this.renderMembersList();
                // Update modal title to show existing session
                const title = document.getElementById('modal-title');
                if (title) title.textContent = 'Attendance (Taken)';
            }
        } catch (err) {
            console.error('Failed to load record members:', err);
        }
    }

    getInitials(name) {
        return name.split(' ').map(n => n[0]).join('').toUpperCase();
    }

    async saveAttendance(e) {
        e.preventDefault();

        const formData = {
            date: document.getElementById('attendance-date').value,
            fellowship_id: document.getElementById('attendance-fellowship').value,
            service_id: document.getElementById('attendance-service').value,
            notes: document.getElementById('attendance-notes').value,
            records: this.members.map(member => ({
                member_id: member.id,
                status: member.status
            }))
        };

        // Validate required fields
        if (!formData.date || !formData.fellowship_id || !formData.service_type) {
            this.showNotification('Please fill all required fields', 'error');
            return;
        }

        const existingRecordId = document.getElementById('attendance-id').value;

        try {
            if (existingRecordId) {
                // Update existing session (PUT)
                const res = await fetch(`../../backend/attendance/update.php`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                const result = await res.json();
                if (result.success) {
                    this.showNotification('Attendance updated successfully!', 'success');
                    this.closeModal('attendance-modal');
                    await this.loadAttendanceData();
                } else {
                    this.showNotification(result.message || 'Failed to update attendance', 'error');
                }
            } else {
                // Create new session
                const response = await fetch('../../backend/attendance/create.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    this.showNotification('Attendance recorded successfully!', 'success');
                    this.closeModal('attendance-modal');
                    await this.loadAttendanceData();
                } else {
                    this.showNotification(result.message || 'Failed to save attendance', 'error');
                }
            }
        } catch (error) {
            console.error('Error saving attendance:', error);
            this.showNotification('Error saving attendance', 'error');
        }
    }

          renderTable() {
                const tbody = document.getElementById('attendance-tbody');
                const start = (this.currentPage - 1) * this.itemsPerPage;
                const end = start + this.itemsPerPage;
                const pageData = this.filteredData.slice(start, end);

                if (pageData.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M9 11H3v2h6m11-2h-6v2h6m-5 8V3H9v18"/>
                                    </svg>
                                    <h3>No records found</h3>
                                    <p>No attendance records match your search criteria</p>
                                </div>
                            </td>
                        </tr>
                    `;
                    this.updatePaginationInfo(0, 0);
                    return;
                }

                tbody.innerHTML = pageData.map(record => `
                    <tr>
                        <td>${this.formatDate(record.date)}</td>
                        <td><span class="badge badge-info">${this.formatServiceType(record.service_type)}</span></td>
                        <td>${record.fellowship}</td>
                        <td><strong style="color: var(--success)">${record.present}</strong></td>
                        <td><strong style="color: var(--danger)">${record.absent}</strong></td>
                        <td>
                            ${this.renderProgressRing(record.percentage)}
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon view" onclick="attendanceManager.viewRecord(${record.id})" title="View Details">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                                <button class="btn-icon edit" onclick="attendanceManager.openModal(${record.id})" title="Edit">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <button class="btn-icon delete" onclick="attendanceManager.deleteRecord(${record.id})" title="Delete">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 6h18m-2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');

                this.updatePaginationInfo(start + 1, Math.min(end, this.totalRecords));
                this.renderPageNumbers();
            }

            renderProgressRing(percentage) {
                const radius = 20;
                const circumference = 2 * Math.PI * radius;
                const offset = circumference - (percentage / 100) * circumference;
                const color = percentage >= 80 ? 'var(--success)' : percentage >= 60 ? 'var(--warning)' : 'var(--danger)';

                return `
                    <div class="progress-ring">
                        <div class="progress-ring-circle">
                            <svg width="48" height="48">
                                <circle class="progress-bg" cx="24" cy="24" r="${radius}" />
                                <circle 
                                    cx="24" 
                                    cy="24" 
                                    r="${radius}"
                                    stroke="${color}"
                                    stroke-dasharray="${circumference}"
                                    stroke-dashoffset="${offset}"
                                />
                            </svg>
                            <div class="progress-ring-text">${percentage}%</div>
                        </div>
                    </div>
                `;
            }

            formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }

            formatServiceType(type) {
                const types = {
                    'sunday_service': 'Sunday Service',
                    'bible_study': 'Bible Study',
                    'prayer_meeting': 'Prayer Meeting',
                    'youth_fellowship': 'Youth Fellowship',
                    'other': 'Other'
                };
                return types[type] || type;
            }

            updatePaginationInfo(start, end) {
                document.getElementById('showing-start').textContent = start;
                document.getElementById('showing-end').textContent = end;
                document.getElementById('total-records').textContent = this.totalRecords;

                // Update button states
                document.getElementById('first-page').disabled = this.currentPage === 1;
                document.getElementById('prev-page').disabled = this.currentPage === 1;
                document.getElementById('next-page').disabled = this.currentPage >= Math.ceil(this.totalRecords / this.itemsPerPage);
                document.getElementById('last-page').disabled = this.currentPage >= Math.ceil(this.totalRecords / this.itemsPerPage);
            }

            renderPageNumbers() {
                const totalPages = Math.ceil(this.totalRecords / this.itemsPerPage);
                const container = document.getElementById('page-numbers');
                const maxButtons = 5;
                
                let startPage = Math.max(1, this.currentPage - Math.floor(maxButtons / 2));
                let endPage = Math.min(totalPages, startPage + maxButtons - 1);
                
                if (endPage - startPage < maxButtons - 1) {
                    startPage = Math.max(1, endPage - maxButtons + 1);
                }

                let html = '';
                for (let i = startPage; i <= endPage; i++) {
                    html += `
                        <button class="page-number ${i === this.currentPage ? 'active' : ''}" 
                                onclick="attendanceManager.goToPage(${i})">
                            ${i}
                        </button>
                    `;
                }

                container.innerHTML = html;
            }

            goToPage(page) {
                const totalPages = Math.ceil(this.totalRecords / this.itemsPerPage);
                if (page < 1 || page > totalPages) return;
                
                this.currentPage = page;
                this.renderTable();
            }

            sortTable(thElement) {
                const column = thElement.textContent.trim().toLowerCase().replace(' ', '_');
                const columnMap = {
                    'date': 'date',
                    'service_type': 'service_type',
                    'fellowship': 'fellowship',
                    'present': 'present',
                    'absent': 'absent',
                    'attendance_rate': 'percentage'
                };

                const sortKey = columnMap[column];
                if (!sortKey) return;

                // Toggle sort direction
                if (this.sortColumn === sortKey) {
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortColumn = sortKey;
                    this.sortDirection = 'asc';
                }

                // Update UI
                document.querySelectorAll('.sortable').forEach(th => {
                    th.classList.remove('sort-asc', 'sort-desc');
                });
                thElement.classList.add(`sort-${this.sortDirection}`);

                // Sort data
                this.filteredData.sort((a, b) => {
                    let aVal = a[sortKey];
                    let bVal = b[sortKey];

                    if (typeof aVal === 'string') {
                        aVal = aVal.toLowerCase();
                        bVal = bVal.toLowerCase();
                    }

                    if (this.sortDirection === 'asc') {
                        return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
                    } else {
                        return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
                    }
                });

                this.renderTable();
            }

            searchTable(query) {
                query = query.toLowerCase().trim();
                
                if (!query) {
                    this.filteredData = [...this.attendanceData];
                } else {
                    this.filteredData = this.attendanceData.filter(record => {
                        return (
                            record.date.includes(query) ||
                            record.fellowship.toLowerCase().includes(query) ||
                            this.formatServiceType(record.service_type).toLowerCase().includes(query) ||
                            record.present.toString().includes(query) ||
                            record.absent.toString().includes(query)
                        );
                    });
                }

                this.totalRecords = this.filteredData.length;
                this.currentPage = 1;
                this.renderTable();
            }

            applyFilters() {
                const startDate = document.getElementById('start-date').value;
                const endDate = document.getElementById('end-date').value;
                const fellowship = document.getElementById('fellowship-filter').value;
                const serviceType = document.getElementById('service-filter').value;

                this.filteredData = this.attendanceData.filter(record => {
                    let matches = true;

                    if (startDate && record.date < startDate) matches = false;
                    if (endDate && record.date > endDate) matches = false;
                    if (fellowship && record.fellowship_id != fellowship) matches = false;
                    if (serviceType && record.service_type !== serviceType) matches = false;

                    return matches;
                });

                this.totalRecords = this.filteredData.length;
                this.currentPage = 1;
                this.renderTable();
                this.showNotification('Filters applied successfully', 'success');
            }

            resetFilters() {
                document.getElementById('start-date').value = '';
                document.getElementById('end-date').value = '';
                document.getElementById('fellowship-filter').value = '';
                document.getElementById('service-filter').value = '';
                document.getElementById('table-search').value = '';

                this.filteredData = [...this.attendanceData];
                this.totalRecords = this.filteredData.length;
                this.currentPage = 1;
                this.renderTable();
                this.showNotification('Filters reset', 'info');
            }

            updateStats() {
                const today = new Date().toISOString().split('T')[0];
                const todayRecords = this.attendanceData.filter(r => r.date === today);
                
                const totalPresent = todayRecords.reduce((sum, r) => sum + r.present, 0);
                const totalAbsent = todayRecords.reduce((sum, r) => sum + r.absent, 0);
                const totalMembers = totalPresent + totalAbsent;
                const attendanceRate = totalMembers > 0 ? Math.round((totalPresent / totalMembers) * 100) : 0;

                document.getElementById('total-present').textContent = totalPresent;
                document.getElementById('total-absent').textContent = totalAbsent;
                document.getElementById('total-members').textContent = totalMembers;
                document.getElementById('attendance-rate').textContent = attendanceRate + '%';
            }

            viewRecord(recordId) {
                const record = this.attendanceData.find(r => r.id === recordId);
                if (!record) return;

                const modal = document.getElementById('view-modal');
                const body = document.getElementById('view-modal-body');

                body.innerHTML = `
                    <div style="display: grid; gap: 1.5rem;">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Date</label>
                                <div class="form-control" style="background: var(--bg-secondary);">
                                    ${this.formatDate(record.date)}
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Service Type</label>
                                <div class="form-control" style="background: var(--bg-secondary);">
                                    ${this.formatServiceType(record.service_type)}
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Fellowship</label>
                                <div class="form-control" style="background: var(--bg-secondary);">
                                    ${record.fellowship}
                                </div>
                            </div>
                        </div>

                        <div class="stats-overview" style="margin: 0;">
                            <div class="stat-card" style="margin: 0;">
                                <div class="stat-header">
                                    <div class="stat-icon present">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 6L9 17l-5-5"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="stat-content">
                                    <h3>Present</h3>
                                    <p class="stat-value">${record.present}</p>
                                </div>
                            </div>

                            <div class="stat-card" style="margin: 0;">
                                <div class="stat-header">
                                    <div class="stat-icon absent">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M18 6L6 18M6 6l12 12"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="stat-content">
                                    <h3>Absent</h3>
                                    <p class="stat-value">${record.absent}</p>
                                </div>
                            </div>

                            <div class="stat-card" style="margin: 0;">
                                <div class="stat-header">
                                    <div class="stat-icon percentage">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <path d="M12 6v6l4 2"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="stat-content">
                                    <h3>Attendance Rate</h3>
                                    <p class="stat-value">${record.percentage}%</p>
                                </div>
                            </div>
                        </div>

                        ${record.notes ? `
                            <div class="form-group">
                                <label>Notes</label>
                                <div class="form-control" style="background: var(--bg-secondary); min-height: 80px;">
                                    ${record.notes}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                `;

                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }

            async deleteRecord(recordId) {
                if (!confirm('Are you sure you want to delete this attendance record? This action cannot be undone.')) {
                    return;
                }

                // Simulate API call
                await new Promise(resolve => setTimeout(resolve, 300));

                this.attendanceData = this.attendanceData.filter(r => r.id !== recordId);
                this.filteredData = this.filteredData.filter(r => r.id !== recordId);
                this.totalRecords = this.filteredData.length;

                // Adjust current page if needed
                const totalPages = Math.ceil(this.totalRecords / this.itemsPerPage);
                if (this.currentPage > totalPages) {
                    this.currentPage = Math.max(1, totalPages);
                }

                this.renderTable();
                this.updateStats();
                this.showNotification('Attendance record deleted successfully', 'success');
            }

            loadRecord(recordId) {
                const record = this.attendanceData.find(r => r.id === recordId);
                if (!record) return;

                document.getElementById('attendance-id').value = record.id;
                document.getElementById('attendance-date').value = record.date;
                document.getElementById('attendance-fellowship').value = record.fellowship_id;
                document.getElementById('attendance-service').value = record.service_type;
                document.getElementById('attendance-notes').value = record.notes || '';

                this.loadMembers();
            }

            exportCSV() {
                const headers = ['Date', 'Service Type', 'Fellowship', 'Present', 'Absent', 'Percentage', 'Notes'];
                const rows = this.filteredData.map(record => [
                    record.date,
                    this.formatServiceType(record.service_type),
                    record.fellowship,
                    record.present,
                    record.absent,
                    record.percentage + '%',
                    record.notes || ''
                ]);

                let csv = headers.join(',') + '\n';
                rows.forEach(row => {
                    csv += row.map(cell => `"${cell}"`).join(',') + '\n';
                });

                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `attendance_records_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);

                this.showNotification('CSV exported successfully', 'success');
            }

    
    showNotification(message, type = 'info') {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 16px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            transition: all 0.3s ease;
            ${type === 'success' ? 'background: var(--success);' : ''}
            ${type === 'error' ? 'background: var(--danger);' : ''}
            ${type === 'warning' ? 'background: var(--warning);' : ''}
            ${type === 'info' ? 'background: var(--info);' : ''}
        `;
        
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

// Initialize only if attendance elements exist on the page
let attendanceManager;
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize AttendanceManager if we're on a page with attendance features
    if (document.getElementById('record-attendance-btn')) {
        console.log('AUTH: Initializing AttendanceManager (attendance page detected)');
        attendanceManager = new AttendanceManager();
    } else {
        console.log('AUTH: Skipping AttendanceManager (not on attendance page)');
    }
});
