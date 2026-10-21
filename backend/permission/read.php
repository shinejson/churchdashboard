<?php
// backend/permission/read.php
require_once '../config/security.php';
require_once '../config/database.php';

secure_session_start();

// Set JSON header first
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    $stmt = $db->query("SELECT role, module, allowed FROM permissions ORDER BY role, module");
    $perms = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $perms[$row['role']][$row['module']] = (bool)$row['allowed'];
    }
    echo json_encode($perms);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load permissions']);
}