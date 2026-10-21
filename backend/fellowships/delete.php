<?php
require_once '../config/security.php';
secure_session_start();
allow_cors();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE");

include_once '../config/database.php';

// Only pastors can delete
if (empty($_SESSION['role']) || $_SESSION['role'] != 'pastor') {
    http_response_code(403);
    echo json_encode(array("message" => "Only pastor can delete fellowships"));
    exit();
}

// Require CSRF
require_csrf_token();

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(array("error" => "Database connection failed"));
    exit();
}

$data = json_decode(file_get_contents("php://input"));

try {
    $id = intval($data->id ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(array("message" => "Invalid fellowship ID"));
        exit();
    }

    // Check if fellowship exists
    $check = "SELECT id FROM fellowships WHERE id = :id LIMIT 1";
    $stmt = $db->prepare($check);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(array("message" => "Fellowship not found"));
        exit();
    }

    // Check if fellowship has members - you may want to handle this differently
    $check_members = "SELECT COUNT(*) as count FROM members WHERE fellowship_id = :id";
    $stmt = $db->prepare($check_members);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        // You can either:
        // 1. Prevent deletion (uncomment next 5 lines)
        // http_response_code(400);
        // echo json_encode(array("message" => "Cannot delete fellowship with members. Please reassign members first."));
        // exit();
        
        // 2. Or reassign members to null
        // For now, let's reassign them to null
        $update_members = "UPDATE members SET fellowship_id = NULL WHERE fellowship_id = :id";
        $stmt = $db->prepare($update_members);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Delete fellowship
    $query = "DELETE FROM fellowships WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(array(
            "message" => "Fellowship deleted successfully",
            "id" => $id
        ));
    } else {
        http_response_code(503);
        echo json_encode(array("message" => "Unable to delete fellowship"));
    }
} catch (Exception $e) {
    http_response_code(500);
    security_log('error', 'Fellowship deletion failed: ' . $e->getMessage());
    echo json_encode(array("message" => "Server error"));
}
?>
