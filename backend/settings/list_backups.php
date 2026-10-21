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
    $backupDir = __DIR__ . '/../backups';
    
    // Create backup directory if it doesn't exist
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $backups = [];
    $files = glob($backupDir . '/*.sql');
    
    foreach ($files as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => formatBytes(filesize($file)),
            'date' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }
    
    // Sort by date descending
    usort($backups, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    echo json_encode([
        'success' => true,
        'backups' => $backups
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
