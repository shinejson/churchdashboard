<?php
// backend/permissions/update.php
require_once '../../config/security.php';
secure_session_start();
require_csrf_token();
if ($_SESSION['role'] !== 'admin') exit(json_encode(['message' => 'Unauthorized'], 403));

$data = json_decode(file_get_contents('php://input'), true);
$db = (new Database())->getConnection();
$stmt = $db->prepare("REPLACE INTO permissions (role, module, allowed) VALUES (?, ?, ?)");

foreach ($data as $item) {
    $stmt->execute([$item['role'], $item['module'], $item['allowed']]);
}

echo json_encode(['message' => 'Permissions saved! Reloading...']);