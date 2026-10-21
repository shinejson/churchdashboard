<?php
try {
    require_once 'c:/xampp/htdocs/church/backend/config/database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "Altering table...\n";
    $pdo->exec("ALTER TABLE users MODIFY COLUMN email VARCHAR(191) NULL DEFAULT NULL");
    
    // Also, if there are existing empty strings, convert them to NULL to avoid unique constraint violations
    $pdo->exec("UPDATE users SET email = NULL WHERE email = ''");
    
    echo "Success!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
