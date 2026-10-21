// frontend/js/cells.js
class CellManager {
    constructor() {
        this.cells = [];
        this.filteredCells = [];
        this.cellMembers = [];
        this.filteredMembers = [];
        this.fellowships = [];
        this.members = [];
        this.sortColumn = 'name';
        this.sortDirection = 'asc';
        this.membersSortColumn = 'name';
        this.membersSortDirection = 'asc';
        this.filters = {
            search: '',
            fellowship: '',
            day: ''
        };
        this.membersFilters = {
            search: '',
            cell: '',
            status: ''
        };
        this.currentTab = 'cells-tab';
        this.init();
    }

    async init() {
        await this.loadDependencies();
        await this.loadCells();
        this.setupEventListeners();
        this.setupTabNavigation();
    }

    async loadDependencies() {
        try {
            // Load fellowships
            const fRes = await fetch('../../backend/attendance/read.php?action=fellowships');
            const fData = await fRes.json();
            if (fData.success) {
                this.fellowships = fData.data;
                this.populateDropdown('cell-fellowship', this.fellowships);
                this.populateFellowshipFilter();
            }

            // Load all members for leader selection
            const mData = [];
            for (const f of this.fellowships) {
                const mRes = await fetch(`../../backend/attendance/read.php?action=members&fellowship_id=${f.id}`);
                const mJson = await mRes.json();
                if (mJson.success) {
                    mData.push(...mJson.data);
                }
            }
            this.members = mData;
            this.populateDropdown('cell-leader', this.members);

        } catch (error) {
            console.error('Error loading dependencies:', error);
        }
    }

    populateDropdown(id, data) {
        const select = document.getElementById(id);
        const placeholder = select.options[0];
        select.innerHTML = '';
        select.appendChild(placeholder);
        data.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.name;
            select.appendChild(opt);
        });
    }

    populateFellowshipFilter() {
        const select = document.getElementById('fellowship-filter');
        const placeholder = select.options[0];
        select.innerHTML = '';
        select.appendChild(placeholder);
        this.fellowships.forEach(f => {
            const opt = document.createElement('option');
            opt.value = f.id;
            opt.textContent = f.name;
            select.appendChild(opt);
        });
    }

    setupEventListeners() {
        document.getElementById('create-cell-btn').addEventListener('click', () => this.openModal());
        document.querySelectorAll('.close, .close-modal').forEach(btn => {
            btn.addEventListener('click', () => this.closeModal());
        });
        document.getElementById('cell-form').addEventListener('submit', (e) => this.saveCell(e));
        
        const searchInput = document.getElementById('search-input');
        searchInput.addEventListener('input', (e) => {
            this.filters.search = e.target.value;
            this.applyFilters();
        });
        
        const fellowshipFilter = document.getElementById('fellowship-filter');
        fellowshipFilter.addEventListener('change', (e) => {
            this.filters.fellowship = e.target.value;
            this.applyFilters();
        });
        
        const dayFilter = document.getElementById('day-filter');
        dayFilter.addEventListener('change', (e) => {
            this.filters.day = e.target.value;
            this.applyFilters();
        });
        
        document.querySelectorAll('[data-sort]').forEach(header => {
            header.addEventListener('click', () => this.handleSort(header.dataset.sort));
        });
        
        document.getElementById('export-btn').addEventListener('click', () => this.exportData());
        
        document.getElementById('cell-modal').addEventListener('click', (e) => {
            if (e.target.id === 'cell-modal') {
                this.closeModal();
            }
        });
        
        // Members tab event listeners
        const membersSearchInput = document.getElementById('members-search-input');
        membersSearchInput.addEventListener('input', (e) => {
            this.membersFilters.search = e.target.value;
            this.applyMembersFilters();
        });
        
        const membersCellFilter = document.getElementById('members-cell-filter');
        membersCellFilter.addEventListener('change', (e) => {
            this.membersFilters.cell = e.target.value;
            this.applyMembersFilters();
        });
        
        const membersStatusFilter = document.getElementById('members-status-filter');
        membersStatusFilter.addEventListener('change', (e) => {
            this.membersFilters.status = e.target.value;
            this.applyMembersFilters();
        });
        
        document.querySelectorAll('[data-sort-members]').forEach(header => {
            header.addEventListener('click', () => this.handleMembersSort(header.dataset.sortMembers));
        });
        
        document.getElementById('export-members-btn').addEventListener('click', () => this.exportMembersData());
    }

    setupTabNavigation() {
        const tabBtns = document.querySelectorAll('.tab-btn');
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetTab = btn.dataset.tab;
                this.switchTab(targetTab);
            });
        });
    }

    switchTab(tabId) {
        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.tab === tabId) {
                btn.classList.add('active');
            }
        });
        
        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
            content.style.display = 'none';
        });
        
        const targetContent = document.getElementById(tabId);
        if (targetContent) {
            targetContent.classList.add('active');
            targetContent.style.display = 'block';
        }
        
        this.currentTab = tabId;
        
        // Load members data when switching to members tab
        if (tabId === 'members-tab' && this.cellMembers.length === 0) {
            this.loadCellMembers();
        }
    }

    async loadCellMembers() {
        try {
            const response = await fetch('../../backend/ajax/cell_members.php');
            const result = await response.json();
            
            if (result.success) {
                this.cellMembers = result.data;
                this.filteredMembers = [...this.cellMembers];
                this.updateMembersStats();
                this.populateMembersCellFilter();
                this.renderMembersTable();
            } else {
                this.showMembersEmptyState();
            }
        } catch (error) {
            console.error('Error loading cell members:', error);
            this.showNotification('Failed to load cell members', 'error');
        }
    }

    updateMembersStats() {
        const totalMembers = this.cellMembers.length;
        const activeMembers = this.cellMembers.filter(m => m.status === 'active').length;
        const cellsWithMembers = new Set(this.cellMembers.map(m => m.cell_id)).size;
        
        document.getElementById('total-cell-members').textContent = totalMembers;
        document.getElementById('active-cell-members').textContent = activeMembers;
        document.getElementById('cells-with-members').textContent = cellsWithMembers;
    }

    populateMembersCellFilter() {
        const select = document.getElementById('members-cell-filter');
        const placeholder = select.options[0];
        select.innerHTML = '';
        select.appendChild(placeholder);
        
        // Get unique cells from members
        const uniqueCells = [...new Map(this.cellMembers.map(m => [m.cell_id, m])).values()];
        uniqueCells.forEach(member => {
            if (member.cell_name) {
                const opt = document.createElement('option');
                opt.value = member.cell_id;
                opt.textContent = member.cell_name;
                select.appendChild(opt);
            }
        });
    }

    applyMembersFilters() {
        this.filteredMembers = this.cellMembers.filter(member => {
            if (this.membersFilters.search) {
                const searchTerm = this.membersFilters.search.toLowerCase();
                const matchesSearch = 
                    member.name.toLowerCase().includes(searchTerm) ||
                    (member.phone && member.phone.toLowerCase().includes(searchTerm)) ||
                    (member.email && member.email.toLowerCase().includes(searchTerm)) ||
                    (member.cell_name && member.cell_name.toLowerCase().includes(searchTerm));
                
                if (!matchesSearch) return false;
            }
            
            if (this.membersFilters.cell && member.cell_id != this.membersFilters.cell) {
                return false;
            }
            
            if (this.membersFilters.status && member.status !== this.membersFilters.status) {
                return false;
            }
            
            return true;
        });
        
        this.renderMembersTable();
    }

    handleMembersSort(column) {
        if (this.membersSortColumn === column) {
            this.membersSortDirection = this.membersSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.membersSortColumn = column;
            this.membersSortDirection = 'asc';
        }
        
        this.filteredMembers.sort((a, b) => {
            let aVal = a[column] || '';
            let bVal = b[column] || '';
            
            if (typeof aVal === 'string') {
                aVal = aVal.toLowerCase();
                bVal = bVal.toLowerCase();
            }
            
            if (this.membersSortDirection === 'asc') {
                return aVal > bVal ? 1 : -1;
            } else {
                return aVal < bVal ? 1 : -1;
            }
        });
        
        this.updateMembersSortIcons(column);
        this.renderMembersTable();
    }

    updateMembersSortIcons(activeColumn) {
        document.querySelectorAll('[data-sort-members] i').forEach(icon => {
            const column = icon.parentElement.dataset.sortMembers;
            if (column === activeColumn) {
                icon.className = this.membersSortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
            } else {
                icon.className = 'fas fa-sort';
            }
        });
    }

    renderMembersTable() {
        const tbody = document.getElementById('members-table-body');
        const emptyState = document.getElementById('members-empty-state');
        const tableContainer = document.querySelector('#members-tab .table-container');
        
        if (this.filteredMembers.length === 0) {
            tableContainer.style.display = 'none';
            emptyState.style.display = 'block';
            return;
        }
        
        tableContainer.style.display = 'block';
        emptyState.style.display = 'none';
        
        tbody.innerHTML = this.filteredMembers.map((member, index) => `
            <tr style="animation: fadeIn 0.3s ease-out ${index * 0.05}s both;">
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.9rem;">
                            ${this.getInitials(member.name)}
                        </div>
                        <div>
                            <strong>${this.escapeHtml(member.name)}</strong>
                            ${member.role_in_church ? `<br><small style="color: var(--text-muted);">${this.escapeHtml(member.role_in_church)}</small>` : ''}
                        </div>
                    </div>
                </td>
                <td>
                    <span class="status-badge status-active">
                        <i class="fas fa-home"></i> ${this.escapeHtml(member.cell_name || 'N/A')}
                    </span>
                </td>
                <td>
                    ${member.phone ? `
                        <a href="tel:${member.phone}" style="color: var(--text-color); text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-phone" style="color: var(--primary-color);"></i>
                            ${this.escapeHtml(member.phone)}
                        </a>
                    ` : '<span style="color: var(--text-muted);">—</span>'}
                </td>
                <td>
                    ${member.email ? `
                        <a href="mailto:${member.email}" style="color: var(--text-color); text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-envelope" style="color: var(--primary-color);"></i>
                            ${this.escapeHtml(member.email)}
                        </a>
                    ` : '<span style="color: var(--text-muted);">—</span>'}
                </td>
                <td>
                    <span class="status-badge ${member.status === 'active' ? 'status-active' : 'status-inactive'}">
                        ${member.status === 'active' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>'}
                        ${this.escapeHtml(member.status || 'N/A')}
                    </span>
                </td>
                <td>
                    <span style="color: var(--text-muted); font-size: 0.9rem;">
                        ${this.escapeHtml(member.category || 'N/A')}
                    </span>
                </td>
            </tr>
        `).join('');
    }

    showMembersEmptyState() {
        const tableContainer = document.querySelector('#members-tab .table-container');
        const emptyState = document.getElementById('members-empty-state');
        tableContainer.style.display = 'none';
        emptyState.style.display = 'block';
    }

    exportMembersData() {
        if (this.filteredMembers.length === 0) {
            this.showNotification('No data to export', 'warning');
            return;
        }

        const csvContent = this.convertMembersToCSV(this.filteredMembers);
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', `cell_members_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        this.showNotification('Data exported successfully', 'success');
    }

    convertMembersToCSV(data) {
        const headers = ['Name', 'Cell', 'Phone', 'Email', 'Status', 'Category', 'Role'];
        const rows = data.map(member => [
            this.escapeCSV(member.name),
            this.escapeCSV(member.cell_name || ''),
            this.escapeCSV(member.phone || ''),
            this.escapeCSV(member.email || ''),
            this.escapeCSV(member.status || ''),
            this.escapeCSV(member.category || ''),
            this.escapeCSV(member.role_in_church || '')
        ]);
        
        return [
            headers.join(','),
            ...rows.map(row => row.join(','))
        ].join('\n');
    }

    async loadCells() {
        try {
            const response = await fetch('../../backend/ajax/cells_crud.php');
            const result = await response.json();
            if (result.success) {
                this.cells = result.data;
                this.filteredCells = [...this.cells];
                this.updateStats();
                this.renderTable();
            } else {
                this.showEmptyState();
            }
        } catch (error) {
            console.error('Error loading cells:', error);
            this.showNotification('Failed to load home cells', 'error');
        }
    }

    updateStats() {
        const totalCells = this.cells.length;
        const cellsWithLeaders = this.cells.filter(c => c.leader_id).length;
        const activeMeetings = this.cells.filter(c => c.meeting_day).length;
        
        document.getElementById('total-cells').textContent = totalCells;
        document.getElementById('cells-with-leaders').textContent = cellsWithLeaders;
        document.getElementById('active-meetings').textContent = activeMeetings;
    }

    applyFilters() {
        this.filteredCells = this.cells.filter(cell => {
            if (this.filters.search) {
                const searchTerm = this.filters.search.toLowerCase();
                const matchesSearch = 
                    cell.name.toLowerCase().includes(searchTerm) ||
                    (cell.leader_name && cell.leader_name.toLowerCase().includes(searchTerm)) ||
                    (cell.location && cell.location.toLowerCase().includes(searchTerm)) ||
                    (cell.fellowship_name && cell.fellowship_name.toLowerCase().includes(searchTerm));
                
                if (!matchesSearch) return false;
            }
            
            if (this.filters.fellowship && cell.fellowship_id != this.filters.fellowship) {
                return false;
            }
            
            if (this.filters.day && cell.meeting_day !== this.filters.day) {
                return false;
            }
            
            return true;
        });
        
        this.renderTable();
    }

    handleSort(column) {
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'asc';
        }
        
        this.filteredCells.sort((a, b) => {
            let aVal = a[column] || '';
            let bVal = b[column] || '';
            
            if (typeof aVal === 'string') {
                aVal = aVal.toLowerCase();
                bVal = bVal.toLowerCase();
            }
            
            if (this.sortDirection === 'asc') {
                return aVal > bVal ? 1 : -1;
            } else {
                return aVal < bVal ? 1 : -1;
            }
        });
        
        this.updateSortIcons(column);
        this.renderTable();
    }

    updateSortIcons(activeColumn) {
        document.querySelectorAll('[data-sort] i').forEach(icon => {
            const column = icon.parentElement.dataset.sort;
            if (column === activeColumn) {
                icon.className = this.sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
            } else {
                icon.className = 'fas fa-sort';
            }
        });
    }

    renderTable() {
        const tbody = document.getElementById('cell-table-body');
        const emptyState = document.getElementById('empty-state');
        const tableContainer = document.querySelector('.table-container');
        
        if (this.filteredCells.length === 0) {
            tableContainer.style.display = 'none';
            emptyState.style.display = 'block';
            return;
        }
        
        tableContainer.style.display = 'block';
        emptyState.style.display = 'none';
        
        tbody.innerHTML = this.filteredCells.map((cell, index) => `
            <tr style="animation: fadeIn 0.3s ease-out ${index * 0.05}s both;">
                <td><strong>${this.escapeHtml(cell.name)}</strong></td>
                <td>
                    ${cell.leader_name ? `
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem;">
                                ${this.getInitials(cell.leader_name)}
                            </div>
                            <span>${this.escapeHtml(cell.leader_name)}</span>
                        </div>
                    ` : '<span style="color: var(--text-muted);">—</span>'}
                </td>
                <td>
                    ${cell.fellowship_name ? `<span class="status-badge status-active"><i class="fas fa-users"></i> ${this.escapeHtml(cell.fellowship_name)}</span>` : '<span style="color: var(--text-muted);">—</span>'}
                </td>
                <td>
                    ${cell.meeting_day ? `<span style="display: inline-flex; align-items: center; gap: 6px;"><i class="fas fa-calendar-day" style="color: var(--primary-color);"></i>${this.escapeHtml(cell.meeting_day)}</span>` : '<span style="color: var(--text-muted);">—</span>'}
                </td>
                <td>
                    ${cell.location ? `<span style="display: inline-flex; align-items: center; gap: 6px; color: var(--text-muted);"><i class="fas fa-map-marker-alt"></i>${this.escapeHtml(cell.location)}</span>` : '<span style="color: var(--text-muted);">—</span>'}
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                        <button class="btn btn-sm btn-outline edit-btn" data-id="${cell.id}" title="Edit Cell"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-delete delete-btn" data-id="${cell.id}" title="Delete Cell"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>
        `).join('');

        tbody.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', () => this.openModal(btn.dataset.id));
        });
        tbody.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', () => this.deleteCell(btn.dataset.id));
        });
    }

    showEmptyState() {
        const tableContainer = document.querySelector('.table-container');
        const emptyState = document.getElementById('empty-state');
        tableContainer.style.display = 'none';
        emptyState.style.display = 'block';
    }

    openModal(id = null) {
        const modal = document.getElementById('cell-modal');
        const form = document.getElementById('cell-form');
        const title = document.getElementById('modal-title');
        
        form.reset();
        document.getElementById('cell-id').value = '';

        if (id) {
            const cell = this.cells.find(c => c.id == id);
            if (cell) {
                document.getElementById('cell-id').value = cell.id;
                document.getElementById('cell-name').value = cell.name;
                document.getElementById('cell-leader').value = cell.leader_id || '';
                document.getElementById('cell-fellowship').value = cell.fellowship_id || '';
                document.getElementById('cell-day').value = cell.meeting_day || '';
                document.getElementById('cell-location').value = cell.location || '';
                title.innerHTML = '<i class="fas fa-edit"></i> Edit Home Cell';
            }
        } else {
            title.innerHTML = '<i class="fas fa-plus"></i> Create New Home Cell';
        }

        modal.classList.add('show');
        setTimeout(() => document.getElementById('cell-name').focus(), 100);
    }

    closeModal() {
        document.getElementById('cell-modal').classList.remove('show');
    }

    async saveCell(e) {
        e.preventDefault();
        
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        const id = document.getElementById('cell-id').value;
        const name = document.getElementById('cell-name').value.trim();
        const leader_id = document.getElementById('cell-leader').value;
        const fellowship_id = document.getElementById('cell-fellowship').value;
        const meeting_day = document.getElementById('cell-day').value;
        const location = document.getElementById('cell-location').value.trim();

        if (!name) {
            this.showNotification('Cell name is required', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            return;
        }

        const method = id ? 'PUT' : 'POST';
        const body = { name, leader_id, fellowship_id, meeting_day, location };
        if (id) body.id = id;

        try {
            const response = await fetch('../../backend/ajax/cells_crud.php', {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const result = await response.json();
            
            if (result.success) {
                this.closeModal();
                await this.loadCells();
                this.showNotification(result.message || 'Cell saved successfully', 'success');
            } else {
                this.showNotification(result.message || 'Failed to save cell', 'error');
            }
        } catch (error) {
            console.error('Error saving cell:', error);
            this.showNotification('An error occurred while saving', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    async deleteCell(id) {
        const cell = this.cells.find(c => c.id == id);
        if (!cell) return;
        
        if (!confirm(`Are you sure you want to delete "${cell.name}"?\n\nThis action cannot be undone.`)) {
            return;
        }

        try {
            const response = await fetch('../../backend/ajax/cells_crud.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const result = await response.json();
            
            if (result.success) {
                await this.loadCells();
                this.showNotification(result.message || 'Cell deleted successfully', 'success');
            } else {
                this.showNotification(result.message || 'Failed to delete cell', 'error');
            }
        } catch (error) {
            console.error('Error deleting cell:', error);
            this.showNotification('An error occurred while deleting', 'error');
        }
    }

    exportData() {
        if (this.filteredCells.length === 0) {
            this.showNotification('No data to export', 'warning');
            return;
        }

        const csvContent = this.convertToCSV(this.filteredCells);
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', `home_cells_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        this.showNotification('Data exported successfully', 'success');
    }

    convertToCSV(data) {
        const headers = ['Cell Name', 'Leader', 'Fellowship', 'Meeting Day', 'Location'];
        const rows = data.map(cell => [
            this.escapeCSV(cell.name),
            this.escapeCSV(cell.leader_name || ''),
            this.escapeCSV(cell.fellowship_name || ''),
            this.escapeCSV(cell.meeting_day || ''),
            this.escapeCSV(cell.location || '')
        ]);
        
        return [
            headers.join(','),
            ...rows.map(row => row.join(','))
        ].join('\n');
    }

    escapeCSV(str) {
        if (str === null || str === undefined) return '';
        str = String(str);
        if (str.includes(',') || str.includes('"') || str.includes('\n')) {
            return `"${str.replace(/"/g, '""')}"`;
        }
        return str;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    getInitials(name) {
        return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
    }

    showNotification(message, type = 'info') {
        if (window.showNotification) {
            window.showNotification(message, type);
        } else {
            alert(message);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.cellManager = new CellManager();
});
