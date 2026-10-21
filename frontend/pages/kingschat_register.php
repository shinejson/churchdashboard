<?php
/**
 * KingsChat Registration Page
 * Complete registration for new users who signed in with KingsChat
 */

require_once '../../backend/config/security.php';
require_once '../../backend/config/database.php';

secure_session_start();

// Check if we have pending KingsChat data
if (!isset($_SESSION['kingschat_pending'])) {
    header('Location: ' . SITE_URL . '/frontend/pages/login.php');
    exit();
}

$kingschatData = $_SESSION['kingschat_pending'];

// Fetch fellowships for dropdown
try {
    $db = (new Database())->getConnection();
    $stmt = $db->query("SELECT id, name FROM fellowships ORDER BY name");
    $fellowships = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching fellowships: " . $e->getMessage());
    $fellowships = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Registration - Church Management</title>
    
    <!-- API Configuration - MUST load first -->
    <?php require_once __DIR__ . '/../includes/config.php'; outputApiConfig(); ?>
    
    <link rel="stylesheet" href="../css/login.css">
    <style>
        .register-info {
            background: var(--surface-color, #f5f5f5);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .register-info img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .register-info-text h3 {
            margin: 0 0 0.25rem 0;
            font-size: 1rem;
            color: var(--text-primary, #1a1a1a);
        }
        
        .register-info-text p {
            margin: 0;
            font-size: 0.875rem;
            color: var(--text-secondary, #666);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
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

    <div class="login-wrapper">
        <div class="login-left">
            <div class="login-content">
                <!-- Logo/Brand -->
                <div class="login-brand">
                    <div class="brand-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <path d="M2 17l10 5 10-5"/>
                            <path d="M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                    <h1>Church Management</h1>
                </div>

                <!-- Welcome Text -->
                <div class="login-header">
                    <h2>Complete Your Registration</h2>
                    <p>Just a few more details to get you started</p>
                </div>

                <!-- KingsChat Info -->
                <div class="register-info">
                    <?php if (!empty($kingschatData['avatar'])): ?>
                        <img src="<?= htmlspecialchars($kingschatData['avatar']) ?>" alt="Profile">
                    <?php else: ?>
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: #6366f1; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                            <?= strtoupper(substr($kingschatData['name'] ?? 'U', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="register-info-text">
                        <h3><?= htmlspecialchars($kingschatData['name'] ?? 'KingsChat User') ?></h3>
                        <p><?= htmlspecialchars($kingschatData['email'] ?? 'No email provided') ?></p>
                    </div>
                </div>

                <!-- Error/Success Messages -->
                <div id="message-container"></div>

                <!-- Registration Form -->
                <form id="register-form" class="login-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <div class="input-wrapper">
                                <input type="text" 
                                       id="first_name" 
                                       name="first_name"
                                       value="<?= htmlspecialchars($kingschatData['first_name'] ?? '') ?>"
                                       placeholder="Enter first name" 
                                       required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <div class="input-wrapper">
                                <input type="text" 
                                       id="last_name" 
                                       name="last_name"
                                       value="<?= htmlspecialchars($kingschatData['last_name'] ?? '') ?>"
                                       placeholder="Enter last name" 
                                       required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-wrapper">
                            <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <input type="text" 
                                   id="username" 
                                   name="username"
                                   placeholder="Choose a username" 
                                   required>
                        </div>
                        <small style="color: var(--text-secondary, #666); font-size: 0.75rem;">This will be your login username</small>
                    </div>

                    <div class="form-group">
                        <label for="fellowship_id">Fellowship</label>
                        <div class="input-wrapper">
                            <select id="fellowship_id" name="fellowship_id" required>
                                <option value="">Select your fellowship</option>
                                <?php foreach ($fellowships as $fellowship): ?>
                                    <option value="<?= $fellowship['id'] ?>">
                                        <?= htmlspecialchars($fellowship['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number (Optional)</label>
                        <div class="input-wrapper">
                            <input type="tel" 
                                   id="phone" 
                                   name="phone"
                                   placeholder="Enter phone number">
                        </div>
                    </div>

                    <button type="submit" class="btn-login">
                        <span>Complete Registration</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14"/>
                            <path d="m12 5 7 7-7 7"/>
                        </svg>
                    </button>
                </form>

                <!-- Footer Links -->
                <div class="login-footer">
                    <p>Already have an account? <a href="login.php">Sign in</a></p>
                </div>

                <!-- Theme Toggle -->
                <button class="theme-toggle" id="theme-toggle" aria-label="Toggle theme">
                    <svg class="theme-icon sun-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="5"/>
                        <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                    </svg>
                    <svg class="theme-icon moon-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Right Side - Image/Visual -->
        <div class="login-right">
            <div class="visual-overlay">
                <div class="visual-content">
                    <h2>Welcome to Our Community</h2>
                    <p>Complete your registration to access all features and connect with your fellowship.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme toggle
        document.getElementById('theme-toggle')?.addEventListener('click', function() {
            const current = document.documentElement.getAttribute('data-theme');
            const newTheme = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });

        // Registration form handler
        document.getElementById('register-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            const messageContainer = document.getElementById('message-container');
            const submitBtn = this.querySelector('button[type="submit"]');
            
            try {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>Creating account...</span>';
                
                const response = await fetch('../../backend/users/complete_kingschat_registration.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    messageContainer.innerHTML = '<div class="alert alert-success">Registration successful! Redirecting...</div>';
                    
                    // Redirect based on role
                    setTimeout(() => {
                        if (result.role === 'member') {
                            window.location.href = 'profile.php';
                        } else {
                            window.location.href = '../../index.php';
                        }
                    }, 1500);
                } else {
                    messageContainer.innerHTML = `<div class="alert alert-error">${result.message || 'Registration failed'}</div>`;
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span>Complete Registration</span><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';
                }
            } catch (error) {
                console.error('Registration error:', error);
                messageContainer.innerHTML = '<div class="alert alert-error">An error occurred. Please try again.</div>';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>Complete Registration</span><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';
            }
        });
    </script>
</body>
</html>
