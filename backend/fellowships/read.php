<?php
require_once '../config/security.php';
secure_session_start();
allow_cors();
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(array("error" => "Database connection failed"));
    exit();
}

try {
    // Get all fellowships with leader and member information
    $query = "SELECT 
                f.id,
                f.name,
                f.location,
                f.leader_id,
                f.assistant_leader_id,
                f.meeting_day,
                f.meeting_time,
                f.status,
                f.description,
                f.members_count,
                f.created_at,
                f.updated_at,
                CONCAT(COALESCE(ml.first_name, ''), ' ', COALESCE(ml.last_name, '')) AS leader_name,
                CONCAT(COALESCE(ma.first_name, ''), ' ', COALESCE(ma.last_name, '')) AS assistant_name,
                (SELECT COUNT(*) FROM members WHERE fellowship_id = f.id) AS actual_member_count
            FROM fellowships f
            LEFT JOIN members ml ON f.leader_id = ml.id
            LEFT JOIN members ma ON f.assistant_leader_id = ma.id
            ORDER BY f.status DESC, f.name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $fellowships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if fellowship_schedules table exists
    $tableExists = false;
    try {
        $checkTable = $db->query("SHOW TABLES LIKE 'fellowship_schedules'");
        $tableExists = $checkTable->rowCount() > 0;
    } catch (Exception $e) {
        // Table doesn't exist, continue with basic data
    }
    
    if ($tableExists) {
        // OPTIMIZED: Fetch all schedules in one query instead of N queries
        $fellowship_ids = array_column($fellowships, 'id');
        
        if (!empty($fellowship_ids)) {
            $placeholders = implode(',', array_fill(0, count($fellowship_ids), '?'));
            $scheduleQuery = "SELECT fellowship_id, day, time, is_primary 
                            FROM fellowship_schedules 
                            WHERE fellowship_id IN ($placeholders)
                            ORDER BY fellowship_id, is_primary DESC, 
                            FIELD(day, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')";
            
            $scheduleStmt = $db->prepare($scheduleQuery);
            $scheduleStmt->execute($fellowship_ids);
            $allSchedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group schedules by fellowship_id
            $schedulesByFellowship = [];
            foreach ($allSchedules as $schedule) {
                $fid = $schedule['fellowship_id'];
                if (!isset($schedulesByFellowship[$fid])) {
                    $schedulesByFellowship[$fid] = [];
                }
                $schedulesByFellowship[$fid][] = [
                    'day' => $schedule['day'],
                    'time' => $schedule['time'],
                    'isPrimary' => (bool)$schedule['is_primary']
                ];
            }
            
            // Merge schedules into fellowships
            foreach ($fellowships as &$fellowship) {
                $fid = $fellowship['id'];
                if (isset($schedulesByFellowship[$fid]) && !empty($schedulesByFellowship[$fid])) {
                    $fellowship['meeting_schedules'] = $schedulesByFellowship[$fid];
                } else {
                    // Fallback to single schedule from main table
                    $fellowship['meeting_schedules'] = [[
                        'day' => $fellowship['meeting_day'],
                        'time' => $fellowship['meeting_time'],
                        'isPrimary' => true
                    ]];
                }
            }
        }
    } else {
        // No separate table, use main table data as single schedule
        foreach ($fellowships as &$fellowship) {
            $fellowship['meeting_schedules'] = [[
                'day' => $fellowship['meeting_day'],
                'time' => $fellowship['meeting_time'],
                'isPrimary' => true
            ]];
        }
    }
    
    // Clean up the response (remove unnecessary fields if needed)
    // Optionally trim leader/assistant names
    foreach ($fellowships as &$fellowship) {
        $fellowship['leader_name'] = trim($fellowship['leader_name']);
        $fellowship['assistant_name'] = trim($fellowship['assistant_name']);
        
        // Ensure numeric fields are proper types
        $fellowship['id'] = (int)$fellowship['id'];
        $fellowship['leader_id'] = $fellowship['leader_id'] ? (int)$fellowship['leader_id'] : null;
        $fellowship['assistant_leader_id'] = $fellowship['assistant_leader_id'] ? (int)$fellowship['assistant_leader_id'] : null;
        $fellowship['members_count'] = (int)$fellowship['members_count'];
        $fellowship['actual_member_count'] = (int)$fellowship['actual_member_count'];
    }
    
    http_response_code(200);
    echo json_encode($fellowships);
    
} catch (Exception $e) {
    http_response_code(500);
    security_log('error', 'Failed to retrieve fellowships: ' . $e->getMessage());
    echo json_encode(array("error" => "Failed to retrieve fellowships"));
}
?>