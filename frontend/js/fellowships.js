class FellowshipsManager {
    constructor() {
        this.fellowships = [];
        this.members = [];
        this.meetingSchedules = [];
        this.currentPage = 1;
        this.perPage = 10;
        this.apiBase = '/church/backend';

        this.init();
    }

    async init() {
        try {
            await this.loadData();
            this.bindEvents();
            this.render();
        } catch (error) {
            console.error('Failed to initialize fellowships:', error);
            this.showNotification('Failed to load fellowships', 'error');
        }
    }

async loadData() {
    try {
        const membersRes = await fetch(`${this.apiBase}/members/read.php`);
        let membersData = [];
        if (membersRes.ok) {
            const json = await membersRes.json();
            membersData = Array.isArray(json) ? json : (json.data || json.members || []);
        }

        const fellowshipsRes = await fetch(`${this.apiBase}/fellowships/read.php`);
        let fellowshipsData = [];
        if (fellowshipsRes.ok) {
            const json = await fellowshipsRes.json();
            fellowshipsData = Array.isArray(json) ? json : (json.data || json.fellowships || []);
        }

        this.members = membersData;
        this.fellowships = fellowshipsData;

        this.populateSelects();
    } catch (error) {
        console.error('Error loading data:', error);
        this.showNotification('Error loading data from server', 'error');
        this.members = [];
        this.fellowships = [];
    }
}

    bindEvents() {
        const createBtn = document.getElementById('create-fellowship-btn');
        const searchInput = document.getElementById('fellowship-search');
        const exportBtn = document.getElementById('export-fellowships');
        const cancelBtn = document.getElementById('cancel-fellowship');
        const closeBtn = document.querySelector('#fellowship-modal .close');
        const form = document.getElementById('fellowship-form');

        if (createBtn) createBtn.onclick = () => this.openModal();
        if (searchInput) searchInput.oninput = () => this.render();
        if (exportBtn) exportBtn.onclick = () => this.exportList();
        if (cancelBtn) cancelBtn.onclick = () => this.closeModal();
        if (closeBtn) closeBtn.onclick = () => this.closeModal();
        if (form) form.onsubmit = (e) => this.save(e);

        const modal = document.getElementById('fellowship-modal');
        if (modal) {
            window.onclick = (event) => {
                if (event.target === modal) {
                    this.closeModal();
                }
            };
        }
    }

    render() {
        this.renderStats();
        this.renderGrid();
        this.renderTable();
    }

    renderStats() {
        const total = this.fellowships.length;
        const active = this.fellowships.filter(f => f.status === 'active').length;
        const members = this.fellowships.reduce((a, f) => a + (f.actual_member_count || f.members_count || 0), 0);

        const totalEl = document.getElementById('total-fellowships');
        const activeEl = document.getElementById('active-fellowships');
        const membersEl = document.getElementById('total-members');

        if (totalEl) totalEl.textContent = total;
        if (activeEl) activeEl.textContent = active;
        if (membersEl) membersEl.textContent = members;
    }

    renderGrid() {
        const search = document.getElementById('fellowship-search').value.toLowerCase();
        const filtered = this.fellowships.filter(f => 
            f.name.toLowerCase().includes(search) || 
            (f.leader_name || '').toLowerCase().includes(search) ||
            (f.location || '').toLowerCase().includes(search)
        );

        const container = document.getElementById('fellowships-grid');
        if (!container) return;

        container.innerHTML = filtered.map(f => {
            const primarySchedule = f.meeting_schedules && f.meeting_schedules.length > 0 
                ? f.meeting_schedules.find(s => s.isPrimary) || f.meeting_schedules[0]
                : { day: f.meeting_day, time: f.meeting_time };
            
            const scheduleText = primarySchedule ? 
                `${this.formatDay(primarySchedule.day)} @ ${primarySchedule.time}` : 
                'No schedule';

            return `
                <div class="fellowship-card" onclick="fellowships.openModal(${f.id})">
                    <div class="card-header">
                        <h3>${this.escapeHtml(f.name)}</h3>
                        <div class="members-badge">${f.actual_member_count || f.members_count || 0} members</div>
                    </div>
                    <div class="card-body">
                        <div class="leader-info">
                            <div class="leader-avatar">${this.getInitials(f.leader_name)}</div>
                            <div>
                                <strong>${this.escapeHtml(f.leader_name || 'No Leader')}</strong>
                                ${f.assistant_name ? `<br><small>Assistant: ${this.escapeHtml(f.assistant_name)}</small>` : ''}
                            </div>
                        </div>
                        <div class="details-grid">
                            <div class="detail-item"><i class="fas fa-calendar"></i> ${scheduleText}</div>
                            <div class="detail-item"><i class="fas fa-clock"></i> ${f.meeting_schedules ? f.meeting_schedules.length + ' schedules' : '1 schedule'}</div>
                            <div class="detail-item"><i class="fas fa-map-marker-alt"></i> ${this.escapeHtml(f.location || 'N/A')}</div>
                            <div class="detail-item"><i class="fas fa-circle"></i> <span class="status-badge status-${f.status}">${this.formatStatus(f.status)}</span></div>
                        </div>
                        ${f.description ? `<p style="margin: 1rem 0; color: var(--text-muted); font-size: 0.9rem;">${this.escapeHtml(f.description)}</p>` : ''}
                        <div class="card-actions">
                            <button class="btn btn-edit btn-sm" onclick="event.stopPropagation(); fellowships.openModal(${f.id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-delete btn-sm" onclick="event.stopPropagation(); fellowships.delete(${f.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    renderTable() {
        const tbody = document.getElementById('fellowships-table-body');
        if (!tbody) return;

        tbody.innerHTML = this.fellowships.map(f => `
            <tr>
                <td><strong>${this.escapeHtml(f.name)}</strong><br><small>${this.escapeHtml(f.description || '')}</small></td>
                <td>${this.escapeHtml(f.leader_name || 'N/A')}</td>
                <td><strong>${f.actual_member_count || f.members_count || 0}</strong></td>
                <td>${this.formatDay(f.meeting_day)} @ ${f.meeting_time}</td>
                <td>${this.escapeHtml(f.location || 'N/A')}</td>
                <td><span class="status-badge status-${f.status}">${this.formatStatus(f.status)}</span></td>
                <td>
                    <button class="btn btn-edit btn-sm" onclick="fellowships.openModal(${f.id})">Edit</button>
                    <button class="btn btn-delete btn-sm" onclick="fellowships.delete(${f.id})">Delete</button>
                </td>
            </tr>
        `).join('');
    }

    formatDay(day) {
        const days = { 
            sunday: 'Sunday', monday: 'Monday', tuesday: 'Tuesday', 
            wednesday: 'Wednesday', thursday: 'Thursday', 
            friday: 'Friday', saturday: 'Saturday' 
        };
        return days[day] || day;
    }

    formatStatus(status) {
        const statuses = { active: 'Active', inactive: 'Inactive', pending: 'Pending' };
        return statuses[status] || status;
    }

    getInitials(name) {
        if (!name) return '?';
        return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    populateSelects() {
        this.updateLeaderSelects();
    }

    // NEW: Update leader and assistant selects with search functionality
    updateLeaderSelects() {
        const leaderSelect = document.getElementById('fellowship-leader');
        const assistantSelect = document.getElementById('fellowship-assistant');

        if (leaderSelect) {
            leaderSelect.innerHTML = '<option value="">Select Leader</option>';
            this.members.forEach(m => {
                const option = document.createElement('option');
                option.value = m.id;
                option.textContent = `${m.first_name} ${m.last_name}`;
                leaderSelect.appendChild(option);
            });

            // Add change event to prevent same person
            leaderSelect.onchange = () => this.validateLeaderSelection();
        }

        if (assistantSelect) {
            assistantSelect.innerHTML = '<option value="">Select Assistant (Optional)</option>';
            this.members.forEach(m => {
                const option = document.createElement('option');
                option.value = m.id;
                option.textContent = `${m.first_name} ${m.last_name}`;
                assistantSelect.appendChild(option);
            });

            // Add change event to prevent same person
            assistantSelect.onchange = () => this.validateLeaderSelection();
        }
    }

    // NEW: Validate that leader and assistant are different
    validateLeaderSelection() {
        const leaderId = document.getElementById('fellowship-leader').value;
        const assistantId = document.getElementById('fellowship-assistant').value;

        if (leaderId && assistantId && leaderId === assistantId) {
            this.showNotification('Leader and Assistant cannot be the same person', 'error');
            document.getElementById('fellowship-assistant').value = '';
            return false;
        }
        return true;
    }

    openModal(id = null) {
        const modal = document.getElementById('fellowship-modal');
        const titleSpan = document.querySelector('#modal-title span');
        const form = document.getElementById('fellowship-form');

        if (!modal) return;

        if (titleSpan) titleSpan.textContent = id ? 'Edit Fellowship' : 'Create New Fellowship';
        
        this.meetingSchedules = [];
        
        if (id) {
            const f = this.fellowships.find(x => x.id === id);
            if (f) {
                document.getElementById('fellowship-id').value = f.id;
                document.getElementById('fellowship-name').value = f.name;
                document.getElementById('fellowship-leader').value = f.leader_id || '';
                document.getElementById('fellowship-assistant').value = f.assistant_leader_id || '';
                document.getElementById('meeting-location').value = f.location || '';
                document.getElementById('fellowship-status').value = f.status || 'active';
                document.getElementById('fellowship-description').value = f.description || '';
                
                if (f.meeting_schedules && Array.isArray(f.meeting_schedules)) {
                    f.meeting_schedules.forEach((schedule, index) => {
                        this.addMeetingSchedule(schedule.day, schedule.time, schedule.isPrimary || index === 0);
                    });
                } else {
                    this.addMeetingSchedule(f.meeting_day || '', f.meeting_time || '', true);
                }
            }
        } else {
            if (form) form.reset();
            document.getElementById('fellowship-id').value = '';
            this.addMeetingSchedule('', '', true);
        }

        modal.style.display = 'block';
    }

    closeModal() {
        const modal = document.getElementById('fellowship-modal');
        if (modal) modal.style.display = 'none';
    }

    async save(e) {
        e.preventDefault();

        try {
            // Validate leader and assistant are different
            if (!this.validateLeaderSelection()) {
                return;
            }

            // Validate meeting schedules
            if (this.meetingSchedules.length === 0) {
                this.showNotification('At least one meeting schedule is required', 'error');
                return;
            }

            // Check for duplicate days
            const days = [];
            for (let i = 0; i < this.meetingSchedules.length; i++) {
                const daySelect = document.querySelectorAll('.meeting-day')[i];
                const timeInput = document.querySelectorAll('.meeting-time')[i];
                
                if (!daySelect.value || !timeInput.value) {
                    this.showNotification('Please fill in all meeting schedule fields', 'error');
                    return;
                }

                if (days.includes(daySelect.value)) {
                    this.showNotification(`Duplicate day detected: ${this.formatDay(daySelect.value)}. Each day can only be selected once.`, 'error');
                    return;
                }
                
                days.push(daySelect.value);
                this.meetingSchedules[i].day = daySelect.value;
                this.meetingSchedules[i].time = timeInput.value;
            }

            const token = this.getCsrfToken();

            const formData = {
                name: document.getElementById('fellowship-name').value,
                leader_id: document.getElementById('fellowship-leader').value,
                assistant_leader_id: document.getElementById('fellowship-assistant').value || null,
                meeting_schedules: this.meetingSchedules,
                location: document.getElementById('meeting-location').value,
                status: document.getElementById('fellowship-status').value,
                description: document.getElementById('fellowship-description').value,
                csrf_token: token
            };

            const id = document.getElementById('fellowship-id').value;
            const method = id ? 'PUT' : 'POST';
            const url = id 
                ? `${this.apiBase}/fellowships/update.php`
                : `${this.apiBase}/fellowships/create.php`;

            if (id) {
                formData.id = id;
            }

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': token
                },
                credentials: 'include',
                body: JSON.stringify(formData)
            });

            const data = await response.json();

            if (response.ok) {
                this.showNotification(data.message || 'Fellowship saved successfully', 'success');
                this.closeModal();
                await this.loadData();
                this.render();
            } else {
                if (response.status === 403) {
                    this.showNotification('CSRF token invalid. Please refresh the page and try again.', 'error');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    this.showNotification(data.message || 'Failed to save fellowship', 'error');
                }
            }
        } catch (error) {
            console.error('Error saving fellowship:', error);
            this.showNotification('Error saving fellowship', 'error');
        }
    }

    async delete(id) {
        if (!confirm('Are you sure you want to delete this fellowship?')) {
            return;
        }

        try {
            const token = this.getCsrfToken();

            const response = await fetch(`${this.apiBase}/fellowships/delete.php`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': token
                },
                body: JSON.stringify({ id: id })
            });

            const data = await response.json();

            if (response.ok) {
                this.showNotification('Fellowship deleted successfully', 'success');
                await this.loadData();
                this.render();
            } else {
                this.showNotification(data.message || 'Failed to delete fellowship', 'error');
            }
        } catch (error) {
            console.error('Error deleting fellowship:', error);
            this.showNotification('Error deleting fellowship', 'error');
        }
    }

    getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            return metaTag.getAttribute('content') || '';
        }
        
        const getCookie = (name) => {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
            return null;
        };
        
        return getCookie('csrf_token') || '';
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background-color: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 9999;
            animation: slideIn 0.3s ease-out;
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    exportList() {
        try {
            let csv = 'Fellowship Name,Leader,Assistant,Meeting Day,Time,Location,Status,Members\n';
            
            this.fellowships.forEach(f => {
                csv += `"${f.name}","${f.leader_name || 'N/A'}","${f.assistant_name || ''}","${this.formatDay(f.meeting_day)}","${f.meeting_time}","${f.location}","${f.status}","${f.actual_member_count || f.members_count || 0}"\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `fellowships-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);

            this.showNotification('Fellowships exported successfully', 'success');
        } catch (error) {
            console.error('Error exporting:', error);
            this.showNotification('Error exporting fellowships', 'error');
        }
    }

    addMeetingSchedule(day = '', time = '', isPrimary = false) {
        this.meetingSchedules.push({ day, time, isPrimary });
        this.renderMeetingSchedules();
    }

    removeMeetingSchedule(index) {
        if (this.meetingSchedules.length <= 1) {
            this.showNotification('At least one meeting schedule is required', 'error');
            return;
        }
        
        const removed = this.meetingSchedules.splice(index, 1)[0];
        
        if (removed.isPrimary && this.meetingSchedules.length > 0) {
            this.meetingSchedules[0].isPrimary = true;
        }
        
        this.renderMeetingSchedules();
    }

    setPrimarySchedule(index) {
        this.meetingSchedules.forEach((schedule, i) => {
            schedule.isPrimary = i === index;
        });
        this.renderMeetingSchedules();
    }

    // NEW: Get already selected days to disable them
    getSelectedDays() {
        return this.meetingSchedules.map(s => s.day).filter(d => d);
    }

    renderMeetingSchedules() {
        const container = document.getElementById('meeting-schedules-container');
        if (!container) return;

        const selectedDays = this.getSelectedDays();

        container.innerHTML = this.meetingSchedules.map((schedule, index) => {
            const otherSelectedDays = selectedDays.filter((d, i) => {
                const allDays = [];
                this.meetingSchedules.forEach((s, idx) => {
                    if (idx !== index) allDays.push(s.day);
                });
                return allDays.includes(d);
            });

            return `
                <div class="meeting-schedule-item ${schedule.isPrimary ? 'primary' : ''}">
                    ${schedule.isPrimary ? '<div class="primary-badge">Primary Meeting</div>' : ''}
                    <div class="schedule-inputs">
                        <div class="form-group">
                            <label>Day</label>
                            <select class="meeting-day" data-index="${index}" required>
                                <option value="">Select Day</option>
                                ${['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'].map(day => {
                                    const isSelected = schedule.day === day;
                                    const isDisabled = otherSelectedDays.includes(day) && !isSelected;
                                    return `<option value="${day}" ${isSelected ? 'selected' : ''} ${isDisabled ? 'disabled' : ''}>
                                        ${this.formatDay(day)} ${isDisabled ? '(Already selected)' : ''}
                                    </option>`;
                                }).join('')}
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Time</label>
                            <input type="time" class="meeting-time" data-index="${index}" value="${schedule.time}" required>
                        </div>
                        <div class="meeting-actions">
                            <button type="button" class="remove-meeting" 
                                title="Remove this schedule"
                                ${this.meetingSchedules.length <= 1 ? 'disabled' : ''}
                                onclick="fellowships.removeMeetingSchedule(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                            ${!schedule.isPrimary ? `
                                <button type="button" class="set-primary-btn" 
                                    title="Set as primary meeting"
                                    onclick="fellowships.setPrimarySchedule(${index})">
                                    <i class="fas fa-star"></i> Primary
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        // Add event listeners to update meetingSchedules when inputs change
        container.querySelectorAll('.meeting-day').forEach(select => {
            select.addEventListener('change', (e) => {
                const index = parseInt(e.target.dataset.index);
                this.meetingSchedules[index].day = e.target.value;
                this.renderMeetingSchedules(); // Re-render to update disabled options
            });
        });

        container.querySelectorAll('.meeting-time').forEach(input => {
            input.addEventListener('change', (e) => {
                const index = parseInt(e.target.dataset.index);
                this.meetingSchedules[index].time = e.target.value;
            });
        });
    }
}

const fellowships = new FellowshipsManager();