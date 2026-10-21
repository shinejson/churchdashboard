<?php
/**
 * Quick Setup Check - JSON Output
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

try {
    $db = (new Database())->getConnection();
    $results = [];
    
    // 1. Check permissions table
    $stmt = $db->query("SELECT COUNT(*) as count FROM permissions WHERE role = 'member'");
    $results['permissions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // 2. Check users.member_id column
    try {
        $db->query("SELECT member_id FROM users LIMIT 1");
        $results['users_member_id'] = true;
    } catch (PDOException $e) {
        $results['users_member_id'] = false;
    }
    
    // 3. Check members.user_id column
    try {
        $db->query("SELECT user_id FROM members LIMIT 1");
        $results['members_user_id'] = true;
    } catch (PDOException $e) {
        $results['members_user_id'] = false;
    }
    
    // 4. Count member role users
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'member'");
    $results['member_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // 5. Count linked accounts
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE member_id IS NOT NULL");
    $results['linked_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // 6. Check required tables
    $tables = ['users', 'members', 'fellowships', 'cells', 'departments', 
               'tithe_transactions', 'partnership_contributions', 'attendance_records'];
    $results['tables'] = [];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $results['tables'][$table] = $stmt->rowCount() > 0;
    }
    
    $results['success'] = true;
    $results['ready'] = (
        $results['permissions'] > 0 &&
        $results['users_member_id'] &&
        $results['members_user_id']
    );
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
