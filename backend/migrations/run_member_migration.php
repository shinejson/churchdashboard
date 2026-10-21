<?php
/**
 * Simple Migration Runner for Member Permissions
 * This script directly executes the SQL statements
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Running Member Permissions Migration</h1>";
echo "<pre>";

try {
    $db = (new Database())->getConnection();
    echo "✓ Database connected\n\n";
    
    // 1. Create permissions table
    echo "1. Creating permissions table...\n";
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `permissions` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `role` varchar(50) NOT NULL,
              `module` varchar(50) NOT NULL,
              `allowed` tinyint(1) NOT NULL DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_role_module` (`role`, `module`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "   ✓ Permissions table created\n";
    } catch (PDOException $e) {
        echo "   ⚠ " . $e->getMessage() . "\n";
    }
    
    // 2. Insert permissions for all roles
    echo "\n2. Inserting permissions...\n";
    
    $permissions = [
        // Admin
        ['admin', 'dashboard', 1],
        ['admin', 'users', 1],
        ['admin', 'fellowships', 1],
        ['admin', 'attendance', 1],
        ['admin', 'financial', 1],
        ['admin', 'reports', 1],
        ['admin', 'settings', 1],
        ['admin', 'profile', 1],
        ['admin', 'members', 1],
        ['admin', 'cells', 1],
        ['admin', 'departments', 1],
        
        // Pastor
        ['pastor', 'dashboard', 1],
        ['pastor', 'users', 0],
        ['pastor', 'fellowships', 1],
        ['pastor', 'attendance', 1],
        ['pastor', 'financial', 1],
        ['pastor', 'reports', 1],
        ['pastor', 'settings', 0],
        ['pastor', 'profile', 1],
        ['pastor', 'members', 1],
        ['pastor', 'cells', 1],
        ['pastor', 'departments', 1],
        
        // PCF
        ['pcf', 'dashboard', 1],
        ['pcf', 'users', 0],
        ['pcf', 'fellowships', 1],
        ['pcf', 'attendance', 1],
        ['pcf', 'financial', 0],
        ['pcf', 'reports', 0],
        ['pcf', 'settings', 0],
        ['pcf', 'profile', 1],
        ['pcf', 'members', 1],
        ['pcf', 'cells', 1],
        ['pcf', 'departments', 0],
        
        // Leader
        ['leader', 'dashboard', 1],
        ['leader', 'users', 0],
        ['leader', 'fellowships', 0],
        ['leader', 'attendance', 1],
        ['leader', 'financial', 0],
        ['leader', 'reports', 0],
        ['leader', 'settings', 0],
        ['leader', 'profile', 1],
        ['leader', 'members', 0],
        ['leader', 'cells', 0],
        ['leader', 'departments', 0],
        
        // Member
        ['member', 'dashboard', 0],
        ['member', 'users', 0],
        ['member', 'fellowships', 0],
        ['member', 'attendance', 0],
        ['member', 'financial', 0],
        ['member', 'reports', 0],
        ['member', 'settings', 0],
        ['member', 'profile', 1],
        ['member', 'members', 0],
        ['member', 'cells', 0],
        ['member', 'departments', 0],
    ];
    
    $stmt = $db->prepare("
        INSERT INTO permissions (role, module, allowed) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)
    ");
    
    $inserted = 0;
    foreach ($permissions as $perm) {
        try {
            $stmt->execute($perm);
            $inserted++;
        } catch (PDOException $e) {
            echo "   ⚠ Error inserting {$perm[0]}/{$perm[1]}: " . $e->getMessage() . "\n";
        }
    }
    echo "   ✓ Inserted/updated $inserted permissions\n";
    
    // 3. Add member_id to users table
    echo "\n3. Adding member_id column to users table...\n";
    try {
        $db->exec("ALTER TABLE `users` ADD COLUMN `member_id` int(11) DEFAULT NULL AFTER `fellowship_id`");
        echo "   ✓ Column added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "   ⚠ Column already exists\n";
        } else {
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // 4. Add index on member_id
    echo "\n4. Adding index on users.member_id...\n";
    try {
        $db->exec("ALTER TABLE `users` ADD KEY `idx_member_id` (`member_id`)");
        echo "   ✓ Index added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "   ⚠ Index already exists\n";
        } else {
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // 5. Add user_id to members table
    echo "\n5. Adding user_id column to members table...\n";
    try {
        $db->exec("ALTER TABLE `members` ADD COLUMN `user_id` int(11) DEFAULT NULL AFTER `id`");
        echo "   ✓ Column added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "   ⚠ Column already exists\n";
        } else {
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // 6. Add index on user_id
    echo "\n6. Adding index on members.user_id...\n";
    try {
        $db->exec("ALTER TABLE `members` ADD KEY `idx_user_id` (`user_id`)");
        echo "   ✓ Index added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "   ⚠ Index already exists\n";
        } else {
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // 7. Link existing users to members where IDs match
    echo "\n7. Linking existing users to members...\n";
    try {
        $stmt = $db->exec("
            UPDATE `users` u
            INNER JOIN `members` m ON u.id = m.id
            SET u.member_id = m.id, m.user_id = u.id
            WHERE u.member_id IS NULL AND m.user_id IS NULL
        ");
        echo "   ✓ Linked $stmt records\n";
    } catch (PDOException $e) {
        echo "   ⚠ " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✓ Migration completed successfully!\n";
    echo str_repeat("=", 50) . "\n\n";
    
    echo "Next steps:\n";
    echo "1. Run the test script: backend/migrations/test_member_setup.php\n";
    echo "2. Create member user accounts in the Users page\n";
    echo "3. Link users to members if needed\n";
    echo "4. Test member login\n";
    
} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
