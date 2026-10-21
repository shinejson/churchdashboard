<?php
ob_start();

require_once '../config/security.php';
require_once '../config/database.php';

secure_session_start();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Admin only
if (($_SESSION['role'] ?? 'user') !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    echo json_encode([
        "success" => false, 
        "message" => "Admins only",
        "debug" => [
            "session_id" => session_id(),
            "role" => $_SESSION['role'] ?? 'NOT SET',
            "user_id" => $_SESSION['user_id'] ?? 'NOT SET'
        ]
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['date']) || empty($data['fellowship_id']) || empty($data['service_id'])) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "date, fellowship_id, service_id required"]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        UPDATE attendance 
        SET is_deleted = 1 
        WHERE service_date = ? 
          AND fellowship_id = ? 
          AND service_id = ?
    ");

    $stmt->execute([$data['date'], $data['fellowship_id'], $data['service_id']]);

    if ($stmt->rowCount() === 0) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Record not found"]);
        exit;
    }

    ob_end_clean();
    echo json_encode(["success" => true, "message" => "Attendance deleted (soft)"]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>



