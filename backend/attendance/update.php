<?php
// backend/attendance/update.php
ob_start();

require_once '../config/security.php';
require_once '../config/database.php';

secure_session_start();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Debug session data
error_log('Update.php session check: ' . json_encode([
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'] ?? 'NOT SET',
    'role' => $_SESSION['role'] ?? 'NOT SET',
    'session_keys' => array_keys($_SESSION)
]));

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['date']) || empty($data['fellowship_id']) || empty($data['service_id']) || empty($data['records'])) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

$service_date       = $data['date'];
$fellowship_id      = $data['fellowship_id'];
$service_id         = $data['service_id'];
$notes              = $data['notes'] ?? '';
$first_timers_count = isset($data['first_timers_count']) ? (int)$data['first_timers_count'] : 0;
$records            = $data['records'];
$updated_by         = $_SESSION['user_id'] ?? null;

// Optional: Require admin role
$user_role = $_SESSION['role'] ?? 'user';
if ($user_role !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    echo json_encode([
        "success" => false, 
        "message" => "Only admins can edit attendance",
        "debug" => [
            "session_id" => session_id(),
            "user_role" => $user_role,
            "user_id" => $_SESSION['user_id'] ?? 'NOT SET'
        ]
    ]);
    exit;
}

try {
    $db->beginTransaction();

    // Delete old records for this session
    $deleteStmt = $db->prepare("
        DELETE FROM attendance 
        WHERE service_date = ? 
          AND fellowship_id = ? 
          AND service_id = ?
    ");
    $deleteStmt->execute([$service_date, $fellowship_id, $service_id]);

    // Check if first_timers_count column exists
    $columns = $db->query("SHOW COLUMNS FROM attendance LIKE 'first_timers_count'")->fetchAll();
    $hasFirstTimers = count($columns) > 0;

    // Insert new records
    if ($hasFirstTimers) {
        $insertStmt = $db->prepare("
            INSERT INTO attendance 
            (fellowship_id, member_id, service_date, service_id, status, notes, first_timers_count, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
    } else {
        $insertStmt = $db->prepare("
            INSERT INTO attendance 
            (fellowship_id, member_id, service_date, service_id, status, notes, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
    }

    $updatedCount = 0;
    foreach ($records as $record) {
        if (empty($record['member_id'])) continue;

        $status = in_array($record['status'], ['present', 'absent', 'excused']) ? $record['status'] : 'present';

        if ($hasFirstTimers) {
            // Only store first_timers_count on the first record of the session
            $firstTimersValue = ($updatedCount === 0) ? $first_timers_count : 0;
            $insertStmt->execute([
                $fellowship_id,
                $record['member_id'],
                $service_date,
                $service_id,
                $status,
                $notes,
                $firstTimersValue,
                $updated_by
            ]);
        } else {
            $insertStmt->execute([
                $fellowship_id,
                $record['member_id'],
                $service_date,
                $service_id,
                $status,
                $notes,
                $updated_by
            ]);
        }
        $updatedCount++;
    }

    $db->commit();

    ob_end_clean();
    echo json_encode([
        "success" => true,
        "message" => "Attendance updated successfully",
        "records_updated" => $updatedCount
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    ob_end_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Update failed: " . $e->getMessage()]);
}
?>