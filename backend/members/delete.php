<?php
require_once '../config/security.php';
secure_session_start();
allow_cors();
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input") ?: '{}');
$id   = intval($data->id ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "ID required"]);
    exit();
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}
require_csrf_token();

require_once '../config/database.php';
$db = (new Database())->getConnection();

// Permission check
$check = $db->prepare("SELECT fellowship_id FROM members WHERE id = ?");
$check->execute([$id]);
$member = $check->fetch();

if (in_array($_SESSION['role'] ?? '', ['fellowship_leader', 'pcf_leader'])
    && $member['fellowship_id'] != ($_SESSION['fellowship_id'] ?? 0)) {
    http_response_code(403);
    echo json_encode(["error" => "Not authorized"]);
    exit();
}

$stmt = $db->prepare("DELETE FROM members WHERE id = ?");
if ($stmt->execute([$id])) {
    echo json_encode([
        "data" => [],
        "pagination" => ["total_records" => 0],
        "message" => "Member deleted successfully"
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Delete failed"]);
}
?>