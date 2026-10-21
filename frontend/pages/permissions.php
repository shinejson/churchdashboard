<?php
require_once  '../../backend/config/security.php';
secure_session_start();
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}
init_csrf();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permissions - Church System</title>
    
    <!-- API Configuration - MUST load first -->
    <?php require_once __DIR__ . '/../includes/config.php'; outputApiConfig(); ?>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/icons.css">
    <?php set_csrf_meta_tag(); ?>
    
    <!-- Environment and Configuration - MUST load before other scripts -->
    <script src="../js/env.js"></script>
    <script src="../js/config.js"></script>
    
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
</head>
<body>
    <script>
        const saved = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const isDark = saved === 'dark' || (!saved && prefersDark);
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    </script>
    <?php include '../topnav.php'; ?>
    <div id="app">
        <?php include '../sidebar.php'; ?>
        <main class="content">
            <div class="page-header">
                <h2>Role Permissions</h2>
            </div>
            <div id="permissions-editor">
                <div class="loading">
                    <div class="loading-spinner"></div> Loading permissions...
                </div>
            </div>
        </main>
    </div>

    <script>
        let modules = [];
        let roles = [];

        async function loadRoles() {
            try {
                const resp = await fetch('../../backend/permission/read.php');
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
            document.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                const role = cb.dataset.role;
                const module = cb.dataset.module;
                const allowed = cb.checked ? 1 : 0;
                changes.push({ role, module, allowed });
            });

            try {
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const resp = await fetch('../../backend/permission/update.php', {
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
    <script src="../js/main.js?v=<?php echo time(); ?>"></script>
</body>
</html>