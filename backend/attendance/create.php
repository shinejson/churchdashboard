<?php
// backend/attendance/create.php
ob_start();
session_start();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

try {
    include_once '../config/database.php';
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database config error"]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) throw new Exception("Database connection failed");
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
    echo json_encode(["success" => false, "message" => "Missing required fields: date, fellowship_id, service_id, or records"]);
    exit;
}

$service_date       = $data['date'];
$fellowship_id      = $data['fellowship_id'];
$service_id         = $data['service_id'];
$notes              = $data['notes'] ?? '';
$first_timers_count = isset($data['first_timers_count']) ? (int)$data['first_timers_count'] : 0;
$records            = $data['records'];
$created_by         = $_SESSION['user_id'] ?? null;

// Debug logging
error_log('Attendance Create - Received data: ' . json_encode([
    'date' => $service_date,
    'fellowship_id' => $fellowship_id,
    'service_id' => $service_id,
    'first_timers_count' => $first_timers_count,
    'records_count' => count($records)
]));

try {
    $db->beginTransaction();

    // 1. Validate service_id exists
    $svcCheck = $db->prepare("SELECT id FROM services WHERE id = ? AND is_active = 1");
    $svcCheck->execute([$service_id]);
    if ($svcCheck->rowCount() === 0) {
        throw new Exception("Invalid or inactive service ID: $service_id");
    }

    // 2. Optional: Validate fellowship exists (if not null)
    if ($fellowship_id !== null) {
        $felCheck = $db->prepare("SELECT id FROM fellowships WHERE id = ? AND status = 'active'");
        $felCheck->execute([$fellowship_id]);
        if ($felCheck->rowCount() === 0) {
            throw new Exception("Invalid or inactive fellowship ID");
        }
    }

    // 3. Delete existing attendance for this date + fellowship + service (replace mode)
    $deleteStmt = $db->prepare("
        DELETE FROM attendance 
        WHERE service_date = ? 
          AND fellowship_id = ? 
          AND service_id = ?
    ");
    $deleteStmt->execute([$service_date, $fellowship_id, $service_id]);

    // 4. Insert new records with first_timers_count
    // Check if first_timers_count column exists
    $columns = $db->query("SHOW COLUMNS FROM attendance LIKE 'first_timers_count'")->fetchAll();
    $hasFirstTimers = count($columns) > 0;
    
    error_log('First timers column exists: ' . ($hasFirstTimers ? 'YES' : 'NO'));
    error_log('First timers count to save: ' . $first_timers_count);
    
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

    $insertedCount = 0;
    foreach ($records as $record) {
        if (empty($record['member_id'])) continue;

        $status = in_array($record['status'], ['present', 'absent', 'excused']) 
                  ? $record['status'] 
                  : 'present';

        if ($hasFirstTimers) {
            // Only store first_timers_count on the first record of the session
            $firstTimersValue = ($insertedCount === 0) ? $first_timers_count : 0;
            
            if ($insertedCount === 0) {
                error_log('Saving first_timers_count on first record: ' . $firstTimersValue);
            }
            
            $insertStmt->execute([
                $fellowship_id,
                $record['member_id'],
                $service_date,
                $service_id,
                $status,
                $notes,
                $firstTimersValue,
                $created_by
            ]);
        } else {
            $insertStmt->execute([
                $fellowship_id,
                $record['member_id'],
                $service_date,
                $service_id,
                $status,
                $notes,
                $created_by
            ]);
        }
        $insertedCount++;
    }

    $db->commit();

    ob_end_clean();
    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Attendance recorded successfully",
        "data" => [
            "records_saved" => $insertedCount,
            "service_date" => $service_date,
            "service" => $service_id,
            "fellowship_id" => $fellowship_id
        ]
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    ob_end_clean();
    http_response_code($e->getMessage() === "Invalid or inactive service ID: $service_id" ? 400 : 500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

ob_end_flush();
?>