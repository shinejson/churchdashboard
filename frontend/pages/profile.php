<?php
require_once '../../backend/config/security.php';
secure_session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/frontend/pages/login.php');
    exit();
}

// Check if user has access to profile
if (!can_access('profile')) {
    header('Location: ../index.php');
    exit();
}

$user_role = $_SESSION['role'] ?? 'member';
$user_name = $_SESSION['username'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile • Church Management System</title>
    <link rel="stylesheet" href="../css/style.css">
    <?php set_csrf_meta_tag(); ?>
    <?php 
    // Output dynamic API configuration
    require_once __DIR__ . '/../includes/config.php';
    outputApiConfig(); 
    ?>
    <style>
        .profile-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .profile-header {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .member-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
        }

        .member-info h1 {
            margin: 0 0 0.5rem 0;
            color: var(--text-primary);
        }

        .member-meta {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            color: var(--text-secondary);
        }

        .member-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .profile-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .profile-card h2 {
            margin: 0 0 1rem 0;
            font-size: 1.25rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .profile-card-icon {
            width: 24px;
            height: 24px;
            color: var(--primary-color);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-secondary);
        }

        .info-value {
            color: var(--text-primary);
            text-align: right;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .summary-item {
            text-align: center;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 8px;
        }

        .summary-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            display: block;
        }

        .summary-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .transactions-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-info {
            flex: 1;
        }

        .transaction-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .transaction-date {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .transaction-amount {
            font-weight: bold;
            color: var(--success-color);
            font-size: 1.125rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            opacity: 0.5;
        }

        .loading {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .error-message {
            background: var(--error-bg);
            color: var(--error-color);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-grid {
                grid-template-columns: 1fr;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../topnav.php'; ?>

    <div id="app">
        <?php include '../sidebar.php'; ?>
        
        <main class="content">
        
        <div class="profile-container">
            <div id="error-container"></div>
            <div id="loading" class="loading">
                <p>Loading your profile...</p>
            </div>
            
            <div id="profile-content" style="display: none;">
                <!-- Profile Header -->
                <div class="profile-header">
                    <img id="profile-avatar" src="../assets/avatar-placeholder.png" alt="Profile" class="member-photo">
                    <div class="member-info">
                        <h1 id="profile-name">Loading...</h1>
                        <div class="member-meta">
                            <div class="member-meta-item">
                                <svg class="profile-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <span id="profile-email">-</span>
                            </div>
                            <div class="member-meta-item">
                                <svg class="profile-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                                <span id="profile-phone">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Grid -->
                <div class="profile-grid">
                    <!-- Personal Information -->
                    <div class="profile-card">
                        <h2>
                            <svg class="profile-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Personal Information
                        </h2>
                        <div id="personal-info"></div>
                    </div>

                    <!-- Fellowship Information -->
                    <div class="profile-card">
                        <h2>
                            <svg class="profile-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Fellowship & Ministry
                        </h2>
                        <div id="fellowship-info"></div>
                    </div>
                </div>

                <!-- Financial Summary -->
                <div class="profile-card" style="margin-bottom: 2rem;">
                    <h2>
                        <svg class="profile-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Financial Contributions
                    </h2>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <div></div>
                        <button onclick="openPaymentModal()" style="padding: 0.75rem 1.5rem; background: var(--primary-color); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Make Payment
                        </button>
                    </div>
                    <div class="summary-grid" id="financial-summary"></div>
                </div>

                <!-- Tithe Contributions -->
                <div class="profile-card" style="margin-bottom: 2rem;">
                    <h2>
                        <svg class="profile-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Recent Tithe Contributions
                    </h2>
                    <div class="transactions-list" id="tithe-list"></div>
                </div>

                <!-- Partnership Contributions -->
                <div class="profile-card" style="margin-bottom: 2rem;">
                    <h2>
                        <svg class="profile-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        Recent Partnership Contributions
                    </h2>
                    <div class="transactions-list" id="partnership-list"></div>
                </div>

                <!-- Attendance Summary -->
                <div class="profile-card">
                    <h2>
                        <svg class="profile-card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        Attendance Summary
                    </h2>
                    <div id="attendance-summary"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="payment-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
        <div style="background: var(--bg-color); border-radius: 12px; padding: 2rem; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.5); position: relative; z-index: 10000;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="margin: 0; color: var(--text-color);">Make Payment</h2>
                <button onclick="closePaymentModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>
            
            <form id="payment-form" onsubmit="submitPayment(event)" style="border: 2px solid var(--border-color); border-radius: 10px; padding: 1.5rem; background: var(--surface-color);">
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-color);">Payment Type</label>
                    <select id="payment-type" required onchange="togglePartnershipField()" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); font-size: 1rem;">
                        <option value="">Select payment type...</option>
                        <option value="tithe">Tithe</option>
                        <option value="partnership">Partnership</option>
                        <option value="offering">Offering/Other</option>
                    </select>
                </div>

                <div id="partnership-field" style="display: none; margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-color);">Partnership Arm</label>
                    <select id="partnership-arm" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); font-size: 1rem;">
                        <option value="">Select partnership arm...</option>
                    </select>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-color);">Amount (GH₵)</label>
                    <input type="number" id="payment-amount" step="0.01" min="0.01" required placeholder="0.00" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); font-size: 1rem;">
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-color);">Payment Method</label>
                    <select id="payment-method" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); font-size: 1rem;">
                        <option value="">Select payment method...</option>
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="cheque">Cheque</option>
                        <option value="card">Card Payment</option>
                        <option value="online">Online Payment</option>
                    </select>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-color);">Payment Date</label>
                    <input type="date" id="payment-date" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); font-size: 1rem; color-scheme: dark;">
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-color);">Reference Number (Optional)</label>
                    <input type="text" id="payment-reference" placeholder="Transaction reference" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); font-size: 1rem;">
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-color);">Notes (Optional)</label>
                    <textarea id="payment-notes" rows="3" placeholder="Additional notes..." style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-color); resize: vertical; font-size: 1rem; font-family: inherit;"></textarea>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closePaymentModal()" style="padding: 0.75rem 1.5rem; background: var(--surface-color); color: var(--text-color); border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; font-weight: 600;">Cancel</button>
                    <button type="submit" id="submit-payment-btn" style="padding: 0.75rem 1.5rem; background: var(--primary-color); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Submit Payment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Use the globally configured API_BASE from api-config.php
        const API_BASE = window.API_BASE || '../../backend';
        
        async function loadProfile() {
            try {
                const response = await fetch(`${API_BASE}/profile/read.php`, {
                    credentials: 'include'
                });

                if (!response.ok) {
                    throw new Error('Failed to load profile');
                }

                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.message || 'Failed to load profile');
                }

                renderProfile(result.data);
                
                document.getElementById('loading').style.display = 'none';
                document.getElementById('profile-content').style.display = 'block';
                
            } catch (error) {
                console.error('Error loading profile:', error);
                document.getElementById('loading').style.display = 'none';
                document.getElementById('error-container').innerHTML = `
                    <div class="error-message">
                        ${error.message}
                    </div>
                `;
            }
        }

        function renderProfile(data) {
            const profile = data.profile;
            
            // Profile Header
            const avatarEl = document.getElementById('profile-avatar');
            if (profile.photo) {
                avatarEl.src = `../../${profile.photo}`;
            }
            
            document.getElementById('profile-name').textContent = 
                `${profile.first_name} ${profile.last_name}`;
            document.getElementById('profile-email').textContent = profile.email || '-';
            document.getElementById('profile-phone').textContent = profile.phone || '-';
            
            // Personal Information
            document.getElementById('personal-info').innerHTML = `
                <div class="info-row">
                    <span class="info-label">Gender</span>
                    <span class="info-value">${profile.gender || '-'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date of Birth</span>
                    <span class="info-value">${profile.birth_date ? formatDate(profile.birth_date) : '-'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Address</span>
                    <span class="info-value">${profile.address || '-'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Member Since</span>
                    <span class="info-value">${profile.created_at ? formatDate(profile.created_at) : '-'}</span>
                </div>
            `;
            
            // Fellowship Information
            document.getElementById('fellowship-info').innerHTML = `
                <div class="info-row">
                    <span class="info-label">Fellowship</span>
                    <span class="info-value">${profile.fellowship_name || '-'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Department</span>
                    <span class="info-value">${profile.department_name || '-'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Home Cell</span>
                    <span class="info-value">${profile.cell_name || '-'}</span>
                </div>
                ${profile.cell_leader_name ? `
                <div class="info-row">
                    <span class="info-label">Cell Leader</span>
                    <span class="info-value">${profile.cell_leader_name}</span>
                </div>
                ` : ''}
                ${profile.cell_meeting_day ? `
                <div class="info-row">
                    <span class="info-label">Cell Meeting</span>
                    <span class="info-value">${profile.cell_meeting_day}</span>
                </div>
                ` : ''}
            `;
            
            // Financial Summary
            const tithe = data.tithe_summary;
            const partnership = data.partnership_summary;
            document.getElementById('financial-summary').innerHTML = `
                <div class="summary-item">
                    <span class="summary-value">GH₵${formatMoney(tithe.total_amount || 0)}</span>
                    <span class="summary-label">Total Tithe</span>
                </div>
                <div class="summary-item">
                    <span class="summary-value">GH₵${formatMoney(tithe.year_to_date || 0)}</span>
                    <span class="summary-label">Tithe This Year</span>
                </div>
                <div class="summary-item">
                    <span class="summary-value">GH₵${formatMoney(partnership.total_amount || 0)}</span>
                    <span class="summary-label">Total Partnership</span>
                </div>
                <div class="summary-item">
                    <span class="summary-value">${partnership.arms_supported || 0}</span>
                    <span class="summary-label">Arms Supported</span>
                </div>
            `;
            
            // Tithe List
            renderTransactions(data.tithes, 'tithe-list', 'tithe');
            
            // Partnership List
            renderTransactions(data.partnerships, 'partnership-list', 'partnership');
            
            // Attendance Summary
            const attendance = data.attendance_summary;
            document.getElementById('attendance-summary').innerHTML = `
                <div class="summary-grid">
                    <div class="summary-item">
                        <span class="summary-value">${attendance.total_services || 0}</span>
                        <span class="summary-label">Total Services</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-value">${attendance.present_count || 0}</span>
                        <span class="summary-label">Present</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-value">${attendance.attendance_rate || 0}%</span>
                        <span class="summary-label">Attendance Rate</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-value">${attendance.last_attendance_date ? formatDate(attendance.last_attendance_date) : '-'}</span>
                        <span class="summary-label">Last Attendance</span>
                    </div>
                </div>
            `;
        }

        function renderTransactions(transactions, containerId, type) {
            const container = document.getElementById(containerId);
            
            if (!transactions || transactions.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                        <p>No ${type} contributions yet</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = transactions.map(t => {
                const date = type === 'tithe' ? t.transaction_date : t.contribution_date;
                const title = type === 'partnership' ? (t.partnership_arm_name || 'Partnership') : 'Tithe';
                const paymentInfo = t.payment_method ? ` • ${t.payment_method}` : '';
                
                return `
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div class="transaction-title">${title}</div>
                            <div class="transaction-date">${formatDate(date)}${paymentInfo}</div>
                        </div>
                        <div class="transaction-amount">GH₵${formatMoney(t.amount)}</div>
                    </div>
                `;
            }).join('');
        }

        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        }

        function formatMoney(amount) {
            return parseFloat(amount || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Load profile on page load
        document.addEventListener('DOMContentLoaded', loadProfile);

        // Payment Modal Functions
        let partnershipArms = [];

        async function loadPaymentOptions() {
            try {
                const response = await fetch(`${API_BASE}/payments/record_payment.php`, {
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    partnershipArms = result.data.partnership_arms;
                    populatePartnershipArms();
                }
            } catch (error) {
                console.error('Error loading payment options:', error);
            }
        }

        function populatePartnershipArms() {
            const select = document.getElementById('partnership-arm');
            select.innerHTML = '<option value="">Select partnership arm...</option>';
            
            partnershipArms.forEach(arm => {
                const option = document.createElement('option');
                option.value = arm.id;
                option.textContent = arm.name;
                if (arm.description) {
                    option.title = arm.description;
                }
                select.appendChild(option);
            });
        }

        function openPaymentModal() {
            const modal = document.getElementById('payment-modal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Set default date to today
            document.getElementById('payment-date').value = new Date().toISOString().split('T')[0];
            
            // Load payment options if not already loaded
            if (partnershipArms.length === 0) {
                loadPaymentOptions();
            }
        }

        function closePaymentModal() {
            const modal = document.getElementById('payment-modal');
            modal.style.display = 'none';
            document.body.style.overflow = '';
            document.getElementById('payment-form').reset();
            document.getElementById('partnership-field').style.display = 'none';
        }

        function togglePartnershipField() {
            const paymentType = document.getElementById('payment-type').value;
            const partnershipField = document.getElementById('partnership-field');
            const partnershipArm = document.getElementById('partnership-arm');
            
            if (paymentType === 'partnership') {
                partnershipField.style.display = 'block';
                partnershipArm.required = true;
            } else {
                partnershipField.style.display = 'none';
                partnershipArm.required = false;
            }
        }

        async function submitPayment(event) {
            event.preventDefault();
            
            const submitBtn = document.getElementById('submit-payment-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
            
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                
                const paymentData = {
                    payment_type: document.getElementById('payment-type').value,
                    amount: parseFloat(document.getElementById('payment-amount').value),
                    payment_method: document.getElementById('payment-method').value,
                    payment_date: document.getElementById('payment-date').value,
                    reference: document.getElementById('payment-reference').value,
                    notes: document.getElementById('payment-notes').value
                };
                
                // Add partnership arm if payment type is partnership
                if (paymentData.payment_type === 'partnership') {
                    paymentData.partnership_arm_id = parseInt(document.getElementById('partnership-arm').value);
                }
                
                const response = await fetch(`${API_BASE}/payments/record_payment.php`, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': token
                    },
                    body: JSON.stringify(paymentData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✓ ' + result.message);
                    closePaymentModal();
                    // Reload profile to show new payment
                    await loadProfile();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error submitting payment:', error);
                alert('Error submitting payment. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Payment';
            }
        }

        // Close modal on outside click
        document.getElementById('payment-modal')?.addEventListener('click', function(e) {
            if (e.target.id === 'payment-modal') {
                closePaymentModal();
            }
        });
    </script>
    <script src="../js/env.js"></script>
    <script src="../js/config.js"></script>
    <script src="../js/ui.js"></script>
    <script src="../js/main.js"></script>
</body>
</html>
