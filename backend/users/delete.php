<?php
// backend/users/delete.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/security.php';

allow_cors();
secure_session_start();
require_csrf_token(); // Must come AFTER session start

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = (int)($data['id'] ?? 0);

    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['message' => 'Valid User ID required']);
        exit();
    }

    $db = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['message' => 'User not found']);
        exit();
    }

    // Prevent last admin deletion
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id != ?");
    $stmt->execute([$user_id]);
    if ($stmt->fetchColumn() == 0) {
        http_response_code(400);
        echo json_encode(['message' => 'Cannot delete the last admin']);
        exit();
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    echo json_encode(['message' => 'User deleted successfully']);
} catch (Exception $e) {
    error_log("Delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Server error']);
}