<?php
require_once '../config/security.php';
secure_session_start();
allow_cors();
header("Content-Type: application/json; charset=UTF-8");

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

include_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get database credentials from config
    $config = require '../config/database.php';
    
    $backupDir = __DIR__ . '/../backups';
    
    // Create backup directory if it doesn't exist
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . '/' . $filename;
    
    // Get all tables
    $tables = [];
    $result = $db->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    // Start building SQL dump
    $sqlDump = "-- Church Management System Database Backup\n";
    $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
        // Get table structure
        $result = $db->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch(PDO::FETCH_NUM);
        
        $sqlDump .= "-- Table: $table\n";
        $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";
        $sqlDump .= $row[1] . ";\n\n";
        
        // Get table data
        $result = $db->query("SELECT * FROM `$table`");
        $numRows = $result->rowCount();
        
        if ($numRows > 0) {
            $sqlDump .= "-- Data for table: $table\n";
            
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $columns = array_keys($row);
                $values = array_values($row);
                
                // Escape values
                $values = array_map(function($value) use ($db) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return $db->quote($value);
                }, $values);
                
                $sqlDump .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
            }
            
            $sqlDump .= "\n";
        }
    }
    
    $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    // Write to file
    file_put_contents($filepath, $sqlDump);
    
    security_log('info', "Database backup created: $filename by user " . $_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Backup created successfully',
        'filename' => $filename
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
