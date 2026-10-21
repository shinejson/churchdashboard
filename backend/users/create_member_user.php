<?php
/**
 * Quick Member User Creation
 * This endpoint helps create member user accounts quickly
 */

require_once '../config/security.php';
require_once '../config/database.php';

secure_session_start();
allow_cors();
header('Content-Type: application/json');

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = (new Database())->getConnection();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['username']) || empty($data['password']) || empty($data['member_id'])) {
            throw new Exception('Username, password, and member_id are required');
        }
        
        $username = $data['username'];
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $member_id = (int)$data['member_id'];
        $fellowship_id = !empty($data['fellowship_id']) ? (int)$data['fellowship_id'] : null;
        
        // Check if username already exists
        $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        if ($checkStmt->rowCount() > 0) {
            throw new Exception('Username already exists');
        }
        
        // Verify member exists
        $memberStmt = $db->prepare("SELECT id, first_name, last_name, email, fellowship_id FROM members WHERE id = ?");
        $memberStmt->execute([$member_id]);
        $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$member) {
            throw new Exception('Member not found');
        }
        
        // Use member's fellowship if not provided
        if (!$fellowship_id) {
            $fellowship_id = $member['fellowship_id'];
        }
        
        $email = !empty($member['email']) ? $member['email'] : null;
        
        // Create user account
        $db->beginTransaction();
        
        $insertStmt = $db->prepare("
            INSERT INTO users (username, password, email, role, fellowship_id, member_id, created_at)
            VALUES (?, ?, ?, 'member', ?, ?, NOW())
        ");
        $insertStmt->execute([$username, $password, $email, $fellowship_id, $member_id]);
        
        $user_id = $db->lastInsertId();
        
        // Update member with user_id
        $updateMemberStmt = $db->prepare("UPDATE members SET user_id = ? WHERE id = ?");
        $updateMemberStmt->execute([$user_id, $member_id]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Member user created successfully for {$member['first_name']} {$member['last_name']}",
            'data' => [
                'user_id' => $user_id,
                'username' => $username,
                'member_id' => $member_id,
                'member_name' => "{$member['first_name']} {$member['last_name']}"
            ]
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get members without user accounts
        $stmt = $db->query("
            SELECT m.id, m.first_name, m.last_name, m.email, m.phone, 
                   m.fellowship_id, m.category, f.name as fellowship_name
            FROM members m
            LEFT JOIN fellowships f ON m.fellowship_id = f.id
            WHERE m.user_id IS NULL
            ORDER BY m.first_name, m.last_name
        ");
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $members
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
