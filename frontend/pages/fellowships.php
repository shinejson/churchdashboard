<?php
require_once '../../backend/config/security.php';
secure_session_start();

// Check authentication
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
    <title>Fellowships • Church Management System</title>
    
    <!-- API Configuration - MUST load first -->
    <?php require_once __DIR__ . '/../includes/config.php'; outputApiConfig(); ?>
    
    <link rel="stylesheet" href="../css/style.css">
     <link rel="stylesheet" href="../css/fellowships.css">
    <link rel="stylesheet" href="../css/icons.css">
    <!-- Add this to your fellowships.php file in the head section -->

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    
    <!-- Environment and Configuration - MUST load before other scripts -->
    <script src="../js/env.js"></script>
    <script src="../js/config.js"></script>
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
            <div id="fellowships-content" class="page-content active">
                <div class="page-header">
                    <h2>Fellowships Management</h2>
                    <button id="create-fellowship-btn" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Create New Fellowship
                    </button>
                </div>

                <!-- Stats Overview -->
                <div class="stats-overview">
                    <div class="stat-card">
                        <div class="stat-icon total">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value" id="total-fellowships">0</div>
                        <div class="stat-label">Total Fellowships</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon active">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value" id="active-fellowships">0</div>
                        <div class="stat-label">Active Fellowships</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon members">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="stat-value" id="total-members">0</div>
                        <div class="stat-label">Total Members</div>
                    </div>
                </div>

                <div class="section-title">
                    <i class="fas fa-th-large"></i>
                    Fellowships Overview
                </div>

                <div class="search-export-bar">
                    <div class="search-box">
                        <input type="text" id="fellowship-search" placeholder="Search fellowships by name, leader or location...">
                        <i class="fas fa-search"></i>
                    </div>
                    <button id="export-fellowships" class="btn btn-outline">
                        <i class="fas fa-download"></i>
                        Export List
                    </button>
                </div>

                <!-- Grid View -->
                <div class="fellowships-grid" id="fellowships-grid">
                    <!-- Cards populated by JS -->
                </div>

                <!-- Table View -->
                <div class="section-title">
                    <i class="fas fa-table"></i>
                    Detailed Records
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fellowship</th>
                                <th>Leader</th>
                                <th>Members</th>
                                <th>Schedule</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="fellowships-table-body"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <?php include '../footer.php'; ?>

    <!-- Modal -->
  <div id="fellowship-modal" class="modal">
    <div class="modal-content">
        <!-- Modal Header -->
        <div class="modal-header">
            <h3 id="modal-title">
                <i class="fas fa-users"></i>
                <span>Create New Fellowship</span>
            </h3>
            <button class="close" type="button" aria-label="Close modal">&times;</button>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div class="modal-body">
            <form id="fellowship-form" class="modal-form">
                <input type="hidden" id="fellowship-id">

                <!-- Basic Information Section -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="fellowship-name">
                            Fellowship Name <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="fellowship-name" 
                            required 
                            placeholder="e.g. Youth Fellowship"
                            autocomplete="off"
                        >
                        <span class="form-hint">Choose a clear, descriptive name</span>
                    </div>

                    <div class="form-group">
                        <label for="fellowship-status">
                            Status <span class="required">*</span>
                        </label>
                        <select id="fellowship-status" required>
                            <option value="">Select Status</option>
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>

                <!-- Leadership Section -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-user-tie"></i>
                        Leadership
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="fellowship-leader">
                            Leader <span class="required">*</span>
                        </label>
                        <select id="fellowship-leader" required>
                            <option value="">Select Leader</option>
                            <!-- Options populated by JS -->
                        </select>
                        <span class="form-hint">Primary fellowship leader</span>
                    </div>

                    <div class="form-group">
                        <label for="fellowship-assistant">
                            Assistant Leader
                        </label>
                        <select id="fellowship-assistant">
                            <option value="">Select Assistant (Optional)</option>
                            <!-- Options populated by JS -->
                        </select>
                        <span class="form-hint">Secondary leader (optional)</span>
                    </div>
                </div>

                <!-- Schedule Section -->
<div class="form-section">
    <div class="form-section-title">
        <i class="fas fa-calendar-alt"></i>
        Meeting Schedules
    </div>
</div>

<div class="form-row full">
    <div class="form-group">
        <label>Meeting Schedules <span class="required">*</span></label>
        <div class="meeting-schedules" id="meeting-schedules-container">
            <!-- Meeting schedules will be populated by JavaScript -->
        </div>
        <button type="button" class="add-meeting-btn" onclick="fellowships.addMeetingSchedule()">
            <i class="fas fa-plus"></i>
            Add Another Meeting Schedule
        </button>
        <span class="form-hint">Add all meeting days and times. The first one will be considered primary.</span>
    </div>
</div>

                <!-- Location Section -->
                <div class="form-row full">
                    <div class="form-group">
                        <label for="meeting-location">
                            Location <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="meeting-location" 
                            required 
                            placeholder="e.g. Main Hall, Room 201"
                            autocomplete="off"
                        >
                        <span class="form-hint">Where the fellowship meets</span>
                    </div>
                </div>

                <!-- Description Section -->
                <div class="form-row full">
                    <div class="form-group">
                        <label for="fellowship-description">
                            Description
                        </label>
                        <textarea 
                            id="fellowship-description" 
                            rows="4" 
                            placeholder="Enter fellowship purpose, vision, and activities..."
                        ></textarea>
                        <span class="form-hint">Describe the fellowship's purpose and activities</span>
                    </div>
                </div>
            </form>
        </div>

        <!-- Modal Footer -->
        <div class="form-actions">
            <button type="button" id="cancel-fellowship" class="btn btn-outline">
                <i class="fas fa-times"></i>
                Cancel
            </button>
            <button type="submit" form="fellowship-form" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Save Fellowship
            </button>
        </div>
    </div>
</div>


    <script src="../js/ui.js"></script>
    <script src="../js/main.js?v=<?php echo time(); ?>"></script>
    <script src="../js/fellowships.js?v=<?php echo time(); ?>"></script>
</body>
</html>