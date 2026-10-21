<?php
// backend/attendance/read.php
ob_start();

session_start();
require_once '../config/security.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

try {
    include_once '../config/database.php';
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database configuration error"
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) throw new Exception("Database connection failed");
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not connect to database: " . $e->getMessage()
    ]);
    exit;
}

$action = $_GET['action'] ?? 'attendance';

try {
    switch ($action) {
    case 'fellowships':
        getFellowships($db);
        break;
    case 'members':
        getMembers($db);
        break;
    case 'record':           // ADD THIS
        getRecord($db);
        break;
    case 'record_members':   // ADD THIS
        getRecordMembers($db);
        break;
        // Add this new case in the switch statement in read.php
    case 'sample':
        getSampleAttendance($db);
        break;

    default:
        getAttendance($db);
        break;
}
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}

ob_end_flush();

// ==================================================================
// 1. GET FELLOWSHIPS (unchanged)
// ==================================================================
function getFellowships($db) {
    try {
        $checkTable = $db->query("SHOW TABLES LIKE 'fellowships'");
        if ($checkTable->rowCount() === 0) {
            echo json_encode([
                "success" => true,
                "data" => [
                    ["id" => 1, "name" => "Youth Fellowship"],
                    ["id" => 2, "name" => "Women's Fellowship"],
                    ["id" => 3, "name" => "Men's Fellowship"],
                    ["id" => 4, "name" => "Children's Ministry"],
                    ["id" => 5, "name" => "Senior Saints"]
                ],
                "message" => "Using sample data - fellowships table not found"
            ]);
            return;
        }

        $stmt = $db->query("SELECT id, name FROM fellowships WHERE status = 'active' ORDER BY name");
        $fellowships = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($fellowships)) {
            echo json_encode([
                "success" => true,
                "data" => [
                    ["id" => 1, "name" => "Youth Fellowship"],
                    ["id" => 2, "name" => "Women's Fellowship"],
                    ["id" => 3, "name" => "Men's Fellowship"],
                    ["id" => 4, "name" => "Children's Ministry"],
                    ["id" => 5, "name" => "Senior Saints"]
                ],
                "message" => "Using sample data - no active fellowships"
            ]);
            return;
        }

        echo json_encode(["success" => true, "data" => $fellowships]);
    } catch (Exception $e) {
        echo json_encode([
            "success" => true,
            "data" => [
                ["id" => 1, "name" => "Youth Fellowship"],
                ["id" => 2, "name" => "Women's Fellowship"],
                ["id" => 3, "name" => "Men's Fellowship"],
                ["id" => 4, "name" => "Children's Ministry"],
                ["id" => 5, "name" => "Senior Saints"]
            ],
            "message" => "Using sample data - error: " . $e->getMessage()
        ]);
    }
}

// ==================================================================
// 2. GET MEMBERS (unchanged)
// ==================================================================
function getMembers($db) {
    $fellowship_id = $_GET['fellowship_id'] ?? null;
    if (!$fellowship_id) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Fellowship ID required"]);
        return;
    }

    try {
        $checkTable = $db->query("SHOW TABLES LIKE 'members'");
        if ($checkTable->rowCount() === 0) {
            echo json_encode([
                "success" => true,
                "data" => [
                    ["id" => 1, "name" => "John Adebayo", "category" => "adult"],
                    ["id" => 2, "name" => "Sarah Okafor", "category" => "adult"],
                    ["id" => 3, "name" => "David Chukwu", "category" => "teen"],
                    ["id" => 4, "name" => "Grace Emmanuel", "category" => "teen"]
                ],
                "message" => "Using sample data - members table not found"
            ]);
            return;
        }

        $columns = $db->query("SHOW COLUMNS FROM members")->fetchAll(PDO::FETCH_COLUMN);
        $statusFilter = in_array('status', $columns) ? "AND status IN ('active', 'visitor')" :
                       (in_array('is_active', $columns) ? "AND is_active = 1" : "");

        $stmt = $db->prepare("
            SELECT id, first_name, last_name, category as age_category 
            FROM members 
            WHERE fellowship_id = ? $statusFilter
            ORDER BY first_name, last_name
        ");
        $stmt->execute([$fellowship_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($members)) {
            echo json_encode([
                "success" => true,
                "data" => [
                    ["id" => 1, "name" => "John Adebayo", "category" => "adult"],
                    ["id" => 2, "name" => "Sarah Okafor", "category" => "adult"]
                ],
                "message" => "No members found"
            ]);
            return;
        }

        $formatted = array_map(function($m) {
            return [
                'id' => $m['id'],
                'name' => trim($m['first_name'] . ' ' . $m['last_name']),
                'category' => $m['age_category'] ?? 'adult'
            ];
        }, $members);

        echo json_encode(["success" => true, "data" => $formatted]);
    } catch (Exception $e) {
        echo json_encode([
            "success" => true,
            "data" => [
                ["id" => 1, "name" => "John Adebayo", "category" => "adult"],
                ["id" => 2, "name" => "Sarah Okafor", "category" => "adult"]
            ],
            "message" => "Error: " . $e->getMessage()
        ]);
    }
}

// ==================================================================
// 3. GET ATTENDANCE – NOW WITH SERVICE NAME FROM services TABLE
// ==================================================================
function getAttendance($db) {
    try {
        $checkTable = $db->query("SHOW TABLES LIKE 'attendance'");
        if ($checkTable->rowCount() === 0) {
            echo json_encode(["success" => true, "records" => [], "total" => 0, "message" => "Attendance table not found"]);
            return;
        }

        // Fellowship data isolation: non-admins are always scoped to their own fellowship
        $scopeId = current_fellowship_scope();
        $fellowship_id = $scopeId ?? ($_SESSION['fellowship_id'] ?? null);
        $role = $_SESSION['role'] ?? 'member';

        $start_date   = $_GET['start_date'] ?? date('Y-m-01');
        $end_date     = $_GET['end_date'] ?? date('Y-m-t');
        $service_id   = $_GET['service_id'] ?? null;   // now filter by service_id
        $member_id    = $_GET['member_id'] ?? null;

        // Check column existence
        $hasNotes   = $db->query("SHOW COLUMNS FROM attendance LIKE 'notes'")->rowCount() > 0;
        $hasDeleted = $db->query("SHOW COLUMNS FROM attendance LIKE 'is_deleted'")->rowCount() > 0;
        $hasFirstTimers = $db->query("SHOW COLUMNS FROM attendance LIKE 'first_timers_count'")->rowCount() > 0;

        $notesSelect   = $hasNotes ? "MAX(a.notes) as notes," : "'' as notes,";
        $deletedWhere  = $hasDeleted ? "AND a.is_deleted = 0" : "";
        $firstTimersSelect = $hasFirstTimers ? "MAX(COALESCE(a.first_timers_count, 0)) as first_timers," : "0 as first_timers,";

        $query = "SELECT 
                    a.service_date as date,
                    a.service_id,
                    COALESCE(s.name, 'Unknown Service') as service_type,
                    a.fellowship_id,
                    COALESCE(f.name, 'Unknown Fellowship') as fellowship,
                    $notesSelect
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent,
                    $firstTimersSelect
                    ROUND(
                        (COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0) / 
                        NULLIF(COUNT(*), 0), 0
                    ) as percentage,
                    MIN(a.id) as sample_id
                  FROM attendance a
                  LEFT JOIN fellowships f ON a.fellowship_id = f.id
                  LEFT JOIN services s ON a.service_id = s.id
                  WHERE a.service_date BETWEEN ? AND ?
                  $deletedWhere";

        $params = [$start_date, $end_date];

        // If we have a scoped fellowship, always enforce it
        if ($fellowship_id) {
            $query .= " AND a.fellowship_id = ?";
            $params[] = $fellowship_id;
        }

        if ($service_id) {
            $query .= " AND a.service_id = ?";
            $params[] = $service_id;
        }

        if ($member_id) {
            $query .= " AND a.member_id = ?";
            $params[] = $member_id;
        }

        $query .= " GROUP BY a.service_date, a.service_id, s.name, a.fellowship_id, f.name
                    ORDER BY a.service_date DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "records" => $records,
            "total"   => count($records),
            "period"  => ['start' => $start_date, 'end' => $end_date]
        ]);

    } catch (Exception $e) {
        echo json_encode([
            "success" => true,
            "records" => [],
            "total"   => 0,
            "message" => "Error loading attendance: " . $e->getMessage()
        ]);
    }
}

// ==================================================================
// 4. GET SINGLE RECORD FOR EDITING
// ==================================================================
// ==================================================================
// 4. GET SINGLE RECORD FOR EDITING
// ==================================================================
function getRecord($db) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Record ID required"]);
        return;
    }

    try {
        // Check if first_timers_count column exists
        $hasFirstTimers = $db->query("SHOW COLUMNS FROM attendance LIKE 'first_timers_count'")->rowCount() > 0;
        $firstTimersSelect = $hasFirstTimers ? ", a.first_timers_count" : ", 0 as first_timers_count";
        
        // Get the specific attendance record
        $query = "SELECT 
                a.id,
                a.service_date as date,
                a.service_id,
                a.fellowship_id,
                COALESCE(f.name, 'Unknown') as fellowship_name,
                a.notes
                $firstTimersSelect
            FROM attendance a
            LEFT JOIN fellowships f ON a.fellowship_id = f.id
            WHERE a.id = ?
            LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Record not found"]);
            return;
        }

        echo json_encode([
            "success" => true,
            "record" => $record
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Error loading record: " . $e->getMessage()
        ]);
    }
}

// ==================================================================
// 5. GET MEMBERS WITH THEIR ATTENDANCE STATUS FOR A SPECIFIC RECORD
// ==================================================================
function getRecordMembers($db) {
    $record_id = $_GET['record_id'] ?? null;
    
    if (!$record_id) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Record ID required"]);
        return;
    }

    try {
        // First, get the attendance session details
        $sessionStmt = $db->prepare("
            SELECT service_date, fellowship_id, service_id 
            FROM attendance 
            WHERE id = ? 
            LIMIT 1
        ");
        $sessionStmt->execute([$record_id]);
        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Attendance session not found"]);
            return;
        }

        // Get all members' attendance for this session
        $stmt = $db->prepare("
            SELECT 
                a.member_id,
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                m.category,
                a.status
            FROM attendance a
            INNER JOIN members m ON a.member_id = m.id
            WHERE a.service_date = ?
              AND a.fellowship_id = ?
              AND a.service_id = ?
            ORDER BY m.first_name, m.last_name
        ");
        
        $stmt->execute([
            $session['service_date'],
            $session['fellowship_id'],
            $session['service_id']
        ]);
        
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($members)) {
            echo json_encode([
                "success" => true,
                "data" => [],
                "message" => "No members found for this attendance session"
            ]);
            return;
        }

        echo json_encode([
            "success" => true,
            "data" => $members
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Error loading members: " . $e->getMessage()
        ]);
    }
}


// Then add this function at the bottom of read.php
function getSampleAttendance($db) {
    $sample_id = $_GET['sample_id'] ?? null;
    
    if (!$sample_id) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Sample ID required"]);
        return;
    }

    try {
        // Check if first_timers_count column exists
        $hasFirstTimers = $db->query("SHOW COLUMNS FROM attendance LIKE 'first_timers_count'")->rowCount() > 0;
        $firstTimersSelect = $hasFirstTimers ? ", first_timers_count" : "";
        
        // First, get the session details from the sample record
        $sessionQuery = "SELECT service_date, fellowship_id, service_id, notes" . $firstTimersSelect . "
            FROM attendance 
            WHERE id = ? 
            LIMIT 1";
        
        $sessionStmt = $db->prepare($sessionQuery);
        $sessionStmt->execute([$sample_id]);
        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Attendance session not found"]);
            return;
        }

        // Now get ALL members for this specific session (service_date + fellowship + service)
        $stmt = $db->prepare("
            SELECT 
                a.member_id,
                m.first_name,
                m.last_name,
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                m.category,
                a.status,
                a.service_date,
                a.service_id,
                f.name as fellowship_name
            FROM attendance a
            INNER JOIN members m ON a.member_id = m.id
            LEFT JOIN fellowships f ON a.fellowship_id = f.id
            WHERE a.service_date = ?
              AND a.fellowship_id = ?
              AND a.service_id = ?
            ORDER BY m.first_name, m.last_name
        ");
        
        $stmt->execute([
            $session['service_date'],
            $session['fellowship_id'],
            $session['service_id']
        ]);
        
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add session notes and first_timers_count to first record
        if (!empty($records)) {
            $records[0]['notes'] = $session['notes'];
            if ($hasFirstTimers) {
                $records[0]['first_timers_count'] = $session['first_timers_count'] ?? 0;
            }
        }

        echo json_encode([
            "success" => true,
            "records" => $records
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Error loading attendance: " . $e->getMessage()
        ]);
    }
}
?>