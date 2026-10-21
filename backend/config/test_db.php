<?php
// backend/config/test_db.php
require_once 'config.php';
require_once 'database.php';

header('Content-Type: application/json');

try {
    echo "--- Database Connection Test ---\n";
    echo "Host: " . DB_HOST . "\n";
    echo "Database: " . DB_NAME . "\n";
    echo "User: " . DB_USER . "\n";
    echo "-------------------------------\n";

    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn) {
        $stmt = $conn->query('SELECT VERSION() as version');
        $row = $stmt->fetch();
        echo json_encode([
            'success' => true,
            'message' => 'Connection Successful!',
            'mysql_version' => $row['version']
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Connection failed, but no exception was thrown.'
        ], JSON_PRETTY_PRINT);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Connection Failed!',
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
