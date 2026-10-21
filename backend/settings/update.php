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
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['category']) || !isset($input['settings'])) {
        throw new Exception('Missing required fields');
    }
    
    $category = $input['category'];
    $settings = $input['settings'];
    
    // Create settings table if it doesn't exist
    $createTable = "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(50) NOT NULL,
        `key` VARCHAR(100) NOT NULL,
        value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_setting (category, `key`)
    )";
    $db->exec($createTable);
    
    $db->beginTransaction();
    
    // Update or insert each setting
    $stmt = $db->prepare("
        INSERT INTO settings (category, `key`, value) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = CURRENT_TIMESTAMP
    ");
    
    foreach ($settings as $key => $value) {
        $stmt->execute([$category, $key, $value]);
    }
    
    $db->commit();
    
    security_log('info', "Settings updated for category: $category by user " . $_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Settings updated successfully'
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
