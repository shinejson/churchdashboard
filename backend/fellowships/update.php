<?php
require_once '../config/security.php';
secure_session_start();
allow_cors();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT");

include_once '../config/database.php';

// Only pastors can update
if (empty($_SESSION['role']) || $_SESSION['role'] != 'pastor') {
    http_response_code(403);
    echo json_encode(array("message" => "Only pastor can update fellowships"));
    exit();
}

// Require CSRF
require_csrf_token();

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(array("error" => "Database connection failed"));
    exit();
}

$data = json_decode(file_get_contents("php://input"));

try {
    $id = intval($data->id ?? 0);
    $name = sanitize_string($data->name ?? null);
    $location = sanitize_string($data->location ?? null);
    $leader_id = intval($data->leader_id ?? 0);
    
    // Handle meeting schedules
    $meeting_schedules = $data->meeting_schedules ?? [];
    if (empty($meeting_schedules) || !is_array($meeting_schedules)) {
        http_response_code(400);
        echo json_encode(array("message" => "At least one meeting schedule is required"));
        exit();
    }
    
    // Validate no duplicate days
    $days_used = [];
    foreach ($meeting_schedules as $schedule) {
        $day = sanitize_string($schedule->day ?? null);
        if (in_array($day, $days_used)) {
            http_response_code(400);
            echo json_encode(array("message" => "Duplicate meeting days are not allowed"));
            exit();
        }
        $days_used[] = $day;
    }
    
    // Get primary schedule or first one
    $primary_schedule = null;
    foreach ($meeting_schedules as $schedule) {
        if (isset($schedule->isPrimary) && $schedule->isPrimary) {
            $primary_schedule = $schedule;
            break;
        }
    }
    if (!$primary_schedule) {
        $primary_schedule = $meeting_schedules[0];
    }
    
    $meeting_day = sanitize_string($primary_schedule->day ?? null);
    $meeting_time = sanitize_string($primary_schedule->time ?? null);
    
    $assistant_leader_id = !empty($data->assistant_leader_id) ? intval($data->assistant_leader_id) : null;
    $status = sanitize_string($data->status ?? null);
    $description = sanitize_string($data->description ?? null);

    // Validate required fields
    if ($id <= 0 || empty($name) || empty($location) || empty($meeting_day) || empty($meeting_time) || $leader_id <= 0) {
        http_response_code(400);
        echo json_encode(array("message" => "Missing required fields"));
        exit();
    }

    // Validate meeting_day
    $valid_days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    if (!in_array($meeting_day, $valid_days)) {
        http_response_code(400);
        echo json_encode(array("message" => "Invalid meeting day"));
        exit();
    }
    
    // Validate all schedule days
    foreach ($meeting_schedules as $schedule) {
        $day = sanitize_string($schedule->day ?? null);
        if (!in_array($day, $valid_days)) {
            http_response_code(400);
            echo json_encode(array("message" => "Invalid meeting day in schedules"));
            exit();
        }
    }

    // Validate status
    $valid_statuses = ['active', 'inactive', 'pending'];
    if (!in_array($status, $valid_statuses)) {
        http_response_code(400);
        echo json_encode(array("message" => "Invalid status"));
        exit();
    }

    // Validate leader and assistant are different
    if ($leader_id && $assistant_leader_id && $leader_id === $assistant_leader_id) {
        http_response_code(400);
        echo json_encode(array("message" => "Leader and assistant cannot be the same person"));
        exit();
    }

    // Check if fellowship exists
    $check = "SELECT id FROM fellowships WHERE id = :id LIMIT 1";
    $stmt = $db->prepare($check);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(array("message" => "Fellowship not found"));
        exit();
    }

    // Check if leader exists
    $check_leader = "SELECT id FROM members WHERE id = :leader_id LIMIT 1";
    $stmt = $db->prepare($check_leader);
    $stmt->bindParam(":leader_id", $leader_id, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        http_response_code(400);
        echo json_encode(array("message" => "Selected leader does not exist"));
        exit();
    }

    // Check if assistant exists (if provided)
    if ($assistant_leader_id) {
        $check_assistant = "SELECT id FROM members WHERE id = :assistant_id LIMIT 1";
        $stmt = $db->prepare($check_assistant);
        $stmt->bindParam(":assistant_id", $assistant_leader_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            http_response_code(400);
            echo json_encode(array("message" => "Selected assistant leader does not exist"));
            exit();
        }
    }

    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Update fellowship main table
        $query = "UPDATE fellowships SET 
                  name = :name,
                  location = :location,
                  leader_id = :leader_id,
                  assistant_leader_id = :assistant_leader_id,
                  meeting_day = :meeting_day,
                  meeting_time = :meeting_time,
                  status = :status,
                  description = :description
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":location", $location);
        $stmt->bindParam(":leader_id", $leader_id, PDO::PARAM_INT);
        $stmt->bindParam(":assistant_leader_id", $assistant_leader_id, PDO::PARAM_INT);
        $stmt->bindParam(":meeting_day", $meeting_day);
        $stmt->bindParam(":meeting_time", $meeting_time);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Check if fellowship_schedules table exists
        $tableExists = false;
        try {
            $checkTable = $db->query("SHOW TABLES LIKE 'fellowship_schedules'");
            $tableExists = $checkTable->rowCount() > 0;
        } catch (Exception $e) {
            // Table doesn't exist, skip schedule management
        }
        
        // If separate schedules table exists, update all schedules
        if ($tableExists) {
            // Delete existing schedules for this fellowship
            $delete_query = "DELETE FROM fellowship_schedules WHERE fellowship_id = :fellowship_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(":fellowship_id", $id, PDO::PARAM_INT);
            $delete_stmt->execute();
            
            // Insert new schedules
            $schedule_query = "INSERT INTO fellowship_schedules (fellowship_id, day, time, is_primary) 
                              VALUES (:fellowship_id, :day, :time, :is_primary)";
            $schedule_stmt = $db->prepare($schedule_query);
            
            foreach ($meeting_schedules as $schedule) {
                $schedule_day = sanitize_string($schedule->day);
                $schedule_time = sanitize_string($schedule->time);
                $is_primary = (isset($schedule->isPrimary) && $schedule->isPrimary) ? 1 : 0;
                
                $schedule_stmt->bindParam(":fellowship_id", $id, PDO::PARAM_INT);
                $schedule_stmt->bindParam(":day", $schedule_day);
                $schedule_stmt->bindParam(":time", $schedule_time);
                $schedule_stmt->bindParam(":is_primary", $is_primary, PDO::PARAM_INT);
                $schedule_stmt->execute();
            }
        }
        
        $db->commit();
        
        http_response_code(200);
        echo json_encode(array(
            "message" => "Fellowship updated successfully",
            "schedules_updated" => count($meeting_schedules)
        ));
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    security_log('error', 'Fellowship update failed: ' . $e->getMessage());
    echo json_encode(array("message" => "Server error"));
}
?>