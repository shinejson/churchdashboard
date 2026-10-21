<?php
/**
 * Test script to check if attendance data is being returned correctly
 * Access this file directly in your browser: http://yoursite/backend/attendance/test_read.php
 */

session_start();

// Set a test session if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
}

header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo json_encode([
        "status" => "Testing attendance read functionality",
        "tests" => [
            "database_connection" => "OK",
            "session_user_id" => $_SESSION['user_id'] ?? 'Not set',
            "session_role" => $_SESSION['role'] ?? 'Not set'
        ]
    ]);
    
    // Test 1: Check if attendance table exists
    $checkTable = $db->query("SHOW TABLES LIKE 'attendance'");
    $tableExists = $checkTable->rowCount() > 0;
    
    if (!$tableExists) {
        echo json_encode([
            "error" => "Attendance table does not exist",
            "suggestion" => "Please create the attendance table first"
        ]);
        exit;
    }
    
    // Test 2: Check columns
    $columns = $db->query("SHOW COLUMNS FROM attendance")->fetchAll(PDO::FETCH_COLUMN);
    
    // Test 3: Get sample data
    $stmt = $db->query("SELECT * FROM attendance LIMIT 5");
    $sampleData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Test 4: Check if first_timers_count exists
    $hasFirstTimers = in_array('first_timers_count', $columns);
    
    echo json_encode([
        "status" => "success",
        "table_exists" => $tableExists,
        "columns" => $columns,
        "has_first_timers_column" => $hasFirstTimers,
        "sample_records_count" => count($sampleData),
        "sample_data" => $sampleData,
        "note" => $hasFirstTimers ? "first_timers_count column exists" : "first_timers_count column MISSING - run migration"
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        "error" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>
