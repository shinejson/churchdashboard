<?php
/**
 * KingsChat Configuration Test
 * Verifies OAuth configuration is set up correctly
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KingsChat OAuth Configuration Test</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .icon {
            font-size: 24px;
            font-weight: bold;
        }
        .config-item {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 4px;
        }
        .config-item strong {
            display: block;
            color: #667eea;
            margin-bottom: 5px;
        }
        .config-item code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .next-steps {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 6px;
            margin-top: 30px;
        }
        .next-steps h2 {
            color: #0066cc;
            margin-top: 0;
        }
        .next-steps ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        .next-steps li {
            margin: 8px 0;
        }
        a {
            color: #667eea;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 KingsChat OAuth Configuration Test</h1>
        
        <?php
        $allGood = true;
        
        // Test 1: Check if constants are defined
        echo '<h2>Configuration Status</h2>';
        
        if (defined('KINGSCHAT_CLIENT_ID') && KINGSCHAT_CLIENT_ID !== 'your_kingschat_client_id') {
            echo '<div class="status success">';
            echo '<span class="icon">✓</span>';
            echo '<div><strong>Client ID:</strong> Configured</div>';
            echo '</div>';
        } else {
            echo '<div class="status error">';
            echo '<span class="icon">✗</span>';
            echo '<div><strong>Client ID:</strong> Not configured or using default value</div>';
            echo '</div>';
            $allGood = false;
        }
        
        if (defined('KINGSCHAT_CLIENT_SECRET') && KINGSCHAT_CLIENT_SECRET !== 'your_kingschat_client_secret') {
            echo '<div class="status success">';
            echo '<span class="icon">✓</span>';
            echo '<div><strong>Client Secret:</strong> Configured</div>';
            echo '</div>';
        } else {
            echo '<div class="status error">';
            echo '<span class="icon">✗</span>';
            echo '<div><strong>Client Secret:</strong> Not configured or using default value</div>';
            echo '</div>';
            $allGood = false;
        }
        
        if (defined('KINGSCHAT_REDIRECT_URI')) {
            $redirectUri = KINGSCHAT_REDIRECT_URI;
            if (strpos($redirectUri, 'yourdomain.com') === false) {
                echo '<div class="status success">';
                echo '<span class="icon">✓</span>';
                echo '<div><strong>Redirect URI:</strong> Configured</div>';
                echo '</div>';
            } else {
                echo '<div class="status warning">';
                echo '<span class="icon">⚠</span>';
                echo '<div><strong>Redirect URI:</strong> Using default domain (yourdomain.com)</div>';
                echo '</div>';
                $allGood = false;
            }
        } else {
            echo '<div class="status error">';
            echo '<span class="icon">✗</span>';
            echo '<div><strong>Redirect URI:</strong> Not defined</div>';
            echo '</div>';
            $allGood = false;
        }
        
        // Test 2: Check HTTPS
        echo '<h2>Security Check</h2>';
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                   || $_SERVER['SERVER_PORT'] == 443
                   || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        
        if ($isHttps) {
            echo '<div class="status success">';
            echo '<span class="icon">✓</span>';
            echo '<div><strong>HTTPS:</strong> Enabled (Required for OAuth)</div>';
            echo '</div>';
        } else {
            echo '<div class="status warning">';
            echo '<span class="icon">⚠</span>';
            echo '<div><strong>HTTPS:</strong> Not detected. OAuth requires HTTPS in production.</div>';
            echo '</div>';
        }
        
        // Test 3: Check cURL
        if (function_exists('curl_init')) {
            echo '<div class="status success">';
            echo '<span class="icon">✓</span>';
            echo '<div><strong>cURL:</strong> Available (Required for API calls)</div>';
            echo '</div>';
        } else {
            echo '<div class="status error">';
            echo '<span class="icon">✗</span>';
            echo '<div><strong>cURL:</strong> Not available. Install PHP cURL extension.</div>';
            echo '</div>';
            $allGood = false;
        }
        
        // Display current configuration
        echo '<h2>Current Configuration</h2>';
        
        echo '<div class="config-item">';
        echo '<strong>Client ID:</strong> ';
        echo '<code>' . (defined('KINGSCHAT_CLIENT_ID') ? substr(KINGSCHAT_CLIENT_ID, 0, 10) . '...' : 'Not set') . '</code>';
        echo '</div>';
        
        echo '<div class="config-item">';
        echo '<strong>Client Secret:</strong> ';
        echo '<code>' . (defined('KINGSCHAT_CLIENT_SECRET') && KINGSCHAT_CLIENT_SECRET !== 'your_kingschat_client_secret' ? '••••••••••' : 'Not set') . '</code>';
        echo '</div>';
        
        echo '<div class="config-item">';
        echo '<strong>Redirect URI:</strong> ';
        echo '<code>' . (defined('KINGSCHAT_REDIRECT_URI') ? KINGSCHAT_REDIRECT_URI : 'Not set') . '</code>';
        echo '</div>';
        
        echo '<div class="config-item">';
        echo '<strong>Authorization URL:</strong> ';
        echo '<code>' . KINGSCHAT_AUTH_URL . '</code>';
        echo '</div>';
        
        echo '<div class="config-item">';
        echo '<strong>Token URL:</strong> ';
        echo '<code>' . KINGSCHAT_TOKEN_URL . '</code>';
        echo '</div>';
        
        echo '<div class="config-item">';
        echo '<strong>API URL:</strong> ';
        echo '<code>' . KINGSCHAT_API_URL . '</code>';
        echo '</div>';
        
        // Test authorization URL generation
        if ($allGood) {
            echo '<h2>Test Authorization URL</h2>';
            try {
                $authUrl = getKingsChatAuthUrl();
                echo '<div class="status success">';
                echo '<span class="icon">✓</span>';
                echo '<div><strong>Authorization URL Generated Successfully</strong><br>';
                echo '<small>URL: <code>' . htmlspecialchars($authUrl) . '</code></small></div>';
                echo '</div>';
            } catch (Exception $e) {
                echo '<div class="status error">';
                echo '<span class="icon">✗</span>';
                echo '<div><strong>Error generating authorization URL:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '</div>';
            }
        }
        
        // Summary and next steps
        if ($allGood) {
            echo '<div class="status success" style="margin-top: 30px; font-size: 18px;">';
            echo '<span class="icon">🎉</span>';
            echo '<div><strong>Configuration Complete!</strong> Your KingsChat OAuth is ready to use.</div>';
            echo '</div>';
            
            echo '<div class="next-steps">';
            echo '<h2>Ready to Test</h2>';
            echo '<ol>';
            echo '<li>Go to your <a href="../../frontend/pages/login.php">login page</a></li>';
            echo '<li>Click "Continue with KingsChat"</li>';
            echo '<li>Authorize the application</li>';
            echo '<li>Complete registration or login</li>';
            echo '</ol>';
            echo '</div>';
        } else {
            echo '<div class="status error" style="margin-top: 30px; font-size: 18px;">';
            echo '<span class="icon">⚠</span>';
            echo '<div><strong>Configuration Incomplete</strong> Please complete the setup steps below.</div>';
            echo '</div>';
            
            echo '<div class="next-steps">';
            echo '<h2>Setup Steps</h2>';
            echo '<ol>';
            echo '<li>Register your application at KingsChat Developer Portal</li>';
            echo '<li>Copy your Client ID and Client Secret</li>';
            echo '<li>Edit <code>backend/config/.env</code> file</li>';
            echo '<li>Add your credentials:';
            echo '<pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 10px;">';
            echo 'KINGSCHAT_CLIENT_ID=your_client_id_here' . "\n";
            echo 'KINGSCHAT_CLIENT_SECRET=your_client_secret_here' . "\n";
            echo 'KINGSCHAT_REDIRECT_URI=https://yourdomain.com/backend/kingschat/callback.php';
            echo '</pre>';
            echo '</li>';
            echo '<li>Refresh this page to verify configuration</li>';
            echo '</ol>';
            echo '<p><strong>Documentation:</strong> See <code>KINGSCHAT_OAUTH_GUIDE.md</code> for detailed instructions.</p>';
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #6c757d;">
            <small>KingsChat OAuth Integration Test • Church Management System</small>
        </div>
    </div>
</body>
</html>
