<?php
// backend/api/search.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/security.php';

allow_cors();
secure_session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    echo json_encode(['data' => []]);
    exit();
}

try {
    $db = (new Database())->getConnection();
    $results = [];
    $searchString = "%$query%";

    // Search members
    $stmt = $db->prepare("SELECT id, first_name, last_name, phone, status, category FROM members WHERE first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? LIMIT 5");
    $stmt->execute([$searchString, $searchString, $searchString]);
    while ($m = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id' => $m['id'],
            'title' => $m['first_name'] . ' ' . $m['last_name'],
            'subtitle' => ($m['phone'] ?: 'No phone') . ' - ' . ucfirst($m['status']),
            'type' => 'Member',
            'url' => 'members.php?search=' . urlencode($m['first_name'] . ' ' . $m['last_name'])
        ];
    }
    
    // Search fellowships
    $stmt = $db->prepare("SELECT id, name FROM fellowships WHERE name LIKE ? LIMIT 3");
    $stmt->execute([$searchString]);
    while ($f = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id' => $f['id'],
            'title' => $f['name'],
            'subtitle' => 'Fellowship Group',
            'type' => 'Fellowship',
            'url' => 'fellowships.php?search=' . urlencode($f['name'])
        ];
    }
    
    // Search users (if admin)
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'pastor') {
        $stmt = $db->prepare("SELECT id, username, role FROM users WHERE username LIKE ? OR email LIKE ? LIMIT 3");
        $stmt->execute([$searchString, $searchString]);
        while ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = [
                'id' => $u['id'],
                'title' => $u['username'],
                'subtitle' => 'System User (' . ucfirst($u['role']) . ')',
                'type' => 'User',
                'url' => 'users.php?search=' . urlencode($u['username'])
            ];
        }
    }

    echo json_encode(['data' => $results]);

} catch (Exception $e) {
    error_log("Search API error: " . $e->getMessage());
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>
