<?php
// backend/ajax/cell_members.php
require_once '../config/security.php';
require_once '../config/database.php';

secure_session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$db = (new Database())->getConnection();
$user_role = $_SESSION['role'] ?? 'user';
$user_id = $_SESSION['user_id'];

try {
    // Get cell_id from query parameter
    $cell_id = isset($_GET['cell_id']) ? intval($_GET['cell_id']) : null;
    
    // Build query based on role
    if ($user_role === 'admin') {
        // Admin can see all cell members
        if ($cell_id) {
            // Specific cell
            $query = "SELECT 
                m.id,
                m.first_name,
                m.last_name,
                m.phone,
                m.email,
                m.status,
                m.category,
                m.cell_id,
                c.name as cell_name,
                f.name as fellowship_name,
                m.role_in_church
            FROM members m
            LEFT JOIN cells c ON m.cell_id = c.id
            LEFT JOIN fellowships f ON m.fellowship_id = f.id
            WHERE m.cell_id = :cell_id
            ORDER BY m.first_name, m.last_name";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':cell_id', $cell_id, PDO::PARAM_INT);
        } else {
            // All members with cells
            $query = "SELECT 
                m.id,
                m.first_name,
                m.last_name,
                m.phone,
                m.email,
                m.status,
                m.category,
                m.cell_id,
                c.name as cell_name,
                f.name as fellowship_name,
                m.role_in_church
            FROM members m
            LEFT JOIN cells c ON m.cell_id = c.id
            LEFT JOIN fellowships f ON m.fellowship_id = f.id
            WHERE m.cell_id IS NOT NULL
            ORDER BY c.name, m.first_name, m.last_name";
            
            $stmt = $db->prepare($query);
        }
    } else {
        // Non-admin users can only see members from their own cell
        // First, get the user's cell_id
        $userQuery = "SELECT cell_id FROM members WHERE id = :user_id";
        $userStmt = $db->prepare($userQuery);
        $userStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $userStmt->execute();
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData || !$userData['cell_id']) {
            echo json_encode([
                'success' => true,
                'data' => [],
                'message' => 'You are not assigned to any cell'
            ]);
            exit;
        }
        
        $user_cell_id = $userData['cell_id'];
        
        // Only show members from the same cell
        $query = "SELECT 
            m.id,
            m.first_name,
            m.last_name,
            m.phone,
            m.email,
            m.status,
            m.category,
            m.cell_id,
            c.name as cell_name,
            f.name as fellowship_name,
            m.role_in_church
        FROM members m
        LEFT JOIN cells c ON m.cell_id = c.id
        LEFT JOIN fellowships f ON m.fellowship_id = f.id
        WHERE m.cell_id = :cell_id
        ORDER BY m.first_name, m.last_name";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':cell_id', $user_cell_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    $formattedMembers = array_map(function($member) {
        return [
            'id' => $member['id'],
            'name' => trim($member['first_name'] . ' ' . $member['last_name']),
            'first_name' => $member['first_name'],
            'last_name' => $member['last_name'],
            'phone' => $member['phone'],
            'email' => $member['email'],
            'status' => $member['status'],
            'category' => $member['category'],
            'cell_id' => $member['cell_id'],
            'cell_name' => $member['cell_name'],
            'fellowship_name' => $member['fellowship_name'],
            'role_in_church' => $member['role_in_church']
        ];
    }, $members);
    
    echo json_encode([
        'success' => true,
        'data' => $formattedMembers,
        'count' => count($formattedMembers),
        'is_admin' => $user_role === 'admin'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch cell members: ' . $e->getMessage()
    ]);
}
