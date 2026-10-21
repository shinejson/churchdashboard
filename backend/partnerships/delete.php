<?php
// backend/partnerships/delete.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

try {
    include_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception("Database connection failed");
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Partnership arm ID is required']);
        exit;
    }

    // Check if partnership arm exists
    $checkQuery = "SELECT id FROM partnership_arms WHERE id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$id]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Partnership arm not found']);
        exit;
    }

    $query = "DELETE FROM partnership_arms WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Partnership arm deleted successfully']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>