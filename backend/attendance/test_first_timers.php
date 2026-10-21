<?php
/**
 * Test script to verify first_timers_count column
 * Access: http://yoursite/backend/attendance/test_first_timers.php
 */

require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Test 1: Check if column exists
    $columns = $db->query("SHOW COLUMNS FROM attendance LIKE 'first_timers_count'")->fetchAll();
    $hasColumn = count($columns) > 0;
    
    $result = [
        'test' => 'First Timers Column Test',
        'column_exists' => $hasColumn
    ];
    
    if (!$hasColumn) {
        $result['error'] = 'Column first_timers_count does not exist!';
        $result['solution'] = 'Run: php backend/migrations/run_migration.php';
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Test 2: Get column details
    $columnInfo = $db->query("SHOW COLUMNS FROM attendance LIKE 'first_timers_count'")->fetch(PDO::FETCH_ASSOC);
    $result['column_info'] = $columnInfo;
    
    // Test 3: Check recent records
    $stmt = $db->query("
        SELECT 
            service_date, 
            service_id, 
            fellowship_id,
            first_timers_count,
            COUNT(*) as record_count
        FROM attendance 
        WHERE service_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAYS)
        GROUP BY service_date, service_id, fellowship_id
        ORDER BY service_date DESC
        LIMIT 5
    ");
    $recentRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result['recent_records'] = $recentRecords;
    $result['total_with_first_timers'] = count(array_filter($recentRecords, function($r) {
        return $r['first_timers_count'] > 0;
    }));
    
    // Test 4: Try to insert a test record
    $testDate = date('Y-m-d');
    $testFellowship = 1;
    $testService = 1;
    $testMember = 1;
    
    // Check if test record exists
    $checkStmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM attendance 
        WHERE service_date = ? 
        AND fellowship_id = ? 
        AND service_id = ?
        AND member_id = ?
    ");
    $checkStmt->execute([$testDate, $testFellowship, $testService, $testMember]);
    $exists = $checkStmt->fetch()['count'] > 0;
    
    if (!$exists) {
        // Try inserting with first_timers_count
        $insertStmt = $db->prepare("
            INSERT INTO attendance 
            (fellowship_id, member_id, service_date, service_id, status, first_timers_count) 
            VALUES (?, ?, ?, ?, 'present', 5)
        ");
        
        try {
            $insertStmt->execute([$testFellowship, $testMember, $testDate, $testService]);
            $result['test_insert'] = 'SUCCESS - Test record inserted with first_timers_count=5';
            
            // Verify it was saved
            $verifyStmt = $db->prepare("
                SELECT first_timers_count 
                FROM attendance 
                WHERE service_date = ? 
                AND fellowship_id = ? 
                AND service_id = ?
                AND member_id = ?
            ");
            $verifyStmt->execute([$testDate, $testFellowship, $testService, $testMember]);
            $saved = $verifyStmt->fetch();
            
            $result['test_verify'] = [
                'saved_value' => $saved['first_timers_count'],
                'expected' => 5,
                'match' => $saved['first_timers_count'] == 5
            ];
            
            // Clean up test record
            $db->prepare("DELETE FROM attendance WHERE service_date = ? AND fellowship_id = ? AND service_id = ? AND member_id = ?")
               ->execute([$testDate, $testFellowship, $testService, $testMember]);
            $result['test_cleanup'] = 'Test record deleted';
            
        } catch (Exception $e) {
            $result['test_insert'] = 'FAILED - ' . $e->getMessage();
        }
    } else {
        $result['test_insert'] = 'SKIPPED - Test record already exists';
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>
