<?php
// backend/members/read.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/security.php';

allow_cors();
secure_session_start();

// Allow both GET and OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed. Use GET."]);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));
    $sort = in_array($_GET['sort'] ?? '', ['first_name','last_name','email','phone','join_date','status','fellowship_name','created_at']) 
        ? $_GET['sort'] : 'first_name';
    $order = strtoupper($_GET['order'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
    $search = trim($_GET['search'] ?? '');
    $fellowship_id = !empty($_GET['fellowship_id']) ? (int)$_GET['fellowship_id'] : null;
    $status = $_GET['status'] ?? '';
    $category = $_GET['category'] ?? '';
    $department_id = !empty($_GET['department']) ? (int)$_GET['department'] : null;
    $cell_id = !empty($_GET['cell']) ? (int)$_GET['cell'] : null;
   
    $offset = ($page - 1) * $limit;
    $params = [];
    $where = [];

    // Fellowship data isolation: non-admins are always scoped to their own fellowship
    $scopeId = current_fellowship_scope();
    if ($scopeId !== null) {
        // Override any UI-provided fellowship filter with the scoped one
        $fellowship_id = $scopeId;
    }

    if ($search !== '') {
        $where[] = "(m.first_name LIKE :search OR m.last_name LIKE :search OR m.phone LIKE :search OR m.email LIKE :search OR f.name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($fellowship_id !== null) {
        $where[] = "m.fellowship_id = :fellowship_id";
        $params[':fellowship_id'] = $fellowship_id;
    }
    if ($status !== '') {
        $where[] = "m.status = :status";
        $params[':status'] = $status;
    }

     if (in_array($category, ['adult', 'children', 'teen'])) {
        $where[] = "m.category = :category";
        $params[':category'] = $category;
    }

    if ($department_id !== null) {
        $where[] = "m.department_id = :department_id";
        $params[':department_id'] = $department_id;
    }

    if ($cell_id !== null) {
        $where[] = "m.cell_id = :cell_id";
        $params[':cell_id'] = $cell_id;
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count total
    $countSql = "SELECT COUNT(*) FROM members m LEFT JOIN fellowships f ON m.fellowship_id = f.id $whereClause";
    $countStmt = $db->prepare($countSql);
    foreach ($params as $k => $v) {
        if (strpos($countSql, $k) !== false) $countStmt->bindValue($k, $v);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();
    $totalPages = ceil($total / $limit);

    // Main query
    $sortField = $sort === 'fellowship_name' ? 'f.name' : "m.$sort";
    $sql = "SELECT m.*, f.name as fellowship_name, f.id as fellowship_id, 
                   d.name as department_name, c.name as cell_name, m.category 
        FROM members m 
        LEFT JOIN fellowships f ON m.fellowship_id = f.id 
        LEFT JOIN departments d ON m.department_id = d.id
        LEFT JOIN cells c ON m.cell_id = c.id
        $whereClause 
        ORDER BY $sortField $order 
        LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add full photo URL
    foreach ($members as &$member) {
        if (!empty($member['photo'])) {
            $member['photo_url'] = "/church/" . $member['photo'];
        }
    }

    echo json_encode([
        "data" => $members,
        "pagination" => [
            "current_page" => $page,
            "total_pages" => $totalPages,
            "total_records" => $total,
            "per_page" => $limit
        ]
    ]);

} catch (Exception $e) {
    error_log("Members read error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Server error"]);
}
?>