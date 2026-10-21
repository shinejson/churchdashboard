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
    
    // Add these properties
    this.chart = null;
    this.pieChart = null;
    this.trendChart = null;
    this.demoChart = null;
    
    this.init();
    
    // Setup tabs after init
    setTimeout(() => {
        this.setupTabNavigation();
        
        // Preload report data
        this.preloadReportData().then(() => {
            this.populateReportFilters();
        });
    }, 500);
}

// Add this method
async preloadReportData() {
    try {
        // Load both fellowships and services in parallel
        await Promise.all([
            this.loadFellowships(),
            this.loadServices()
        ]);
    } catch (error) {
        console.error('Error preloading data:', error);
    }
}

    init() {
        this.setupEventListeners();
        this.loadFellowships();
        this.loadServices();
        this.loadAttendanceData();
        this.setDefaultDate();
        this.populateReportFilters();
    

    }

    setupEventListeners() {
        // Modal controls with null checks
        const manageServicesBtn = document.getElementById('manage-services-btn');
        const recordBtn = document.getElementById('record-attendance-btn');
        const closeModal = document.getElementById('close-modal');
        const cancelModal = document.getElementById('cancel-modal');
        const attendanceForm = document.getElementById('attendance-form');
         const refreshServicesBtn = document.getElementById('refresh-services-modal');
    
        if (refreshServicesBtn) {
                refreshServicesBtn.addEventListener('click', () => this.refreshServices());
            }

              // Manage Services button
    
    if (manageServicesBtn) {
        this.loadServices();
        manageServicesBtn.addEventListener('click', () => this.openServiceModal());
    }
         const addServiceQuickBtn = document.getElementById('add-service-quick-modal');
        const serviceModal = document.getElementById('service-modal');
        const closeServiceModal = document.getElementById('close-service-modal');
        const cancelService = document.getElementById('cancel-service');
        const serviceForm = document.getElementById('service-form');

           if (addServiceQuickBtn) {
        addServiceQuickBtn.addEventListener('click', () => this.openServiceModal());
        }
        if (closeServiceModal) {
            closeServiceModal.addEventListener('click', () => this.closeModal('service-modal'));
        }
        if (cancelService) {
            cancelService.addEventListener('click', () => this.closeModal('service-modal'));
        }
        if (serviceForm) {
            serviceForm.addEventListener('submit', (e) => this.saveService(e));
        }


    if (recordBtn) {
        recordBtn.addEventListener('click', () => this.openModal(false)); // No lock – creating new
    }
        if (closeModal) closeModal.addEventListener('click', () => this.closeModal('attendance-modal'));
        if (cancelModal) cancelModal.addEventListener('click', () => this.closeModal('attendance-modal'));
        if (attendanceForm) attendanceForm.addEventListener('submit', (e) => this.saveAttendance(e));

        // Fellowship change
        const fellowshipSelect = document.getElementById('attendance-fellowship');
        if (fellowshipSelect) fellowshipSelect.addEventListener('change', () => this.loadMembers());

        // Bulk attendance actions
        const markAllPresent = document.getElementById('mark-all-present');
        const markAllAbsent = document.getElementById('mark-all-absent');
        if (markAllPresent) markAllPresent.addEventListener('click', () => this.markAll('present'));
        if (markAllAbsent) markAllAbsent.addEventListener('click', () => this.markAll('absent'));

        // Filter actions
        const applyFilters = document.getElementById('apply-filters');
        const resetFilters = document.getElementById('reset-filters');
        const exportCSV = document.getElementById('export-csv');
        const tableSearch = document.getElementById('table-search');

        if (applyFilters) applyFilters.addEventListener('click', () => this.applyFilters());
        if (resetFilters) resetFilters.addEventListener('click', () => this.resetFilters());
        if (exportCSV) exportCSV.addEventListener('click', () => this.exportCSV());
        if (tableSearch) tableSearch.addEventListener('input', (e) => this.searchTable(e.target.value));

        // Pagination
        const firstPage = document.getElementById('first-page');
        const prevPage = document.getElementById('prev-page');
        const nextPage = document.getElementById('next-page');
        const lastPage = document.getElementById('last-page');

        if (firstPage) firstPage.addEventListener('click', () => this.goToPage(1));
        if (prevPage) prevPage.addEventListener('click', () => this.goToPage(this.currentPage - 1));
        if (nextPage) nextPage.addEventListener('click', () => this.goToPage(this.currentPage + 1));
        if (lastPage) {
            lastPage.addEventListener('click', () => {
                const totalPages = Math.ceil(this.totalRecords / this.itemsPerPage);
                this.goToPage(totalPages);
            });
        }

        // Table sorting
        document.querySelectorAll('.sortable').forEach(th => {
            th.addEventListener('click', () => this.sortTable(th));
        });

// EVENT DELEGATION FOR TABLE ACTIONS (MUST BE INSIDE setupEventListeners!)
document.getElementById('attendance-tbody')?.addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;

    const id = btn.dataset.id;

    if (btn.classList.contains('view')) {
        this.viewAttendance(id); // Now works!
    }

    if (btn.classList.contains('edit') && window.isAdmin) {
        this.editAttendance(id); // Now works with real id
    }

    if (btn.classList.contains('delete') && window.isAdmin) {
        const date = btn.dataset.date;
        const fellowshipId = btn.dataset.fellowship;
        const serviceId = btn.dataset.service || null;
        this.deleteAttendance(date, fellowshipId, serviceId); // Now works!
    }
});


// Reports Generation
const generateReportBtn = document.getElementById('generate-report');
const backToMainBtn = document.getElementById('back-to-main');

if (generateReportBtn) {
    generateReportBtn.addEventListener('click', () => this.generateReport());
}

if (backToMainBtn) {
    backToMainBtn.addEventListener('click', () => {
        document.querySelector('[data-tab="attendance"]').click();
    });
}
    }


 setupTabNavigation() {
    // Check if tab buttons already exist
    if (document.querySelector('.tab-buttons')) return;
    
    const header = document.querySelector('.page-header:first-child');
    if (header) {
        const tabButtons = `
            <div class="tab-buttons" style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                <button class="btn btn-primary btn-sm active" data-tab="attendance">Attendance</button>
                <button class="btn btn-outline btn-sm" data-tab="reports">Reports</button>
            </div>
        `;
        header.insertAdjacentHTML('beforeend', tabButtons);
        
        // Add tab switching
        document.querySelectorAll('[data-tab]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tab = e.target.dataset.tab;
                
                // Update button states
                document.querySelectorAll('[data-tab]').forEach(b => {
                    b.classList.remove('active', 'btn-primary');
                    b.classList.add('btn-outline');
                });
                e.target.classList.add('active', 'btn-primary');
                e.target.classList.remove('btn-outline');
                
                // Show/hide tabs
                if (tab === 'reports') {
                    this.showReportsTab();
                } else {
                    this.showMainTab();
                }
            });
        });
    }
}

async loadFellowships() {
    try {
        const response = await fetch('../../backend/attendance/read.php?action=fellowships');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        let data;
        
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('Failed to parse fellowships response:', text);
            this.loadSampleFellowships();
            return;
        }
        
        if (data.success) {
            this.fellowships = data.data;
            this.populateFellowshipDropdowns();
            // Also populate report filters if they exist
            this.populateReportFilters();
        } else {
            console.error('Failed to load fellowships:', data.message);
            this.loadSampleFellowships();
        }
    } catch (error) {
        console.error('Error loading fellowships:', error);
        this.loadSampleFellowships();
    }
}

async viewAttendance(sampleId) {
    try {
        this.showLoading(true);

        const response = await fetch(`../../backend/attendance/read.php?action=sample&sample_id=${sampleId}`);
        const result = await response.json();

        if (!result.success || !result.records || result.records.length === 0) {
            throw new Error(result.message || 'No attendance data found');
        }

        const records = result.records;
        const first = records[0];
        const firstTimers = parseInt(first.first_timers_count || 0);
        const presentMembers = records.filter(r => r.status === 'present').length;
        const totalPresent = presentMembers + firstTimers;

        // Build content with proper name handling
        const html = `
            <div style="padding: 1.5rem; line-height: 1.7;">
                <!-- Summary Section -->
                <div style="background: var(--bg-secondary); padding: 1.25rem; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid var(--border);">
                    <div style="display: grid; grid-template-columns: 130px 1fr; gap: 0.75rem 1.5rem; font-size: 1rem;">
                        <strong>Date:</strong>
                        <div>${this.formatDate(first.service_date || first.date)}</div>

                        <strong>Service:</strong>
                        <div><span class="badge badge-info">${this.formatServiceType(first.service_id)}</span></div>

                        <strong>Fellowship:</strong>
                        <div>${this.escapeHtml(first.fellowship_name || first.fellowship || '—')}</div>

                        ${firstTimers > 0 ? `
                        <strong>First Timers:</strong>
                        <div><span class="badge badge-warning">${firstTimers} visitor(s)</span></div>
                        ` : ''}

                        <strong>Notes:</strong>
                        <div style="grid-column: span 2;">
                            ${first.notes 
                                ? '<div style="background: white; padding: 0.75rem; border-radius: 6px; border-left: 4px solid var(--primary);">' + 
                                  this.escapeHtml(first.notes) + 
                                  '</div>'
                                : '<em style="color: var(--text-secondary);">No notes added</em>'
                            }
                        </div>
                    </div>
                </div>

                <!-- Members List -->
                <h4 style="margin: 1.5rem 0 1rem; color: var(--text); font-weight: 600; border-bottom: 2px solid var(--primary); padding-bottom: 0.5rem;">
                    Members Attendance (${records.length})
                </h4>

                <div style="max-height: 450px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px; background: white;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.95rem;">
                        <thead style="background: var(--bg-secondary); position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th style="text-align: left; padding: 1rem; border-bottom: 2px solid var(--border);">Member Name</th>
                                <th style="text-align: center; width: 130px; padding: 1rem; border-bottom: 2px solid var(--border);">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${records.map(r => {
                                const statusBadge = r.status === 'present'
                                    ? '<span class="badge badge-success">Present</span>'
                                    : '<span class="badge badge-danger">Absent</span>';

                                // FIX: Build name from first_name and last_name
                                const firstName = r.first_name || '';
                                const lastName = r.last_name || '';
                                const fullName = `${firstName} ${lastName}`.trim() || r.member_name || 'Unknown Member';

                                return `
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <td style="padding: 0.9rem 1rem;">${this.escapeHtml(fullName)}</td>
                                        <td style="text-align: center; padding: 0.9rem;">${statusBadge}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 1.5rem; text-align: right; color: var(--text-secondary); font-size: 0.9rem;">
                    Total Members: <strong>${records.length}</strong> | 
                    Present: <strong style="color: var(--success);">${presentMembers}</strong> | 
                    Absent: <strong style="color: var(--danger);">${records.filter(r => r.status === 'absent').length}</strong>
                    ${firstTimers > 0 ? ` | First Timers: <strong style="color: var(--warning);">${firstTimers}</strong>` : ''}
                    ${firstTimers > 0 ? `<br><strong>Total Present (including first-timers): ${totalPresent}</strong>` : ''}
                </div>
            </div>
        `;

        document.getElementById('view-modal-body').innerHTML = html;

        const modal = document.getElementById('view-modal');
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        this.showLoading(false);

    } catch (error) {
        this.showLoading(false);
        console.error('View Attendance Error:', error);
        this.showNotification('Failed to load details: ' + error.message, 'error');
    }
}

// DELETE ATTENDANCE RECORD
async deleteAttendance(date, fellowshipId, serviceId) {
    if (!confirm('Delete this entire attendance record?\nThis cannot be undone.')) return;

    try {
        this.showLoading(true);

        const response = await fetch('../../backend/attendance/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                date: date,
                fellowship_id: fellowshipId,
                service_id: serviceId || null
            })
        });

        const result = await response.json();

        if (result.success) {
            this.showNotification('Attendance deleted successfully', 'success');
            this.loadAttendanceData(); // Refresh table
        } else {
            this.showNotification(result.message || 'Delete failed', 'error');
        }
    } catch (error) {
        this.showNotification('Error deleting record', 'error');
        console.error(error);
    } finally {
        this.showLoading(false);
    }
}

async loadServices() {
    try {
        const response = await fetch('../../backend/ajax/services_crud.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        let data;
        
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('Failed to parse services response:', text);
            this.loadSampleServices();
            return;
        }
        
        if (data.success) {
            this.services = data.data;
            this.populateServiceDropdowns();
            // Also populate report filters if they exist
            this.populateReportFilters();
        } else {
            console.error('Failed to load services:', data.message);
            this.loadSampleServices();
        }
    } catch (error) {
        console.error('Error loading services:', error);
        this.loadSampleServices();
    }
}

openServiceModal() {
    
    const modal = document.getElementById('service-modal');
    const form = document.getElementById('service-form');
    const title = document.getElementById('service-modal-title');
    const submitText = document.getElementById('service-submit-text');
    
    if (!modal || !form || !title || !submitText) {
        console.error('Service modal elements not found');
        return;
    }
    
    // Reset form for adding new service
    form.reset();
    title.textContent = 'Manage Services';
    submitText.textContent = 'Add Service';
    delete form.dataset.mode;
    delete form.dataset.serviceId;
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Load services table for management view
    this.loadServicesTable();
    this.loadServices();
}
    loadSampleFellowships() {
        this.fellowships = [
            { id: 1, name: "Youth Fellowship" },
            { id: 2, name: "Women's Fellowship" },
            { id: 3, name: "Men's Fellowship" },
            { id: 4, name: "Children's Ministry" },
            { id: 5, name: "Senior Saints" }
        ];
        this.populateFellowshipDropdowns();
        this.showNotification('Using sample fellowship data', 'warning');
    }

    loadSampleServices() {
        this.services = [
            { id: 'sunday_service', name: 'Sunday Service' },
            { id: 'bible_study', name: 'Bible Study' },
            { id: 'prayer_meeting', name: 'Prayer Meeting' },
            { id: 'youth_fellowship', name: 'Youth Fellowship' },
            { id: 'children_service', name: 'Children Service' }
        ];
        this.populateServiceDropdowns();
        this.showNotification('Using sample service data', 'warning');
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

    async loadAttendanceData() {
        try {
            const response = await fetch('../../backend/attendance/read.php');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const text = await response.text();
            
            let data;
            
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                console.error('Failed to parse attendance response:', text);
                this.showNotification('Error loading attendance data', 'error');
                this.loadSampleAttendance();
                return;
            }
            
            
            if (data.success && data.records) {
                this.attendanceData = data.records;
                this.filteredData = [...this.attendanceData];
                this.totalRecords = this.filteredData.length;
                this.renderTable();
                this.updateStats();
            } else {
                console.error('Failed to load attendance:', data.message);
                this.showNotification(data.message || 'No attendance data found', 'warning');
                this.loadSampleAttendance();
            }
        } catch (error) {
            console.error('Error loading attendance data:', error);
            this.showNotification('Error loading attendance: ' + error.message, 'error');
            this.loadSampleAttendance();
        }
    }

    loadSampleAttendance() {
        const today = new Date().toISOString().split('T')[0];
        this.attendanceData = [
            {
                id: 1,
                date: today,
                service_id: 'sunday_service',
                fellowship: 'Youth Fellowship',
                fellowship_id: 1,
                present: 45,
                absent: 5,
                percentage: 90,
                notes: 'Good turnout today'
            },
            {
                id: 2,
                date: today,
                service_id: 'bible_study',
                fellowship: "Women's Fellowship",
                fellowship_id: 2,
                present: 32,
                absent: 8,
                percentage: 80,
                notes: ''
            }
        ];
        this.filteredData = [...this.attendanceData];
        this.totalRecords = this.filteredData.length;
        this.renderTable();
        this.updateStats();
        this.showNotification('Using sample attendance data', 'warning');
    }

populateServiceDropdowns() {
    const dropdowns = ['service-filter', 'attendance-service'];
    
    dropdowns.forEach(id => {
        const select = document.getElementById(id);
        if (!select) return;

        // Clear existing options except first
        select.innerHTML = id === 'service-filter' 
            ? '<option value="">All Services</option>'
            : '<option value="">Select Service</option>';

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
        const dateInput = document.getElementById('attendance-date');
        if (dateInput) dateInput.value = today;
        
        const firstDay = new Date();
        firstDay.setDate(1);
        const startDateInput = document.getElementById('start-date');
        const endDateInput = document.getElementById('end-date');
        
        if (startDateInput) startDateInput.value = firstDay.toISOString().split('T')[0];
        if (endDateInput) endDateInput.value = today;
    }

    updateStats() {
        const today = new Date().toISOString().split('T')[0];
        const todayRecords = this.attendanceData.filter(r => r.date === today);
        
        const totalPresent = todayRecords.reduce((sum, r) => sum + (parseInt(r.present) || 0), 0);
        const totalAbsent = todayRecords.reduce((sum, r) => sum + (parseInt(r.absent) || 0), 0);
        const totalFirstTimers = todayRecords.reduce((sum, r) => sum + (parseInt(r.first_timers) || 0), 0);
        const totalMembers = totalPresent + totalAbsent;
        const attendanceRate = totalMembers > 0 ? Math.round((totalPresent / totalMembers) * 100) : 0;

        const presentEl = document.getElementById('total-present');
        const absentEl = document.getElementById('total-absent');
        const firstTimersEl = document.getElementById('total-first-timers');
        const membersEl = document.getElementById('total-members');
        const rateEl = document.getElementById('attendance-rate');

        if (presentEl) presentEl.textContent = totalPresent;
        if (absentEl) absentEl.textContent = totalAbsent;
        if (firstTimersEl) firstTimersEl.textContent = totalFirstTimers;
        if (membersEl) membersEl.textContent = totalMembers;
        if (rateEl) rateEl.textContent = attendanceRate + '%';
    }


    openModal(isEdit = false) {
        const modal = document.getElementById('attendance-modal');
        const title = document.getElementById('modal-title');
        const form = document.getElementById('attendance-form');
        const indicator = document.getElementById('attendance-taken-indicator');

        // Hide the attendance-taken indicator when opening modal
        if (indicator) {
            indicator.style.display = 'none';
        }

        // DO NOT reset form here when editing!
        if (!isEdit) {
            form.reset();
            document.getElementById('attendance-id').value = '';
            document.getElementById('selected-count').textContent = '0';
            document.getElementById('members-list').innerHTML = '<div style="padding:1rem; text-align:center; color:var(--text-secondary);">Select fellowship to load members</div>';
            // Set default date to today
            this.setDefaultDate();
            this.loadMembers();
        }

        title.textContent = isEdit ? 'Edit Attendance' : 'Record Attendance';

        // Remove any existing lock overlay
        const existing = document.getElementById('edit-lock-overlay');
        if (existing) existing.remove();

        // Show lock overlay only if editing AND not admin
        if (isEdit && !window.isAdmin) {
            const overlay = document.createElement('div');
            overlay.id = 'edit-lock-overlay';
            overlay.style.cssText = `
                position: absolute; inset: 0; background: rgba(0,0,0,0.6);
                display: flex; align-items: center; justify-content: center;
                z-index: 100; border-radius: 8px; backdrop-filter: blur(4px);
            `;
            overlay.innerHTML = `
                <div style="background: white; padding: 2rem 3rem; border-radius: 12px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
                    <h3 style="margin: 0 0 1rem 0; color: var(--danger);">Locked</h3>
                    <p style="margin: 0 0 1.5rem 0; line-height: 1.5;">
                        This attendance record is locked.<br>
                        <strong>Only administrators can edit past records.</strong>
                    </p>
                    <button type="button" class="btn btn-outline" onclick="attendanceManager.closeModal('attendance-modal')">
                        Close
                    </button>
                </div>
            `;
            modal.querySelector('.modal-content').style.position = 'relative';
            modal.querySelector('.modal-content').prepend(overlay);
        }

        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

// Optional: Add this helper
unlockEdit() {
    const overlay = document.getElementById('edit-lock-overlay');
    if (overlay) overlay.remove();
}

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    async loadMembers() {
        const fellowshipSelect = document.getElementById('attendance-fellowship');
        const membersList = document.getElementById('members-list');
        
        if (!fellowshipSelect || !membersList) {
            console.error('Required elements not found');
            return;
        }

        const fellowshipId = fellowshipSelect.value;
        
        if (!fellowshipId) {
            membersList.innerHTML = '<p style="text-align:center; padding: 2rem; color: var(--text-muted);">Please select a fellowship first</p>';
            return;
        }

        try {
            const response = await fetch(`../../backend/attendance/read.php?action=members&fellowship_id=${fellowshipId}`);
            const data = await response.json();
            
            if (data.success) {
                this.members = data.data.map(member => ({
                    ...member,
                    status: 'present'
                }));
                this.renderMembersList();
            } else {
                this.loadSampleMembers();
            }
        } catch (error) {
            console.error('Error loading members:', error);
            this.loadSampleMembers();
        }
    }

    loadSampleMembers() {
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
                <button type="button" class="toggle-option present ${member.status === 'present' ? 'active' : ''}" 
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

    container.querySelectorAll('.toggle-option').forEach(btn => {
        btn.addEventListener('click', (e) => this.toggleAttendance(e.target));
    });

    this.updateSelectedCount();
}

unlockEdit() {
    if (!window.isAdmin) {
        this.showNotification("You don't have permission to edit", "error");
        return;
    }
    document.getElementById('edit-lock-overlay')?.remove();
    // Now allow editing
}

toggleAttendance(button) {
    const memberId = button.dataset.memberId;
    const status = button.dataset.status;
    const memberItem = button.closest('.member-item');
    const buttons = memberItem.querySelectorAll('.toggle-option');
    
    // Remove active class from all buttons
    buttons.forEach(btn => btn.classList.remove('active'));
    
    // Add active class to clicked button
    button.classList.add('active');

    // Add visual feedback animation
    memberItem.style.transform = 'scale(0.98)';
    setTimeout(() => {
        memberItem.style.transform = 'scale(1)';
    }, 150);

    // Add checkmark animation for present
    if (status === 'present') {
        const checkmark = document.createElement('div');
        checkmark.innerHTML = '✓';
        checkmark.style.cssText = `
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%) scale(0);
            color: var(--success);
            font-size: 24px;
            font-weight: bold;
            animation: checkPop 0.3s ease-out forwards;
        `;
        memberItem.style.position = 'relative';
        memberItem.appendChild(checkmark);
        setTimeout(() => checkmark.remove(), 500);
    }

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

    getInitials(name) {
        return name.split(' ').map(n => n[0]).join('').toUpperCase();
    }

async saveAttendance(e) {
    e.preventDefault();

    // Get the attendance count before proceeding
    const presentCount = this.members.filter(m => m.status === 'present').length;
    const totalMembers = this.members.length;
    const firstTimersCount = parseInt(document.getElementById('first-timers-count')?.value || 0);
    
    if (totalMembers === 0 && firstTimersCount === 0) {
        this.showNotification('No members or first-timers recorded', 'error');
        return;
    }

    const recordId = document.getElementById('attendance-id')?.value;
    const isEdit = !!recordId;
    
    const date = document.getElementById('attendance-date')?.value;
    const fellowshipId = document.getElementById('attendance-fellowship')?.value;
    const serviceId = document.getElementById('attendance-service')?.value;

    // Check for duplicate attendance (only when creating new, not editing)
    if (!isEdit) {
        const duplicate = this.attendanceData.find(record => 
            record.date === date && 
            record.fellowship_id == fellowshipId && 
            record.service_id === serviceId
        );

        if (duplicate) {
            const serviceName = this.formatServiceType(serviceId);
            const confirmOverwrite = confirm(
                `Attendance for ${serviceName} on ${date} already exists.\n\n` +
                `Do you want to overwrite the existing record?`
            );
            
            if (!confirmOverwrite) {
                return;
            }
        }
    }
    
    // Show attendance summary confirmation including first-timers
    const confirmMsg = firstTimersCount > 0 
        ? `Record attendance for ${presentCount}/${totalMembers} members present + ${firstTimersCount} first-timer(s)?`
        : `Record attendance for ${presentCount}/${totalMembers} members present?`;
    
    if (!confirm(confirmMsg)) {
        return;
    }

    const formData = {
        date: date,
        fellowship_id: fellowshipId,
        service_id: serviceId,
        notes: document.getElementById('attendance-notes')?.value || '',
        first_timers_count: firstTimersCount,
        present_count: presentCount,
        absent_count: totalMembers - presentCount,
        total_members: totalMembers,
        records: this.members.map(member => ({
            member_id: member.id,
            status: member.status
        }))
    };

    // Add ID for edit operations
    if (isEdit) {
        formData.id = recordId;
    }

    if (!formData.date || !formData.fellowship_id || !formData.service_id) {
        this.showNotification('Please fill all required fields', 'error');
        return;
    }

    try {
        // Show loading state
        this.showLoading(true);
        
        const url = isEdit ? '../../backend/attendance/update.php' : '../../backend/attendance/create.php';
        const method = isEdit ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (response.ok && result.success) {
            // Show success notification with details including first-timers
            const successMsg = firstTimersCount > 0
                ? `✓ Attendance ${isEdit ? 'updated' : 'recorded'} successfully!\n${presentCount} members present, ${totalMembers - presentCount} absent, ${firstTimersCount} first-timer(s)`
                : `✓ Attendance ${isEdit ? 'updated' : 'recorded'} successfully!\n${presentCount} present, ${totalMembers - presentCount} absent`;
            
            this.showNotification(successMsg, 'success');
            
            // Show attendance taken indicator
            const indicator = document.getElementById('attendance-taken-indicator');
            if (indicator) {
                indicator.style.display = 'flex';
            }
            
            // Wait a moment for visual confirmation, then close
            setTimeout(() => {
                this.closeModal('attendance-modal');
                this.loadAttendanceData();
                // Update stats after reload
                setTimeout(() => this.updateStats(), 500);
            }, 1000);
        } else {
            this.showNotification(result.message || `Failed to ${isEdit ? 'update' : 'save'} attendance`, 'error');
        }
    } catch (error) {
        console.error(`Error ${isEdit ? 'updating' : 'saving'} attendance:`, error);
        this.showNotification(`Error ${isEdit ? 'updating' : 'saving'} attendance: ${error.message}`, 'error');
    } finally {
        this.showLoading(false);
    }
}


async editAttendance(sampleId) {
    if (!window.isAdmin) {
        this.showNotification('Only administrators can edit attendance records', 'error');
        return;
    }

    try {
        this.showLoading(true);

        // STEP 1: Get the main record info
        const recordResponse = await fetch(`../../backend/attendance/read.php?action=record&id=${sampleId}`);
        const recordResult = await recordResponse.json();

        if (!recordResult.success || !recordResult.record) {
            throw new Error(recordResult.message || 'Record not found');
        }

        const record = recordResult.record;

        // STEP 2: Get members with attendance status
        const membersResponse = await fetch(`../../backend/attendance/read.php?action=record_members&record_id=${sampleId}`);
        const membersResult = await membersResponse.json();

        if (!membersResult.success) {
            throw new Error(membersResult.message || 'Failed to load members');
        }


        // NOW open modal and populate
        this.openModal(true);

        // Populate form fields
        document.getElementById('attendance-id').value = sampleId;
        document.getElementById('attendance-date').value = record.date;
        document.getElementById('attendance-fellowship').value = record.fellowship_id;
        document.getElementById('attendance-service').value = record.service_id || '';
        document.getElementById('attendance-notes').value = record.notes || '';
        
        // Get first_timers_count if available
        const firstTimersCount = record.first_timers_count || 0;
        const firstTimersInput = document.getElementById('first-timers-count');
        if (firstTimersInput) {
            firstTimersInput.value = firstTimersCount;
        }

        // Load members list for this fellowship
        await this.loadMembers();

        // Update member statuses based on the attendance data
        setTimeout(() => {
            // Update the members array with the correct statuses
            membersResult.data.forEach(attendanceRecord => {
                const member = this.members.find(m => m.id == attendanceRecord.member_id);
                if (member) {
                    member.status = attendanceRecord.status || 'present';
                }
            });

            // Re-render the members list with updated statuses
            this.renderMembersList();
            
            this.showLoading(false);
        }, 300);

    } catch (error) {
        this.showLoading(false);
        console.error('Edit error:', error);
        this.showNotification('Failed to load record: ' + error.message, 'error');
    }
}

showLoading(show = true) {
    let loader = document.getElementById('global-loader');
    
    // Create loader if it doesn't exist
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'global-loader';
        loader.innerHTML = `
            <div style="
                position: fixed; 
                inset: 0; 
                background: rgba(0,0,0,0.7); 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                z-index: 9999; 
                backdrop-filter: blur(5px);
            ">
                <div style="
                    background: white; 
                    padding: 2rem 3rem; 
                    border-radius: 12px; 
                    text-align: center; 
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                ">
                    <div class="spinner" style="width: 40px; height: 40px; margin: 0 auto 1rem;"></div>
                    <p style="margin: 0; font-weight: 500;">Loading...</p>
                </div>
            </div>
        `;
        document.body.appendChild(loader);
    }

    loader.style.display = show ? 'flex' : 'none';
}

async refreshServices() {
    try {
        await this.loadServices();
        this.populateServiceDropdowns();
        
        // Only show notification when manually clicking refresh button
        this.showNotification('Services refreshed successfully', 'success');
    } catch (error) {
        console.error('Error refreshing services:', error);
        this.showNotification('Error refreshing services', 'error');
    }
}

renderTable() {
    const tbody = document.getElementById('attendance-tbody');
    if (!tbody) return;

    const start = (this.currentPage - 1) * this.itemsPerPage;
    const end = start + this.itemsPerPage;
    const pageData = this.filteredData.slice(start, end);

    if (pageData.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8">
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

    tbody.innerHTML = pageData.map(record => {
        // Safely escape service_id for data attribute
        const safeServiceId = record.service_id ? record.service_id.replace(/'/g, "&#39;") : '';
        const firstTimers = parseInt(record.first_timers || 0);
        const totalPresent = parseInt(record.present || 0) + firstTimers;

        return `
            <tr>
                <td>${this.formatDate(record.date)}</td>
                <td><span class="badge badge-info">${this.formatServiceType(record.service_id)}</span></td>
                <td>${record.fellowship || '—'}</td>
                <td>
                    <strong style="color: var(--success)">${totalPresent}</strong>
                    ${firstTimers > 0 ? `<span style="font-size: 0.85em; color: var(--text-secondary);"> (${record.present} + ${firstTimers})</span>` : ''}
                </td>
                <td><span class="badge badge-warning">${firstTimers}</span></td>
                <td><strong style="color: var(--danger)">${record.absent || 0}</strong></td>
                <td>${this.renderProgressRing(record.percentage)}</td>
                <td>
                    <div class="action-buttons">
                        <!-- View Button -->
                        <button class="btn-icon view" 
                                data-id="${record.sample_id}" 
                                title="View Details">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>

                        <!-- Edit & Delete - Only for Admins -->
                        ${window.isAdmin ? `
                            <button class="btn-icon edit" 
                                    data-id="${record.sample_id}" 
                                    title="Edit Attendance">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                            <button class="btn-icon delete" 
                                    data-date="${record.date}"
                                    data-fellowship="${record.fellowship_id}"
                                    data-service="${safeServiceId}"
                                    title="Delete Attendance">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 6h18m-2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    this.updatePaginationInfo(start + 1, Math.min(end, this.totalRecords));
    this.renderPageNumbers();
}

    updateSelectedCount() {
        const presentCount = this.members.filter(m => m.status === 'present').length;
        const element = document.getElementById('selected-count');
        if (element) {
            element.textContent = presentCount;
        }
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
            'children_service': 'Children Service',
            'other': 'Other'
        };
        return types[type] || type;
    }

    updatePaginationInfo(start, end) {
        const startEl = document.getElementById('showing-start');
        const endEl = document.getElementById('showing-end');
        const totalEl = document.getElementById('total-records');

        if (startEl) startEl.textContent = start;
        if (endEl) endEl.textContent = end;
        if (totalEl) totalEl.textContent = this.totalRecords;

        const firstPage = document.getElementById('first-page');
        const prevPage = document.getElementById('prev-page');
        const nextPage = document.getElementById('next-page');
        const lastPage = document.getElementById('last-page');
        const totalPages = Math.ceil(this.totalRecords / this.itemsPerPage);

        if (firstPage) firstPage.disabled = this.currentPage === 1;
        if (prevPage) prevPage.disabled = this.currentPage === 1;
        if (nextPage) nextPage.disabled = this.currentPage >= totalPages;
        if (lastPage) lastPage.disabled = this.currentPage >= totalPages;
    }

    renderPageNumbers() {
        const totalPages = Math.ceil(this.totalRecords / this.itemsPerPage);
        const container = document.getElementById('page-numbers');
        if (!container) return;

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
            'service_id': 'service_id',
            'fellowship': 'fellowship',
            'present': 'present',
            'absent': 'absent',
            'attendance_rate': 'percentage'
        };

        const sortKey = columnMap[column];
        if (!sortKey) return;

        if (this.sortColumn === sortKey) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = sortKey;
            this.sortDirection = 'asc';
        }

        document.querySelectorAll('.sortable').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
        });
        thElement.classList.add(`sort-${this.sortDirection}`);

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
                    this.formatServiceType(record.service_id).toLowerCase().includes(query) ||
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
        const startDate = document.getElementById('start-date')?.value;
        const endDate = document.getElementById('end-date')?.value;
        const fellowship = document.getElementById('fellowship-filter')?.value;
        const serviceType = document.getElementById('service-filter')?.value;

        this.filteredData = this.attendanceData.filter(record => {
            let matches = true;

            if (startDate && record.date < startDate) matches = false;
            if (endDate && record.date > endDate) matches = false;
            if (fellowship && record.fellowship_id != fellowship) matches = false;
            if (serviceType && record.service_id !== serviceType) matches = false;

            return matches;
        });

        this.totalRecords = this.filteredData.length;
        this.currentPage = 1;
        this.renderTable();
        this.showNotification('Filters applied successfully', 'success');
    }

    resetFilters() {
        const startDate = document.getElementById('start-date');
        const endDate = document.getElementById('end-date');
        const fellowship = document.getElementById('fellowship-filter');
        const serviceType = document.getElementById('service-filter');
        const search = document.getElementById('table-search');

        if (startDate) startDate.value = '';
        if (endDate) endDate.value = '';
        if (fellowship) fellowship.value = '';
        if (serviceType) serviceType.value = '';
        if (search) search.value = '';

        this.filteredData = [...this.attendanceData];
        this.totalRecords = this.filteredData.length;
        this.currentPage = 1;
        this.renderTable();
        this.showNotification('Filters reset', 'info');
    }

    viewRecord(recordId) {
        const record = this.attendanceData.find(r => r.id === recordId);
        if (!record) return;

        const modal = document.getElementById('view-modal');
        const body = document.getElementById('view-modal-body');

        if (!modal || !body) return;

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
                            ${this.formatServiceType(record.service_id)}
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

        try {
            const response = await fetch(`../../backend/attendance/delete.php`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: recordId })
            });

            const result = await response.json();

            if (result.success) {
                this.attendanceData = this.attendanceData.filter(r => r.id !== recordId);
                this.filteredData = this.filteredData.filter(r => r.id !== recordId);
                this.totalRecords = this.filteredData.length;

                const totalPages = Math.ceil(this.totalRecords / this.itemsPerPage);
                if (this.currentPage > totalPages) {
                    this.currentPage = Math.max(1, totalPages);
                }

                this.renderTable();
                this.updateStats();
                this.showNotification('Attendance record deleted successfully', 'success');
            } else {
                this.showNotification(result.message || 'Failed to delete record', 'error');
            }
        } catch (error) {
            console.error('Error deleting record:', error);
            this.showNotification('Error deleting record', 'error');
        }
    }

async loadRecord(recordId) {
    
    try {
        // Fetch the full record details from the server
        const response = await fetch(`../../backend/attendance/read.php?action=record&id=${recordId}`);
        
        const data = await response.json();
        
        if (!data.success || !data.record) {
            console.error('Failed to load record:', data.message);
            this.showNotification('Failed to load record details', 'error');
            return;
        }

        const record = data.record;

        // Populate form fields
        const idInput = document.getElementById('attendance-id');
        const dateInput = document.getElementById('attendance-date');
        const fellowshipInput = document.getElementById('attendance-fellowship');
        const serviceInput = document.getElementById('attendance-service');
        const notesInput = document.getElementById('attendance-notes');

        if (idInput) idInput.value = record.id;
        if (dateInput) dateInput.value = record.date;
        if (fellowshipInput) fellowshipInput.value = record.fellowship_id;
        if (serviceInput) serviceInput.value = record.service_id;
        if (notesInput) notesInput.value = record.notes || '';

        // CRITICAL: Load members with their attendance status
        await this.loadMembersForEdit(recordId, record.fellowship_id);

    } catch (error) {
        console.error('Error loading record:', error);
        this.showNotification('Error loading record details', 'error');
    }
}

async loadMembersForEdit(recordId, fellowshipId) {
    const membersList = document.getElementById('members-list');
    
    if (!membersList) {
        console.error('Members list element not found');
        return;
    }

    try {
        // Fetch member attendance for this specific record
        const response = await fetch(`../../backend/attendance/read.php?action=record_members&record_id=${recordId}`);
        const data = await response.json();
        
        
        if (data.success && data.data && data.data.length > 0) {
            // Map the response to match our members structure
            this.members = data.data.map(member => ({
                id: member.member_id,
                name: member.member_name,
                category: member.category || 'member',
                status: member.status || 'present' // Use actual status from database
            }));
            
            this.renderMembersList();
        } else {
            // Fallback: Load all fellowship members and mark all as present
            console.warn('Could not load member attendance, falling back to fellowship members');
            const fellowshipSelect = document.getElementById('attendance-fellowship');
            if (fellowshipSelect) fellowshipSelect.value = fellowshipId;
            await this.loadMembers();
        }
        
    } catch (error) {
        console.error('Error loading members for edit:', error);
        // Fallback to regular member loading
        const fellowshipSelect = document.getElementById('attendance-fellowship');
        if (fellowshipSelect) fellowshipSelect.value = fellowshipId;
        await this.loadMembers();
    }
}

    exportCSV() {
        const headers = ['Date', 'Service Type', 'Fellowship', 'Present', 'Absent', 'Percentage', 'Notes'];
        const rows = this.filteredData.map(record => [
            record.date,
            this.formatServiceType(record.service_id),
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
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            ${type === 'success' ? 'background: #10b981;' : ''}
            ${type === 'error' ? 'background: #ef4444;' : ''}
            ${type === 'warning' ? 'background: #f59e0b;' : ''}
            ${type === 'info' ? 'background: #3b82f6;' : ''}
        `;
        
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Add these methods to the AttendanceManager class

async saveService(e) {
    e.preventDefault();
    
    const form = e.target;
    const isEdit = form.dataset.mode === 'edit';
    const serviceId = form.dataset.serviceId;
    const serviceName = document.getElementById('service-name').value.trim();
    
    if (!serviceName) {
        this.showNotification('Service name is required', 'error');
        return;
    }
    
    try {
        const method = isEdit ? 'PUT' : 'POST';
       // Inside saveAttendance() function – find this block
                const payload = {
                    date: date,
                    fellowship_id: fellowshipId,
                    service_id: document.getElementById('attendance-service').value,  // ← FIXED
                    notes: notes,
                    records: records
                };
        
        if (isEdit && serviceId) {
            payload.id = serviceId;
        }
        
        const response = await fetch('../../backend/ajax/services_crud.php', {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        if (!response.ok) {
            const errorResult = await response.json();
            throw new Error(errorResult.message || `Server error: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            this.showNotification(result.message, 'success');
            this.closeModal('service-modal');
            
            // Refresh services silently (no notification)
            await this.loadServices(); // Changed from refreshServices()
            this.populateServiceDropdowns(); // Update dropdowns
            this.loadServicesTable(); // Update services table in modal
            
            // Reset form
            form.reset();
            delete form.dataset.mode;
            delete form.dataset.serviceId;
            document.getElementById('service-modal-title').textContent = 'Manage Services';
            document.getElementById('service-submit-text').textContent = 'Add Service';
        } else {
            throw new Error(result.message || 'Failed to save service');
        }
    } catch (error) {
        console.error('Error saving service:', error);
        let userMessage = error.message;
        if (error.message.includes('already exists')) {
            userMessage = 'A service with this name already exists.';
        } else if (error.message.includes('404')) {
            userMessage = 'Service not found. Please refresh the page.';
        }
        this.showNotification(userMessage, 'error');
    }
}

async loadServicesTable() {
    try {
        const response = await fetch('../../backend/ajax/services_crud.php');
        const data = await response.json();
        
        if (data.success) {
            this.services = data.data;
            this.renderServicesTable();
        }
    } catch (error) {
        console.error('Error loading services table:', error);
    }
}

renderServicesTable() {
    const container = document.getElementById('services-table-container');
    if (!container) return;
    
    if (this.services.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/>
                </svg>
                <h3>No Services Found</h3>
                <p>Add your first service to get started</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = `
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Service Name</th>
                        <th>Service ID</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${this.services.map(service => `
                        <tr>
                            <td>${this.escapeHtml(service.name)}</td>
                            <td><code>${service.id}</code></td>
                            <td>
                                <div class="action-buttons">
                                  
                                        <button class="btn-icon edit" onclick="attendanceManager.editService('${service.id}')" title="Edit Service">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </button>
                                    <button class="btn-icon delete" onclick="attendanceManager.deleteService('${service.id}')" title="Delete Service">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M3 6h18m-2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

// Replace the editService method in attendance.js (around line 1233)
editService(serviceId) {

    const service = this.services.find(s => s.id === serviceId);
    if (!service) {
        console.error('Service not found:', serviceId);
        this.showNotification('Service not found', 'error');
        return;
    }

    const form = document.getElementById('service-form');
    const title = document.getElementById('service-modal-title');
    const submitText = document.getElementById('service-submit-text');
    const nameInput = document.getElementById('service-name');

    if (!form || !title || !submitText || !nameInput) {
        console.error('Service form elements not found');
        return;
    }

    // Populate the form
    nameInput.value = service.name;
    title.textContent = 'Edit Service';
    submitText.textContent = 'Update Service';
    form.dataset.mode = 'edit';
    form.dataset.serviceId = service.id;

    // Properly open the modal
    const modal = document.getElementById('service-modal');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        nameInput.focus();  // Nice touch: focus the input
    }
}

async deleteService(serviceId) {
    if (!confirm('Are you sure you want to delete this service? This action cannot be undone.')) {
        return;
    }

    try {
        const response = await fetch('../../backend/ajax/services_crud.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({ id: serviceId })
        });

        const result = await response.json();

        if (result.success) {
            this.showNotification('Service deleted successfully', 'success');
            await this.refreshServices();
            this.loadServicesTable();
        } else {
            this.showNotification(result.message || 'Failed to delete service', 'error');
        }
    } catch (error) {
        console.error('Error deleting service:', error);
        this.showNotification('Error deleting service', 'error');
    }
}

// Utility method for escaping HTML
escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Replace these methods in attendance.js

// SIMPLE FIX: Add retry mechanism
populateReportFilters(retryCount = 0) {
    const fellowshipSelect = document.getElementById('report-fellowship-filter');
    const serviceSelect = document.getElementById('report-service-filter');
    
    if (!fellowshipSelect || !serviceSelect) {
        console.warn('Report filter elements not found');
        return;
    }
    
    // Check if data is ready
    if ((!this.fellowships || this.fellowships.length === 0) || 
        (!this.services || this.services.length === 0)) {
        
        if (retryCount < 3) { // Try up to 3 times
            setTimeout(() => this.populateReportFilters(retryCount + 1), 500);
        } else {
            console.warn('Failed to load data after 3 retries');
        }
        return;
    }
    
    // Data is ready, populate the filters
    
    // Populate fellowships
    fellowshipSelect.innerHTML = '<option value="">All Fellowships</option>';
    this.fellowships.forEach(fellowship => {
        const option = document.createElement('option');
        option.value = fellowship.id;
        option.textContent = fellowship.name;
        fellowshipSelect.appendChild(option);
    });
    
    // Populate services
    serviceSelect.innerHTML = '<option value="">All Services</option>';
    this.services.forEach(service => {
        const option = document.createElement('option');
        option.value = service.id;
        option.textContent = service.name;
        serviceSelect.appendChild(option);
    });
}



createOverviewChart(data, canvas) {
    try {
        
        if (!data.records || data.records.length === 0) {
            this.showNoDataMessage(canvas);
            return;
        }
        
        // Sort by date
        const sortedRecords = [...data.records].sort((a, b) => {
            return new Date(a.date) - new Date(b.date);
        });
        
        const labels = sortedRecords.map(r => {
            try {
                const date = new Date(r.date);
                return date.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric' 
                });
            } catch (e) {
                return r.date;
            }
        });

                // After creating chart, fix container spacing
        const chartContainer = canvas.closest('.chart-container');
        if (chartContainer) {
            chartContainer.style.margin = '0';
            chartContainer.style.padding = '0.75rem';
            chartContainer.style.height = '350px';
        }
        
        // Fix canvas spacing
        canvas.style.margin = '0';
        canvas.style.padding = '0';
        
        const percentages = sortedRecords.map(r => parseFloat(r.percentage) || 0);
        
        // Create chart context
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            console.error('Cannot get 2D context');
            return;
        }
        
        // Destroy previous chart if exists
        if (this.trendChart) {
            this.trendChart.destroy();
        }
        
        this.trendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Attendance Rate %',
                    data: percentages,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#4f46e5'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Attendance Trends',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Attendance: ${context.raw}%`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        
    } catch (error) {
        console.error('Error creating overview chart:', error);
        this.showNoDataMessage(canvas);
    }
}

// ADD this method to AttendanceManager class
forcePopulateReportFilters() {
    
    // First ensure we have data
    if (!this.fellowships || this.fellowships.length === 0) {
        this.loadFellowships().then(() => {
            if (!this.services || this.services.length === 0) {
                this.loadServices().then(() => {
                    this.populateReportFilters();
                });
            } else {
                this.populateReportFilters();
            }
        });
    } else if (!this.services || this.services.length === 0) {
        this.loadServices().then(() => {
            this.populateReportFilters();
        });
    } else {
        this.populateReportFilters();
    }
}

// 4. FIX: Demographics Bar Chart
createDemographicsBarChart(data, ctx) {
    const labels = data.demographics_table.map(r => r.age_group);
    const totals = data.demographics_table.map(r => parseInt(r.total) || 0);
    const rates = data.demographics_table.map(r => parseFloat(r.attendance_rate) || 0);
    
    this.trendChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Total Members',
                    data: totals,
                    backgroundColor: '#4f46e5',
                    borderColor: '#4338ca',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Attendance Rate %',
                    data: rates,
                    backgroundColor: '#10b981',
                    borderColor: '#059669',
                    borderWidth: 1,
                    type: 'line',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Member Demographics & Attendance'
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Total Members'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Attendance Rate %'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

// 5. FIX: Demographics Pie Chart
createDemographicsPieChart(data, ctx) {
    const labels = Object.keys(data.demographics).map(key => 
        key.charAt(0).toUpperCase() + key.slice(1)
    );
    const values = Object.values(data.demographics);
    
    this.demoChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: [
                    '#4f46e5',
                    '#10b981',
                    '#f59e0b',
                    '#ef4444',
                    '#8b5cf6'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Demographics Distribution'
                },
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// 6. FIX: Fellowship Chart
createFellowshipChart(data, ctx) {
    if (!data.fellowships || data.fellowships.length === 0) {
        console.warn('No fellowship data available');
        return;
    }
    
    const labels = data.fellowships.map(f => f.fellowship_name);
    const rates = data.fellowships.map(f => parseFloat(f.attendance_rate) || 0);
    const counts = data.fellowships.map(f => parseInt(f.total_present) || 0);
    
    this.trendChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Attendance Rate %',
                    data: rates,
                    backgroundColor: '#4f46e5',
                    borderColor: '#4338ca',
                    borderWidth: 1,
                    yAxisID: 'y1'
                },
                {
                    label: 'Total Present',
                    data: counts,
                    backgroundColor: '#10b981',
                    borderColor: '#059669',
                    borderWidth: 1,
                    yAxisID: 'y'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Fellowship Comparison'
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Total Present'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Attendance Rate %'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

// 7. FIX: Trends Chart
createTrendsChart(data, ctx) {
    if (!data.dates || data.dates.length === 0) {
        console.warn('No trend data available');
        return;
    }
    
    this.trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.dates,
            datasets: [{
                label: 'Attendance Rate %',
                data: data.percentages,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Attendance Trends'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
}

// 8. FIX: Initialize Reports (Call this in constructor after init())
initReports() {
    // Set default date range (last 30 days)
    const end = new Date();
    const start = new Date();
    start.setDate(start.getDate() - 30);
    
    const endDateInput = document.getElementById('report-end-date');
    const startDateInput = document.getElementById('report-start-date');
    
    if (endDateInput) endDateInput.value = end.toISOString().split('T')[0];
    if (startDateInput) startDateInput.value = start.toISOString().split('T')[0];
    
    // Event listeners
    document.getElementById('generate-report')?.addEventListener('click', () => this.generateReport());
    document.getElementById('export-report-pdf')?.addEventListener('click', () => this.exportPDF());
    document.getElementById('export-report-csv')?.addEventListener('click', () => this.exportReportCSV());
    document.getElementById('back-to-main')?.addEventListener('click', () => this.showMainTab());
    
    // Add listener to populate filters when reports tab is shown
    document.querySelectorAll('[data-tab]').forEach(btn => {
        if (btn.dataset.tab === 'reports') {
            btn.addEventListener('click', () => {
                // Populate filters when switching to reports tab
                setTimeout(() => this.populateReportFilters(), 100);
            });
        }
    });
}

// 1. FIX: Populate Report Filters (Fellowship & Services)
populateReportFilters() {
    const fellowshipSelect = document.getElementById('report-fellowship-filter');
    const serviceSelect = document.getElementById('report-service-filter');
    
    // Populate fellowships
    if (fellowshipSelect && this.fellowships && this.fellowships.length > 0) {
        fellowshipSelect.innerHTML = '<option value="">All Fellowships</option>';
        this.fellowships.forEach(fellowship => {
            const option = document.createElement('option');
            option.value = fellowship.id;
            option.textContent = fellowship.name;
            fellowshipSelect.appendChild(option);
        });
    } else {
        console.error('Fellowship select not found or no fellowships loaded');
    }
    
    // Populate services
    if (serviceSelect && this.services && this.services.length > 0) {
        serviceSelect.innerHTML = '<option value="">All Services</option>';
        this.services.forEach(service => {
            const option = document.createElement('option');
            option.value = service.id;
            option.textContent = service.name;
            serviceSelect.appendChild(option);
        });
    } else {
        console.error('Service select not found or no services loaded');
    }
}
 


displayReport(data, type) {
    // Clear previous charts
    if (this.trendChart) this.trendChart.destroy();
    if (this.demoChart) this.demoChart.destroy();

    // Show/hide charts
    document.getElementById('demographics-chart-container').style.display = 'none';

    if (type === 'overview') {
        this.createTrendsChart(data, document.getElementById('trendChart'));
        this.populateReportStats(data);
        this.populateReportTable(data.records || [], [
            'Date', 'Service', 'Fellowship', 'Present', 'Absent', 'Rate %'
        ], ['date', 'service_name', 'fellowship', 'present', 'absent', 'percentage']);
    }
    else if (type === 'demographics') {
        document.getElementById('demographics-chart-container').style.display = 'block';
        this.createDemographicsBarChart(data, document.getElementById('trendChart'));
        this.createDemographicsPieChart(data, document.getElementById('demographicsChart'));
        this.populateReportStats(data);
        this.populateReportTable(data.records || [], [
            'Age Group', 'Male', 'Female', 'Total', 'Attendance %'
        ], ['age_group', 'male', 'female', 'total', 'attendance_rate']);
    }
    else if (type === 'fellowship') {
        this.createFellowshipChart(data, document.getElementById('trendChart'));
        this.populateReportStats(data);
        this.populateReportTable(data.records || [], [
            'Fellowship', 'Sessions', 'Members', 'Present', 'Rate %'
        ], ['fellowship_name', 'total_sessions', 'unique_members', 'total_present', 'attendance_rate']);
    }
    else if (type === 'trends') {
        this.createTrendsChart(data, document.getElementById('trendChart'));
        this.populateReportStats(data);
    }
}

populateReportStats(data) {
    const statsContainer = document.getElementById('report-stats');
    if (!statsContainer) return;

    let html = '';

    if (data.total_sessions !== undefined) {
        html += `<div class="stat-card"><div class="stat-value">${data.total_sessions}</div><div class="stat-label">Total Sessions</div></div>`;
    }
    if (data.avg_attendance !== undefined) {
        html += `<div class="stat-card"><div class="stat-value">${data.avg_attendance}%</div><div class="stat-label">Avg Attendance</div></div>`;
    }
    if (data.total_present !== undefined) {
        html += `<div class="stat-card"><div class="stat-value">${data.total_present}</div><div class="stat-label">Total Present</div></div>`;
    }
    if (data.active_members !== undefined) {
        html += `<div class="stat-card"><div class="stat-value">${data.active_members}</div><div class="stat-label">Active Members</div></div>`;
    }

    statsContainer.innerHTML = html || '<p>No statistics available</p>';
}

async generateReport() {
    const reportType = document.getElementById('report-type').value;
    const startDate = document.getElementById('report-start-date').value;
    const endDate = document.getElementById('report-end-date').value;
    const fellowshipId = document.getElementById('report-fellowship-filter').value;
    const serviceId = document.getElementById('report-service-filter').value;
    const category = document.getElementById('report-category-filter').value;

    if (!startDate || !endDate) {
        this.showNotification('Please select a date range', 'error');
        return;
    }

    this.showLoading(true);

    try {
        await this.ensureChartJsLoaded();
    } catch (error) {
        this.showNotification('Failed to load charts library', 'error');
        this.showLoading(false);
        return;
    }

    try {
        const response = await fetch('../../backend/attendance/report.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json'
            },
            body: new URLSearchParams({
                action: 'generate',
                report_type: reportType,
                start_date: startDate,
                end_date: endDate,
                fellowship_id: fellowshipId || '',
                service_id: serviceId || '',
                category: category || ''
            })
        });

        // First check if response is OK
        if (!response.ok) {
            const text = await response.text();
            console.error('Server response not OK:', text);
            throw new Error(`Server error: ${response.status} ${response.statusText}`);
        }

        // Get response as text first to debug
        const responseText = await response.text();
        
        try {
            const result = JSON.parse(responseText);
            
            if (!result.success) {
                throw new Error(result.message || 'Failed to generate report');
            }

            // Render the data
            this.displayReport(result.data, reportType);
            
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response was:', responseText);
            throw new Error('Invalid response from server. Check server logs.');
        }

    } catch (error) {
        console.error('Report Error:', error);
        this.showNotification('Failed to generate report: ' + error.message, 'error');
        
        // Debug: Check if server is reachable
       
    } finally {
        this.showLoading(false);
    }
}



displayReport(data, type) {
    
    // 1. Clear previous charts properly
    this.clearCharts();
    
    // 2. Show/Hide demographics chart container
    const demoContainer = document.getElementById('demographics-chart-container');
    if (demoContainer) {
        demoContainer.style.display = type === 'demographics' ? 'block' : 'none';
    }
    
    // 3. Ensure chart containers are visible
    const chartContainers = document.querySelectorAll('.chart-container');
    chartContainers.forEach(container => {
        container.style.display = 'block';
        container.style.opacity = '1';
    });
    
    // 4. Render Stats
    this.populateReportStats(data);
    
    // 5. Render Charts with proper error handling
    setTimeout(() => {
        try {
            this.renderReportCharts(data, type);
        } catch (error) {
            console.error('Error rendering charts:', error);
            this.showNotification('Error rendering charts: ' + error.message, 'error');
        }
    }, 100);
    
    // 6. Render Table with proper headers
    let headers = [];
    let keys = [];
    let tableData = data.records || [];
    
    switch(type) {
        case 'overview':
            headers = ['Date', 'Service', 'Fellowship', 'Present', 'Absent', 'Rate %'];
            keys = ['date', 'service_name', 'fellowship', 'present', 'absent', 'percentage'];
            break;
        case 'demographics':
            headers = ['Age Group', 'Male', 'Female', 'Total', 'Attendance %'];
            keys = ['age_group', 'male', 'female', 'total', 'attendance_rate'];
            tableData = data.records || data.demographics_table || [];
            break;
        case 'fellowship':
            headers = ['Fellowship', 'Sessions', 'Members', 'Present', 'Rate %'];
            keys = ['fellowship_name', 'total_sessions', 'unique_members', 'total_present', 'attendance_rate'];
            tableData = data.records || data.fellowships || [];
            break;
        case 'trends':
            headers = ['Date', 'Attendance Rate %'];
            keys = ['date', 'percentage'];
            break;
    }
    
    // 7. Ensure we have tableData to display
    if (!tableData || tableData.length === 0) {
        console.warn('No table data available for report type:', type);
        if (type === 'demographics' && data.demographics) {
            // Convert demographics object to array for table
            tableData = Object.entries(data.demographics).map(([key, value]) => ({
                age_group: key,
                total: value
            }));
        }
    }
    
    this.populateReportTable(tableData, headers, keys);
}


// Add this helper method to clear charts properly
clearCharts() {
    // Destroy existing Chart instances
    if (this.trendChart && typeof this.trendChart.destroy === 'function') {
        this.trendChart.destroy();
        this.trendChart = null;
    }
    if (this.demoChart && typeof this.demoChart.destroy === 'function') {
        this.demoChart.destroy();
        this.demoChart = null;
    }
    
    // Clear canvas elements
    const trendCanvas = document.getElementById('trendChart');
    const demoCanvas = document.getElementById('demographicsChart');
    
    if (trendCanvas) {
        const ctx = trendCanvas.getContext('2d');
        ctx.clearRect(0, 0, trendCanvas.width, trendCanvas.height);
    }
    
    if (demoCanvas) {
        const ctx = demoCanvas.getContext('2d');
        ctx.clearRect(0, 0, demoCanvas.width, demoCanvas.height);
    }
}

async ensureChartJsLoaded() {
    if (typeof Chart !== 'undefined') {
        return true;
    }
    
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
        script.onload = () => {
            resolve();
        };
        script.onerror = () => {
            console.error('Failed to load Chart.js');
            reject(new Error('Chart.js failed to load'));
        };
        document.head.appendChild(script);
    });
}



renderReportCharts(data, reportType) {
    
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded!');
        this.showNotification('Charts library not loaded', 'error');
        return;
    }
    
    // Get canvas elements
    const trendCanvas = document.getElementById('trendChart');
    const demoCanvas = document.getElementById('demographicsChart');
    
    if (!trendCanvas) {
        console.error('Trend chart canvas not found!');
        return;
    }
    
    // Reset canvas display
    trendCanvas.style.display = 'block';
    
    // Create charts based on report type
    switch(reportType) {
        case 'overview':
            if (data.records && data.records.length > 0) {
                this.createOverviewChart(data, trendCanvas);
            } else {
                console.warn('No data for overview chart');
                this.showNoDataMessage(trendCanvas);
            }
            break;
            
        case 'demographics':
            // Bar chart for demographics
            if (data.records && data.records.length > 0) {
                this.createDemographicsBarChart(data, trendCanvas);
            } else {
                this.showNoDataMessage(trendCanvas);
            }
            
            // Pie chart for demographics distribution
            if (demoCanvas && data.demographics) {
                this.createDemographicsPieChart(data, demoCanvas);
            }
            break;
            
        case 'fellowship':
            if (data.fellowships && data.fellowships.length > 0) {
                this.createFellowshipChart(data, trendCanvas);
            } else {
                this.showNoDataMessage(trendCanvas);
            }
            break;
            
        case 'trends':
            if (data.records && data.records.length > 0) {
                this.createTrendsChart(data, trendCanvas);
            } else {
                this.showNoDataMessage(trendCanvas);
            }
            break;
            
        default:
            console.warn('Unknown report type:', reportType);
    }
}

populateReportTable(records, headers, keys) {
    const tableSection = document.getElementById('reports-table-section');
    const thead = document.getElementById('reports-table-head');
    const tbody = document.getElementById('reports-table-body');
    
    if (!tableSection || !thead || !tbody) {
        console.error('Report table elements not found');
        return;
    }
    
    // Always show table section
    tableSection.style.display = 'block';
    tableSection.style.opacity = '1';
    tableSection.style.visibility = 'visible';
    
    // Render Headers
    thead.innerHTML = `<tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr>`;
    
    // Render Body
    if (!records || records.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="${headers.length}" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    No records found for the selected criteria
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = records.map(record => {
        let rowHtml = '<tr>';
        keys.forEach(key => {
            let val = record[key] !== undefined ? record[key] : '—';
            
            // Format based on key
            if (key.includes('percentage') || key.includes('rate')) {
                val = `${val}%`;
            }
            if (key === 'present') {
                val = `<span style="color: var(--success); font-weight: 600;">${val}</span>`;
            }
            if (key === 'absent') {
                val = `<span style="color: var(--danger); font-weight: 600;">${val}</span>`;
            }
            
            rowHtml += `<td>${val}</td>`;
        });
        rowHtml += '</tr>';
        return rowHtml;
    }).join('');
    
}



renderReport(data, reportType) {
      
    // Update stats cards
    this.renderReportStats(data);
    
    // Make sure chart containers are visible
    const trendContainer = document.querySelector('#trendChart').parentElement;
    const demoContainer = document.querySelector('#demographicsChart').parentElement;
    
    if (trendContainer) trendContainer.style.display = 'block';
    if (demoContainer) {
        demoContainer.style.display = reportType === 'demographics' ? 'block' : 'none';
    }
    
    // Render charts
    this.renderReportCharts(data, reportType);
    
    // Render table
    this.renderReportTable(data, reportType);
}



// Render report stats
renderReportStats(data) {
    const statsContainer = document.getElementById('report-stats');
    if (!statsContainer) return;
    
    statsContainer.innerHTML = `
        <div class="stat-card">
            <div class="stat-value">${data.total_sessions || 0}</div>
            <div class="stat-label">Total Sessions</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">${data.average_attendance ? data.average_attendance.toFixed(1) : 0}%</div>
            <div class="stat-label">Avg Attendance</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">${data.total_present || 0}</div>
            <div class="stat-label">Total Present</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">${data.total_members || 0}</div>
            <div class="stat-label">Total Members</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">${data.most_attended_fellowship || 'N/A'}</div>
            <div class="stat-label">Top Fellowship</div>
        </div>
    `;
}

loadChartJsDynamically() {
    return new Promise((resolve, reject) => {
        // Check if already loaded
        if (typeof Chart !== 'undefined') {
            resolve();
            return;
        }
        
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
        script.onload = () => {
            resolve();
        };
        script.onerror = () => {
            console.error('Failed to load Chart.js dynamically');
            reject(new Error('Failed to load Chart.js'));
        };
        document.head.appendChild(script);
    });
}

renderReportTable(data, reportType) {
    const tableHead = document.getElementById('reports-table-head');
    const tableBody = document.getElementById('reports-table-body');
    const tableSection = document.getElementById('reports-table-section');
    
    if (!tableHead || !tableBody) return;
    
    // Show/hide table section
    tableSection.style.display = data.records && data.records.length > 0 ? 'block' : 'none';
    
    if (!data.records || data.records.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 2rem;">
                    No data available for the selected criteria
                </td>
            </tr>
        `;
        return;
    }
    
    // Set table headers based on report type
    let headers = [];
    switch(reportType) {
        case 'overview':
            headers = ['Date', 'Service', 'Fellowship', 'Present', 'Absent', 'Rate'];
            break;
        case 'demographics':
            headers = ['Age Group', 'Male', 'Female', 'Total', 'Percentage'];
            break;
        case 'fellowship':
            headers = ['Fellowship', 'Sessions', 'Total Present', 'Avg Attendance', 'Trend'];
            break;
        case 'trends':
            headers = ['Week/Month', 'Attendance Rate', 'Change', 'Peak Day', 'Lowest Day'];
            break;
    }
    
    // Render headers
    tableHead.innerHTML = `
        <tr>
            ${headers.map(header => `<th>${header}</th>`).join('')}
        </tr>
    `;
    
    // Helper function to format service name
    const formatServiceName = (serviceId, serviceName) => {
        if (serviceName && serviceName !== 'N/A') return serviceName;
        if (!serviceId) return 'General Service';
        
        // Format service ID to readable name
        const serviceMap = {
            'sunday_service': 'Sunday Service',
            'bible_study': 'Bible Study',
            'prayer_meeting': 'Prayer Meeting',
            'youth_fellowship': 'Youth Fellowship',
            'children_service': 'Children Service'
        };
        
        if (serviceMap[serviceId]) {
            return serviceMap[serviceId];
        }
        
        // Convert underscores to spaces and capitalize
        return serviceId.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    };
    
    // Render table rows with proper field mapping
    tableBody.innerHTML = data.records.map(record => {
        switch(reportType) {
            case 'overview':
                const serviceName = formatServiceName(record.service_id, record.service_name || record.service_type);
                const fellowshipName = record.fellowship_name || record.fellowship || '—';
                
                return `
                    <tr>
                        <td>${this.formatDate(record.date)}</td>
                        <td><span class="badge badge-info">${serviceName}</span></td>
                        <td>${fellowshipName}</td>
                        <td style="color: var(--success); font-weight: 600;">${record.present || 0}</td>
                        <td style="color: var(--danger); font-weight: 600;">${record.absent || 0}</td>
                        <td>
                            <div class="progress-ring">
                                <div class="progress-ring-circle">
                                    <svg width="48" height="48">
                                        <circle class="progress-bg" cx="24" cy="24" r="20" />
                                        <circle cx="24" cy="24" r="20"
                                            stroke="${parseFloat(record.percentage) >= 80 ? 'var(--success)' : parseFloat(record.percentage) >= 60 ? 'var(--warning)' : 'var(--danger)'}"
                                            stroke-dasharray="125.6"
                                            stroke-dashoffset="${125.6 - (parseFloat(record.percentage) / 100 * 125.6)}" />
                                    </svg>
                                    <div class="progress-ring-text">${record.percentage || 0}%</div>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            case 'demographics':
                return `
                    <tr>
                        <td>${record.age_group ? this.capitalizeFirst(record.age_group) : '—'}</td>
                        <td>${record.male || 0}</td>
                        <td>${record.female || 0}</td>
                        <td>${record.total || 0}</td>
                        <td>${record.percentage || 0}%</td>
                    </tr>
                `;
            case 'fellowship':
                const trendClass = record.trend === 'up' ? 'stat-trend up' : 'stat-trend down';
                const trendIcon = record.trend === 'up' ? '↑' : '↓';
                
                return `
                    <tr>
                        <td>${record.fellowship_name || '—'}</td>
                        <td>${record.total_sessions || 0}</td>
                        <td>${record.total_present || 0}</td>
                        <td>${record.average_attendance || 0}%</td>
                        <td>
                            <span class="${trendClass}">
                                ${trendIcon} ${record.change || 0}%
                            </span>
                        </td>
                    </tr>
                `;
            default:
                return '';
        }
    }).join('');
}

// Add helper function for capitalizing
capitalizeFirst(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}

showReportsTab() {
    
    // Hide ALL page-content divs except reports
    document.querySelectorAll('.page-content').forEach(tab => {
        tab.style.display = 'none';
        tab.classList.remove('active');
    });
    
    // Show reports tab
    const reportsTab = document.getElementById('reports-tab');
    if (reportsTab) {
        reportsTab.style.display = 'block';
        reportsTab.classList.add('active');
        
        
        // Ensure filters are populated
        setTimeout(() => {
            this.populateReportFilters();
            
            // Set default date range if empty
            const startInput = document.getElementById('report-start-date');
            const endInput = document.getElementById('report-end-date');
            
            if (startInput && !startInput.value) {
                const startDate = new Date();
                startDate.setDate(startDate.getDate() - 30);
                startInput.value = startDate.toISOString().split('T')[0];
            }
            
            if (endInput && !endInput.value) {
                endInput.value = new Date().toISOString().split('T')[0];
            }
            
            // Force charts to re-render after tab is visible
            setTimeout(() => {
                if (typeof Chart !== 'undefined') {
                    // Auto-generate report if data exists
                    const generateBtn = document.getElementById('generate-report');
                    if (generateBtn) {
                        generateBtn.click();
                    }
                } else {
                    this.loadChartJsDynamically();
                }
            }, 500);
        }, 100);
    }
}

showMainTab() {
    const reportsTab = document.getElementById('reports-tab');
    if (reportsTab) {
        reportsTab.style.display = 'none';
        reportsTab.classList.remove('active');
    }
    document.querySelectorAll('.page-content').forEach(pc => {
        if (pc.id !== 'reports-tab') pc.style.display = 'block';
    });
    document.querySelector('.table-section')?.style.removeProperty('display');
}

// ADD this method to check Chart.js availability
checkChartJsLoaded() {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded!');
        // Try to load it dynamically
        this.loadChartJsDynamically();
        return false;
    }
    return true;
}

loadChartJsDynamically() {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
    script.onload = () => {
        // Regenerate report if needed
        const generateBtn = document.getElementById('generate-report');
        if (generateBtn) {
            setTimeout(() => generateBtn.click(), 500);
        }
    };
    script.onerror = () => {
        console.error('Failed to load Chart.js dynamically');
    };
    document.head.appendChild(script);
}

showMainTab() {
    // Hide reports tab
    document.getElementById('reports-tab').style.display = 'none';
    document.getElementById('reports-tab').classList.remove('active');
    
    // Show main tab
    const mainTab = document.querySelector('.page-content:not(#reports-tab)');
    mainTab.style.display = 'block';
    mainTab.classList.add('active');
}

// Add this method to initialize charts (optional)
initReportCharts() {
    // Initialize Chart.js charts here if you're using them
    // You'll need to include Chart.js in your HTML first
}

// Export report as CSV
exportReportCSV() {
    const reportType = document.getElementById('report-type').value;
    const startDate = document.getElementById('report-start-date').value;
    const endDate = document.getElementById('report-end-date').value;
    
    const rows = [
        ['Attendance Report', reportType.charAt(0).toUpperCase() + reportType.slice(1)],
        ['Date Range', `${startDate} to ${endDate}`],
        ['Generated', new Date().toLocaleString()],
        []
    ];
    
    // Add table data if available
    const tableRows = document.querySelectorAll('#reports-table tr');
    tableRows.forEach(row => {
        const rowData = [];
        row.querySelectorAll('td, th').forEach(cell => {
            rowData.push(cell.textContent.trim());
        });
        rows.push(rowData);
    });
    
    const csv = rows.map(row => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `Attendance_Report_${reportType}_${startDate}_to_${endDate}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}


// Add these methods to AttendanceManager class


// Replace these methods in attendance.js

// 1. FIX: Populate Report Filters (Fellowship & Services)
populateReportFilters() {
    
    const fellowshipSelect = document.getElementById('report-fellowship-filter');
    const serviceSelect = document.getElementById('report-service-filter');
    
    if (!fellowshipSelect || !serviceSelect) {
        return false;
    }
    
    // Populate fellowships
    if (this.fellowships && this.fellowships.length > 0) {
        fellowshipSelect.innerHTML = '<option value="">All Fellowships</option>';
        this.fellowships.forEach(fellowship => {
            const option = document.createElement('option');
            option.value = fellowship.id;
            option.textContent = fellowship.name;
            fellowshipSelect.appendChild(option);
        });
    }
    
    // Populate services
    if (this.services && this.services.length > 0) {
        serviceSelect.innerHTML = '<option value="">All Services</option>';
        this.services.forEach(service => {
            const option = document.createElement('option');
            option.value = service.id;
            option.textContent = service.name;
            serviceSelect.appendChild(option);
        });
    }
    
    return true;
}
// Helper function to show "no data" message
showNoDataMessage(canvasElement) {
    if (!canvasElement || !canvasElement.parentElement) return;
    
    const container = canvasElement.parentElement;
    container.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: center; height: 300px; color: var(--text-muted);">
            <div style="text-align: center;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <path d="M9 17h6m-6-4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/>
                </svg>
                <p style="margin-top: 1rem;">No chart data available</p>
            </div>
        </div>
    `;
}


// 4. FIXED: Demographics Bar Chart
createDemographicsBarChart(data, canvas) {
    try {
        
        if (!data.demographics_table || data.demographics_table.length === 0) {
            this.showNoDataMessage(canvas);
            return;
        }
        
        const labels = data.demographics_table.map(r => {
            const ageGroup = r.age_group || 'Unknown';
            return ageGroup.charAt(0).toUpperCase() + ageGroup.slice(1);
        });
        const totals = data.demographics_table.map(r => parseInt(r.total) || 0);
        const rates = data.demographics_table.map(r => parseFloat(r.attendance_rate) || 0);
        
        
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            console.error('Cannot get 2D context from canvas');
            return;
        }
        
        this.trendChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Members',
                        data: totals,
                        backgroundColor: '#4f46e5',
                        borderColor: '#4338ca',
                        borderWidth: 1,
                        yAxisID: 'y',
                        borderRadius: 4
                    },
                    {
                        label: 'Attendance Rate %',
                        data: rates,
                        backgroundColor: '#10b981',
                        borderColor: '#059669',
                        borderWidth: 2,
                        type: 'line',
                        yAxisID: 'y1',
                        pointRadius: 4,
                        pointBackgroundColor: '#10b981'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Member Demographics & Attendance',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return `Members: ${context.raw}`;
                                } else {
                                    return `Attendance: ${context.raw}%`;
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Total Members',
                            font: {
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Attendance Rate %',
                            font: {
                                weight: 'bold'
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
        
        
    } catch (error) {
        console.error('Error creating demographics bar chart:', error);
        this.showNoDataMessage(canvas);
    }
}

// 5. FIXED: Demographics Pie Chart
createDemographicsPieChart(data, canvas) {
    try {
        
        if (!data.demographics || Object.keys(data.demographics).length === 0) {
            console.warn('No demographics data for pie chart');
            return;
        }
        
        const labels = Object.keys(data.demographics)
            .filter(key => data.demographics[key] > 0)
            .map(key => key.charAt(0).toUpperCase() + key.slice(1));
        
        const values = Object.values(data.demographics)
            .filter((val, index) => data.demographics[Object.keys(data.demographics)[index]] > 0);
        
        if (labels.length === 0 || values.length === 0) {
            console.warn('No valid data for pie chart');
            return;
        }
        
        
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            console.error('Cannot get 2D context from canvas');
            return;
        }
        
        this.demoChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: [
                        '#4f46e5',  // Adults - Indigo
                        '#10b981',  // Teens - Emerald
                        '#f59e0b',  // Children - Amber
                        '#ef4444',  // Unknown - Red
                        '#8b5cf6',  // Extra - Violet
                        '#0ea5e9',  // Extra - Sky
                        '#ec4899'   // Extra - Pink
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Demographics Distribution',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} members (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        
    } catch (error) {
        console.error('Error creating demographics pie chart:', error);
    }
}

// 6. FIXED: Fellowship Chart
createFellowshipChart(data, canvas) {
    try {
        
        if (!data.fellowships || data.fellowships.length === 0) {
            console.warn('No fellowship data available');
            this.showNoDataMessage(canvas);
            return;
        }
        
        const labels = data.fellowships.map(f => f.fellowship_name || 'Unknown');
        const rates = data.fellowships.map(f => parseFloat(f.attendance_rate) || 0);
        const counts = data.fellowships.map(f => parseInt(f.total_present) || 0);
        
        
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            console.error('Cannot get 2D context from canvas');
            return;
        }
        
        this.trendChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Attendance Rate %',
                        data: rates,
                        backgroundColor: '#4f46e5',
                        borderColor: '#4338ca',
                        borderWidth: 1,
                        yAxisID: 'y1',
                        borderRadius: 4,
                        order: 2
                    },
                    {
                        label: 'Total Present',
                        data: counts,
                        backgroundColor: '#10b981',
                        borderColor: '#059669',
                        borderWidth: 1,
                        yAxisID: 'y',
                        borderRadius: 4,
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Fellowship Comparison',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return `Attendance: ${context.raw}%`;
                                } else {
                                    return `Present: ${context.raw}`;
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Total Present',
                            font: {
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Attendance Rate %',
                            font: {
                                weight: 'bold'
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
        
        
    } catch (error) {
        console.error('Error creating fellowship chart:', error);
        this.showNoDataMessage(canvas);
    }
}

// 7. FIXED: Trends Chart (with fallback if data structure is different)
createTrendsChart(data, canvas) {
    try {
        
        // Check different possible data structures
        let dates = data.dates;
        let percentages = data.percentages;
        
        // If no specific trends data, try to use records data
        if ((!dates || !percentages) && data.records && data.records.length > 0) {
            const sortedRecords = [...data.records].sort((a, b) => new Date(a.date) - new Date(b.date));
            dates = sortedRecords.map(r => {
                try {
                    const date = new Date(r.date);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                } catch (e) {
                    return r.date;
                }
            });
            percentages = sortedRecords.map(r => parseFloat(r.percentage) || 0);
        }
        
        if (!dates || dates.length === 0 || !percentages || percentages.length === 0) {
            console.warn('No trend data available');
            this.showNoDataMessage(canvas);
            return;
        }
        
        
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            console.error('Cannot get 2D context from canvas');
            return;
        }
        
        this.trendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Attendance Rate %',
                    data: percentages,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#10b981'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Attendance Trends',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Attendance: ${context.raw}%`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    }
                }
            }
        });
        
        
    } catch (error) {
        console.error('Error creating trends chart:', error);
        this.showNoDataMessage(canvas);
    }
}

}

// Initialize
let attendanceManager;
document.addEventListener('DOMContentLoaded', () => {
    attendanceManager = new AttendanceManager();
});