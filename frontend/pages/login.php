<?php
// Temporarily enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../backend/config/security.php';
secure_session_start();


?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Login - Church Management</title>
	
	<!-- API Configuration - MUST load first -->
	<?php 
	try {
		if (file_exists(__DIR__ . '/../includes/config.php')) {
			require_once __DIR__ . '/../includes/config.php'; 
			outputApiConfig();
		} else {
			// Fallback configuration
			echo '<script>';
			echo 'window.API_BASE = window.location.origin + "/backend";';
			echo 'window.BASE_URL = window.location.origin;';
			echo 'window.BACKEND_URL = window.location.origin + "/backend";';
			echo 'console.log("Using fallback configuration");';
			echo '</script>';
		}
	} catch (Exception $e) {
		// Fallback if config fails
		echo '<script>';
		echo 'window.API_BASE = window.location.origin + "/backend";';
		echo 'console.error("Config error:", ' . json_encode($e->getMessage()) . ');';
		echo '</script>';
	}
	?>
	
	<link rel="stylesheet" href="../css/login.css">
</head>
<body>
	<script>
		const saved = localStorage.getItem('theme');
		const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
		const isDark = saved === 'dark' || (!saved && prefersDark);
		document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
	</script>

	<div class="login-page">
		<!-- Background Image Overlay -->
		<div class="login-bg" style="background-image: url('../assets/images/church_bg.png');"></div>
		<div class="bg-overlay"></div>

		<div class="login-container">
			<!-- Login Card -->
			<div class="login-card">
				<div class="card-glass"></div>
				
				<div class="login-content">
					<!-- Brand -->
					<div class="login-brand">
						<div class="brand-icon">
							<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
								<path d="M12 2L2 7l10 5 10-5-10-5z"/>
								<path d="M2 17l10 5 10-5"/>
								<path d="M2 12l10 5 10-5"/>
							</svg>
						</div>
						<h1>Christ Embassy</h1>
					</div>

					<div class="login-header">
						<h2>Welcome Home</h2>
						<p>Connect with your community and manage your spiritual journey.</p>
					</div>

					<!-- KingsChat Login (Primary Choice for Members) -->
					<div class="kingschat-section">
						<button type="button" id="kingschat-login-btn" class="btn-kingschat-premium">
							<div class="kc-icon">
								<svg viewBox="0 0 24 24" fill="currentColor">
									<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
								</svg>
							</div>
							<span>Sign in with KingsChat</span>
						</button>
						<p class="member-badge">Member Exclusive Login</p>
					</div>

					<!-- Divider -->
					<div class="login-divider">
						<span>OR USE CREDENTIALS</span>
					</div>

					<?php if (isset($_SESSION['login_error'])): ?>
						<div class="alert alert-error">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
							</svg>
							<?= htmlspecialchars($_SESSION['login_error']) ?>
						</div>
						<?php unset($_SESSION['login_error']); ?>
					<?php endif; ?>

					<!-- Standard Login Form -->
					<div id="login-inline-error" class="alert alert-error" style="display: none;"></div>

					<form id="login-form" class="login-form" method="post" action="../../backend/auth/login.php">
						<div class="form-group">
							<div class="input-wrapper-modern">
								<input type="text" id="username" name="username" placeholder=" " required>
								<label for="username">Username</label>
								<svg class="field-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
								</svg>
							</div>
						</div>

						<div class="form-group">
							<div class="input-wrapper-modern">
								<input type="password" id="password" name="password" placeholder=" " required>
								<label for="password">Password</label>
								<svg class="field-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
								</svg>
								<button type="button" class="toggle-password-v2" aria-label="Toggle password">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
										<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
									</svg>
								</button>
							</div>
						</div>

						<div class="form-footer">
							<label class="custom-checkbox">
								<input type="checkbox" id="remember">
								<span class="checkmark"></span>
								<span>Remember Me</span>
							</label>
							<a href="#" class="link-forgot">Forgot?</a>
						</div>

						<button type="submit" class="btn-submit">
							<span>Sign In</span>
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<path d="M5 12h14m-7-7 7 7-7 7"/>
							</svg>
						</button>
					</form>
				</div>
			</div>

			<!-- Church Life Gallery (Under the card) -->
			<div class="login-gallery">
				<h3>Experience Church Life</h3>
				<div class="gallery-grid">
					<div class="gallery-item">
						<img src="../assets/images/church_exterior.png" alt="Church Community">
						<div class="overlay-text">Our Community</div>
					</div>
					<div class="gallery-item">
						<img src="../assets/images/church_bg.png" alt="Worship">
						<div class="overlay-text">Worship</div>
					</div>
					<div class="gallery-item">
						<div class="placeholder-img" style="background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));">
							<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
								<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
							</svg>
						</div>
						<div class="overlay-text">Fellowships</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Theme Toggle -->
		<button class="theme-toggle-modern" id="theme-toggle" aria-label="Toggle theme">
			<svg class="sun-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
			<svg class="moon-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
		</button>
	</div>


	<!-- Scripts -->
	<script>
		// Theme toggle
		document.getElementById('theme-toggle')?.addEventListener('click', function() {
			const current = document.documentElement.getAttribute('data-theme');
			const newTheme = current === 'dark' ? 'light' : 'dark';
			document.documentElement.setAttribute('data-theme', newTheme);
			localStorage.setItem('theme', newTheme);
		});

		// Password toggle
		document.querySelector('.toggle-password-v2')?.addEventListener('click', function() {
			const passwordInput = document.getElementById('password');
			const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
			passwordInput.setAttribute('type', type);
			this.classList.toggle('active');
		});
	</script>

	<!-- Load scripts in correct order -->
	<script src="../js/env.js"></script>
	<script src="../js/config.js"></script>
	<script src="../js/auth.js"></script>
	
	<!-- Simple test to verify scripts loaded -->
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			// KingsChat Login Handler
			const kingschatBtn = document.getElementById('kingschat-login-btn');
			if (kingschatBtn) {
				kingschatBtn.addEventListener('click', function() {
					// Redirect to KingsChat OAuth
					window.location.href = '../../backend/kingschat/authorize.php';
				});
			}
		});
	</script>
</body>
</html>