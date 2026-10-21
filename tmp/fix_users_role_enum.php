<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=church_management', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'pastor', 'pcf', 'leader', 'member') NOT NULL DEFAULT 'member'");
    echo "Table users altered successfully.\n";
    
    // Also update existing user 17 to have the correct role
    $pdo->exec("UPDATE users SET role = 'member' WHERE id = 17");
    echo "Updated user 17 role to 'member'.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
