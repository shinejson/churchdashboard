<?php
/**
 * Add KingsChat OAuth Fields to Users Table
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Adding KingsChat OAuth Fields</h1>";
echo "<pre>";

try {
    $db = (new Database())->getConnection();
    echo "✓ Database connected\n\n";
    
    // 1. Add kingschat_id column
    echo "1. Adding kingschat_id column...\n";
    try {
        $db->exec("ALTER TABLE `users` ADD COLUMN `kingschat_id` varchar(100) DEFAULT NULL AFTER `password`");
        echo "   ✓ kingschat_id column added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "   ⚠ kingschat_id column already exists\n";
        } else {
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // 2. Add kingschat_token column
    echo "\n2. Adding kingschat_token column...\n";
    try {
        $db->exec("ALTER TABLE `users` ADD COLUMN `kingschat_token` text DEFAULT NULL AFTER `kingschat_id`");
        echo "   ✓ kingschat_token column added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "   ⚠ kingschat_token column already exists\n";
        } else {
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // 3. Add email column
    echo "\n3. Adding email column...\n";
    try {
        $db->exec("ALTER TABLE `users` ADD COLUMN `email` varchar(255) DEFAULT NULL AFTER `username`");
        echo "   ✓ email column added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "   ⚠ email column already exists\n";
        } else {
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // 4. Add avatar column
    echo "\n4. Adding avatar column...\n";
    try {
        $db->exec("ALTER TABLE `users` ADD COLUMN `avatar` varchar(255) DEFAULT NULL AFTER `email`");
        echo "   ✓ avatar column added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "   ⚠ avatar column already exists\n";
        } else {
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // 5. Add unique index on kingschat_id
    echo "\n5. Adding unique index on kingschat_id...\n";
    try {
        $db->exec("ALTER TABLE `users` ADD UNIQUE KEY `unique_kingschat_id` (`kingschat_id`)");
        echo "   ✓ Unique index added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "   ⚠ Index already exists\n";
        } else {
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // 6. Add index on email
    echo "\n6. Adding index on email...\n";
    try {
        $db->exec("ALTER TABLE `users` ADD KEY `idx_email` (`email`)");
        echo "   ✓ Index added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "   ⚠ Index already exists\n";
        } else {
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✓ Migration completed successfully!\n";
    echo str_repeat("=", 60) . "\n\n";
    
    echo "KingsChat OAuth fields added:\n";
    echo "✓ kingschat_id - Stores KingsChat user ID\n";
    echo "✓ kingschat_token - Stores OAuth access token\n";
    echo "✓ email - Stores user email\n";
    echo "✓ avatar - Stores user avatar URL\n\n";
    
    echo "Next steps:\n";
    echo "1. Configure KingsChat OAuth credentials in .env file\n";
    echo "2. Test KingsChat login from login page\n";
    echo "3. Users can now sign in with KingsChat\n";
    
} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
