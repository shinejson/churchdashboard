<?php
/**
 * Create Member User Accounts
 *
 * Admin-only utility that bulk-creates login accounts for members who do not
 * have one yet. Everything is driven by the inline script in $createScript
 * (there is no separate js/create_member_users.js).
 *
 * Reached from the Members screen, not from the sidebar itself.
 *
 * Changes made while moving this onto the shared layout:
 *   - the inline <style> block became css/create_member_users.css (verbatim
 *     rules);
 *   - the malformed markup was corrected. The original opened `<div
 *     class="container">` inside `<main>` but closed two extra `</div>`s, so
 *     `#app` closed early; the structure is now balanced and renders the same;
 *   - the topnav stays nested inside <main class="content"> via
 *     'topnavPlacement' => 'in-content', because that is how this page ships
 *     today and moving it would change the layout;
 *   - the hardcoded `const API_BASE = '../../backend'` became
 *     window.getApiUrl(). That constant shadowed the window.API_BASE published
 *     by outputApiConfig() and only resolved correctly because of this file's
 *     directory depth.
 *
 * The .search-box / .filter-group / .empty-state / .stat-value rules here come
 * from css/create_member_users.css and deliberately differ from the shared ones
 * in style.css, so the filters markup below is left page-local.
 */

require_once __DIR__ . '/../layout/bootstrap.php';

/**
 * Loads members without accounts, filters them, and creates accounts.
 *
 * Kept inline because the rendered member cards use onclick="createUser(...)" /
 * oninput="filterMembers()" attributes, so these functions must stay in global
 * scope.
 */
$createScript = <<<'JS'
<script>
    let members = [];
    let filteredMembers = [];
    let createdCount = 0;
    let fellowships = [];

    async function loadMembers() {
        try {
            const response = await fetch(window.getApiUrl('users/create_member_user.php'), {
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
                    <h3>&#127881; All members have user accounts!</h3>
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

            const response = await fetch(window.getApiUrl('users/create_member_user.php'), {
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
JS;

$page = layout_page_init([
    'id' => 'create_member_users',
    'title' => 'Create Member Users',
    'titleSuffix' => 'Church Management System',
    'permission' => 'admin_only',
    'roles' => ['admin'],
    'fontWeights' => '300;400;500;600;700',
    'fontAwesome' => true,
    'css' => ['css/style.css', 'css/screens.css', 'css/create_member_users.css'],
    'scripts' => ['js/ui.js', 'js/main.js'],
    'footer' => $createScript,
    // This page nests its topnav inside <main> and has no .page-content wrapper.
    'topnavPlacement' => 'in-content',
    'contentWrapper' => false,
]);

require_once LAYOUT_DIR . '/head.php';
?>

<?php require_once LAYOUT_DIR . '/body-open.php'; ?>

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

<?php require_once LAYOUT_DIR . '/body-close.php'; ?>
