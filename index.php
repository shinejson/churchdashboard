<?php
require_once 'backend/config/security.php';
secure_session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/frontend/pages/login.php');
    exit();
}
$user_role = $_SESSION['role'] ?? 'guest';
if ($user_role === 'member') {
    header('Location: ' . SITE_URL . '/frontend/pages/profile.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Church Management System</title>
    <meta name="description" content="Grace Community Church — Management Dashboard">
    <link rel="stylesheet" href="frontend/css/style.css">
    <link rel="stylesheet" href="frontend/css/screens.css">
    <link rel="stylesheet" href="frontend/css/icons.css">
    <link rel="stylesheet" href="frontend/css/dashboard.css?v=<?= time() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (function(){
            const saved = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
</head>
<body>
    <?php include __DIR__ . '/frontend/topnav.php'; ?>

    <div id="app">
        <?php include __DIR__ . '/frontend/sidebar.php'; ?>

        <main class="content">
            <div id="dashboard-content" class="page-content active dashboard-page">

                <!-- ===================== Header ===================== -->
                <header class="dashboard-header" aria-label="Dashboard header">
                    <div class="dashboard-header-left">
                        <div class="eyebrow"><span class="dot" aria-hidden="true"></span> Overview • Live</div>
                        <h1 class="dashboard-title">Church Dashboard</h1>
                        <p class="dashboard-subtitle">Welcome back — track members, attendance and giving at a glance.</p>
                    </div>
                    <div class="dashboard-header-right">
                        <div class="date-pill" id="current-date" aria-live="polite">Loading date…</div>
                        <div class="quick-actions">
                            <button id="dashboard-refresh" class="btn-sm" type="button" aria-label="Refresh dashboard">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-9-9c2.5 0 4.7 1 6.3 2.7"/><path d="M21 3v6h-6"/></svg>
                                Refresh
                            </button>
                            <a class="btn-sm primary" href="frontend/pages/members.php">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0-3-3.87"/></svg>
                                Add member
                            </a>
                        </div>
                    </div>
                </header>

                <!-- ===================== Stats ===================== -->
                <section class="stats-grid modern" aria-label="Key metrics">
                    <!-- Total Members -->
                    <article class="stat-card primary">
                        <div class="stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <div class="stat-body">
                            <h3>Total Members</h3>
                            <p id="total-members">—</p>
                            <small>Active: <span id="active-members">—</span> • <span id="stat-members-sub">All fellowships</span></small>
                        </div>
                        <span class="stat-trend up" title="vs last month">↗ 12%</span>
                    </article>

                    <!-- Attendance -->
                    <article class="stat-card success">
                        <div class="stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M9 16l2 2 4-5"/></svg>
                        </div>
                        <div class="stat-body">
                            <h3>This Week's Attendance</h3>
                            <p id="weekly-attendance">—</p>
                            <small>Avg per service • Last 7 days</small>
                        </div>
                        <span class="stat-trend">Today</span>
                    </article>

                    <!-- Fellowships -->
                    <article class="stat-card info">
                        <div class="stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V6l-8-4-8 4v6c0 6 8 10 8 10z"/></svg>
                        </div>
                        <div class="stat-body">
                            <h3>Active Fellowships</h3>
                            <p id="active-fellowships">—</p>
                            <small>Out of <span id="total-fellowships">—</span> total</small>
                        </div>
                        <span class="stat-trend up">Active</span>
                    </article>

                    <!-- Tithes -->
                    <article class="stat-card warning">
                        <div class="stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                        <div class="stat-body">
                            <h3>Tithes — Current Period</h3>
                            <p id="current-tithe-total">$0.00</p>
                            <small id="current-period-name">No active period</small>
                        </div>
                        <span class="stat-trend">USD</span>
                    </article>
                </section>

                <!-- ===================== Main Grid ===================== -->
                <div class="dashboard-grid">
                    <!-- Left: Charts -->
                    <div class="charts-container">

                        <section class="chart-card" aria-label="Attendance trend">
                            <div class="chart-header">
                                <h3>Attendance Trend</h3>
                                <a href="frontend/pages/attendance.php">View details →</a>
                            </div>
                            <div class="chart-body">
                                <div class="chart-wrap">
                                    <canvas id="attendanceChart" role="img" aria-label="Attendance chart last 12 weeks"></canvas>
                                </div>
                            </div>
                            <div class="chart-footer">
                                <span>Last 12 weeks</span>
                                <strong id="attendance-footer-note">Live data</strong>
                            </div>
                        </section>

                        <section class="chart-card" aria-label="Financial overview">
                            <div class="chart-header">
                                <h3>Financial Overview</h3>
                                <a href="frontend/pages/financial.php">View details →</a>
                            </div>
                            <div class="chart-body">
                                <div class="chart-wrap">
                                    <canvas id="financialChart" role="img" aria-label="Financial chart"></canvas>
                                </div>
                            </div>
                            <div class="chart-footer">
                                <span>Tithes vs expenses</span>
                                <strong id="financial-footer-note">Monthly</strong>
                            </div>
                        </section>

                        <section class="chart-card" aria-label="Member distribution">
                            <div class="chart-header">
                                <h3>Member Distribution</h3>
                                <a href="frontend/pages/members.php">View details →</a>
                            </div>
                            <div class="chart-body">
                                <div class="chart-wrap short">
                                    <canvas id="categoryChart" role="img" aria-label="Member category distribution"></canvas>
                                </div>
                            </div>
                        </section>

                    </div>

                    <!-- Right: Widgets -->
                    <aside class="widgets-container" aria-label="Quick insights">

                        <section class="widget-card">
                            <div class="widget-header-with-icon">
                                <div class="widget-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                </div>
                                <h3>Recent Services</h3>
                                <span class="widget-count" id="recent-count">—</span>
                            </div>
                            <div class="widget-body">
                                <div id="recent-services-list">
                                    <div class="empty">
                                        <div class="empty-icon">◷</div>
                                        <strong>No recent services</strong>
                                        <span>Attendance will appear here once recorded.</span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="widget-card">
                            <div class="widget-header-with-icon">
                                <div class="widget-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                </div>
                                <h3>Active Fellowships</h3>
                                <span class="widget-count" id="fellowship-count">—</span>
                            </div>
                            <div class="widget-body">
                                <div id="fellowships-list">
                                    <div class="empty"><div class="empty-icon">◌</div><strong>Loading…</strong></div>
                                </div>
                            </div>
                        </section>

                        <section class="widget-card">
                            <div class="widget-header-with-icon">
                                <div class="widget-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                </div>
                                <h3>Current Tithe Period</h3>
                                <span class="badge badge-primary" style="margin-left:auto">Period</span>
                            </div>
                            <div class="widget-body">
                                <div id="tithe-period-info">
                                    <div class="empty"><div class="empty-icon">₵</div><strong>No active period</strong><span>Create a period in Financials.</span></div>
                                </div>
                            </div>
                        </section>

                        <section class="widget-card" style="padding:12px 14px; background: linear-gradient(135deg, #3a86ff 0%, #8338ec 100%); color:#fff; border:none;">
                            <div style="display:flex; gap:12px; align-items:center;">
                                <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0">✦</div>
                                <div style="flex:1; min-width:0;">
                                    <div style="font-weight:800; font-size:13px; line-height:1.2;">Need to record attendance?</div>
                                    <div style="font-size:12px; opacity:.9; margin-top:2px;">Go to Attendance to capture today's service.</div>
                                </div>
                                <a href="frontend/pages/attendance.php" style="background:#fff; color:#3a3a3a; padding:8px 12px; border-radius:999px; font-size:12px; font-weight:700; text-decoration:none; white-space:nowrap;">Open →</a>
                            </div>
                        </section>

                    </aside>
                </div>

            </div>
        </main>
    </div>

    <?php include __DIR__ . '/frontend/footer.php'; ?>

    <!-- Modal (kept for future use) -->
    <div id="modal" class="modal" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Modal Title</h3>
                <span class="close" role="button" tabindex="0" aria-label="Close">&times;</span>
            </div>
            <div class="modal-body" id="modal-body"><p>Modal content will appear here.</p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="modal-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="modal-submit">Submit</button>
            </div>
        </div>
    </div>

    <script src="frontend/js/ui.js"></script>
    <script src="frontend/js/main.js?v=<?= time() ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="frontend/js/dashboard.js?v=<?= time() ?>"></script>
    <script>
        const currentPath = window.location.pathname;
        const appRoot = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
        window.APP_CONFIG = {
            baseUrl: window.location.origin + appRoot,
            loginUrl: window.location.origin + appRoot + 'frontend/pages/login.php'
        };
        document.getElementById('dashboard-refresh')?.addEventListener('click', () => {
            const btn = document.getElementById('dashboard-refresh');
            const orig = btn.innerHTML;
            btn.innerHTML = 'Refreshing…';
            btn.disabled = true;
            if (typeof loadDashboardData === 'function') {
                Promise.resolve(loadDashboardData()).finally(()=>{
                    btn.innerHTML = orig;
                    btn.disabled = false;
                });
            } else location.reload();
        });
    </script>
</body>
</html>
