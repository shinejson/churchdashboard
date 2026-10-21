<?php
header('Content-Type: application/json');
require_once dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/backend/config/database.php';
require_once dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/backend/config/security.php';

secure_session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!function_exists('getDateRange')) {
    function getDateRange() {
        $start = $_GET['start_date'] ?? date('Y-m-01');
        $end = $_GET['end_date'] ?? date('Y-m-d');
        return ['start' => $start, 'end' => $end];
    }
}

if (!function_exists('getPeriodFilter')) {
    function getPeriodFilter($column = 'service_date') {
        $period = $_GET['period'] ?? 'monthly';
        switch($period) {
            case 'daily': return "DATE($column)";
            case 'weekly': return "YEARWEEK($column)";
            case 'monthly': return "DATE_FORMAT($column, '%Y-%m')";
            case 'quarterly': return "CONCAT(YEAR($column), '-Q', QUARTER($column))";
            case 'yearly': return "YEAR($column)";
            default: return "DATE_FORMAT($column, '%Y-%m')";
        }
    }
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $dateRange = getDateRange();
    
    // Get report logs
    $stmt = $pdo->prepare("
        SELECT 
            rl.*,
            CONCAT(u.first_name, ' ', u.last_name) as sent_by_name,
            u.username as sent_by_username,
            u.role as sent_by_role
        FROM report_logs rl
        LEFT JOIN users u ON rl.sent_by = u.id
        WHERE rl.created_at BETWEEN ? AND ?
        ORDER BY rl.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$dateRange['start'] . ' 00:00:00', $dateRange['end'] . ' 23:59:59']);
    $reportLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get import logs
    $stmt = $pdo->prepare("
        SELECT 
            il.*,
            CONCAT(u.first_name, ' ', u.last_name) as imported_by_name,
            u.username as imported_by_username
        FROM import_log il
        LEFT JOIN users u ON il.imported_by = u.id
        WHERE il.imported_at BETWEEN ? AND ?
        ORDER BY il.imported_at DESC
        LIMIT 100
    ");
    $stmt->execute([$dateRange['start'] . ' 00:00:00', $dateRange['end'] . ' 23:59:59']);
    $importLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get system statistics
    $tables = ['members', 'attendance', 'financial_transactions', 'tithe_transactions', 'users', 'fellowships'];
    $tableStats = [];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM {$table}");
        $stmt->execute();
        $total = $stmt->fetch()['total'];
        
        // Get recent activity for each table
        $dateColumn = $table === 'members' ? 'created_at' : 
                     ($table === 'users' ? 'created_at' : 
                     ($table === 'fellowships' ? 'created_at' : 
                     ($table === 'attendance' ? 'created_at' : 
                     ($table === 'financial_transactions' ? 'recorded_at' : 'recorded_at'))));
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as recent_count 
            FROM {$table} 
            WHERE DATE({$dateColumn}) BETWEEN ? AND ?
        ");
        $stmt->execute([$dateRange['start'], $dateRange['end']]);
        $recent = $stmt->fetch()['recent_count'];
        
        $tableStats[$table] = [
            'total' => $total,
            'recent' => $recent,
            'recent_percentage' => $total > 0 ? round(($recent / $total) * 100, 2) : 0
        ];
    }
    
    // Get deleted records count
    $stmt = $pdo->prepare("
        SELECT 
            'attendance' as table_name,
            COUNT(*) as deleted_count
        FROM attendance 
        WHERE is_deleted = 1
        AND updated_at BETWEEN ? AND ?
        UNION ALL
        SELECT 
            'members' as table_name,
            COUNT(*) as deleted_count
        FROM members 
        WHERE is_deleted = 1
        AND updated_at BETWEEN ? AND ?
    ");
    $stmt->execute([
        $dateRange['start'] . ' 00:00:00', $dateRange['end'] . ' 23:59:59',
        $dateRange['start'] . ' 00:00:00', $dateRange['end'] . ' 23:59:59'
    ]);
    $deletedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get error logs (if any - you can implement this if you have an error_logs table)
    // For now, we'll check for failed imports
    $failedImports = array_filter($importLogs, function($log) {
        return $log['failed_count'] > 0;
    });
    
    // Get report statistics
    $stmt = $pdo->prepare("
        SELECT 
            report_type,
            send_method,
            COUNT(*) as count,
            SUM(recipients_count) as total_recipients,
            MIN(created_at) as first_sent,
            MAX(created_at) as last_sent
        FROM report_logs 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY report_type, send_method
        ORDER BY count DESC
    ");
    $stmt->execute([$dateRange['start'] . ' 00:00:00', $dateRange['end'] . ' 23:59:59']);
    $reportStatistics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'report_logs' => $reportLogs,
        'import_logs' => $importLogs,
        'table_statistics' => $tableStats,
        'deleted_records' => $deletedRecords,
        'failed_imports' => array_values($failedImports),
        'report_statistics' => $reportStatistics,
        'summary' => [
            'total_reports_generated' => count($reportLogs),
            'total_imports' => count($importLogs),
            'failed_imports_count' => count($failedImports),
            'most_common_report_type' => count($reportStatistics) > 0 ? $reportStatistics[0]['report_type'] : 'N/A',
            'total_recipients' => array_sum(array_column($reportStatistics, 'total_recipients'))
        ]
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}