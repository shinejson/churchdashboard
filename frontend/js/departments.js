// frontend/js/departments.js
class DepartmentManager {
    constructor() {
        this.depts = [];
        this.filteredDepts = [];
        this.sortColumn = 'name';
        this.sortDirection = 'asc';
        this.init();
    }

    init() {
        this.loadDepartments();
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Create button
        document.getElementById('create-dept-btn').addEventListener('click', () => this.openModal());
        
        // Close modal buttons
        document.querySelectorAll('.close, .close-modal').forEach(btn => {
            btn.addEventListener('click', () => this.closeModal());
        });
        
        // Form submit
        document.getElementById('dept-form').addEventListener('submit', (e) => this.saveDepartment(e));
        
        // Search input
        const searchInput = document.getElementById('search-input');
        searchInput.addEventListener('input', (e) => this.handleSearch(e.target.value));
        
        // Sort headers
        document.querySelectorAll('[data-sort]').forEach(header => {
            header.addEventListener('click', () => this.handleSort(header.dataset.sort));
        });
        
        // Export button
        document.getElementById('export-btn').addEventListener('click', () => this.exportData());
        
        // Close modal on outside click
        document.getElementById('dept-modal').addEventListener('click', (e) => {
            if (e.target.id === 'dept-modal') {
                this.closeModal();
            }
        });
    }

    async loadDepartments() {
        try {
            const response = await fetch('../../backend/ajax/departments_crud.php');
            const result = await response.json();
            if (result.success) {
                this.depts = result.data;
                this.filteredDepts = [...this.depts];
                this.updateStats();
                this.renderTable();
            } else {
                this.showEmptyState();
            }
        } catch (error) {
            console.error('Error loading departments:', error);
            this.showNotification('Failed to load departments', 'error');
        }
    }

    updateStats() {
        document.getElementById('total-depts').textContent = this.depts.length;
        document.getElementById('active-depts').textContent = this.depts.length;
    }

    handleSearch(query) {
        const searchTerm = query.toLowerCase().trim();
        
        if (!searchTerm) {
            this.filteredDepts = [...this.depts];
        } else {
            this.filteredDepts = this.depts.filter(dept => 
                dept.name.toLowerCase().includes(searchTerm) ||
                (dept.description && dept.description.toLowerCase().includes(searchTerm))
            );
        }
        
        this.renderTable();
    }

    handleSort(column) {
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'asc';
        }
        
        this.filteredDepts.sort((a, b) => {
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
        const tbody = document.getElementById('dept-table-body');
        const emptyState = document.getElementById('empty-state');
        const tableContainer = document.querySelector('.table-container');
        
        if (this.filteredDepts.length === 0) {
            tableContainer.style.display = 'none';
            emptyState.style.display = 'block';
            return;
        }
        
        tableContainer.style.display = 'block';
        emptyState.style.display = 'none';
        
        tbody.innerHTML = this.filteredDepts.map((dept, index) => `
            <tr style="animation: fadeIn 0.3s ease-out ${index * 0.05}s both;">
                <td>
                    <strong>${this.escapeHtml(dept.name)}</strong>
                </td>
                <td>
                    <span style="color: var(--text-muted);">
                        ${dept.description ? this.escapeHtml(dept.description) : '—'}
                    </span>
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                        <button class="btn btn-sm btn-outline edit-btn" data-id="${dept.id}" title="Edit Department">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-sm btn-delete delete-btn" data-id="${dept.id}" title="Delete Department">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        // Attach event listeners
        tbody.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', () => this.openModal(btn.dataset.id));
        });
        tbody.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', () => this.deleteDepartment(btn.dataset.id));
        });
    }

    showEmptyState() {
        const tableContainer = document.querySelector('.table-container');
        const emptyState = document.getElementById('empty-state');
        tableContainer.style.display = 'none';
        emptyState.style.display = 'block';
    }

    openModal(id = null) {
        const modal = document.getElementById('dept-modal');
        const form = document.getElementById('dept-form');
        const title = document.getElementById('modal-title');
        
        form.reset();
        document.getElementById('dept-id').value = '';

        if (id) {
            const dept = this.depts.find(d => d.id == id);
            if (dept) {
                document.getElementById('dept-id').value = dept.id;
                document.getElementById('dept-name').value = dept.name;
                document.getElementById('dept-description').value = dept.description || '';
                title.innerHTML = '<i class="fas fa-edit"></i> Edit Department';
            }
        } else {
            title.innerHTML = '<i class="fas fa-plus"></i> Create New Department';
        }

        modal.classList.add('show');
        setTimeout(() => document.getElementById('dept-name').focus(), 100);
    }

    closeModal() {
        document.getElementById('dept-modal').classList.remove('show');
    }

    async saveDepartment(e) {
        e.preventDefault();
        
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        const id = document.getElementById('dept-id').value;
        const name = document.getElementById('dept-name').value.trim();
        const description = document.getElementById('dept-description').value.trim();

        if (!name) {
            this.showNotification('Department name is required', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            return;
        }

        const method = id ? 'PUT' : 'POST';
        const body = { name, description };
        if (id) body.id = id;

        try {
            const response = await fetch('../../backend/ajax/departments_crud.php', {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const result = await response.json();
            
            if (result.success) {
                this.closeModal();
                this.loadDepartments();
                this.showNotification(result.message || 'Department saved successfully', 'success');
            } else {
                this.showNotification(result.message || 'Failed to save department', 'error');
            }
        } catch (error) {
            console.error('Error saving department:', error);
            this.showNotification('An error occurred while saving', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    async deleteDepartment(id) {
        const dept = this.depts.find(d => d.id == id);
        if (!dept) return;
        
        if (!confirm(`Are you sure you want to delete "${dept.name}"?\n\nThis action cannot be undone.`)) {
            return;
        }

        try {
            const response = await fetch('../../backend/ajax/departments_crud.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const result = await response.json();
            
            if (result.success) {
                this.loadDepartments();
                this.showNotification(result.message || 'Department deleted successfully', 'success');
            } else {
                this.showNotification(result.message || 'Failed to delete department', 'error');
            }
        } catch (error) {
            console.error('Error deleting department:', error);
            this.showNotification('An error occurred while deleting', 'error');
        }
    }

    exportData() {
        if (this.filteredDepts.length === 0) {
            this.showNotification('No data to export', 'warning');
            return;
        }

        const csvContent = this.convertToCSV(this.filteredDepts);
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', `departments_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        this.showNotification('Data exported successfully', 'success');
    }

    convertToCSV(data) {
        const headers = ['Name', 'Description'];
        const rows = data.map(dept => [
            this.escapeCSV(dept.name),
            this.escapeCSV(dept.description || '')
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

    showNotification(message, type = 'info') {
        // Use existing notification system if available
        if (window.showNotification) {
            window.showNotification(message, type);
        } else {
            alert(message);
        }
    }
}

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    window.departmentManager = new DepartmentManager();
});
