<?php
header("Content-Type: application/json");
include_once '../config/database.php';
include_once '../config/security.php';

$date = $_GET['date'] ?? '';
$fellowship_id = $_GET['fellowship_id'] ?? '';
$service_id = $_GET['service_id'] ?? '';

// Fellowship data isolation: non-admins are always scoped to their own fellowship
$scopeId = current_fellowship_scope();
if ($scopeId !== null) {
    $fellowship_id = (string)$scopeId;
}

if (!$date || !$fellowship_id) {
    echo json_encode(["success" => false, "attendance" => null]);
    exit;
}

try {
    $db = (new Database())->getConnection();

    // Check if first_timers_count column exists
    $hasFirstTimers = $db->query("SHOW COLUMNS FROM attendance LIKE 'first_timers_count'")->rowCount() > 0;
    $firstTimersSelect = $hasFirstTimers ? "MAX(COALESCE(a.first_timers_count, 0)) as firstTimersCount," : "0 as firstTimersCount,";

    // Build WHERE clause - add service_id filter if provided
    $whereConditions = ["a.service_date = ?", "a.fellowship_id = ?", "a.status = 'present'"];
    $params = [$date, $fellowship_id];
    
    if (!empty($service_id)) {
        $whereConditions[] = "a.service_id = ?";
        $params[] = $service_id;
    }
    
    $whereClause = implode(' AND ', $whereConditions);

    // Join attendance with members to get category information
    $query = "SELECT 
        MAX(a.service_id) as service_id,
        $firstTimersSelect
        SUM(CASE WHEN m.category = 'adult' THEN 1 ELSE 0 END) as adultCount,
        SUM(CASE WHEN m.category = 'teen' THEN 1 ELSE 0 END) as teenCount,
        SUM(CASE WHEN m.category = 'children' THEN 1 ELSE 0 END) as childCount,
        COUNT(a.id) as totalCount
        FROM attendance a
        JOIN members m ON a.member_id = m.id
        WHERE $whereClause";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "attendance" => [
            "service_id" => $row['service_id'],
            "adultCount" => (int)($row['adultCount'] ?? 0),
            "teenCount" => (int)($row['teenCount'] ?? 0),
            "childCount" => (int)($row['childCount'] ?? 0),
            "firstTimersCount" => (int)($row['firstTimersCount'] ?? 0),
            "totalCount" => (int)($row['totalCount'] ?? 0)
        ]
    ]);
} catch (Exception $e) {
    error_log("Attendance error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "attendance" => [
            "adultCount" => 0,
            "teenCount" => 0,
            "childCount" => 0,
            "firstTimersCount" => 0,
            "totalCount" => 0
        ]
    ]);
}
?>