<?php
/**
 * Test Member Profile Setup
 * This script verifies that the member profile feature is properly configured
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Member Profile Setup Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #f5f5f5; }
        .status { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Member Profile Setup Test</h1>
";

try {
    $db = (new Database())->getConnection();
    echo "<p class='success'>✓ Database connection successful</p>";
    
    // Test 1: Check if permissions table exists
    echo "<div class='section'>";
    echo "<h2>1. Permissions Table</h2>";
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'permissions'");
        if ($stmt->rowCount() > 0) {
            echo "<p class='success'>✓ Permissions table exists</p>";
            
            // Check member permissions
            $permStmt = $db->query("SELECT module, allowed FROM permissions WHERE role = 'member'");
            $perms = $permStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($perms) > 0) {
                echo "<p class='success'>✓ Member permissions configured (" . count($perms) . " modules)</p>";
                echo "<table>";
                echo "<tr><th>Module</th><th>Allowed</th></tr>";
                foreach ($perms as $perm) {
                    $allowed = $perm['allowed'] ? 'Yes' : 'No';
                    $class = $perm['allowed'] ? 'success' : '';
                    echo "<tr><td>{$perm['module']}</td><td class='$class'>$allowed</td></tr>";
                }
                echo "</table>";
            } else {
                echo "<p class='error'>✗ No member permissions found</p>";
            }
        } else {
            echo "<p class='error'>✗ Permissions table does not exist</p>";
            echo "<p>Run the migration: <code>backend/migrations/apply_member_permissions.php</code></p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error checking permissions table: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
    
    // Test 2: Check users table structure
    echo "<div class='section'>";
    echo "<h2>2. Users Table Structure</h2>";
    try {
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'member_id'");
        if ($stmt->rowCount() > 0) {
            echo "<p class='success'>✓ Users table has member_id column</p>";
        } else {
            echo "<p class='warning'>⚠ Users table missing member_id column</p>";
            echo "<p>Run the migration to add this column</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error checking users table: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
    
    // Test 3: Check members table structure
    echo "<div class='section'>";
    echo "<h2>3. Members Table Structure</h2>";
    try {
        $stmt = $db->query("SHOW COLUMNS FROM members LIKE 'user_id'");
        if ($stmt->rowCount() > 0) {
            echo "<p class='success'>✓ Members table has user_id column</p>";
        } else {
            echo "<p class='warning'>⚠ Members table missing user_id column</p>";
            echo "<p>Run the migration to add this column</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error checking members table: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
    
    // Test 4: Check for member role users
    echo "<div class='section'>";
    echo "<h2>4. Member Role Users</h2>";
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'member'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $result['count'];
        
        if ($count > 0) {
            echo "<p class='success'>✓ Found $count user(s) with member role</p>";
            
            // Show member users
            $usersStmt = $db->query("
                SELECT u.id, u.username, u.member_id, 
                       m.first_name, m.last_name
                FROM users u
                LEFT JOIN members m ON u.member_id = m.id
                WHERE u.role = 'member'
                LIMIT 10
            ");
            $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table>";
            echo "<tr><th>User ID</th><th>Username</th><th>Member ID</th><th>Linked Member</th><th>Status</th></tr>";
            foreach ($users as $user) {
                $linked = $user['member_id'] ? 'Yes' : 'No';
                $memberName = $user['first_name'] ? "{$user['first_name']} {$user['last_name']}" : '-';
                $statusClass = $user['member_id'] ? 'success' : 'warning';
                $status = $user['member_id'] ? '✓ Linked' : '⚠ Not Linked';
                
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td>{$user['username']}</td>";
                echo "<td>" . ($user['member_id'] ?: '-') . "</td>";
                echo "<td>$memberName</td>";
                echo "<td class='$statusClass'>$status</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠ No users with member role found</p>";
            echo "<p>Create member users in the Users management page</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error checking member users: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
    
    // Test 5: Check unlinked users and members
    echo "<div class='section'>";
    echo "<h2>5. Unlinked Accounts</h2>";
    try {
        // Unlinked users
        $unlinkedUsersStmt = $db->query("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE member_id IS NULL AND role IN ('member', 'leader', 'pcf', 'pastor')
        ");
        $unlinkedUsers = $unlinkedUsersStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Unlinked members
        $unlinkedMembersStmt = $db->query("
            SELECT COUNT(*) as count 
            FROM members 
            WHERE user_id IS NULL
        ");
        $unlinkedMembers = $unlinkedMembersStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($unlinkedUsers > 0) {
            echo "<p class='warning'>⚠ $unlinkedUsers user(s) not linked to members</p>";
        } else {
            echo "<p class='success'>✓ All users are linked to members</p>";
        }
        
        if ($unlinkedMembers > 0) {
            echo "<p class='warning'>⚠ $unlinkedMembers member(s) not linked to users</p>";
        } else {
            echo "<p class='success'>✓ All members are linked to users</p>";
        }
        
        if ($unlinkedUsers > 0 || $unlinkedMembers > 0) {
            echo "<p>Use the link_member.php endpoint to link accounts</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error checking unlinked accounts: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
    
    // Test 6: Check required tables for profile
    echo "<div class='section'>";
    echo "<h2>6. Required Tables</h2>";
    $requiredTables = [
        'users' => 'User accounts',
        'members' => 'Member records',
        'fellowships' => 'Fellowship information',
        'cells' => 'Home cell information',
        'departments' => 'Department information',
        'tithe_transactions' => 'Tithe contributions',
        'partnership_contributions' => 'Partnership contributions',
        'partnership_arms' => 'Partnership arms',
        'attendance_records' => 'Attendance records',
        'services' => 'Service information'
    ];
    
    echo "<table>";
    echo "<tr><th>Table</th><th>Description</th><th>Status</th></tr>";
    foreach ($requiredTables as $table => $description) {
        try {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<tr><td>$table</td><td>$description</td><td class='success'>✓ Exists</td></tr>";
            } else {
                echo "<tr><td>$table</td><td>$description</td><td class='error'>✗ Missing</td></tr>";
            }
        } catch (Exception $e) {
            echo "<tr><td>$table</td><td>$description</td><td class='error'>✗ Error</td></tr>";
        }
    }
    echo "</table>";
    echo "</div>";
    
    // Summary
    echo "<div class='section'>";
    echo "<h2>Summary</h2>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>If permissions table is missing, run: <code>backend/migrations/apply_member_permissions.php</code></li>";
    echo "<li>Create member user accounts in the Users management page</li>";
    echo "<li>Link user accounts to member records using <code>backend/users/link_member.php</code></li>";
    echo "<li>Test member login and profile access</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Database connection failed: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
