<?php
/**
 * Link Users to Members
 *
 * Admin-only utility that connects a system user account to a member record so
 * the member can reach their profile. Everything on the page is driven by the
 * inline script in $linkScript (there is no separate js/link_members.js).
 *
 * Reached from the "Link to Members" button on the Users page, not from the
 * sidebar itself.
 *
 * Changes made while moving this onto the shared layout:
 *   - the inline <style> block became css/link_members.css (verbatim rules);
 *   - the malformed markup was corrected. The original nested
 *     `<div class="link-container">` inside `<main>` but closed three `</div>`s
 *     for only two openings, so `#app` closed early; the structure is now
 *     balanced and renders the same;
 *   - the topnav stays nested inside <main class="content"> via
 *     'topnavPlacement' => 'in-content', because that is how this page ships
 *     today and moving it would change the layout;
 *   - the hardcoded `const API_BASE = '../../backend'` became
 *     window.getApiUrl(). That constant shadowed the window.API_BASE published
 *     by outputApiConfig() and only resolved correctly because of this file's
 *     directory depth.
 *
 * The .modal / .close-btn / .form-group / .empty-state rules for this page come
 * from css/link_members.css and differ from the shared ones in style.css, so the
 * modal markup below is intentionally left page-local.
 */

require_once __DIR__ . '/../layout/bootstrap.php';

/** The "Link User to Member" dialog, shown by adding `.show` to #link-modal. */
$linkModal = <<<'HTML'
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
HTML;

/**
 * Loads the link data and renders the three lists.
 *
 * Kept inline because the list rows it renders use `onclick="openLinkModal(...)"`
 * attributes, so these functions must stay in global scope.
 */
$linkScript = <<<'JS'
<script>
    let data = { unlinked_users: [], unlinked_members: [], linked: [] };

    async function loadData() {
        try {
            const response = await fetch(window.getApiUrl('users/link_member.php'), {
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
                        <p>&rarr; ${item.first_name} ${item.last_name} (${item.email || 'No email'})</p>
                    </div>
                    <span class="status-badge success">&check; Linked</span>
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

            const response = await fetch(window.getApiUrl('users/link_member.php'), {
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
JS;

$page = layout_page_init([
    'id' => 'link_members',
    'title' => 'Link Users to Members',
    'titleSuffix' => 'Church Management System',
    'permission' => 'admin_only',
    'roles' => ['admin'],
    'fontWeights' => '300;400;500;600;700',
    'fontAwesome' => true,
    'css' => ['css/style.css', 'css/screens.css', 'css/link_members.css'],
    'scripts' => ['js/ui.js', 'js/main.js'],
    'footer' => $linkScript,
    'overlays' => $linkModal,
    // This page nests its topnav inside <main> and has no .page-content wrapper.
    'topnavPlacement' => 'in-content',
    'contentWrapper' => false,
]);

require_once LAYOUT_DIR . '/head.php';
?>

<?php require_once LAYOUT_DIR . '/body-open.php'; ?>

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

<?php require_once LAYOUT_DIR . '/body-close.php'; ?>
