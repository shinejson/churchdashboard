class ServiceManager {
    constructor() {
        this.services = [];
        this.init();
    }

    init() {
        this.setupEventListeners();
    }

    setupEventListeners() {
        const manageServicesBtn = document.getElementById('manage-services-btn');
        const addServiceBtn = document.getElementById('add-service-btn');
        const addServiceQuick = document.getElementById('add-service-quick');
        const addServiceQuickModal = document.getElementById('add-service-quick-modal');
        const closeServiceModal = document.getElementById('close-service-modal');
        const cancelService = document.getElementById('cancel-service');
        const serviceForm = document.getElementById('service-form');
        
        // Refresh buttons
        const refreshServices = document.getElementById('refresh-services');
        const refreshServicesModal = document.getElementById('refresh-services-modal');

        if (addServiceBtn) addServiceBtn.addEventListener('click', () => this.openModal());
        if (addServiceQuick) addServiceQuick.addEventListener('click', () => this.openModal());
        if (addServiceQuickModal) addServiceQuickModal.addEventListener('click', () => this.openModal());
        if (refreshServices) refreshServices.addEventListener('click', () => this.refreshServices());
        if (refreshServicesModal) refreshServicesModal.addEventListener('click', () => this.refreshServices());
        if (closeServiceModal) closeServiceModal.addEventListener('click', () => this.closeModal());
        if (cancelService) cancelService.addEventListener('click', () => this.closeModal());
        if (serviceForm) serviceForm.addEventListener('submit', (e) => this.saveService(e));
        
        if (manageServicesBtn) {
            manageServicesBtn.addEventListener('click', () => this.openServiceModal());
        }
    }

    async refreshServices() {
        try {
            this.showNotification('Refreshing services...', 'info');
            
            // Refresh services in attendance manager
            if (window.attendanceManager && typeof window.attendanceManager.loadServices === 'function') {
                await window.attendanceManager.loadServices();
                this.showNotification('Services refreshed successfully!', 'success');
            } else {
                // Fallback: reload the page
                location.reload();
            }
        } catch (error) {
            console.error('Error refreshing services:', error);
            this.showNotification('Error refreshing services', 'error');
        }
    }

    openServiceModal(serviceId = null) {
        // Use the attendance manager's method instead
        if (window.attendanceManager && typeof window.attendanceManager.openServiceModal === 'function') {
            window.attendanceManager.openServiceModal(serviceId);
        }
    }

    openModal(service = null) {
        const modal = document.getElementById('service-modal');
        if (!modal) {
            console.error('Service modal not found');
            return;
        }

        const title = modal.querySelector('h3');
        const nameInput = document.getElementById('service-name');
        const form = document.getElementById('service-form');

        if (!title || !nameInput || !form) {
            console.error('Required service modal elements not found');
            return;
        }

        // Clear previous hidden ID
        const old = form.querySelector('#service-id-hidden');
        if (old) old.remove();

        if (service) {
            title.textContent = 'Edit Service';
            nameInput.value = service.name;
            form.insertAdjacentHTML('afterbegin', 
                `<input type="hidden" id="service-id-hidden" name="id" value="${service.id}">`);
        } else {
            title.textContent = 'Add New Service';
            nameInput.value = '';
        }

        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    closeModal() {
        const modal = document.getElementById('service-modal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    async saveService(e) {
        e.preventDefault();
        
        const nameInput = document.getElementById('service-name');
        if (!nameInput) {
            this.showNotification('Service name input not found', 'error');
            return;
        }

        const name = nameInput.value.trim();
        const existingId = document.querySelector('#service-id-hidden')?.value;

        if (!name) {
            this.showNotification('Service name is required', 'error');
            return;
        }

        const payload = { name };
        if (existingId) payload.id = existingId;

        try {
            const response = await fetch('../../backend/ajax/services_crud.php', {
                method: existingId ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload)
            });

            // Check if response is JSON
            const text = await response.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (parseError) {
                console.error('Failed to parse response:', text);
                this.showNotification('Server response error', 'error');
                return;
            }

            if (result.success) {
                this.showNotification(
                    existingId ? 'Service updated successfully!' : 'Service added successfully!', 
                    'success'
                );
                this.closeModal();
                
                // Refresh services in attendance manager
                if (window.attendanceManager && typeof window.attendanceManager.loadServices === 'function') {
                    await window.attendanceManager.loadServices();
                }
            } else {
                this.showNotification(result.message || 'Failed to save service', 'error');
            }
        } catch (error) {
            console.error('Error saving service:', error);
            this.showNotification('Network error saving service', 'error');
        }
    }

    showNotification(message, type = 'info') {
        // Use attendance manager's notification system if available
        if (window.attendanceManager && typeof window.attendanceManager.showNotification === 'function') {
            window.attendanceManager.showNotification(message, type);
        } else {
            // Fallback notification
            const alertType = type === 'error' ? 'alert-danger' : 
                            type === 'success' ? 'alert-success' : 'alert-info';
            const alert = document.createElement('div');
            alert.className = `alert ${alertType} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
    }
}

// Initialize
let serviceManager;
document.addEventListener('DOMContentLoaded', () => {
    serviceManager = new ServiceManager();
});