/**
 * Birthdays Management
 * Handles fetching, displaying, and wishing members
 */
document.addEventListener('DOMContentLoaded', () => {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const birthdaysGrid = document.getElementById('birthdaysGrid');
    const loadingIndicator = document.getElementById('loadingIndicator');
    
    // Modal elements
    const wishModal = document.getElementById('wish-modal');
    const wishForm = document.getElementById('wish-form');
    const memberIdInput = document.getElementById('wish-member-id');
    const memberNameDisplay = document.getElementById('wish-member-name');
    const closeBtns = wishModal.querySelectorAll('.close-modal');
    
    const baseUrl = window.APP_CONFIG?.baseUrl || window.location.protocol + '//' + window.location.host + '/church/';
    
    // Initialize
    loadBirthdays('today');
    
    // Switch filters
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const filter = btn.getAttribute('data-filter');
            loadBirthdays(filter);
        });
    });
    
    // Fetch Birthdays
    async function loadBirthdays(filter) {
        birthdaysGrid.innerHTML = '';
        loadingIndicator.style.display = 'block';
        
        try {
            const url = `${baseUrl}backend/members/birthdays_read.php?filter=${filter}`;
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'include'
            });
            
            if (!response.ok) throw new Error('Failed to fetch data');
            const res = await response.json();
            
            renderBirthdays(res.data, filter);
        } catch (error) {
            console.error('Error loading birthdays:', error);
            showNotification('Error loading birthdays. Please try again.', 'error');
        } finally {
            loadingIndicator.style.display = 'none';
        }
    }
    
    function renderBirthdays(members, filter) {
        birthdaysGrid.innerHTML = '';
        
        if (!members || members.length === 0) {
            birthdaysGrid.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-gift"></i>
                    <h3>No birthdays found</h3>
                    <p>There are no members with birthdays for the selected filter.</p>
                </div>
            `;
            return;
        }
        
        members.forEach(member => {
            const dp = member.photo_url || '../assets/avatar-placeholder.png';
            const bdate = new Date(member.birth_date);
            const dateFmt = bdate.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
            
            const isToday = filter === 'today' || member.days_until === 0;
            const cardClass = isToday ? 'birthday-card today' : 'birthday-card';
            
            const card = document.createElement('div');
            card.className = cardClass;
            
            let html = `
                ${isToday ? '<div class="card-badge">Today</div>' : ''}
                <img src="${dp}" alt="Photo" class="avatar" loading="lazy" onerror="this.src='../assets/avatar-placeholder.png'">
                <h3 class="member-name">${member.first_name} ${member.last_name}</h3>
            `;
            
            if (member.fellowship_name) {
                html += `<div class="member-details">
                    <span><i class="fas fa-church"></i> ${member.fellowship_name}</span>
                </div>`;
            }
            
            html += `
                <div class="birthday-info">
                    <span class="birthday-date"><i class="fas fa-calendar-star"></i> ${dateFmt}</span>
                    <div class="age-turning">Turning ${member.age_turning}</div>
                </div>
                <div class="action-buttons">
                    <button class="btn-wish send-wish-btn" 
                        data-id="${member.id}" 
                        data-name="${member.first_name} ${member.last_name}"
                        ${!member.email ? 'disabled title="No email address on file"' : ''}>
                        <i class="fas fa-envelope"></i> Send Wish
                    </button>
                    ${member.phone ? `<a href="tel:${member.phone}" class="btn-secondary" style="border-radius:6px;width:auto;flex:none;display:flex;align-items:center;padding:10px;"><i class="fas fa-phone"></i></a>` : ''}
                </div>
            `;
            
            card.innerHTML = html;
            birthdaysGrid.appendChild(card);
        });
        
        // Add listeners to buttons
        document.querySelectorAll('.send-wish-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.getAttribute('data-id');
                const name = e.currentTarget.getAttribute('data-name');
                openWishModal(id, name);
            });
        });
    }
    
    // Modal logic
    function openWishModal(id, name) {
        memberIdInput.value = id;
        memberNameDisplay.textContent = name;
        wishModal.classList.add('open');
    }
    
    function closeWishModal() {
        wishModal.classList.remove('open');
        wishForm.reset();
    }
    
    closeBtns.forEach(btn => btn.addEventListener('click', closeWishModal));
    wishModal.addEventListener('click', (e) => {
        if (e.target === wishModal) closeWishModal();
    });
    
    // Handle form submit
    wishForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        const submitBtn = document.getElementById('submit-wish-btn');
        const spinner = document.getElementById('wish-spinner');
        const text = document.getElementById('wish-submit-text');
        
        submitBtn.disabled = true;
        spinner.style.display = 'inline-block';
        text.textContent = 'Sending...';
        
        const payload = {
            member_id: memberIdInput.value,
            message: document.getElementById('wish-message').value
        };
        
        try {
            const response = await fetch(`${baseUrl}backend/members/send_birthday_wish.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': token
                },
                body: JSON.stringify(payload),
                credentials: 'include'
            });
            
            const res = await response.json();
            
            if (response.ok) {
                if(res.errors && res.errors.length > 0) {
                     showNotification(res.errors[0], 'error');
                } else {
                     showNotification('Birthday wish sent successfully!', 'success');
                     closeWishModal();
                }
            } else {
                throw new Error(res.error || 'Failed to send wish');
            }
        } catch (error) {
            console.error('Wish Error:', error);
            showNotification(error.message, 'error');
        } finally {
            submitBtn.disabled = false;
            spinner.style.display = 'none';
            text.innerHTML = '<i class="fas fa-paper-plane"></i> Send Wish';
        }
    });

    // Helper for notifications (fallback if global showNotification is missing)
    function showNotification(message, type = 'success') {
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type);
            return;
        }
        alert(message);
    }
});
