<?php
// Auto-fix attendance table structure
// Save as: backend/attendance/auto_fix_attendance.php
// Run once: http://localhost/church/backend/attendance/auto_fix_attendance.php

header("Content-Type: text/html; charset=UTF-8");

echo "<h1>Attendance Table Auto-Fix</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
    pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
</style>";

try {
    include_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "<p class='success'>✓ Connected to database</p>";
    
    // Check if attendance table exists
    $check = $db->query("SHOW TABLES LIKE 'attendance'");
    if ($check->rowCount() === 0) {
        echo "<p class='error'>✗ Attendance table does not exist!</p>";
        exit;
    }
    
    echo "<p class='success'>✓ Attendance table exists</p>";
    
    // Get current columns
    $currentColumns = [];
    $stmt = $db->query("SHOW COLUMNS FROM attendance");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currentColumns[] = $row['Field'];
    }
    
    echo "<h2>Current Columns:</h2>";
    echo "<pre>" . implode(", ", $currentColumns) . "</pre>";
    
    $fixesApplied = [];
    $errors = [];
    
    // Check and add missing columns
    $columnsToAdd = [
        'status' => [
            'sql' => "ALTER TABLE `attendance` ADD COLUMN `status` ENUM('present', 'absent', 'excused') NOT NULL DEFAULT 'present' AFTER `fellowship_id`",
            'description' => 'Status column (present/absent/excused)'
        ],
        'notes' => [
            'sql' => "ALTER TABLE `attendance` ADD COLUMN `notes` TEXT NULL AFTER `status`",
            'description' => 'Notes column for additional information'
        ],
        'is_deleted' => [
            'sql' => "ALTER TABLE `attendance` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notes`",
            'description' => 'Soft delete flag'
        ],
        'created_by' => [
            'sql' => "ALTER TABLE `attendance` ADD COLUMN `created_by` INT(11) NULL AFTER `is_deleted`",
            'description' => 'User who created the record'
        ],
        'updated_at' => [
            'sql' => "ALTER TABLE `attendance` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`",
            'description' => 'Last update timestamp'
        ]
    ];
    
    echo "<h2>Applying Fixes:</h2>";
    
    foreach ($columnsToAdd as $column => $config) {
        if (!in_array($column, $currentColumns)) {
            echo "<p class='info'>Adding column: <strong>$column</strong> - {$config['description']}</p>";
            try {
                $db->exec($config['sql']);
                echo "<p class='success'>✓ Successfully added $column column</p>";
                $fixesApplied[] = $column;
            } catch (Exception $e) {
                echo "<p class='error'>✗ Error adding $column: " . $e->getMessage() . "</p>";
                $errors[] = $column . ": " . $e->getMessage();
            }
        } else {
            echo "<p class='success'>✓ Column $column already exists</p>";
        }
    }
    
    // Add indexes if they don't exist
    echo "<h2>Adding Indexes:</h2>";
    
    try {
        $indexes = $db->query("SHOW INDEXES FROM attendance")->fetchAll(PDO::FETCH_ASSOC);
        $indexNames = array_column($indexes, 'Key_name');
        
        if (!in_array('idx_status', $indexNames)) {
            $db->exec("ALTER TABLE `attendance` ADD INDEX `idx_status` (`status`)");
            echo "<p class='success'>✓ Added idx_status index</p>";
        } else {
            echo "<p class='success'>✓ idx_status already exists</p>";
        }
        
        if (!in_array('idx_is_deleted', $indexNames)) {
            $db->exec("ALTER TABLE `attendance` ADD INDEX `idx_is_deleted` (`is_deleted`)");
            echo "<p class='success'>✓ Added idx_is_deleted index</p>";
        } else {
            echo "<p class='success'>✓ idx_is_deleted already exists</p>";
        }
        
        if (!in_array('idx_service_date', $indexNames)) {
            $db->exec("ALTER TABLE `attendance` ADD INDEX `idx_service_date` (`service_date`)");
            echo "<p class='success'>✓ Added idx_service_date index</p>";
        } else {
            echo "<p class='success'>✓ idx_service_date already exists</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>Error adding indexes: " . $e->getMessage() . "</p>";
    }
    
    // Show final structure
    echo "<h2>Final Table Structure:</h2>";
    $finalStruct = $db->query("DESCRIBE attendance")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($finalStruct as $col) {
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Summary
    echo "<h2>Summary:</h2>";
    if (count($fixesApplied) > 0) {
        echo "<p class='success'>✓ Fixed " . count($fixesApplied) . " columns: " . implode(", ", $fixesApplied) . "</p>";
    } else {
        echo "<p class='info'>No fixes were needed - table structure is correct!</p>";
    }
    
    if (count($errors) > 0) {
        echo "<p class='error'>✗ " . count($errors) . " errors occurred:</p>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li class='error'>$error</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='success'><strong>✓ All fixes applied successfully!</strong></p>";
        echo "<p class='success'>Your attendance system should now work properly.</p>";
        echo "<p><a href='../../frontend/pages/attendance.php'>Go to Attendance Page</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>Fatal Error: " . $e->getMessage() . "</p>";
}
?>