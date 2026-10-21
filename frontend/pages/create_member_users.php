<?php
require_once '../../backend/config/security.php';
secure_session_start();

// Only admins can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/frontend/pages/login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Member Users • Church Management System</title>
    
    <!-- API Configuration - MUST load first -->
    <?php require_once __DIR__ . '/../includes/config.php'; outputApiConfig(); ?>
    
    <!-- Main Stylesheets -->
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/screens.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <?php set_csrf_meta_tag(); ?>
    
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .card h2 {
            margin: 0 0 1rem 0;
            color: var(--text-primary);
        }

        .members-grid {
            display: grid;
            gap: 1rem;
            max-height: 600px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }

        .members-grid::-webkit-scrollbar {
            width: 8px;
        }

        .members-grid::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 4px;
        }

        .members-grid::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }

        .members-grid::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }

        .member-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }

        .member-card:hover {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
        }

        .member-info h3 {
            margin: 0 0 0.5rem 0;
            color: var(--text-primary);
        }

        .member-info p {
            margin: 0.25rem 0;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .btn-create {
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-create:hover {
            opacity: 0.9;
        }

        .btn-create:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .form-inline {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .form-inline input {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }

        .notification.success {
            background: var(--success-color);
            color: white;
        }

        .notification.error {
            background: var(--error-color);
            color: white;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .filters-section {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            pointer-events: none;
            z-index: 2;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .filter-group label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .filter-group select {
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
            min-width: 180px;
            cursor: pointer;
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            background: var(--bg-secondary);
            border-radius: 8px;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .results-count {
            font-weight: 600;
            color: var(--text-primary);
        }

        .clear-filters {
            padding: 0.5rem 1rem;
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .clear-filters:hover {
            background: var(--primary-color);
            color: white;
        }

        @media (max-width: 768px) {
            .filters-section {
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }

            .filter-group {
                width: 100%;
            }

            .filter-group select {
                width: 100%;
            }

            .member-card {
                flex-direction: column;
                gap: 1rem;
            }

            .form-inline {
                flex-direction: column;
                width: 100%;
            }

            .form-inline input,
            .form-inline button {
                width: 100% !important;
            }

            .stats {
                flex-direction: column;
            }
        }

        .stats {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            flex: 1;
            padding: 1.5rem;
            background: var(--bg-secondary);
            border-radius: 8px;
            text-align: center;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <script>
        // Restore theme before page renders to avoid flash
        const saved = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const isDark = saved === 'dark' || (!saved && prefersDark);
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    </script>

    <div id="app">
        <?php include '../sidebar.php'; ?>
        
        <main class="content">
            <?php include '../topnav.php'; ?>
        
        <div class="container">
            <h1>Create Member User Accounts</h1>
            <p style="color: var(--text-secondary); margin-bottom: 2rem;">
                Create login accounts for members so they can access their profiles.
            </p>

            <div class="stats">
                <div class="stat-box">
                    <div class="stat-value" id="total-members">0</div>
                    <div class="stat-label">Members Without Accounts</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value" id="created-count">0</div>
                    <div class="stat-label">Accounts Created</div>
                </div>
            </div>

            <div class="card">
                <h2>Members Without User Accounts</h2>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                    Enter a username and password for each member to create their login account.
                </p>
                
                <!-- Filters Section -->
                <div class="filters-section">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input 
                            type="text" 
                            id="member-search-input" 
                            placeholder="Search by name, email, or phone..."
                            oninput="filterMembers()"
                        >
                    </div>
                    
                    <div class="filter-group">
                        <label for="fellowship-filter">Fellowship</label>
                        <select id="fellowship-filter" onchange="filterMembers()">
                            <option value="">All Fellowships</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="category-filter">Category</label>
                        <select id="category-filter" onchange="filterMembers()">
                            <option value="">All Categories</option>
                            <option value="adult">Adult</option>
                            <option value="youth">Youth</option>
                            <option value="teen">Teen</option>
                            <option value="child">Child</option>
                        </select>
                    </div>
                    
                    <button class="clear-filters" onclick="clearFilters()">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                </div>
                
                <!-- Results Info -->
                <div class="results-info" id="results-info">
                    <span>Showing <span class="results-count" id="showing-count">0</span> of <span class="results-count" id="total-count">0</span> members</span>
                </div>
                
                <div id="members-list" class="members-grid"></div>
            </div>
        </div>
            </div>
        </main>
    </div>

    <script>
        const API_BASE = '../../backend';
        let members = [];
        let filteredMembers = [];
        let createdCount = 0;
        let fellowships = [];

        async function loadMembers() {
            try {
                const response = await fetch(`${API_BASE}/users/create_member_user.php`, {
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    members = result.data;
                    filteredMembers = [...members];
                    
                    // Extract unique fellowships
                    fellowships = [...new Set(members.map(m => m.fellowship_name).filter(Boolean))];
                    populateFellowshipFilter();
                    
                    renderMembers();
                } else {
                    showNotification('Failed to load members: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error loading members:', error);
                showNotification('Error loading members', 'error');
            }
        }

        function populateFellowshipFilter() {
            const select = document.getElementById('fellowship-filter');
            select.innerHTML = '<option value="">All Fellowships</option>';
            
            fellowships.sort().forEach(fellowship => {
                const option = document.createElement('option');
                option.value = fellowship;
                option.textContent = fellowship;
                select.appendChild(option);
            });
        }

        function filterMembers() {
            const searchTerm = document.getElementById('member-search-input').value.toLowerCase();
            const fellowshipFilter = document.getElementById('fellowship-filter').value;
            const categoryFilter = document.getElementById('category-filter').value;

            filteredMembers = members.filter(member => {
                // Search filter
                const matchesSearch = !searchTerm || 
                    member.first_name.toLowerCase().includes(searchTerm) ||
                    member.last_name.toLowerCase().includes(searchTerm) ||
                    (member.email && member.email.toLowerCase().includes(searchTerm)) ||
                    (member.phone && member.phone.includes(searchTerm));

                // Fellowship filter
                const matchesFellowship = !fellowshipFilter || 
                    member.fellowship_name === fellowshipFilter;

                // Category filter
                const matchesCategory = !categoryFilter || 
                    (member.category && member.category.toLowerCase() === categoryFilter);

                return matchesSearch && matchesFellowship && matchesCategory;
            });

            renderMembers();
        }

        function clearFilters() {
            document.getElementById('member-search-input').value = '';
            document.getElementById('fellowship-filter').value = '';
            document.getElementById('category-filter').value = '';
            filterMembers();
        }

        function renderMembers() {
            document.getElementById('total-members').textContent = members.length;
            document.getElementById('showing-count').textContent = filteredMembers.length;
            document.getElementById('total-count').textContent = members.length;
            
            const container = document.getElementById('members-list');
            
            if (members.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <h3>🎉 All members have user accounts!</h3>
                        <p>There are no members without user accounts.</p>
                    </div>
                `;
                return;
            }

            if (filteredMembers.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <h3>No members found</h3>
                        <p>Try adjusting your search or filters.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = filteredMembers.map(member => `
                <div class="member-card" id="member-${member.id}">
                    <div class="member-info">
                        <h3>${member.first_name} ${member.last_name}</h3>
                        <p><strong>Fellowship:</strong> ${member.fellowship_name || 'Not assigned'}</p>
                        ${member.category ? `<p><strong>Category:</strong> ${member.category}</p>` : ''}
                        <p><strong>Email:</strong> ${member.email || 'Not provided'}</p>
                        <p><strong>Phone:</strong> ${member.phone || 'Not provided'}</p>
                    </div>
                    <div class="form-inline">
                        <input 
                            type="text" 
                            id="username-${member.id}" 
                            placeholder="Username"
                            value="${generateUsername(member)}"
                            style="width: 150px;"
                        >
                        <input 
                            type="password" 
                            id="password-${member.id}" 
                            placeholder="Password"
                            value="password123"
                            style="width: 120px;"
                        >
                        <button 
                            class="btn-create" 
                            onclick="createUser(${member.id})"
                            id="btn-${member.id}"
                        >
                            Create Account
                        </button>
                    </div>
                </div>
            `).join('');
        }

        function generateUsername(member) {
            // Generate username from first and last name
            const first = member.first_name.toLowerCase().replace(/\s+/g, '');
            const last = member.last_name.toLowerCase().replace(/\s+/g, '');
            return `${first}.${last}`;
        }

        async function createUser(memberId) {
            const username = document.getElementById(`username-${memberId}`).value.trim();
            const password = document.getElementById(`password-${memberId}`).value.trim();
            const button = document.getElementById(`btn-${memberId}`);

            if (!username || !password) {
                showNotification('Please enter username and password', 'error');
                return;
            }

            if (password.length < 6) {
                showNotification('Password must be at least 6 characters', 'error');
                return;
            }

            button.disabled = true;
            button.textContent = 'Creating...';

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                
                const response = await fetch(`${API_BASE}/users/create_member_user.php`, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': token
                    },
                    body: JSON.stringify({
                        username: username,
                        password: password,
                        member_id: memberId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showNotification(result.message, 'success');
                    
                    // Remove the member card
                    const card = document.getElementById(`member-${memberId}`);
                    card.style.opacity = '0';
                    setTimeout(() => {
                        card.remove();
                        members = members.filter(m => m.id !== memberId);
                        filteredMembers = filteredMembers.filter(m => m.id !== memberId);
                        createdCount++;
                        document.getElementById('created-count').textContent = createdCount;
                        document.getElementById('total-members').textContent = members.length;
                        document.getElementById('showing-count').textContent = filteredMembers.length;
                        document.getElementById('total-count').textContent = members.length;
                        
                        if (members.length === 0) {
                            renderMembers();
                        }
                    }, 300);
                } else {
                    showNotification(result.message || 'Failed to create account', 'error');
                    button.disabled = false;
                    button.textContent = 'Create Account';
                }
            } catch (error) {
                console.error('Error creating user:', error);
                showNotification('Error creating account', 'error');
                button.disabled = false;
                button.textContent = 'Create Account';
            }
        }

        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Load members on page load
        document.addEventListener('DOMContentLoaded', loadMembers);
    </script>
    
    <!-- Load scripts in correct order -->
    <script src="../js/env.js"></script>
    <script src="../js/config.js"></script>
    <script src="../js/ui.js"></script>
    <script src="../js/main.js"></script>
</body>
</html>
