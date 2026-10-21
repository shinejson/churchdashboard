<?php
/**
 * KingsChat OAuth Callback Handler
 * Handles the OAuth callback from KingsChat
 */

require_once '../config/security.php';
require_once '../config/database.php';
require_once 'config.php';

secure_session_start();

try {
    // Check for authorization code
    if (!isset($_GET['code'])) {
        throw new Exception('Authorization code not received');
    }
    
    $code = $_GET['code'];
    
    // Exchange code for access token
    $tokenData = getKingsChatAccessToken($code);
    
    if (!$tokenData || !isset($tokenData['access_token'])) {
        throw new Exception('Failed to get access token');
    }
    
    $accessToken = $tokenData['access_token'];
    
    // Get user profile from KingsChat
    $userProfile = getKingsChatUserProfile($accessToken);
    
    if (!$userProfile) {
        throw new Exception('Failed to get user profile');
    }
    
    // Extract user information
    $kingschatId = $userProfile['id'] ?? null;
    $email = $userProfile['email'] ?? null;
    $name = $userProfile['name'] ?? '';
    $firstName = $userProfile['first_name'] ?? '';
    $lastName = $userProfile['last_name'] ?? '';
    $avatar = $userProfile['avatar'] ?? null;
    
    if (!$kingschatId) {
        throw new Exception('KingsChat user ID not found');
    }
    
    $db = (new Database())->getConnection();
    
    // Check if user exists with this KingsChat ID
    $stmt = $db->prepare("SELECT * FROM users WHERE kingschat_id = ?");
    $stmt->execute([$kingschatId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // User exists, log them in
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['fellowship_id'] = $user['fellowship_id'];
        $_SESSION['failed_login'] = ['count' => 0, 'ts' => 0];
        
        // Update last_seen
        $updateStmt = $db->prepare("UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        // Redirect based on role
        if ($user['role'] === 'member') {
            header('Location: ../../frontend/pages/profile.php');
        } else {
            // KingsChat is for members only
            throw new Exception('KingsChat login is reserved for church members only. Please use your credentials for admin access.');
        }
        exit();
        
    } else {
        // User doesn't exist, check if we can find them by email
        if ($email) {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Link KingsChat account to existing user
                $updateStmt = $db->prepare("
                    UPDATE users 
                    SET kingschat_id = ?, 
                        kingschat_token = ?,
                        avatar = COALESCE(?, avatar)
                    WHERE id = ?
                ");
                $updateStmt->execute([$kingschatId, $accessToken, $avatar, $user['id']]);
                
                // Log them in
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['fellowship_id'] = $user['fellowship_id'];
                
                // Redirect based on role
                if ($user['role'] === 'member') {
                    header('Location: ../../frontend/pages/profile.php');
                } else {
                    // KingsChat is for members only
                    throw new Exception('KingsChat login is reserved for church members only. Please use your credentials for admin access.');
                }
                exit();
            }
        }
        
        // New user - store in session and redirect to registration
        $_SESSION['kingschat_pending'] = [
            'kingschat_id' => $kingschatId,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => $name,
            'avatar' => $avatar,
            'access_token' => $accessToken
        ];
        
        header('Location: ../../frontend/pages/kingschat_register.php');
        exit();
    }
    
} catch (Exception $e) {
    error_log('KingsChat OAuth Error: ' . $e->getMessage());
    
    // Redirect to login with error
    $_SESSION['login_error'] = 'KingsChat authentication failed: ' . $e->getMessage();
    header('Location: ../../frontend/pages/login.php');
    exit();
}
