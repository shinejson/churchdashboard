<?php
/**
 * Link User Account to Member Record
 * This endpoint allows admins to link user accounts to member records
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
        // Link a user to a member
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['user_id']) || empty($data['member_id'])) {
            throw new Exception('User ID and Member ID are required');
        }
        
        $user_id = (int)$data['user_id'];
        $member_id = (int)$data['member_id'];
        
        // Verify user exists
        $userStmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Verify member exists
        $memberStmt = $db->prepare("SELECT id, first_name, last_name FROM members WHERE id = ?");
        $memberStmt->execute([$member_id]);
        $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$member) {
            throw new Exception('Member not found');
        }
        
        // Update both tables
        $db->beginTransaction();
        
        $updateUser = $db->prepare("UPDATE users SET member_id = ? WHERE id = ?");
        $updateUser->execute([$member_id, $user_id]);
        
        $updateMember = $db->prepare("UPDATE members SET user_id = ? WHERE id = ?");
        $updateMember->execute([$user_id, $member_id]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "User '{$user['username']}' linked to member '{$member['first_name']} {$member['last_name']}'",
            'data' => [
                'user_id' => $user_id,
                'member_id' => $member_id
            ]
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get unlinked users and members
        
        // Unlinked users (users without member_id)
        $unlinkedUsersStmt = $db->query("
            SELECT id, username, email, role, created_at 
            FROM users 
            WHERE member_id IS NULL AND role IN ('member', 'leader', 'pcf', 'pastor')
            ORDER BY username
        ");
        $unlinkedUsers = $unlinkedUsersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Unlinked members (members without user_id)
        $unlinkedMembersStmt = $db->query("
            SELECT id, first_name, last_name, email, phone, created_at 
            FROM members 
            WHERE user_id IS NULL
            ORDER BY first_name, last_name
        ");
        $unlinkedMembers = $unlinkedMembersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Already linked
        $linkedStmt = $db->query("
            SELECT u.id as user_id, u.username, u.role,
                   m.id as member_id, m.first_name, m.last_name, m.email
            FROM users u
            INNER JOIN members m ON u.member_id = m.id
            ORDER BY u.username
        ");
        $linked = $linkedStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'unlinked_users' => $unlinkedUsers,
                'unlinked_members' => $unlinkedMembers,
                'linked' => $linked
            ]
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
