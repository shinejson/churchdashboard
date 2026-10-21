<?php
// backend/partnerships/update.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, POST, OPTIONS");
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

    $data = json_decode(file_get_contents("php://input"), true);
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Partnership arm ID is required']);
        exit;
    }

    if (!isset($data['name']) || empty(trim($data['name']))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Partnership arm name is required']);
        exit;
    }

    $name = trim($data['name']);
    $description = isset($data['description']) ? trim($data['description']) : null;
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;

    // Check if name already exists (excluding current record)
    $checkQuery = "SELECT id FROM partnership_arms WHERE name = ? AND id != ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$name, $id]);
    
    if ($checkStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Partnership arm name already exists']);
        exit;
    }

    $query = "UPDATE partnership_arms SET name = ?, description = ?, is_active = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$name, $description, $is_active, $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Partnership arm updated successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Partnership arm not found or no changes made']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>