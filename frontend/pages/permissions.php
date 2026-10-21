<?php
/**
 * Role Permissions
 *
 * Admin-only matrix of role => module => allowed, rendered client-side by the
 * script in $permissionsScript against backend/permission/read.php and
 * backend/permission/update.php.
 *
 * Reached from the profile dropdown in topnav.php, not from the sidebar itself.
 *
 * Two bugs in the original page are fixed by moving it onto the shared layout:
 *   1. The non-admin guard redirected to `index.php`, which does not exist
 *      inside frontend/pages/ (so it 404'd). The layout redirects to the real
 *      application root instead.
 *   2. The page called init_csrf() but never emitted the CSRF meta tag, while
 *      its own save handler reads `meta[name="csrf-token"]` - saving therefore
 *      threw "Cannot read properties of null". The layout always emits the tag,
 *      so the save path now works.
 *
 * The API calls also moved from hard-coded `../../backend/...` relative paths to
 * the `window.getApiUrl()` helper the layout publishes, so they no longer depend
 * on this file's directory depth.
 */

require_once __DIR__ . '/../layout/bootstrap.php';

/** Page-specific styles for the permissions matrix and its toggle switches. */
$permissionsStyles = <<<'CSS'
<style>
    .perms-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    .perms-table th, .perms-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border); }
    .perms-table th { background: var(--card-bg); font-weight: 600; }
    .perms-table tr:hover { background: var(--hover-bg); }
    .toggle { position: relative; display: inline-block; width: 44px; height: 24px; }
    .toggle input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #ccc; transition: .3s; border-radius: 34px; }
    .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: white; transition: .3s; border-radius: 50%; }
    input:checked + .slider { background: var(--primary-color); }
    input:checked + .slider:before { transform: translateX(20px); }
    .save-btn { margin-top: 1.5rem; }
</style>
CSS;

/**
 * Builds and wires the permissions table.
 *
 * Kept inline (as it was) because it is the whole behaviour of this page and is
 * not shared with any other screen. Endpoint URLs now go through getApiUrl()
 * rather than relative `../../backend` paths.
 */
$permissionsScript = <<<'JS'
<script>
    let modules = [];
    let roles = [];

    async function loadRoles() {
        try {
            const resp = await fetch(window.getApiUrl('permission/read.php'), { credentials: 'include' });
            const data = await resp.json();
            roles = Object.keys(data).sort();
            modules = data[roles[0]] ? Object.keys(data[roles[0]]).sort() : [];
            return data;
        } catch (e) {
            console.error('Failed to load roles:', e);
            return {};
        }
    }

    async function loadPermissions() {
        const container = document.getElementById('permissions-editor');
        try {
            const data = await loadRoles();
            renderTable(data);
        } catch (e) {
            container.innerHTML = `<p class="error">Failed to load permissions.</p>`;
        }
    }

    function renderTable(perms) {
        let html = `
            <table class="perms-table">
                <thead>
                    <tr>
                        <th>Module</th>
                        ${roles.map(r => `<th>${r.charAt(0).toUpperCase() + r.slice(1)}</th>`).join('')}
                    </tr>
                </thead>
                <tbody>
        `;

        modules.forEach(mod => {
            html += `<tr>
                <td><strong>${mod.charAt(0).toUpperCase() + mod.slice(1)}</strong></td>`;
            roles.forEach(role => {
                const allowed = perms[role]?.[mod] ?? false;
                html += `
                    <td>
                        <label class="toggle">
                            <input type="checkbox" data-role="${role}" data-module="${mod}" ${allowed ? 'checked' : ''}>
                            <span class="slider"></span>
                        </label>
                    </td>`;
            });
            html += `</tr>`;
        });

        html += `</tbody></table>
            <button class="btn-primary save-btn" id="save-permissions">Save Changes</button>
            <p id="save-status" style="margin-top:0.5rem;"></p>`;

        document.getElementById('permissions-editor').innerHTML = html;

        document.getElementById('save-permissions').addEventListener('click', savePermissions);
    }

    async function savePermissions() {
        const status = document.getElementById('save-status');
        const changes = [];
        document.querySelectorAll('.perms-table input[type="checkbox"]').forEach(cb => {
            const role = cb.dataset.role;
            const module = cb.dataset.module;
            const allowed = cb.checked ? 1 : 0;
            changes.push({ role, module, allowed });
        });

        try {
            const token = document.querySelector('meta[name="csrf-token"]').content;
            const resp = await fetch(window.getApiUrl('permission/update.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
                credentials: 'include',
                body: JSON.stringify(changes)
            });
            const result = await resp.json();
            status.textContent = result.message;
            status.style.color = resp.ok ? 'var(--success)' : 'var(--danger)';
            if (resp.ok) setTimeout(() => location.reload(), 1000);
        } catch (e) {
            status.textContent = 'Save failed.';
            status.style.color = 'var(--danger)';
        }
    }

    document.addEventListener('DOMContentLoaded', loadPermissions);
</script>
JS;

$page = layout_page_init([
    'id' => 'permissions',
    'title' => 'Permissions',
    'titleSuffix' => 'Church System',
    // Hard admin gate, matching the original `$_SESSION['role'] !== 'admin'`.
    'permission' => 'admin_only',
    'roles' => ['admin'],
    'css' => ['css/style.css', 'css/icons.css'],
    'scripts' => ['js/main.js'],
    'head' => $permissionsStyles,
    'footer' => $permissionsScript,
    // The original page puts .page-header and the editor directly inside
    // <main class="content">, with no .page-content wrapper.
    'contentWrapper' => false,
]);

require_once LAYOUT_DIR . '/head.php';
?>

<?php require_once LAYOUT_DIR . '/body-open.php'; ?>

                <?= layout_page_header(['title' => 'Role Permissions']) ?>

                <div id="permissions-editor">
                    <div class="loading">
                        <div class="loading-spinner"></div> Loading permissions...
                    </div>
                </div>

<?php require_once LAYOUT_DIR . '/body-close.php'; ?>
