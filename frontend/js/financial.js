class FinancialManager {
    constructor() {
        // API Configuration
        // Prefer the global API_BASE (set by env.js/config.js) so protocol and host are always correct
        // Fallback to APP_CONFIG.baseUrl or a relative path for local development
        this.apiBase = window.API_BASE
            || (window.APP_CONFIG?.baseUrl ? window.APP_CONFIG.baseUrl + 'backend' : '/church/backend');

        this.financialData = [];
        this.currentStep = 1;
        this.totalSteps = 5;
        this.filters = { from: '', to: '', type: '', category: '' };
        this.formData = {
            date: '',
            fellowship: '',
            member: '',
            service: '',
            offeringAmount: 0,
            titheMembers: [],
            titheTotal: 0,
            partnershipMembers: [],
            partnershipTotal: 0,
            partnershipRecords: [] // Added missing property
        };
        this.fellowships = [];
        this.members = [];
        this.services = [];
        this.partnershipArms = [];
        this.attendanceData = {};
        this.partnershipArmsData = [];
        this.editingArmId = null;


        this.initializeEventListeners();
        this.initializePartnershipArmModal();
        this.loadInitialData(); // This will call loadPartnershipArmsDropdown()
        this.loadTransactions();
        this.loadTitheData();

        setTimeout(() => {
            this.setupPartnershipMemberDropdown();
            this.setupTitheMemberDropdown();
        }, 1000);
    }

    initializeEventListeners() {
        const modal = document.getElementById('financial-modal');
        const overlay = document.getElementById('modal-overlay');

        // Modal controls
        document.getElementById('record-financial-btn')?.addEventListener('click', () => this.openModal());
        document.getElementById('record-from-empty')?.addEventListener('click', () => this.openModal());
        document.getElementById('cancel-financial')?.addEventListener('click', () => this.closeModal());
        document.getElementById('close-modal')?.addEventListener('click', () => this.closeModal());
        overlay?.addEventListener('click', () => this.closeModal());

        // Form navigation
        document.getElementById('next-step')?.addEventListener('click', () => this.nextStep());
        document.getElementById('prev-step')?.addEventListener('click', () => this.prevStep());
        document.getElementById('submit-transaction')?.addEventListener('click', (e) => this.submitTransaction(e));

        // Form field changes
        document.getElementById('transaction-date')?.addEventListener('change', (e) => {
            this.handleDateChange(e);
        });

        document.getElementById('fellowship')?.addEventListener('change', (e) => {
            this.handleFellowshipChange(e);
        });

        document.getElementById('service')?.addEventListener('change', (e) => {
            this.loadAttendanceForDate();
        });

        document.getElementById('offering-amount')?.addEventListener('input', () => this.updateReview());

        // Tithe event listeners
        const titheMemberSearch = document.getElementById('tithe-member-search');
        if (titheMemberSearch) {
            titheMemberSearch.addEventListener('input', (e) => this.searchTitheMembers(e.target.value));
            titheMemberSearch.addEventListener('focus', () => this.searchTitheMembers(''));
            titheMemberSearch.addEventListener('click', () => this.searchTitheMembers(''));
        }
        document.getElementById('add-tithe-member')?.addEventListener('click', () => this.addTitheMember());

        // Partnership event listeners
        const partnershipMemberSearch = document.getElementById('partnership-member-search');
        if (partnershipMemberSearch) {
            partnershipMemberSearch.addEventListener('input', (e) => this.filterPartnershipMembers(e.target.value));
            partnershipMemberSearch.addEventListener('focus', () => this.filterPartnershipMembers(''));
            partnershipMemberSearch.addEventListener('click', () => this.filterPartnershipMembers(''));
        }
        document.getElementById('add-partnership-member')?.addEventListener('click', () => this.addPartnershipMember());
        document.getElementById('add-partnership-record')?.addEventListener('click', () => this.addPartnershipArm());

        // Shared dropdown closure
        document.addEventListener('click', (e) => {
            // Tithe
            const titheDropdown = document.getElementById('tithe-member-options');
            const titheSearch = document.getElementById('tithe-member-search');
            if (titheDropdown && !titheDropdown.contains(e.target) && e.target !== titheSearch) {
                titheDropdown.style.display = 'none';
                titheSearch?.closest('.searchable-select')?.classList.remove('active');
            }
            // Partnership
            const partnershipDropdown = document.getElementById('partnership-member-options');
            const partnershipSearch = document.getElementById('partnership-member-search');
            if (partnershipDropdown && !partnershipDropdown.contains(e.target) && e.target !== partnershipSearch) {
                partnershipDropdown.style.display = 'none';
                partnershipSearch?.closest('.searchable-select')?.classList.remove('active');
            }
        });

        // Initialize dropdown handlers
        this.setupTitheMemberDropdown();
        this.setupPartnershipMemberDropdown();

        // Existing filters
        document.getElementById('apply-financial-filters')?.addEventListener('click', () => this.applyFilters());
        document.getElementById('reset-financial-filters')?.addEventListener('click', () => this.resetFilters());

        // Report generation
        document.getElementById('export-financial-data')?.addEventListener('click', () => this.exportFinancialData());

    }
    initializePartnershipArmModal() {
        const addButton = document.getElementById('add-partnership-arm-btn');
        const closeButton = document.getElementById('close-partnership-arm-modal');
        const cancelButton = document.getElementById('cancel-partnership-arm');
        const saveButton = document.getElementById('save-partnership-arm');

        if (addButton) {
            addButton.onclick = () => {
                this.openPartnershipArmModal();
            };
        }

        if (closeButton) closeButton.onclick = () => this.closePartnershipArmModal();
        if (cancelButton) cancelButton.onclick = () => this.closePartnershipArmModal();
        if (saveButton) saveButton.onclick = () => this.savePartnershipArm();
    }

    setupTitheMemberDropdown() {
        const dropdown = document.getElementById('tithe-member-options');
        const searchInput = document.getElementById('tithe-member-search');
        if (!dropdown || !searchInput) return;

        dropdown.addEventListener('click', (e) => {
            const item = e.target.closest('.dropdown-item');
            if (!item || item.classList.contains('no-results')) return;

            const memberId = item.dataset.memberId;
            const memberName = item.dataset.memberName || item.textContent;

            searchInput.value = memberName;
            searchInput.dataset.selectedId = memberId;

            dropdown.style.display = 'none';
            document.getElementById('tithe-amount')?.focus();
        });
    }

    setupPartnershipMemberDropdown() {
        const dropdown = document.getElementById('partnership-member-options');
        const searchInput = document.getElementById('partnership-member-search');
        if (!dropdown || !searchInput) return;

        dropdown.addEventListener('click', (e) => {
            const item = e.target.closest('.dropdown-item');
            if (!item || item.classList.contains('no-results')) return;

            const memberId = item.dataset.memberId;
            const memberName = item.dataset.memberName || item.textContent;

            searchInput.value = memberName;
            searchInput.dataset.selectedId = memberId;

            dropdown.style.display = 'none';
            document.getElementById('partnership-member-amount')?.focus();
        });
    }

    async loadInitialData() {
        try {
            // Load fellowships
            const fellowshipRes = await fetch(`${this.apiBase}/financial/read.php?action=fellowships`);
            const fellowshipData = await fellowshipRes.json();
            this.fellowships = fellowshipData.success ? fellowshipData.fellowships : [];

            // Load services
            const serviceRes = await fetch(`${this.apiBase}/ajax/services_crud.php`);
            const serviceData = await serviceRes.json();
            this.services = serviceData.success ? serviceData.data : [];

            // Load partnership arms - uses loadPartnershipArmsDropdown
            await this.loadPartnershipArmsDropdown(); // Changed here

            // Populate dropdowns
            this.populateFellowships();
            this.populateServices();

            // Note: populatePartnershipArms() is now called inside loadPartnershipArmsDropdown()

        } catch (err) {
            console.error("Failed to load initial data:", err);
            this.showNotification("Failed to load dropdowns", "error");
        }
    }

    async loadTransactions() {
        try {
            const res = await fetch(`${this.apiBase}/financial/read.php`);
            const data = await res.json();

            if (data.success) {
                this.financialData = data.records || [];
                this.renderTable();
                this.updateStats();
            } else {
                this.showNotification("Failed to load transactions", "error");
            }
        } catch (err) {
            console.error("Failed to load transactions:", err);
            this.showNotification("Failed to load data", "error");
        }
    }

    async loadTitheData() {
        try {
            const res = await fetch(`${this.apiBase}/financial/read.php?action=tithe_summary`);
            const data = await res.json();

            if (data.success) {
                this.renderTitheTable(data.tithes || []);
            }
        } catch (err) {
            console.error("Failed to load tithe data:", err);
        }
    }

    // TITHE METHODS
    async searchTitheMembers(query = '') {
        const dropdown = document.getElementById('tithe-member-options');
        if (!dropdown) return;

        const fellowshipId = document.getElementById('fellowship').value;
        if (!fellowshipId) {
            this.showNotification("Please select a fellowship first", "warning");
            dropdown.style.display = 'none';
            return;
        }

        // Use cached members if available
        if (!this.members || this.members.length === 0) {
            await this.loadMembersByFellowship(fellowshipId);
        }

        dropdown.innerHTML = '';
        const addedMemberIds = this.formData.titheMembers.map(m => m.memberId.toString());

        const filtered = this.members.filter(member =>
            (member.name.toLowerCase().includes(query.toLowerCase()) || query === '') &&
            !addedMemberIds.includes(member.id.toString())
        );

        if (filtered.length === 0) {
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            item.textContent = query === '' ? 'No members found' : 'No matching members found';
            item.style.padding = '10px';
            item.style.color = 'var(--text-muted)';
            dropdown.appendChild(item);
        } else {
            filtered.forEach(member => {
                const item = document.createElement('div');
                item.className = 'dropdown-item';
                item.textContent = member.name;
                item.dataset.memberId = member.id;
                item.dataset.memberName = member.name;
                dropdown.appendChild(item);
            });
        }

        const container = dropdown.closest('.searchable-select');
        if (container) container.classList.add('active');
        dropdown.style.display = 'block';
    }

    addTitheMember() {
        const searchInput = document.getElementById('tithe-member-search');
        const amountInput = document.getElementById('tithe-amount');

        const memberId = searchInput.dataset.selectedId;
        const memberName = searchInput.value;
        const amount = parseFloat(amountInput.value);

        if (!memberId || !memberName || isNaN(amount) || amount <= 0) {
            this.showNotification('Please select a member and enter a valid amount', 'error');
            return;
        }

        // Check if member already added
        const existingIndex = this.formData.titheMembers.findIndex(m => m.memberId === memberId);
        if (existingIndex !== -1) {
            this.formData.titheMembers[existingIndex].amount = amount;
        } else {
            this.formData.titheMembers.push({
                memberId: memberId,
                memberName: memberName,
                amount: amount
            });
        }

        // Clear inputs
        searchInput.value = '';
        searchInput.dataset.selectedId = '';
        amountInput.value = '';

        // Update UI
        this.renderTitheMembersList();
        this.updateTitheTotal();
    }

    async filterPartnershipMembers(query = '') {
        const dropdown = document.getElementById('partnership-member-options');
        if (!dropdown) return;

        const fellowshipId = document.getElementById('fellowship').value;
        if (!fellowshipId) {
            this.showNotification("Please select a fellowship first", "warning");
            dropdown.style.display = 'none';
            return;
        }

        // Use cached members if available
        if (!this.members || this.members.length === 0) {
            await this.loadMembersByFellowship(fellowshipId);
        }

        dropdown.innerHTML = '';
        const addedMemberIds = this.formData.partnershipMembers.map(m => m.memberId.toString());

        const filtered = this.members.filter(member =>
            (member.name.toLowerCase().includes(query.toLowerCase()) || query === '') &&
            !addedMemberIds.includes(member.id.toString())
        );

        if (filtered.length === 0) {
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            item.textContent = query === '' ? 'No members found' : 'No matching members found';
            item.style.padding = '10px';
            item.style.color = 'var(--text-muted)';
            dropdown.appendChild(item);
        } else {
            filtered.forEach(member => {
                const item = document.createElement('div');
                item.className = 'dropdown-item';
                item.textContent = member.name;
                item.dataset.memberId = member.id;
                item.dataset.memberName = member.name;
                dropdown.appendChild(item);
            });
        }

        const container = dropdown.closest('.searchable-select');
        if (container) container.classList.add('active');
        dropdown.style.display = 'block';
    }
    renderTitheMembersList() {
        const container = document.getElementById('tithe-members-list');
        if (!container) return;

        if (this.formData.titheMembers.length === 0) {
            container.innerHTML = `
                <div class="empty-state" style="text-align: center; padding: 20px; color: var(--text-muted);">
                    <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <p>No tithe contributions added yet</p>
                </div>
            `;
            return;
        }

        container.innerHTML = this.formData.titheMembers.map((member, index) => `
            <div class="member-tithe-item" style="
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px;
                margin-bottom: 8px;
                background: var(--background-color);
                border-radius: 6px;
                border: 1px solid var(--border-color);
            ">
                <div>
                    <strong>${member.memberName}</strong>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-weight: bold; color: var(--success);">
                        GHS ${member.amount.toFixed(2)}
                    </span>
                    <button type="button" class="btn-action btn-delete delete-tithe" data-index="${index}">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');

        // Add event listeners for delete buttons
        container.querySelectorAll('.delete-tithe').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = parseInt(e.target.closest('.delete-tithe').dataset.index);
                this.removeTitheMember(index);
            });
        });
    }

    removeTitheMember(index) {
        if (index >= 0 && index < this.formData.titheMembers.length) {
            this.formData.titheMembers.splice(index, 1);
            this.renderTitheMembersList();
            this.updateTitheTotal();
        }
    }

    updateTitheTotal() {
        this.formData.titheTotal = this.formData.titheMembers.reduce((total, member) => total + member.amount, 0);

        const titheTotalInput = document.getElementById('tithe-total');
        if (titheTotalInput) {
            titheTotalInput.value = this.formData.titheTotal.toFixed(2);
        }

        this.updateReview();
    }

    renderTitheTable(tithes) {
        const tbody = document.getElementById('tithe-table-body');
        const emptyState = document.getElementById('tithe-empty-state');
        const countElement = document.getElementById('tithe-count');

        if (!tbody) return;

        if (!tithes || tithes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9">No tithe records found</td></tr>';
            if (emptyState) emptyState.style.display = 'flex';
            if (countElement) countElement.textContent = '0';
            return;
        }

        if (emptyState) emptyState.style.display = 'none';
        if (countElement) countElement.textContent = tithes.length.toString();

        tbody.innerHTML = tithes.map(tithe => `
            <tr>
                <td>${new Date(tithe.transaction_date).toLocaleDateString()}</td>
                <td>${tithe.member_name || 'Unknown'}</td>
                <td>${tithe.fellowship_name || 'Unknown'}</td>
                <td class="amount-income">GHS ${parseFloat(tithe.amount).toFixed(2)}</td>
                <td>${tithe.period || 'N/A'}</td>
                <td><span class="method-badge">${tithe.payment_method || 'cash'}</span></td>
                <td>
                    ${tithe.is_first_tithe == 1 ? '<span class="badge badge-info">First</span>' : ''}
                    ${tithe.is_second_tithe == 1 ? '<span class="badge badge-warning">Second</span>' : ''}
                    ${!tithe.is_first_tithe && !tithe.is_second_tithe ? '<span class="badge">Regular</span>' : ''}
                </td>
                <td>${tithe.recorded_by_name || 'System'}</td>
                <td>
                    <div class="action-buttons-cell">
                        <button class="btn-action edit" onclick="financialManager.editTithe(${tithe.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-action delete" onclick="financialManager.deleteTithe(${tithe.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    async editTithe(titheId) {
        try {
            const res = await fetch(`${this.apiBase}/financial/tithe.php?id=${titheId}`);

            // Check if response is OK
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: Failed to load tithe`);
            }

            const data = await res.json();

            if (data.success && data.data) {
                const tithe = data.data;

                // Store tithe ID for update operation
                window.currentEditTitheId = titheId;

                // Pre-fill the edit modal with tithe data
                document.getElementById('transaction-date').value = tithe.transaction_date || '';
                document.getElementById('fellowship').value = tithe.fellowship_id || '';

                // Open financial modal for editing
                const modal = document.getElementById('financial-modal');
                const overlay = document.getElementById('modal-overlay');
                if (modal && overlay) {
                    modal.classList.add('show');
                    overlay.classList.add('show');
                    document.body.style.overflow = 'hidden';

                    // Update modal title to indicate edit mode
                    const header = modal.querySelector('.modal-header-content');
                    if (header) {
                        header.querySelector('h3').textContent = 'Edit Tithe Record';
                        header.querySelector('.subtitle').textContent = `Editing tithe record #${titheId}`;
                    }

                    // Update formData
                    this.formData.date = tithe.transaction_date || '';
                    this.formData.fellowship = tithe.fellowship_id || '';
                    this.formData.titheMembers = [{
                        memberId: tithe.member_id,
                        memberName: `${tithe.first_name} ${tithe.last_name}`,
                        amount: parseFloat(tithe.amount) || 0
                    }];

                    this.updateTitheTotal();
                    this.renderTitheMembersList();

                    // Update session for attendance loading if needed
                    this.loadAttendanceForDate();

                    // Go to step 3 (Tithe) for editing
                    this.goToStep(3);

                    // Show success notification
                    this.showNotification("Tithe loaded for editing", "success");
                }
            } else {
                this.showNotification(data.error || "Failed to load tithe details", "error");
            }
        } catch (err) {
            console.error("Error loading tithe:", err);
            this.showNotification(`Failed to load tithe details: ${err.message}`, "error");
        }
    }

    async deleteTithe(titheId) {
        if (!confirm("Are you sure you want to delete this tithe record? This action cannot be undone.")) {
            return;
        }

        try {
            const res = await fetch(`${this.apiBase}/financial/tithe.php`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: titheId })
            });

            const data = await res.json();

            if (data.success) {
                this.showNotification("Tithe record deleted successfully", "success");
                this.loadTitheData();
            } else {
                this.showNotification(data.message || "Failed to delete tithe", "error");
            }
        } catch (err) {
            console.error("Error deleting tithe:", err);
            this.showNotification("Failed to delete tithe", "error");
        }
    }

    // STEP NAVIGATION METHODS
    goToStep(step) {
        if (step >= 1 && step <= this.totalSteps) {
            this.currentStep = step;
            this.showStep(this.currentStep);
        }
    }

    nextStep() {
        if (!this.validateStep(this.currentStep)) {
            return;
        }

        if (this.currentStep < this.totalSteps) {
            this.currentStep++;
            this.showStep(this.currentStep);
        }

        if (this.currentStep === 3) {
            this.loadPartnershipArmsDropdown(); // Load arms when going to partnership step
        }
    }

    prevStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.showStep(this.currentStep);
        }
    }

    validateStep(step) {
        switch (step) {
            case 1:
                const date = document.getElementById('transaction-date').value;
                const fellowship = document.getElementById('fellowship').value;

                if (!date || !fellowship) {
                    this.showNotification("Please select date and fellowship", "error");
                    return false;
                }
                return true;

            case 2:
                const offeringAmount = document.getElementById('offering-amount').value;
                const service = document.getElementById('service').value;

                if (!service) {
                    this.showNotification("Please select a service", "error");
                    return false;
                }

                if (!offeringAmount || parseFloat(offeringAmount) < 0) {
                    this.showNotification("Please enter a valid offering amount", "error");
                    return false;
                }
                return true;

            case 3:
                // Tithe step is optional
                return true;

            case 4:
                // Partnership step is optional
                return true;

            default:
                return true;
        }
    }

    async loadPartnershipArmsDropdown() {
        const select = document.getElementById('partnership-arms');
        if (!select) return;

        try {
            // Clear existing options
            select.innerHTML = '<option value="">Select Partnership Arm</option>';

            // Load partnership arms - using correct frontend API path
            const response = await fetch('../api/partnerships/read.php');
            const arms = await response.json();

            if (Array.isArray(arms)) {
                // Get IDs of already added partnership arms
                const addedArmIds = (this.formData.partnershipRecords || []).map(r => r.armId.toString());

                arms.forEach(arm => {
                    // Skip if this arm has already been added
                    if (addedArmIds.includes(arm.id.toString())) {
                        return;
                    }

                    const option = document.createElement('option');
                    option.value = arm.id;
                    option.textContent = arm.name;
                    select.appendChild(option);
                });

                // Also update the global partnershipArms array
                this.partnershipArms = arms;
                this.partnershipArmsData = arms;

                // Update the partnership arms management modal table too
                this.renderPartnershipArmsTable();
            } else {
                console.error('Expected array but got:', arms);
            }
        } catch (err) {
            console.error('Failed to load partnership arms:', err);
            this.showNotification('Failed to load partnership arms', 'error');
        }
    }

    // Update the showStep method to load partnership arms when step 4 is shown
    showStep(step) {
        document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));

        const currentStep = document.getElementById(`step-${step}`);
        if (currentStep) {
            currentStep.classList.add('active');
        }

        document.querySelector(`.step[data-step="${step}"]`)?.classList.add('active');

        // Update navigation buttons
        const prevBtn = document.getElementById('prev-step');
        const nextBtn = document.getElementById('next-step');
        const submitBtn = document.getElementById('submit-transaction');

        if (prevBtn) prevBtn.style.display = step === 1 ? 'none' : 'inline-flex';
        if (nextBtn) nextBtn.style.display = step === this.totalSteps ? 'none' : 'inline-flex';
        if (submitBtn) submitBtn.style.display = step === this.totalSteps ? 'inline-flex' : 'none';

        // Load partnership arms when step 4 is shown
        if (step === 4) {
            this.loadPartnershipArmsDropdown();
        }

        // Scroll to top of form container
        const formContainer = document.querySelector('.form-container');
        if (formContainer) {
            formContainer.scrollTop = 0;
        }
    }

    async submitTransaction(e) {
        e.preventDefault();

        const submitBtn = document.getElementById('submit-transaction');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

        const isEditMode = window.currentEditTransactionId !== undefined;

        // Prepare payload
        const payload = {
            id: isEditMode ? window.currentEditTransactionId : undefined,
            date: document.getElementById('transaction-date').value,
            fellowship_id: document.getElementById('fellowship').value,
            service_id: document.getElementById('service').value,
            offering_amount: parseFloat(document.getElementById('offering-amount').value) || 0,
            offering_notes: document.getElementById('offering-notes').value || '',
            payment_method: 'cash', // Default payment method
            tithe_members: this.formData.titheMembers,
            tithe_total: this.formData.titheTotal,
            partnership_records: this.formData.partnershipRecords || []
        };

        try {
            const endpoint = isEditMode ? `${this.apiBase}/financial/create.php` : `${this.apiBase}/financial/create.php`;
            const method = isEditMode ? 'PUT' : 'POST';

            const response = await fetch(endpoint, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload)
            });

            // First, check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                // If not JSON, get the text to see what's wrong
                const text = await response.text();
                console.error('Non-JSON response:', text);

                // Try to extract error message from HTML
                const errorMatch = text.match(/<b>(.*?)<\/b>/);
                const errorMessage = errorMatch ? errorMatch[1] : 'Server returned non-JSON response';

                throw new Error(errorMessage);
            }

            // Parse JSON response
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || `HTTP ${response.status}`);
            }

            if (result.success) {
                const action = isEditMode ? 'updated' : 'saved';
                this.showNotification(`Transaction ${action} successfully!`, "success");
                this.closeModal();

                // Refresh data
                this.loadTransactions();
                this.loadTitheData();

                // Clear edit mode
                window.currentEditTransactionId = undefined;

                // Clear form data
                this.formData.titheMembers = [];
                this.formData.titheTotal = 0;
                this.formData.partnershipMembers = [];
                this.formData.partnershipTotal = 0;
                this.formData.partnershipRecords = [];

            } else {
                this.showNotification(result.message || "Save failed", "error");
            }

        } catch (err) {
            console.error("Submit error details:", err);
            let errorMessage = err.message;

            // Handle specific error types
            if (err.name === 'SyntaxError' && err.message.includes('Unexpected token')) {
                errorMessage = "Server returned invalid response. Check API endpoint.";
            } else if (err.message.includes('Failed to fetch')) {
                errorMessage = "Network error. Check if server is running.";
            }

            this.showNotification(`Error: ${errorMessage}`, "error");

            // Log detailed error for debugging
            console.error('Full error object:', err);

        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    async saveTitheTransactions(financialTransactionId, fellowshipId, transactionDate) {
        if (this.formData.titheMembers.length === 0) {
            return { success: true, saved: 0 };
        }

        try {
            let savedCount = 0;

            for (const tithe of this.formData.titheMembers) {
                const titheData = {
                    financial_transaction_id: financialTransactionId,
                    member_id: tithe.memberId,
                    fellowship_id: fellowshipId,
                    amount: tithe.amount,
                    transaction_date: transactionDate,
                    payment_method: 'cash',
                    period: new Date(transactionDate).toISOString().slice(0, 7),
                    notes: `Tithe payment from member`,
                    create_financial_record: false
                };

                const response = await fetch(`${this.apiBase}/financial/tithe.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(titheData)
                });

                const result = await response.json();

                if (result.success) {
                    savedCount++;
                } else {
                    console.error('Failed to save tithe:', result.message);
                }
            }

            if (savedCount === this.formData.titheMembers.length) {
                this.showNotification(`${savedCount} tithe records saved`, "success");
            } else {
                this.showNotification(`Saved ${savedCount} of ${this.formData.titheMembers.length} tithe records`, "warning");
            }

            return { success: savedCount === this.formData.titheMembers.length, saved: savedCount };

        } catch (error) {
            console.error('Error saving tithe transactions:', error);
            this.showNotification("Failed to save tithe records", "error");
            return { success: false, saved: 0 };
        }
    }

    updateReview() {
        // Basic info
        const dateElement = document.getElementById('review-date');
        const fellowshipElement = document.getElementById('review-fellowship');
        const serviceElement = document.getElementById('review-service');
        const offeringElement = document.getElementById('review-offering');
        const titheCountElement = document.getElementById('review-tithe-count');
        const titheTotalElement = document.getElementById('review-tithe');
        const partnershipArmElement = document.getElementById('review-partnership-arm');
        const partnershipMembersElement = document.getElementById('review-partnership-members');
        const partnershipElement = document.getElementById('review-partnership');
        const totalElement = document.getElementById('review-total');

        if (dateElement) dateElement.textContent = this.formData.date || '-';

        const fellowship = document.getElementById('fellowship');
        if (fellowshipElement && fellowship) {
            const fellowshipText = fellowship.options[fellowship.selectedIndex]?.text || '-';
            fellowshipElement.textContent = fellowshipText;
        }

        const service = document.getElementById('service');
        if (serviceElement && service) {
            const serviceText = service.options[service.selectedIndex]?.text || '-';
            serviceElement.textContent = serviceText;
        }

        // Financial info
        const offeringAmountInput = document.getElementById('offering-amount');
        const offeringAmount = offeringAmountInput ? parseFloat(offeringAmountInput.value) || 0 : 0;

        if (offeringElement) offeringElement.textContent = `GHS ${offeringAmount.toFixed(2)}`;

        // Tithe info
        if (titheCountElement) titheCountElement.textContent = `${this.formData.titheMembers.length} members`;
        if (titheTotalElement) titheTotalElement.textContent = `GHS ${this.formData.titheTotal.toFixed(2)}`;

        // Partnership info
        const recordCount = (this.formData.partnershipRecords || []).length;
        const partnershipArmSelect = document.getElementById('partnership-arms');

        if (partnershipArmElement && partnershipArmSelect) {
            // Show summary of ALL arms
            if (recordCount > 0) {
                partnershipArmElement.textContent = `${recordCount} Partnership Arm(s) Added`;
            } else {
                const armText = partnershipArmSelect.options[partnershipArmSelect.selectedIndex]?.text || '-';
                partnershipArmElement.textContent = armText === 'Select Partnership Arm' ? '-' : armText;
            }
        }

        if (partnershipMembersElement) {
            const totalSavedMembers = (this.formData.partnershipRecords || []).reduce((total, record) =>
                total + (record.members?.length || 0), 0);
            const currentDraftMembers = this.formData.partnershipMembers.length;
            const totalMembers = totalSavedMembers + currentDraftMembers;

            partnershipMembersElement.textContent = `${totalMembers} (${recordCount} records)`;
        }

        if (partnershipElement) partnershipElement.textContent = `GHS ${this.formData.partnershipTotal.toFixed(2)}`;

        // Grand total
        const grandTotal = offeringAmount + this.formData.titheTotal + this.formData.partnershipTotal;
        if (totalElement) totalElement.textContent = `GHS ${grandTotal.toFixed(2)}`;
    }

    // Helper methods (kept from original)
    populateFellowships() {
        const select = document.getElementById('fellowship');
        if (select) {
            select.innerHTML = '<option value="">Select Fellowship</option>';
            this.fellowships.forEach(fellowship => {
                select.innerHTML += `<option value="${fellowship.id}">${fellowship.name}</option>`;
            });
        }
    }

    populateServices() {
        const select = document.getElementById('service');
        if (select) {
            select.innerHTML = '<option value="">Select Service</option>';
            this.services.forEach(service => {
                select.innerHTML += `<option value="${service.id}">${service.name}</option>`;
            });
        }
    }

    populatePartnershipArms() {
        const select = document.getElementById('partnership-arms');
        if (!select || !this.partnershipArms || this.partnershipArms.length === 0) return;

        select.innerHTML = '<option value="">Select Partnership Arm</option>';
        this.partnershipArms.forEach(arm => {
            const option = document.createElement('option');
            option.value = arm.id;
            option.textContent = arm.name;
            select.appendChild(option);
        });
    }

    async loadPartnershipArms() {
        try {
            const response = await fetch('../api/partnerships/read.php');
            const arms = await response.json();

            if (Array.isArray(arms)) {
                this.partnershipArms = arms;
                this.partnershipArmsData = arms;
                this.renderPartnershipArmsTable();
            }
        } catch (err) {
            console.error("Failed to load partnership arms:", err);
        }
    }

    handleDateChange(e) {
        this.formData.date = e.target.value;
        this.loadAttendanceForDate();  // ADD THIS
        this.updateReview();
    }

    handleFellowshipChange(e) {
        const fellowshipId = e.target.value;
        this.formData.fellowship = fellowshipId;

        // Only call loadMembersByFellowship if the method exists
        if (typeof this.loadMembersByFellowship === 'function') {
            this.loadMembersByFellowship(fellowshipId);
        }

        this.loadAttendanceForDate();
        this.updateReview();
    }


    async loadMembersByFellowship(fellowshipId) {
        if (!fellowshipId) {
            this.members = [];
            return;
        }

        try {
            const res = await fetch(`${this.apiBase}/financial/read.php?action=members_by_fellowship&fellowship_id=${fellowshipId}`);
            const data = await res.json();

            if (data.success) {
                this.members = data.members || [];
            } else {
                this.members = [];
                console.error('Failed to load members:', data.message);
            }
        } catch (err) {
            console.error('Error loading members:', err);
            this.members = [];
        }
    }

    async loadAttendanceData() {
        if (!this.formData.date || !this.formData.fellowship) return;

        try {
            // You'll need to implement this based on your attendance API
            const res = await fetch(`${this.apiBase}/attendance/read.php?date=${this.formData.date}&fellowship_id=${this.formData.fellowship}`);
            const data = await res.json();

            if (data.success) {
                this.attendanceData = data.data || {};
                this.updateAttendanceSummary();
            }
        } catch (err) {
            console.error("Failed to load attendance data:", err);
        }
    }

    async loadAttendanceForDate() {
        const date = document.getElementById('transaction-date')?.value;
        const fellowshipId = document.getElementById('fellowship')?.value;
        const serviceId = document.getElementById('service')?.value;

        if (!date || !fellowshipId) {
            this.updateAttendanceDisplay({ adultCount: 0, teenCount: 0, childCount: 0, firstTimersCount: 0, totalCount: 0 });
            return;
        }

        try {
            // Build URL with service_id if available
            let url = `${this.apiBase}/attendance/get_by_date.php?date=${date}&fellowship_id=${fellowshipId}`;
            if (serviceId) {
                url += `&service_id=${serviceId}`;
            }

            const response = await fetch(url);

            // Check if response is valid
            if (!response.ok) {
                console.error('Attendance API error:', response.status);
                this.updateAttendanceDisplay({ adultCount: 0, teenCount: 0, childCount: 0, firstTimersCount: 0, totalCount: 0 });
                return;
            }

            const text = await response.text();

            // Try to parse JSON
            let result;
            try {
                result = JSON.parse(text);
            } catch (parseErr) {
                console.error('Failed to parse attendance response as JSON:', text.substring(0, 100));
                this.updateAttendanceDisplay({ adultCount: 0, teenCount: 0, childCount: 0, firstTimersCount: 0, totalCount: 0 });
                return;
            }

            if (result.success && result.attendance) {
                this.attendanceData = result.attendance;
                this.updateAttendanceDisplay(result.attendance);

                // Auto-populate service if it exists and hasn't been manually set yet
                if (result.attendance.service_id && !serviceId) {
                    const serviceSelect = document.getElementById('service');
                    if (serviceSelect && (!this.formData.service || this.formData.service === '')) {
                        serviceSelect.value = result.attendance.service_id;
                        this.formData.service = result.attendance.service_id;
                    }
                }
            } else {
                this.updateAttendanceDisplay({ adultCount: 0, teenCount: 0, childCount: 0, firstTimersCount: 0, totalCount: 0 });
            }
        } catch (err) {
            console.error('Failed to load attendance:', err);
            this.updateAttendanceDisplay({ adultCount: 0, teenCount: 0, childCount: 0, firstTimersCount: 0, totalCount: 0 });
        }
    }

    updateAttendanceDisplay(data) {
        // Use IDs present in the markup and guard against missing elements
        const adultEl = document.getElementById('adult-count');
        const teenEl = document.getElementById('teen-count');
        const childEl = document.getElementById('child-count');
        const firstTimersEl = document.getElementById('first-timers-count');
        const totalEl = document.getElementById('total-count');

        const adultCount = data.adultCount || 0;
        const teenCount = data.teenCount || 0;
        const childCount = data.childCount || 0;
        const firstTimersCount = data.firstTimersCount || 0;
        // Total is already calculated from members, first timers is separate
        const totalCount = data.totalCount || (adultCount + teenCount + childCount);

        if (adultEl) adultEl.textContent = adultCount;
        if (teenEl) teenEl.textContent = teenCount;
        if (childEl) childEl.textContent = childCount;
        if (firstTimersEl) firstTimersEl.textContent = firstTimersCount;
        if (totalEl) totalEl.textContent = totalCount;
    }

    updateAttendanceSummary(adults, teens, children, firstTimers, total) {
        document.getElementById('adult-count').textContent = adults;
        document.getElementById('teen-count').textContent = teens;
        document.getElementById('child-count').textContent = children;
        document.getElementById('first-timers-count').textContent = firstTimers || 0;
        document.getElementById('total-count').textContent = total;
    }


    updateAttendanceSummary() {
        const adultCount = this.attendanceData.adults || 0;
        const teenCount = this.attendanceData.teens || 0;
        const childCount = this.attendanceData.children || 0;
        const firstTimersCount = this.attendanceData.firstTimers || 0;
        const totalCount = adultCount + teenCount + childCount + firstTimersCount;

        document.getElementById('adult-count').textContent = adultCount;
        document.getElementById('teen-count').textContent = teenCount;
        document.getElementById('child-count').textContent = childCount;
        document.getElementById('first-timers-count').textContent = firstTimersCount;
        document.getElementById('total-count').textContent = totalCount;
    }

    // Modal methods
    openModal() {
        const modal = document.getElementById('financial-modal');
        const overlay = document.getElementById('modal-overlay');

        if (modal && overlay) {
            modal.classList.add('show');
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';

            // Reset form and show first step
            this.currentStep = 1;
            this.showStep(1);
            document.getElementById('transaction-date').value = new Date().toISOString().split('T')[0];
            this.formData.date = document.getElementById('transaction-date').value;

            // Reset data arrays
            this.formData.titheMembers = [];
            this.formData.titheTotal = 0;
            this.formData.partnershipMembers = [];
            this.formData.partnershipTotal = 0;
            this.formData.partnershipRecords = [];

            // Clear UI
            this.renderTitheMembersList();
            this.renderPartnershipMembers();
            this.renderPartnershipRecords();
            this.loadPartnershipArmsDropdown();

            // Update totals
            this.updateTitheTotal();
            this.updatePartnershipTotal();
            this.updateReview();

        }
    }
    // Add these methods to FinancialManager class

    // Add these methods to FinancialManager class

    renderPartnershipMembers() {
        const container = document.getElementById('partnership-members-list');
        if (!container) return;

        // ALWAYS use the temporary partnershipMembers array, NOT the last record
        const membersToDisplay = this.formData.partnershipMembers;

        if (membersToDisplay.length === 0) {
            container.innerHTML = `
                <div class="empty-state" style="text-align: center; padding: 20px; color: var(--text-muted);">
                    <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <p>No members added yet</p>
                </div>
            `;
            return;
        }

        container.innerHTML = membersToDisplay.map((member, index) => `
            <div class="member-item" style="
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px;
                margin-bottom: 8px;
                background: var(--background-color);
                border-radius: 6px;
                border: 1px solid var(--border-color);
            ">
                <div style="flex: 1;">
                    <strong>${member.memberName}</strong>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 0.9em; color: var(--text-muted);">GHS</span>
                        <input type="number" 
                               class="partnership-member-amount" 
                               data-index="${index}"
                               value="${member.amount || 0}" 
                               step="0.01" 
                               min="0"
                               style="width: 100px; padding: 6px 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--surface-color); color: var(--text-color);"
                               placeholder="Amount">
                    </div>
                    <button type="button" 
                            class="btn-action btn-delete delete-partnership-member" 
                            data-index="${index}">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');

        // Add event listeners for amount inputs
        const membersRef = this.formData.partnershipMembers;

        container.querySelectorAll('.partnership-member-amount').forEach(input => {
            input.addEventListener('change', (e) => {
                const index = parseInt(e.target.dataset.index);
                const newAmount = parseFloat(e.target.value) || 0;

                if (index >= 0 && index < membersRef.length) {
                    membersRef[index].amount = newAmount;
                    this.updatePartnershipTotal();
                }
            });

            input.addEventListener('input', (e) => {
                const index = parseInt(e.target.dataset.index);
                const newAmount = parseFloat(e.target.value) || 0;

                if (index >= 0 && index < membersRef.length) {
                    membersRef[index].amount = newAmount;
                    this.updatePartnershipTotal();
                }
            });
        });

        // Add event listeners for delete buttons
        container.querySelectorAll('.delete-partnership-member').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault(); // Prevent bubbling
                const index = parseInt(e.target.closest('.delete-partnership-member').dataset.index);
                this.removePartnershipMember(index);
            });
        });
    }

    removePartnershipMember(index) {
        // ALWAYS remove from the temporary array
        if (index >= 0 && index < this.formData.partnershipMembers.length) {
            this.formData.partnershipMembers.splice(index, 1);
            this.updatePartnershipTotal();
            this.renderPartnershipMembers();
        }
    }

    setupPartnershipMemberDropdown() {
        const dropdown = document.getElementById('partnership-member-options');
        if (!dropdown) return;

        // Clear existing event listeners by cloning
        const newDropdown = dropdown.cloneNode(true);
        dropdown.parentNode.replaceChild(newDropdown, dropdown);

        // Add click handler for dropdown items
        newDropdown.addEventListener('click', (e) => {
            const item = e.target.closest('.dropdown-item');
            if (!item || item.textContent === 'No members found') return;

            const memberId = item.dataset.memberId;
            const memberName = item.dataset.memberName || item.textContent;

            document.getElementById('partnership-member-search').value = memberName;
            document.getElementById('partnership-member-search').dataset.selectedId = memberId;
            document.getElementById('partnership-member-search').dataset.selectedName = memberName;

            newDropdown.style.display = 'none';
        });
    }


    renderPartnershipRecords() {
        const container = document.getElementById('partnership-records-container');
        if (!container) return;

        if (!this.formData.partnershipRecords || this.formData.partnershipRecords.length === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--text-muted);">No partnership records added yet</div>';
            return;
        }

        container.innerHTML = this.formData.partnershipRecords.map((record, index) => `
        <div class="partnership-record" style="
            padding: 16px;
            margin-bottom: 12px;
            background: var(--background-color);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        ">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <div>
                    <strong>${record.armName}</strong>
                    <div style="font-size: 0.9em; color: var(--text-muted); margin-top: 4px;">
                        ${record.members.length} member(s) • Total: GHS ${record.total.toFixed(2)}
                    </div>
                </div>
                <button type="button" class="btn-icon delete-partnership-record" data-index="${index}" style="color: var(--danger); background: none; border: none; cursor: pointer; padding: 5px;">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div style="font-size: 0.9em; color: var(--text-muted);">
                Members: ${record.members.map(m => m.memberName).join(', ')}
            </div>
        </div>
    `).join('');

        // Add event listeners for delete buttons
        container.querySelectorAll('.delete-partnership-record').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = parseInt(e.target.closest('.delete-partnership-record').dataset.index);
                this.removePartnershipRecord(index);
            });
        });
    }

    // Add this method to remove partnership records
    removePartnershipRecord(index) {
        if (index >= 0 && index < this.formData.partnershipRecords.length) {
            const removedRecord = this.formData.partnershipRecords.splice(index, 1)[0];
            this.formData.partnershipTotal -= removedRecord.total || 0;

            // Update UI
            this.renderPartnershipRecords();
            this.updatePartnershipTotal();
            this.updateReview();

            this.showNotification(`Removed partnership "${removedRecord.armName}"`, 'success');
        }
    }

    addPartnershipMember() {
        const searchInput = document.getElementById('partnership-member-search');
        const memberId = searchInput?.dataset?.selectedId || null;

        if (!memberId) {
            this.showNotification("Please select a member", "error");
            return;
        }

        const memberName = searchInput.value.trim();
        const amountInput = document.getElementById('partnership-member-amount');

        // Parse the amount
        let amount = parseFloat(amountInput?.value);

        // Check if amount is a valid positive number
        if (isNaN(amount) || amount <= 0) {
            this.showNotification(`Please enter a valid partnership amount greater than 0.`, "error");
            amountInput?.focus();
            amountInput?.select();
            return;
        }

        // Check if member already exists in CURRENT partnership members list
        // Note: We user loose equality (==) to match string IDs with number IDs
        /*
        const existsInCurrent = this.formData.partnershipMembers.some(m => m.memberId == memberId);
        if (existsInCurrent) {
            this.showNotification("This member is already added to the current partnership.", "warning");
            return;
        }
        */

        const memberObj = {
            memberId: parseInt(memberId),
            memberName: memberName,
            amount: amount
        };

        // ALWAYS add to the temporary list
        this.formData.partnershipMembers.push(memberObj);

        // Update totals
        this.updatePartnershipTotal();

        // Update UI
        this.renderPartnershipMembers();
        this.updateReview();

        // Reset inputs
        if (searchInput) {
            searchInput.value = '';
            searchInput.dataset.selectedId = '';
            // Don't clear dataset.selectedName, it doesn't hurt and might be useful
        }
        if (amountInput) amountInput.value = '';

        const options = document.getElementById('partnership-member-options');
        if (options) options.style.display = 'none';

        this.showNotification("Member added successfully", "success");
    }


    addPartnershipArm() {
        const partnershipArmSelect = document.getElementById('partnership-arms');
        const selectedArmId = partnershipArmSelect.value;
        const selectedArmName = partnershipArmSelect.options[partnershipArmSelect.selectedIndex]?.text || 'Unknown Arm';

        if (!selectedArmId) {
            this.showNotification('Please select a partnership arm first', 'error');
            return;
        }

        if (this.formData.partnershipMembers.length === 0) {
            this.showNotification('Please add at least one member to the partnership', 'error');
            return;
        }

        // Create partnership record
        const partnershipRecord = {
            armId: selectedArmId,
            armName: selectedArmName,
            members: [...this.formData.partnershipMembers], // Copy the array
            total: this.formData.partnershipMembers.reduce((sum, member) => sum + (member.amount || 0), 0)
        };

        // Initialize partnershipRecords array if it doesn't exist
        if (!this.formData.partnershipRecords) {
            this.formData.partnershipRecords = [];
        }

        // Add to records
        this.formData.partnershipRecords.push(partnershipRecord);

        // Update partnership total
        this.formData.partnershipTotal = this.formData.partnershipRecords.reduce((total, record) => total + record.total, 0);

        // Clear current partnership data
        this.formData.partnershipMembers = [];

        // Clear the partnership arm selection (reset dropdown)
        partnershipArmSelect.value = '';

        // Clear member search input and amount input for next partnership arm
        const memberSearchInput = document.getElementById('partnership-member-search');
        const memberAmountInput = document.getElementById('partnership-member-amount');
        if (memberSearchInput) {
            memberSearchInput.value = '';
            memberSearchInput.dataset.selectedId = '';
            memberSearchInput.placeholder = 'Type to search members...';
        }
        if (memberAmountInput) {
            memberAmountInput.value = '';
            memberAmountInput.placeholder = '0.00';
        }

        // Close dropdown options if open
        const dropdownOptions = document.getElementById('partnership-member-options');
        if (dropdownOptions) {
            dropdownOptions.style.display = 'none';
        }

        // Update UI
        this.renderPartnershipMembers();
        this.renderPartnershipRecords();
        this.updatePartnershipTotal();
        this.updateReview();

        this.showNotification(`Partnership arm "${selectedArmName}" added with ${partnershipRecord.members.length} members`, 'success');
    }

    updatePartnershipTotal() {
        // Calculate total from partnership records
        this.formData.partnershipTotal = (this.formData.partnershipRecords || []).reduce((total, record) => {
            return total + (record.total || 0);
        }, 0);

        // Also add any current partnership members not yet saved
        const currentTotal = this.formData.partnershipMembers.reduce((total, member) => total + (member.amount || 0), 0);
        this.formData.partnershipTotal += currentTotal;

        const partnershipTotalInput = document.getElementById('partnership-grand-total');
        if (partnershipTotalInput) {
            partnershipTotalInput.value = this.formData.partnershipTotal.toFixed(2);
        }

        const currentTotalInput = document.getElementById('partnership-total');
        if (currentTotalInput) {
            currentTotalInput.value = currentTotal.toFixed(2);
        }

        this.updateReview();
    }

    closeModal() {
        const modal = document.getElementById('financial-modal');
        const overlay = document.getElementById('modal-overlay');

        if (modal && overlay) {
            modal.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = 'auto';

            // Reset modal title to default
            const header = modal.querySelector('.modal-header-content');
            if (header) {
                header.querySelector('h3').textContent = 'Record Financial Transaction';
                header.querySelector('.subtitle').textContent = 'Multi-step transaction form (5 steps)';
            }

            // Clear edit mode
            window.currentEditTransactionId = undefined;
            window.currentEditTitheId = undefined;

            // Reset form data completely
            this.formData = {
                date: '',
                fellowship: '',
                member: '',
                service: '',
                offeringAmount: 0,
                titheMembers: [],
                titheTotal: 0,
                partnershipMembers: [],
                partnershipTotal: 0,
                partnershipRecords: []
            };

            // Reset form inputs
            document.getElementById('transaction-date').value = '';
            document.getElementById('fellowship').value = '';
            document.getElementById('service').value = '';
            document.getElementById('offering-amount').value = '0';

            // Reset step
            this.currentStep = 1;

            // Clear lists
            this.renderTitheMembersList();
            this.renderPartnershipMembers();
            this.renderPartnershipRecords();
        }
    }

    // Existing methods for partnership (keep as is)
    renderTable() {
        const tbody = document.getElementById('financial-table-body');
        const emptyState = document.getElementById('table-empty-state');
        if (!tbody) return;

        const filtered = this.getFilteredData();

        // Hide/show empty state
        if (emptyState) {
            emptyState.style.display = filtered.length === 0 ? 'flex' : 'none';
        }

        tbody.innerHTML = filtered.length === 0
            ? `<tr><td colspan="8" class="empty-state">No transactions found</td></tr>`
            : filtered.map(t => `
                <tr>
                    <td>${new Date(t.transaction_date).toLocaleDateString()}</td>
                    <td>${t.description || '-'}</td>
                    <td><span class="category-badge category-${t.category}">${this.formatCategory(t.category)}</span></td>
                    <td><span class="status-badge status-${t.type}">${t.type}</span></td>
                    <td class="amount-${t.type}">GHS ${parseFloat(t.amount).toFixed(2)}</td>
                    <td><span class="method-badge">${t.payment_method || 'cash'}</span></td>
                    <td>${t.recorded_by_name || 'Admin'}</td>
                    <td>
                        <div class="action-buttons-cell">
                            <button class="btn-action edit" onclick="financialManager.editTransaction(${t.id})" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-action delete" onclick="financialManager.deleteTransaction(${t.id})" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
    }

    getFilteredData() {
        return this.financialData.filter(t => {
            if (this.filters.from && t.transaction_date < this.filters.from) return false;
            if (this.filters.to && t.transaction_date > this.filters.to) return false;
            if (this.filters.type && t.type !== this.filters.type) return false;
            if (this.filters.category && t.category !== this.filters.category) return false;
            return true;
        });
    }

    applyFilters() {
        this.filters.from = document.getElementById('date-from').value;
        this.filters.to = document.getElementById('date-to').value;
        this.filters.type = document.getElementById('transaction-type').value;
        this.filters.category = document.getElementById('financial-category').value;
        this.renderTable();
        this.updateStats();
    }

    resetFilters() {
        document.getElementById('date-from').value = '';
        document.getElementById('date-to').value = '';
        document.getElementById('transaction-type').value = '';
        document.getElementById('financial-category').value = '';
        this.filters = { from: '', to: '', type: '', category: '' };
        this.renderTable();
        this.updateStats();
    }

    updateStats() {
        const data = this.getFilteredData();
        const income = data.filter(t => t.type === 'income').reduce((s, t) => s + parseFloat(t.amount), 0);
        const expense = data.filter(t => t.type === 'expense').reduce((s, t) => s + parseFloat(t.amount), 0);
        const balance = income - expense;

        document.getElementById('total-income').textContent = `GHS ${income.toFixed(2)}`;
        document.getElementById('total-expenses').textContent = `GHS ${expense.toFixed(2)}`;
        document.getElementById('net-balance').textContent = `GHS ${balance.toFixed(2)}`;
        document.getElementById('net-balance').style.color = balance >= 0 ? '#06d6a0' : '#ff5e5b';
    }

    formatCategory(cat) {
        if (!cat) return 'Uncategorized';
        return cat.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    showNotification(msg, type = 'success') {
        const colors = {
            success: '#06d6a0',
            error: '#ff5e5b',
            info: '#3A86FF',
            warning: '#f59e0b'
        };

        const n = document.createElement('div');
        n.className = `notification ${type}`;
        n.style.cssText = `
            position:fixed;
            top:20px;
            right:20px;
            padding:16px 24px;
            border-radius:12px;
            color:white;
            z-index:10000;
            transform:translateX(400px);
            transition:all .4s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        `;
        n.style.background = colors[type] || colors.info;
        n.textContent = msg;
        document.body.appendChild(n);

        setTimeout(() => n.style.transform = 'translateX(0)', 100);
        setTimeout(() => {
            n.style.transform = 'translateX(400px)';
            setTimeout(() => n.remove(), 400);
        }, 3000);
    }

    // Add these methods for transaction CRUD
    async editTransaction(id) {
        try {
            const res = await fetch(`${this.apiBase}/financial/read.php?id=${id}`);

            // Check if response is OK
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: Failed to load transaction`);
            }

            const data = await res.json();

            if (data.success && data.data) {
                const transaction = data.data;

                // Store transaction ID for update operation
                window.currentEditTransactionId = id;

                // Open financial modal for editing
                const modal = document.getElementById('financial-modal');
                const overlay = document.getElementById('modal-overlay');
                if (modal && overlay) {
                    modal.classList.add('show');
                    overlay.classList.add('show');
                    document.body.style.overflow = 'hidden';

                    // Update modal title to indicate edit mode
                    const header = modal.querySelector('.modal-header-content');
                    if (header) {
                        header.querySelector('h3').textContent = 'Edit Transaction';
                        header.querySelector('.subtitle').textContent = `Editing transaction #${id}`;
                    }

                    // Reset form and populate with transaction data
                    document.getElementById('transaction-date').value = transaction.transaction_date || '';
                    document.getElementById('fellowship').value = transaction.fellowship_id || '';
                    document.getElementById('service').value = transaction.service_id || '';

                    // Update formData
                    this.formData.date = transaction.transaction_date || '';
                    this.formData.fellowship = transaction.fellowship_id || '';
                    this.formData.service = transaction.service_id || '';

                    // Parse offering amount from description or use total amount
                    // The description format is: "Offering: GHS 100.00, Tithe: GHS 50.00, Partnership: GHS 25.00"
                    let offeringAmount = 0;

                    if (transaction.description) {
                        const offeringMatch = transaction.description.match(/Offering:\s*GHS\s*([\d,]+\.?\d*)/i);
                        if (offeringMatch) {
                            offeringAmount = parseFloat(offeringMatch[1].replace(/,/g, ''));
                        }
                    }

                    // If no offering in description, use the total amount as offering
                    if (offeringAmount === 0 && transaction.amount) {
                        offeringAmount = parseFloat(transaction.amount) || 0;
                    }

                    document.getElementById('offering-amount').value = offeringAmount;
                    this.formData.offeringAmount = offeringAmount;

                    // Populate offering notes if available
                    const offeringNotes = transaction.offering_notes || transaction.notes || '';
                    if (document.getElementById('offering-notes')) {
                        document.getElementById('offering-notes').value = offeringNotes;
                    }

                    // Load attendance data for the selected date and fellowship
                    this.loadAttendanceForDate();

                    // Handle Tithes
                    this.formData.titheMembers = (transaction.tithes || []).map(t => ({
                        memberId: t.member_id,
                        memberName: t.member_name,
                        amount: parseFloat(t.amount) || 0
                    }));
                    this.updateTitheTotal();
                    this.renderTitheMembersList();

                    // Handle Partnerships (group by arm)
                    const groupedPartnerships = {};
                    (transaction.partnerships || []).forEach(p => {
                        const armId = p.partnership_arm_id;
                        if (!groupedPartnerships[armId]) {
                            groupedPartnerships[armId] = {
                                armId: armId,
                                armName: p.arm_name,
                                members: [],
                                total: 0
                            };
                        }
                        groupedPartnerships[armId].members.push({
                            memberId: p.member_id,
                            memberName: p.member_name,
                            amount: parseFloat(p.amount) || 0
                        });
                        groupedPartnerships[armId].total += parseFloat(p.amount) || 0;
                    });

                    this.formData.partnershipRecords = Object.values(groupedPartnerships);
                    this.updatePartnershipTotal();
                    this.renderPartnershipRecords();

                    // Update the review step with all loaded data
                    this.updateReview();

                    // Go to step 1 (Basic Info)
                    this.goToStep(1);

                    // Show success notification
                    this.showNotification("Transaction loaded for editing", "success");
                }
            } else {
                this.showNotification(data.message || "Failed to load transaction details", "error");
            }
        } catch (err) {
            console.error("Error loading transaction:", err);
            this.showNotification(`Failed to load transaction: ${err.message}`, "error");
        }
    }

    async deleteTransaction(id) {
        if (!confirm("Are you sure you want to delete this transaction? This action cannot be undone.")) {
            return;
        }

        try {
            const res = await fetch(`${this.apiBase}/financial/delete.php`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });

            const data = await res.json();

            if (data.success) {
                this.showNotification("Transaction deleted successfully", "success");
                this.loadTransactions();
            } else {
                this.showNotification(data.message || "Failed to delete transaction", "error");
            }
        } catch (err) {
            console.error("Error deleting transaction:", err);
            this.showNotification("Failed to delete transaction", "error");
        }
    }


    async searchPartnershipMembers(query) {
        const dropdown = document.getElementById('partnership-member-options');
        if (!dropdown) return;

        if (!query || query.length < 2) {
            dropdown.style.display = 'none';
            return;
        }

        const fellowshipId = document.getElementById('fellowship').value;
        if (!fellowshipId) {
            this.showNotification("Please select a fellowship first", "warning");
            return;
        }

        try {
            const res = await fetch(`../../backend/financial/read.php?action=members_by_fellowship&fellowship_id=${fellowshipId}`);
            const data = await res.json();

            if (data.success) {
                const filtered = data.members.filter(member =>
                    member.name.toLowerCase().includes(query.toLowerCase())
                );

                dropdown.innerHTML = '';

                if (filtered.length === 0) {
                    dropdown.innerHTML = '<div class="dropdown-item">No members found</div>';
                } else {
                    filtered.forEach(member => {
                        const item = document.createElement('div');
                        item.className = 'dropdown-item';
                        item.textContent = member.name;
                        item.dataset.memberId = member.id;
                        item.dataset.memberName = member.name;

                        item.addEventListener('click', () => {
                            document.getElementById('partnership-member-search').value = member.name;
                            document.getElementById('partnership-member-search').dataset.selectedId = member.id;
                            dropdown.style.display = 'none';
                        });

                        dropdown.appendChild(item);
                    });
                }

                dropdown.style.display = 'block';
            }
        } catch (err) {
            console.error("Error searching partnership members:", err);
        }
    }

    // This is a duplicate of filterPartnershipMembers but with a different name for compatibility
    // REMOVED - Use filterPartnershipMembers instead

    // Export functionality
    exportFinancialData() {
        const data = this.getFilteredData();
        if (data.length === 0) {
            this.showNotification('No data to export', 'warning');
            return;
        }

        const headers = ['Date', 'Description', 'Category', 'Type', 'Amount', 'Payment Method', 'Recorded By'];
        const csv = [
            headers.join(','),
            ...data.map(t => [
                t.transaction_date,
                `"${(t.description || '').replace(/"/g, '""')}"`,
                t.category,
                t.type,
                t.amount,
                t.payment_method,
                t.recorded_by_name
            ].join(','))
        ].join('\n');

        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `financial_records_${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        URL.revokeObjectURL(url);

        this.showNotification('Data exported successfully', 'success');
    }

    // Keep existing partnership methods


    renderPartnershipArmsTable() {
        const tbody = document.getElementById('partnership-arms-table-body');
        const emptyState = document.getElementById('partnership-arms-empty-state');
        const countElement = document.getElementById('partnership-arms-count');

        if (!tbody) return;

        if (this.partnershipArmsData.length === 0) {
            tbody.innerHTML = '';
            if (emptyState) emptyState.style.display = 'flex';
            if (countElement) countElement.textContent = '0';
            return;
        }

        if (emptyState) emptyState.style.display = 'none';
        if (countElement) countElement.textContent = this.partnershipArmsData.length.toString();

        tbody.innerHTML = this.partnershipArmsData.map(arm => {
            const isActive = arm.is_active !== undefined ? arm.is_active : 1;
            const statusClass = isActive == 1 ? 'status-income' : 'status-expense';
            const statusText = isActive == 1 ? 'Active' : 'Inactive';
            const createdDate = arm.created_at ? new Date(arm.created_at).toLocaleDateString() : 'N/A';

            return `
            <tr>
                <td>
                    <div class="arm-name">${arm.name || 'Unnamed Arm'}</div>
                </td>
                <td>
                    <div class="arm-description">${arm.description || '-'}</div>
                </td>
                <td>
                    <span class="status-badge ${statusClass}">
                        ${statusText}
                    </span>
                </td>
                <td>${createdDate}</td>
                <td>
                    <div class="action-buttons-cell">
                        <button class="btn-action edit" onclick="financialManager.editPartnershipArm(${arm.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-action delete" onclick="financialManager.deletePartnershipArm(${arm.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            `;
        }).join('');
    }

    async editPartnershipArm(armId) {
        // Try finding arm in loaded data first
        let arm = (this.partnershipArmsData || []).find(a => String(a.id) === String(armId));

        if (!arm) {
            try {
                const res = await fetch('../api/partnerships/read.php');
                const data = await res.json();
                if (Array.isArray(data)) {
                    arm = data.find(a => String(a.id) === String(armId));
                }
            } catch (err) {
                console.error('Failed to fetch partnership arms for edit:', err);
            }
        }

        if (!arm) {
            this.showNotification('Partnership arm not found', 'error');
            return;
        }

        // Open modal populated with arm data
        this.openPartnershipArmModal(arm);
    }

    openPartnershipArmModal(arm = null) {
        const modal = document.getElementById('partnership-arm-modal');
        const overlay = document.getElementById('partnership-arm-overlay');

        if (modal && overlay) {
            modal.classList.add('show');
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';

            this.editingArmId = arm ? arm.id : null;

            const formSectionTitle = document.querySelector('.form-section h4');
            const saveButton = document.getElementById('save-partnership-arm');

            if (arm) {
                if (formSectionTitle) {
                    formSectionTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Partnership Arm';
                }
                if (saveButton) {
                    saveButton.innerHTML = '<i class="fas fa-save"></i> Update Partnership Arm';
                }

                document.getElementById('partnership-arm-name').value = arm.name || '';
                document.getElementById('partnership-arm-description').value = arm.description || '';

                const activeCheckbox = document.getElementById('partnership-arm-active');
                if (activeCheckbox) {
                    activeCheckbox.checked = arm.is_active == 1;
                }
            } else {
                if (formSectionTitle) {
                    formSectionTitle.innerHTML = '<i class="fas fa-plus-circle"></i> Add New Partnership Arm';
                }
                if (saveButton) {
                    saveButton.innerHTML = '<i class="fas fa-save"></i> Save Partnership Arm';
                }

                document.getElementById('partnership-arm-form').reset();
                const activeCheckbox = document.getElementById('partnership-arm-active');
                if (activeCheckbox) {
                    activeCheckbox.checked = true;
                }
            }
        }
    }

    closePartnershipArmModal() {
        const modal = document.getElementById('partnership-arm-modal');
        const overlay = document.getElementById('partnership-arm-overlay');

        if (modal && overlay) {
            modal.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = 'auto';

            // Reset form
            const form = document.getElementById('partnership-arm-form');
            if (form) form.reset();
            this.editingArmId = null;
        }
    }

    async savePartnershipArm() {
        const name = document.getElementById('partnership-arm-name').value.trim();
        const description = document.getElementById('partnership-arm-description').value.trim();
        const isActive = document.getElementById('partnership-arm-active').checked;

        if (!name) {
            this.showNotification("Please enter a partnership arm name", "error");
            return;
        }

        const submitBtn = document.getElementById('save-partnership-arm');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        const payload = {
            name: name,
            description: description,
            is_active: isActive ? 1 : 0
        };

        const url = this.editingArmId
            ? `../../backend/partnerships/update.php?id=${this.editingArmId}`
            : '../../backend/partnerships/create.php';

        const method = this.editingArmId ? 'PUT' : 'POST';

        try {
            const res = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await res.json();
            if (result.success) {
                this.showNotification(
                    `Partnership arm ${this.editingArmId ? 'updated' : 'created'} successfully!`,
                    "success"
                );

                document.getElementById('partnership-arm-form').reset();
                document.getElementById('partnership-arm-active').checked = true;
                this.editingArmId = null;

                document.querySelector('.form-section h4').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Partnership Arm';
                document.getElementById('save-partnership-arm').innerHTML = '<i class="fas fa-save"></i> Save Partnership Arm';

                await this.loadPartnershipArmsDropdown();
            } else {
                this.showNotification(result.message || "Operation failed", "error");
            }
        } catch (err) {
            this.showNotification("Network error: " + err.message, "error");
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    async deletePartnershipArm(armId) {
        if (!confirm('Are you sure you want to delete this partnership arm? This action cannot be undone.')) {
            return;
        }

        try {
            const res = await fetch(`../../backend/partnerships/delete.php?id=${armId}`, {
                method: 'DELETE'
            });

            const result = await res.json();
            if (result.success) {
                this.showNotification("Partnership arm deleted successfully!", "success");

                await this.loadPartnershipArmsDropdown();
            } else {
                this.showNotification(result.message || "Delete failed", "error");
            }
        } catch (err) {
            this.showNotification("Network error", "error");
        }
    }
}

// Initialize
let financialManager;
document.addEventListener('DOMContentLoaded', () => {
    financialManager = new FinancialManager();
});