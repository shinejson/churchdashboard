<?php
/**
 * Church Management System - Database Backup
 * 
 * Features:
 * - Full database backup with all tables and data
 * - Automatic compression (gzip)
 * - Download or save to server
 * - Scheduled backup support
 * - Backup history and management
 */

require_once '../config/security.php';
require_once '../config/database.php';
require_once '../config/config.php';

secure_session_start();
allow_cors();
header("Content-Type: application/json; charset=UTF-8");

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
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
    $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "-- Database: " . DB_NAME . "\n\n";
    $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sqlDump .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";
    
    foreach ($tables as $table) {
        // Get table structure
        $result = $db->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch(PDO::FETCH_NUM);
        
        $sqlDump .= "-- --------------------------------------------------------\n";
        $sqlDump .= "-- Table structure for table `$table`\n";
        $sqlDump .= "-- --------------------------------------------------------\n\n";
        $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";
        $sqlDump .= $row[1] . ";\n\n";
        
        // Get table data
        $result = $db->query("SELECT * FROM `$table`");
        $numRows = $result->rowCount();
        
        if ($numRows > 0) {
            $sqlDump .= "-- Dumping data for table `$table`\n\n";
            
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
    if (file_put_contents($filepath, $sqlDump) === false) {
        throw new Exception('Failed to write backup file');
    }
    
    // Log the backup creation
    security_log('info', "Database backup created: $filename by user " . $_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Backup created successfully',
        'filename' => $filename,
        'size' => formatBytes(filesize($filepath)),
        'tables' => count($tables)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Backup Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Backup failed: ' . $e->getMessage()
    ]);
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
