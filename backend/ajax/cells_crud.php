<?php
// backend/ajax/cells_crud.php
ob_start();
session_start();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

try {
    include_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) throw new Exception("Database connection failed");
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            getCells($db);
            break;
        case 'POST':
            createCell($db);
            break;
        case 'PUT':
            updateCell($db);
            break;
        case 'DELETE':
            deleteCell($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(["success" => false, "message" => "Method not allowed"]);
            break;
    }
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
}

ob_end_flush();

function getCells($db) {
    $stmt = $db->query("
        SELECT c.*, 
               CONCAT(m.first_name, ' ', m.last_name) as leader_name,
               f.name as fellowship_name
        FROM cells c
        LEFT JOIN members m ON c.leader_id = m.id
        LEFT JOIN fellowships f ON c.fellowship_id = f.id
        ORDER BY c.name
    ");
    $cells = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "data" => $cells]);
}

function createCell($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['name'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Name is required"]);
        return;
    }

    $stmt = $db->prepare("INSERT INTO cells (name, leader_id, fellowship_id, location, meeting_day) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        trim($input['name']), 
        $input['leader_id'] ?: null, 
        $input['fellowship_id'] ?: null, 
        $input['location'] ?? '', 
        $input['meeting_day'] ?? ''
    ]);
    echo json_encode(["success" => true, "message" => "Cell created successfully", "id" => $db->lastInsertId()]);
}

function updateCell($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['id']) || empty($input['name'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID and name are required"]);
        return;
    }

    $stmt = $db->prepare("UPDATE cells SET name = ?, leader_id = ?, fellowship_id = ?, location = ?, meeting_day = ? WHERE id = ?");
    $stmt->execute([
        trim($input['name']), 
        $input['leader_id'] ?: null, 
        $input['fellowship_id'] ?: null, 
        $input['location'] ?? '', 
        $input['meeting_day'] ?? '', 
        $input['id']
    ]);
    echo json_encode(["success" => true, "message" => "Cell updated successfully"]);
}

function deleteCell($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID is required"]);
        return;
    }

    // Check if any members are in this cell
    $check = $db->prepare("SELECT id FROM members WHERE cell_id = ? LIMIT 1");
    $check->execute([$input['id']]);
    if ($check->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Cannot delete cell: It still has members assigned"]);
        return;
    }

    $stmt = $db->prepare("DELETE FROM cells WHERE id = ?");
    $stmt->execute([$input['id']]);
    echo json_encode(["success" => true, "message" => "Cell deleted successfully"]);
}
