<?php
// backend/ajax/departments_crud.php
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
            getDepartments($db);
            break;
        case 'POST':
            createDepartment($db);
            break;
        case 'PUT':
            updateDepartment($db);
            break;
        case 'DELETE':
            deleteDepartment($db);
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

function getDepartments($db) {
    $stmt = $db->query("SELECT id, name, description FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "data" => $departments]);
}

function createDepartment($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['name'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Name is required"]);
        return;
    }

    $stmt = $db->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
    $stmt->execute([trim($input['name']), $input['description'] ?? '']);
    echo json_encode(["success" => true, "message" => "Department created successfully", "id" => $db->lastInsertId()]);
}

function updateDepartment($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['id']) || empty($input['name'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID and name are required"]);
        return;
    }

    $stmt = $db->prepare("UPDATE departments SET name = ?, description = ? WHERE id = ?");
    $stmt->execute([trim($input['name']), $input['description'] ?? '', $input['id']]);
    echo json_encode(["success" => true, "message" => "Department updated successfully"]);
}

function deleteDepartment($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID is required"]);
        return;
    }

    // Check if any members are in this department
    $check = $db->prepare("SELECT id FROM members WHERE department_id = ? LIMIT 1");
    $check->execute([$input['id']]);
    if ($check->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Cannot delete department: It still has members assigned"]);
        return;
    }

    $stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
    $stmt->execute([$input['id']]);
    echo json_encode(["success" => true, "message" => "Department deleted successfully"]);
}
