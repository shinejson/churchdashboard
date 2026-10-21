<?php
// backend/partnerships/create.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

    if (!isset($data['name']) || empty(trim($data['name']))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Partnership arm name is required']);
        exit;
    }

    $name = trim($data['name']);
    $description = isset($data['description']) ? trim($data['description']) : null;
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;

    // Check if name already exists
    $checkQuery = "SELECT id FROM partnership_arms WHERE name = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$name]);
    
    if ($checkStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Partnership arm name already exists']);
        exit;
    }

    $query = "INSERT INTO partnership_arms (name, description, is_active) VALUES (?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$name, $description, $is_active]);

    echo json_encode([
        'success' => true, 
        'message' => 'Partnership arm created successfully',
        'id' => $db->lastInsertId()
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>