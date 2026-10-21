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
    <title>Link Users to Members • Church Management System</title>
    
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
        .link-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .section-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .section-card h2 {
            margin: 0 0 1.5rem 0;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .status-badge.success {
            background: var(--success-bg);
            color: var(--success-color);
        }

        .status-badge.warning {
            background: var(--warning-bg);
            color: var(--warning-color);
        }

        .grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .list-item {
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .list-item:hover {
            background: var(--bg-secondary);
        }

        .item-info h4 {
            margin: 0 0 0.25rem 0;
            color: var(--text-primary);
        }

        .item-info p {
            margin: 0;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .btn-link {
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .btn-link:hover {
            opacity: 0.9;
        }

        .btn-link:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .linked-item {
            background: var(--success-bg);
            border-color: var(--success-color);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn-secondary {
            padding: 0.75rem 1.5rem;
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-primary {
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .grid-2col {
                grid-template-columns: 1fr;
            }
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
        
        <div class="link-container">
            <h1>Link Users to Members</h1>
            <p style="color: var(--text-secondary); margin-bottom: 2rem;">
                Connect user accounts to member records to enable profile access.
            </p>

            <!-- Summary -->
            <div class="section-card">
                <h2>
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Summary
                </h2>
                <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                    <div>
                        <div style="font-size: 2rem; font-weight: bold; color: var(--primary-color);" id="linked-count">0</div>
                        <div style="color: var(--text-secondary);">Linked Accounts</div>
                    </div>
                    <div>
                        <div style="font-size: 2rem; font-weight: bold; color: var(--warning-color);" id="unlinked-users-count">0</div>
                        <div style="color: var(--text-secondary);">Unlinked Users</div>
                    </div>
                    <div>
                        <div style="font-size: 2rem; font-weight: bold; color: var(--warning-color);" id="unlinked-members-count">0</div>
                        <div style="color: var(--text-secondary);">Unlinked Members</div>
                    </div>
                </div>
            </div>

            <!-- Already Linked -->
            <div class="section-card">
                <h2>
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Already Linked
                    <span class="status-badge success" id="linked-badge">0</span>
                </h2>
                <div id="linked-list"></div>
            </div>

            <!-- Unlinked Accounts -->
            <div class="section-card">
                <h2>
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Quick Link
                    <span class="status-badge warning">Action Required</span>
                </h2>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                    Click "Link" to connect a user account to a member record.
                </p>
                <div class="grid-2col">
                    <div>
                        <h3>Unlinked Users</h3>
                        <div id="unlinked-users-list"></div>
                    </div>
                    <div>
                        <h3>Unlinked Members</h3>
                        <div id="unlinked-members-list"></div>
                    </div>
                </div>
            </div>
            </div>
        </main>
    </div>
    </div>

    <!-- Link Modal -->
    <div id="link-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Link User to Member</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="link-form">
                <div class="form-group">
                    <label for="user-select">Select User</label>
                    <select id="user-select" required>
                        <option value="">Choose a user...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="member-select">Select Member</label>
                    <select id="member-select" required>
                        <option value="">Choose a member...</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Link Accounts</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const API_BASE = '../../backend';
        let data = { unlinked_users: [], unlinked_members: [], linked: [] };

        async function loadData() {
            try {
                const response = await fetch(`${API_BASE}/users/link_member.php`, {
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    data = result.data;
                    renderAll();
                } else {
                    showNotification('Failed to load data: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error loading data:', error);
                showNotification('Error loading data', 'error');
            }
        }

        function renderAll() {
            // Update counts
            document.getElementById('linked-count').textContent = data.linked.length;
            document.getElementById('unlinked-users-count').textContent = data.unlinked_users.length;
            document.getElementById('unlinked-members-count').textContent = data.unlinked_members.length;
            document.getElementById('linked-badge').textContent = data.linked.length;

            // Render linked accounts
            const linkedList = document.getElementById('linked-list');
            if (data.linked.length === 0) {
                linkedList.innerHTML = '<div class="empty-state">No linked accounts yet</div>';
            } else {
                linkedList.innerHTML = data.linked.map(item => `
                    <div class="list-item linked-item">
                        <div class="item-info">
                            <h4>${item.username} (${item.role})</h4>
                            <p>→ ${item.first_name} ${item.last_name} (${item.email || 'No email'})</p>
                        </div>
                        <span class="status-badge success">✓ Linked</span>
                    </div>
                `).join('');
            }

            // Render unlinked users
            const unlinkedUsersList = document.getElementById('unlinked-users-list');
            if (data.unlinked_users.length === 0) {
                unlinkedUsersList.innerHTML = '<div class="empty-state">All users are linked!</div>';
            } else {
                unlinkedUsersList.innerHTML = data.unlinked_users.map(user => `
                    <div class="list-item">
                        <div class="item-info">
                            <h4>${user.username}</h4>
                            <p>Role: ${user.role} | ID: ${user.id}</p>
                        </div>
                        <button class="btn-link" onclick="openLinkModal(${user.id}, null)">Link</button>
                    </div>
                `).join('');
            }

            // Render unlinked members
            const unlinkedMembersList = document.getElementById('unlinked-members-list');
            if (data.unlinked_members.length === 0) {
                unlinkedMembersList.innerHTML = '<div class="empty-state">All members are linked!</div>';
            } else {
                unlinkedMembersList.innerHTML = data.unlinked_members.map(member => `
                    <div class="list-item">
                        <div class="item-info">
                            <h4>${member.first_name} ${member.last_name}</h4>
                            <p>${member.email || member.phone || 'No contact'} | ID: ${member.id}</p>
                        </div>
                        <button class="btn-link" onclick="openLinkModal(null, ${member.id})">Link</button>
                    </div>
                `).join('');
            }

            // Populate dropdowns
            populateDropdowns();
        }

        function populateDropdowns() {
            const userSelect = document.getElementById('user-select');
            const memberSelect = document.getElementById('member-select');

            userSelect.innerHTML = '<option value="">Choose a user...</option>' +
                data.unlinked_users.map(u => 
                    `<option value="${u.id}">${u.username} (${u.role})</option>`
                ).join('');

            memberSelect.innerHTML = '<option value="">Choose a member...</option>' +
                data.unlinked_members.map(m => 
                    `<option value="${m.id}">${m.first_name} ${m.last_name}</option>`
                ).join('');
        }

        function openLinkModal(userId = null, memberId = null) {
            if (userId) document.getElementById('user-select').value = userId;
            if (memberId) document.getElementById('member-select').value = memberId;
            document.getElementById('link-modal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('link-modal').classList.remove('show');
            document.getElementById('link-form').reset();
        }

        document.getElementById('link-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const userId = document.getElementById('user-select').value;
            const memberId = document.getElementById('member-select').value;

            if (!userId || !memberId) {
                showNotification('Please select both user and member', 'error');
                return;
            }

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                
                const response = await fetch(`${API_BASE}/users/link_member.php`, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': token
                    },
                    body: JSON.stringify({
                        user_id: parseInt(userId),
                        member_id: parseInt(memberId)
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showNotification(result.message, 'success');
                    closeModal();
                    await loadData();
                } else {
                    showNotification(result.message || 'Failed to link accounts', 'error');
                }
            } catch (error) {
                console.error('Error linking accounts:', error);
                showNotification('Error linking accounts', 'error');
            }
        });

        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Load data on page load
        document.addEventListener('DOMContentLoaded', loadData);
    </script>
    
    <!-- Load scripts in correct order -->
    <script src="../js/env.js"></script>
    <script src="../js/config.js"></script>
    <script src="../js/ui.js"></script>
    <script src="../js/main.js"></script>
</body>
</html>
