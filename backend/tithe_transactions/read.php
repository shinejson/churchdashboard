<?php
// backend/tithe_transactions/read.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/security.php';

allow_cors();
secure_session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $params = [];
    $where = [];

    // Filter by period_id if provided
    if (!empty($_GET['period_id'])) {
        $where[] = "period_id = :period_id";
        $params[':period_id'] = (int)$_GET['period_id'];
    }

    // Filter by member_id if provided
    if (!empty($_GET['member_id'])) {
        $where[] = "member_id = :member_id";
        $params[':member_id'] = (int)$_GET['member_id'];
    }

    // Filter by fellowship_id if provided
    if (!empty($_GET['fellowship_id'])) {
        $where[] = "fellowship_id = :fellowship_id";
        $params[':fellowship_id'] = (int)$_GET['fellowship_id'];
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT tt.*, m.first_name, m.last_name, f.name as fellowship_name 
            FROM tithe_transactions tt
            LEFT JOIN members m ON tt.member_id = m.id
            LEFT JOIN fellowships f ON tt.fellowship_id = f.id
            $whereClause
            ORDER BY tt.transaction_date DESC";

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "data" => $transactions,
        "count" => count($transactions)
    ]);

} catch (Exception $e) {
    error_log("Tithe transactions read error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Server error"]);
}
?>
