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
    
    // Get user login activity (from last_seen in users table)
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            u.email,
            u.first_name,
            u.last_name,
            u.role,
            f.name as fellowship_name,
            u.last_seen,
            u.last_login,
            u.created_at,
            DATEDIFF(CURDATE(), DATE(u.last_seen)) as days_since_last_seen,
            CASE 
                WHEN DATEDIFF(CURDATE(), DATE(u.last_seen)) <= 1 THEN 'Active Today'
                WHEN DATEDIFF(CURDATE(), DATE(u.last_seen)) <= 7 THEN 'Active This Week'
                WHEN DATEDIFF(CURDATE(), DATE(u.last_seen)) <= 30 THEN 'Active This Month'
                ELSE 'Inactive'
            END as activity_status
        FROM users u
        LEFT JOIN fellowships f ON u.fellowship_id = f.id
        WHERE u.status = 'active'
        ORDER BY u.last_seen DESC
    ");
    $stmt->execute();
    $userActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user count by role
    $stmt = $pdo->prepare("
        SELECT 
            role,
            COUNT(*) as user_count,
            COUNT(CASE WHEN DATEDIFF(CURDATE(), DATE(last_seen)) <= 7 THEN 1 END) as active_last_7_days,
            COUNT(CASE WHEN DATEDIFF(CURDATE(), DATE(last_seen)) <= 30 THEN 1 END) as active_last_30_days,
            ROUND(AVG(DATEDIFF(CURDATE(), DATE(last_seen))), 1) as avg_days_since_last_seen
        FROM users 
        WHERE status = 'active'
        GROUP BY role
        ORDER BY user_count DESC
    ");
    $stmt->execute();
    $usersByRole = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user creation trends
    $periodGroup = getPeriodFilter('created_at');
    $stmt = $pdo->prepare("
        SELECT 
            $periodGroup as period,
            COUNT(*) as users_created,
            GROUP_CONCAT(DISTINCT role ORDER BY role SEPARATOR ', ') as roles_created
        FROM users 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY $periodGroup
        ORDER BY period
    ");
    $stmt->execute([$dateRange['start'], $dateRange['end']]);
    $userCreationTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user actions from attendance records (created_by)
    $stmt = $pdo->prepare("
        SELECT 
            u.username,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            COUNT(a.id) as attendance_records_created,
            MIN(a.created_at) as first_record_date,
            MAX(a.created_at) as last_record_date,
            COUNT(DISTINCT DATE(a.service_date)) as unique_days_with_activity
        FROM attendance a
        JOIN users u ON a.created_by = u.id
        WHERE a.created_at BETWEEN ? AND ?
        GROUP BY a.created_by
        ORDER BY attendance_records_created DESC
    ");
    $stmt->execute([$dateRange['start'], $dateRange['end']]);
    $attendanceActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user actions from financial records (recorded_by)
    $stmt = $pdo->prepare("
        SELECT 
            u.username,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            COUNT(ft.id) as financial_records_created,
            COALESCE(SUM(ft.amount), 0) as total_amount_recorded,
            MIN(ft.recorded_at) as first_record_date,
            MAX(ft.recorded_at) as last_record_date,
            COUNT(DISTINCT DATE(ft.transaction_date)) as unique_days_with_activity
        FROM financial_transactions ft
        JOIN users u ON ft.recorded_by = u.id
        WHERE ft.recorded_at BETWEEN ? AND ?
        GROUP BY ft.recorded_by
        ORDER BY financial_records_created DESC
    ");
    $stmt->execute([$dateRange['start'], $dateRange['end']]);
    $financialActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get most active users overall
    $stmt = $pdo->prepare("
        SELECT 
            u.username,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            u.role,
            f.name as fellowship_name,
            (SELECT COUNT(*) FROM attendance WHERE created_by = u.id AND created_at BETWEEN ? AND ?) as attendance_actions,
            (SELECT COUNT(*) FROM financial_transactions WHERE recorded_by = u.id AND recorded_at BETWEEN ? AND ?) as financial_actions,
            (SELECT COUNT(*) FROM members WHERE created_by = u.id AND created_at BETWEEN ? AND ?) as member_actions,
            u.last_seen
        FROM users u
        LEFT JOIN fellowships f ON u.fellowship_id = f.id
        WHERE u.status = 'active'
        HAVING (attendance_actions + financial_actions + member_actions) > 0
        ORDER BY (attendance_actions + financial_actions + member_actions) DESC
        LIMIT 20
    ");
    $stmt->execute([
        $dateRange['start'], $dateRange['end'],
        $dateRange['start'], $dateRange['end'],
        $dateRange['start'], $dateRange['end']
    ]);
    $mostActiveUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'user_activity' => $userActivity,
        'users_by_role' => $usersByRole,
        'user_creation_trends' => $userCreationTrends,
        'attendance_activities' => $attendanceActivities,
        'financial_activities' => $financialActivities,
        'most_active_users' => $mostActiveUsers,
        'summary' => [
            'total_users' => count($userActivity),
            'active_today' => count(array_filter($userActivity, function($user) {
                return $user['days_since_last_seen'] <= 1;
            })),
            'active_this_week' => count(array_filter($userActivity, function($user) {
                return $user['days_since_last_seen'] <= 7;
            })),
            'inactive_users' => count(array_filter($userActivity, function($user) {
                return $user['days_since_last_seen'] > 30;
            })),
            'total_actions' => array_sum(array_column($mostActiveUsers, 'attendance_actions')) +
                              array_sum(array_column($mostActiveUsers, 'financial_actions')) +
                              array_sum(array_column($mostActiveUsers, 'member_actions'))
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