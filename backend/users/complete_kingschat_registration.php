<?php
/**
 * Complete KingsChat Registration
 * Creates a new user account with KingsChat OAuth data
 */

require_once '../config/security.php';
require_once '../config/database.php';

secure_session_start();

header('Content-Type: application/json');

try {
    // Check if we have pending KingsChat data
    if (!isset($_SESSION['kingschat_pending'])) {
        throw new Exception('No pending KingsChat registration found');
    }
    
    $kingschatData = $_SESSION['kingschat_pending'];
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid request data');
    }
    
    // Validate required fields
    $required = ['username', 'first_name', 'last_name', 'fellowship_id'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    $username = trim($input['username']);
    $firstName = trim($input['first_name']);
    $lastName = trim($input['last_name']);
    $fellowshipId = (int)$input['fellowship_id'];
    $phone = !empty($input['phone']) ? trim($input['phone']) : null;
    
    // Validate username format
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        throw new Exception('Username must be 3-20 characters and contain only letters, numbers, and underscores');
    }
    
    $db = (new Database())->getConnection();
    
    // Check if username already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        throw new Exception('Username already exists');
    }
    
    // Check if fellowship exists
    $stmt = $db->prepare("SELECT id FROM fellowships WHERE id = ?");
    $stmt->execute([$fellowshipId]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid fellowship selected');
    }
    
    // Create user account
    $insertStmt = $db->prepare("
        INSERT INTO users (
            username, 
            email, 
            password, 
            role, 
            fellowship_id, 
            kingschat_id, 
            kingschat_token, 
            avatar,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    // Generate a random password (won't be used since they login with KingsChat)
    $randomPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    
    // Default role for new KingsChat users is 'member'
    $role = 'member';
    
    $insertStmt->execute([
        $username,
        $kingschatData['email'] ?? null,
        $randomPassword,
        $role,
        $fellowshipId,
        $kingschatData['kingschat_id'],
        $kingschatData['access_token'],
        $kingschatData['avatar'] ?? null
    ]);
    
    $userId = $db->lastInsertId();
    
    // Create member record
    $memberStmt = $db->prepare("
        INSERT INTO members (
            first_name,
            last_name,
            phone,
            email,
            fellowship_id,
            user_id,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $memberStmt->execute([
        $firstName,
        $lastName,
        $phone,
        $kingschatData['email'] ?? null,
        $fellowshipId,
        $userId
    ]);
    
    $memberId = $db->lastInsertId();
    
    // Link user to member
    $updateStmt = $db->prepare("UPDATE users SET member_id = ? WHERE id = ?");
    $updateStmt->execute([$memberId, $userId]);
    
    // Set up permissions for member role
    $permStmt = $db->prepare("
        INSERT IGNORE INTO permissions (role, permission, can_view, can_create, can_edit, can_delete)
        VALUES ('member', 'profile', 1, 0, 1, 0)
    ");
    $permStmt->execute();
    
    // Log them in
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
    $_SESSION['fellowship_id'] = $fellowshipId;
    $_SESSION['failed_login'] = ['count' => 0, 'ts' => 0];
    
    // Clear pending registration data
    unset($_SESSION['kingschat_pending']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Registration completed successfully',
        'role' => $role,
        'user_id' => $userId
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
