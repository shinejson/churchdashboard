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

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['filename'])) {
        throw new Exception('No file specified');
    }
    
    $filename = basename($input['filename']); // Prevent directory traversal
    $backupDir = __DIR__ . '/../backups';
    $filepath = $backupDir . '/' . $filename;
    
    if (!file_exists($filepath)) {
        throw new Exception('File not found');
    }
    
    // Verify it's a SQL file
    if (pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
        throw new Exception('Invalid file type');
    }
    
    // Delete file
    if (unlink($filepath)) {
        security_log('info', "Database backup deleted: $filename by user " . $_SESSION['user_id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete file');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
