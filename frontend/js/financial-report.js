/**
 * Financial Report Manager
 * Handles UI for generating and sending financial reports
 */

class FinancialReportManager {
    constructor() {
        this.fellowships = [];
        this.users = [];
        this.apiBase = '../../backend';
        this.init();
    }

    async init() {
        this.attachEventListeners();
    }

    attachEventListeners() {
        const sendReportBtn = document.getElementById('send-financial-report-btn');
        if (sendReportBtn) {
            sendReportBtn.addEventListener('click', () => this.openReportModal());
        }
    }

    async loadData() {
        try {
            // Load fellowships
            const fRes = await fetch('../api/fellowships/read.php');
            if (fRes.ok) {
                const fData = await fRes.json();
                this.fellowships = Array.isArray(fData) ? fData : (fData.records || fData.data || []);
            }

            // Load users
            const uRes = await fetch('../api/users/read.php');
            if (uRes.ok) {
                const uData = await uRes.json();
                this.users = Array.isArray(uData) ? uData : (uData.records || uData.data || []);
            }
        } catch (error) {
            console.error('Error loading report data:', error);
            // Default to empty arrays instead of throwing
            this.fellowships = this.fellowships || [];
            this.users = this.users || [];
        }
    }

    async openReportModal() {
        try {
            await this.loadData();
        } catch (e) {
            console.error('Failed to load initial data, opening modal anyway:', e);
        }

        const modal = document.createElement('div');
        modal.id = 'financial-report-modal';
        modal.className = 'modal show';
        modal.style.zIndex = '1000002'; // Above existing notifications
        modal.style.display = 'flex'; // Explicitly set display

        // ... rest of modal creation ...
        const fellowshipOptions = this.fellowships.map(f => `<option value="${f.id}">${f.name}</option>`).join('');
        const userOptions = this.users.filter(u => u.email).map(u => `
            <label style="display: block; padding: 5px; cursor: pointer;">
                <input type="checkbox" name="recipients" value="${u.id}" data-email="${u.email}">
                ${u.username} (${u.email})
            </label>
        `).join('');

        modal.innerHTML = `
            <div class="modal-content" style="max-width: 500px; margin: auto;">
                <div class="modal-header">
                    <h3><i class="fas fa-envelope"></i> Send Financial Report</h3>
                    <span class="close-modal" style="cursor: pointer; font-size: 24px;">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="financial-report-form">
                        <div class="form-group">
                            <label>Report Type</label>
                            <select id="report-type" class="form-input" style="width: 100%; padding: 8px; margin-top: 5px;">
                                <option value="overview">Overview Summary</option>
                                <option value="detailed">Detailed Transaction List</option>
                            </select>
                        </div>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <div class="form-group" style="flex: 1;">
                                <label>Start Date</label>
                                <input type="date" id="report-start" class="form-input" style="width: 100%; padding: 8px; margin-top: 5px;" required value="${new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]}">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>End Date</label>
                                <input type="date" id="report-end" class="form-input" style="width: 100%; padding: 8px; margin-top: 5px;" required value="${new Date().toISOString().split('T')[0]}">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top: 15px;">
                            <label>Fellowship (Optional)</label>
                            <select id="report-fellowship" class="form-input" style="width: 100%; padding: 8px; margin-top: 5px;">
                                <option value="">All Fellowships</option>
                                ${fellowshipOptions}
                            </select>
                        </div>
                        <div class="form-group" style="margin-top: 15px;">
                            <label>Recipients</label>
                            <div style="max-height: 150px; overflow-y: auto; border: 1px solid var(--border-color); padding: 10px; border-radius: 8px; margin-top: 5px; background: var(--bg-color);">
                                ${userOptions}
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="padding: 15px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                    <button class="btn-secondary close-btn">Cancel</button>
                    <button class="btn-primary" id="send-report-submit">
                        <i class="fas fa-paper-plane"></i> Send Report
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Add overlay if doesn't exist or show existing
        let overlay = document.getElementById('modal-overlay');
        if (overlay) {
            overlay.classList.add('show');
            overlay.style.zIndex = '1000001'; // Just below modal
        } else {
            overlay = document.createElement('div');
            overlay.id = 'temp-modal-overlay';
            overlay.className = 'modal-overlay show';
            overlay.style.cssText = 'position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:1000001;';
            document.body.appendChild(overlay);
        }

        // Close handlers
        const close = () => {
            modal.remove();
            if (overlay) overlay.classList.remove('show');
            const tempOverlay = document.getElementById('temp-modal-overlay');
            if (tempOverlay) tempOverlay.remove();

            // Clean up z-index on real overlay
            const realOverlay = document.getElementById('modal-overlay');
            if (realOverlay) realOverlay.style.zIndex = '';
        };
        modal.querySelector('.close-modal').onclick = close;
        modal.querySelector('.close-btn').onclick = close;

        modal.querySelector('#send-report-submit').onclick = () => this.submitReport(modal);
    }

    async submitReport(modal) {
        const type = modal.querySelector('#report-type').value;
        const start = modal.querySelector('#report-start').value;
        const end = modal.querySelector('#report-end').value;
        const fellowshipId = modal.querySelector('#report-fellowship').value;
        const recipientIds = Array.from(modal.querySelectorAll('input[name="recipients"]:checked')).map(cb => cb.value);

        if (recipientIds.length === 0) {
            window.showNotification('Please select at least one recipient', 'warning');
            return;
        }

        const btn = modal.querySelector('#send-report-submit');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

        try {
            const response = await fetch(`${this.apiBase}/financial/send_report.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    report_type: type,
                    send_method: 'email',
                    start_date: start,
                    end_date: end,
                    fellowship_id: fellowshipId,
                    recipient_ids: recipientIds
                })
            });

            const result = await response.json();
            if (result.success) {
                window.showNotification('Report sent successfully!', 'success');
                modal.remove();
                const overlay = document.getElementById('modal-overlay');
                if (overlay) overlay.classList.remove('show');
            } else {
                window.showNotification(result.message || 'Failed to send report', 'error');
            }
        } catch (error) {
            window.showNotification('Error connecting to server', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.financialReportManager = new FinancialReportManager();
});
