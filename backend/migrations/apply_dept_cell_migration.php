<?php
/**
 * Migration to add Departments and Cells
 */
require_once __DIR__ . '/../config/database.php';

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    echo "Starting Department and Cell migration...\n";
    
    // 1. Create Departments Table
    $db->exec("CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    echo "✓ Departments table ready.\n";
    
    // 2. Create Cells Table
    $db->exec("CREATE TABLE IF NOT EXISTS cells (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        leader_id INT NULL, 
        fellowship_id INT NULL,
        location TEXT NULL,
        meeting_day VARCHAR(20) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    echo "✓ Cells table ready.\n";
    
    // 3. Update Members Table
    $columnsToAdd = [
        'department_id' => "INT NULL AFTER fellowship_id",
        'role_in_church' => "VARCHAR(100) NULL AFTER department_id",
        'cell_id' => "INT NULL AFTER role_in_church"
    ];
    
    foreach ($columnsToAdd as $col => $definition) {
        $check = $db->query("SHOW COLUMNS FROM members LIKE '$col'");
        if ($check->rowCount() === 0) {
            $db->exec("ALTER TABLE members ADD COLUMN $col $definition");
            echo "✓ Column '$col' added to members table.\n";
        } else {
            echo "✓ Column '$col' already exists in members table.\n";
        }
    }
    
    echo "\nAll migrations applied successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
