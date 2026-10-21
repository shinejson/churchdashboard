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
    
    $category = $_GET['category'] ?? 'general';
    
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
    
    // Get settings for category
    $stmt = $db->prepare("SELECT * FROM settings WHERE category = ? ORDER BY `key`");
    $stmt->execute([$category]);
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
