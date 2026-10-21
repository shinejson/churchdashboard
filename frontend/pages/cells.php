<?php
require_once '../../backend/config/security.php';
secure_session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo get_csrf_token(); ?>">
    <title>Home Cells • Church Management System</title>
    
    <!-- API Configuration - MUST load first -->
    <?php require_once __DIR__ . '/../includes/config.php'; outputApiConfig(); ?>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/fellowships.css">
    <link rel="stylesheet" href="../css/icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    
    <!-- Environment and Configuration -->
    <script src="../js/env.js"></script>
    <script src="../js/config.js"></script>
    
    <style>
        /* Tab Navigation Styles */
        .tab-navigation {
            display: flex;
            gap: 0.5rem;
            padding-bottom: 0;
        }
        
        .tab-btn {
            padding: 12px 24px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            color: var(--text-muted);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }
        
        .tab-btn:hover {
            color: var(--text-color);
            background: rgba(58, 134, 255, 0.05);
        }
        
        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease-out;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
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
            <div class="page-content active">
                <!-- Page Header -->
                <div class="page-header">
                    <h2><i class="fas fa-home"></i> Home Cells Management</h2>
                    <button id="create-cell-btn" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Cell
                    </button>
                </div>

                <!-- Tab Navigation -->
                <div class="tab-navigation" style="margin-bottom: 2rem; border-bottom: 2px solid var(--border-color);">
                    <button class="tab-btn active" data-tab="cells-tab">
                        <i class="fas fa-home"></i> Cells
                    </button>
                    <button class="tab-btn" data-tab="members-tab">
                        <i class="fas fa-users"></i> Cell Members
                    </button>
                </div>

                <!-- Cells Tab -->
                <div id="cells-tab" class="tab-content active">
                    <!-- Stats Overview -->
                    <div class="stats-overview">
                        <div class="stat-card">
                            <div class="stat-icon total">
                                <i class="fas fa-home"></i>
                            </div>
                            <span class="stat-value" id="total-cells">0</span>
                            <span class="stat-label">Total Home Cells</span>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon active">
                                <i class="fas fa-users"></i>
                            </div>
                            <span class="stat-value" id="cells-with-leaders">0</span>
                            <span class="stat-label">Cells with Leaders</span>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon members">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <span class="stat-value" id="active-meetings">0</span>
                            <span class="stat-label">Active Meetings</span>
                        </div>
                    </div>

                    <!-- Search and Filter Bar -->
                    <div class="search-export-bar">
                        <div class="search-box">
                            <input type="text" id="search-input" placeholder="Search cells by name, leader, or location...">
                            <i class="fas fa-search"></i>
                        </div>
                        <select id="fellowship-filter" class="form-input" style="min-width: 200px; padding: 12px 16px; border-radius: 12px; border: 2px solid var(--border-color);">
                            <option value="">All Fellowships</option>
                        </select>
                        <select id="day-filter" class="form-input" style="min-width: 180px; padding: 12px 16px; border-radius: 12px; border: 2px solid var(--border-color);">
                            <option value="">All Days</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                        <button class="btn btn-outline" id="export-btn">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>

                    <!-- Table Container -->
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 20%;">
                                        <span style="cursor: pointer;" data-sort="name">
                                            Cell Name <i class="fas fa-sort"></i>
                                        </span>
                                    </th>
                                    <th style="width: 18%;">
                                        <span style="cursor: pointer;" data-sort="leader_name">
                                            Leader <i class="fas fa-sort"></i>
                                        </span>
                                    </th>
                                    <th style="width: 18%;">
                                        <span style="cursor: pointer;" data-sort="fellowship_name">
                                            Fellowship <i class="fas fa-sort"></i>
                                        </span>
                                    </th>
                                    <th style="width: 14%;">
                                        <span style="cursor: pointer;" data-sort="meeting_day">
                                            Meeting Day <i class="fas fa-sort"></i>
                                        </span>
                                    </th>
                                    <th style="width: 20%;">Location</th>
                                    <th style="width: 10%; text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="cell-table-body">
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem;">
                                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                                        <p style="margin-top: 1rem; color: var(--text-muted);">Loading home cells...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div id="empty-state" style="display: none; text-align: center; padding: 3rem;">
                        <i class="fas fa-home" style="font-size: 4rem; color: var(--text-muted); opacity: 0.3;"></i>
                        <h3 style="margin-top: 1rem; color: var(--text-color);">No Home Cells Found</h3>
                        <p style="color: var(--text-muted); margin-top: 0.5rem;">Get started by creating your first home cell.</p>
                        <button class="btn btn-primary" style="margin-top: 1.5rem;" onclick="document.getElementById('create-cell-btn').click()">
                            <i class="fas fa-plus"></i> Create Home Cell
                        </button>
                    </div>
                </div>

                <!-- Members Tab -->
                <div id="members-tab" class="tab-content" style="display: none;">
                    <!-- Members Stats -->
                    <div class="stats-overview">
                        <div class="stat-card">
                            <div class="stat-icon total">
                                <i class="fas fa-users"></i>
                            </div>
                            <span class="stat-value" id="total-cell-members">0</span>
                            <span class="stat-label">Total Cell Members</span>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon active">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <span class="stat-value" id="active-cell-members">0</span>
                            <span class="stat-label">Active Members</span>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon members">
                                <i class="fas fa-home"></i>
                            </div>
                            <span class="stat-value" id="cells-with-members">0</span>
                            <span class="stat-label">Cells with Members</span>
                        </div>
                    </div>

                    <!-- Members Search and Filter -->
                    <div class="search-export-bar">
                        <div class="search-box">
                            <input type="text" id="members-search-input" placeholder="Search members by name, phone, or email...">
                            <i class="fas fa-search"></i>
                        </div>
                        <select id="members-cell-filter" class="form-input" style="min-width: 200px; padding: 12px 16px; border-radius: 12px; border: 2px solid var(--border-color);">
                            <option value="">All Cells</option>
                        </select>
                        <select id="members-status-filter" class="form-input" style="min-width: 150px; padding: 12px 16px; border-radius: 12px; border: 2px solid var(--border-color);">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <button class="btn btn-outline" id="export-members-btn">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>

                    <!-- Members Table -->
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">
                                        <span style="cursor: pointer;" data-sort-members="name">
                                            Member Name <i class="fas fa-sort"></i>
                                        </span>
                                    </th>
                                    <th style="width: 20%;">
                                        <span style="cursor: pointer;" data-sort-members="cell_name">
                                            Cell <i class="fas fa-sort"></i>
                                        </span>
                                    </th>
                                    <th style="width: 15%;">Phone</th>
                                    <th style="width: 20%;">Email</th>
                                    <th style="width: 10%;">
                                        <span style="cursor: pointer;" data-sort-members="status">
                                            Status <i class="fas fa-sort"></i>
                                        </span>
                                    </th>
                                    <th style="width: 10%;">Role</th>
                                </tr>
                            </thead>
                            <tbody id="members-table-body">
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem;">
                                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>
                                        <p style="margin-top: 1rem; color: var(--text-muted);">Loading cell members...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Members Empty State -->
                    <div id="members-empty-state" style="display: none; text-align: center; padding: 3rem;">
                        <i class="fas fa-users" style="font-size: 4rem; color: var(--text-muted); opacity: 0.3;"></i>
                        <h3 style="margin-top: 1rem; color: var(--text-color);">No Cell Members Found</h3>
                        <p style="color: var(--text-muted); margin-top: 0.5rem;">Members need to be assigned to cells from the Members page.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Cell Modal -->
    <div id="cell-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title"><i class="fas fa-home"></i> Create New Home Cell</h3>
                <button class="close" type="button">&times;</button>
            </div>
            <div class="modal-body">
                <form id="cell-form" class="modal-form">
                    <input type="hidden" id="cell-id">
                    
                    <div class="form-group">
                        <label for="cell-name">
                            Cell Name <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="cell-name" 
                            required 
                            placeholder="e.g. Life Cell, Victory Cell"
                            autocomplete="off"
                        >
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cell-leader">Cell Leader</label>
                            <select id="cell-leader">
                                <option value="">Select Leader</option>
                            </select>
                            <span class="form-hint">Optional: Assign a leader to this cell</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="cell-fellowship">Parent Fellowship</label>
                            <select id="cell-fellowship">
                                <option value="">Select Fellowship</option>
                            </select>
                            <span class="form-hint">Optional: Link to a fellowship</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cell-day">Meeting Day</label>
                            <select id="cell-day">
                                <option value="">Select Day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="cell-location">Location</label>
                            <input 
                                type="text" 
                                id="cell-location" 
                                placeholder="e.g. 123 Church St, Community Center"
                            >
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline close-modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Cell
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../footer.php'; ?>
    <script src="../js/ui.js"></script>
    <script src="../js/main.js"></script>
    <script src="../js/cells.js"></script>
</body>
</html>
