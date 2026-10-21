<?php
require_once '../config/security.php';
secure_session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('Access denied');
}

try {
    if (!isset($_GET['file'])) {
        throw new Exception('No file specified');
    }
    
    $filename = basename($_GET['file']); // Prevent directory traversal
    $backupDir = __DIR__ . '/../backups';
    $filepath = $backupDir . '/' . $filename;
    
    if (!file_exists($filepath)) {
        throw new Exception('File not found');
    }
    
    // Verify it's a SQL file
    if (pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
        throw new Exception('Invalid file type');
    }
    
    security_log('info', "Database backup downloaded: $filename by user " . $_SESSION['user_id']);
    
    // Send file
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit();
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error: ' . $e->getMessage());
}
