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

// Fellowship data isolation: non-admins are always scoped to their own fellowship
$scopeId = current_fellowship_scope();

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

if (!function_exists('getFilterConditions')) {
    function getFilterConditions($options = []) {
        $fellowshipColumn = $options['fellowship_column'] ?? 'fellowship_id';
        $serviceColumn = $options['service_column'] ?? 'service_id';
        $tableAlias = $options['table_alias'] ?? '';
        
        $conditions = [];
        $params = [];
        $fellowshipId = null;
        $serviceId = null;
        
        // Handle fellowship filter
        if (isset($_GET['fellowship_id']) && $_GET['fellowship_id'] !== '') {
            if (!is_numeric($_GET['fellowship_id'])) {
                throw new InvalidArgumentException('Invalid filter parameter: fellowship_id must be numeric');
            }
            $fellowshipId = (int)$_GET['fellowship_id'];
            $conditions[] = $tableAlias . $fellowshipColumn . ' = ?';
            $params[] = $fellowshipId;
        }
        
        // Handle service filter
        if (isset($_GET['service_id']) && $_GET['service_id'] !== '') {
            // Service IDs can be strings (varchar) like 'friday_service' or numeric
            // Just validate it's not empty and doesn't contain dangerous characters
            $serviceId = $_GET['service_id'];
            
            // Basic validation: alphanumeric, underscore, and dash only
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $serviceId)) {
                throw new InvalidArgumentException('Invalid filter parameter: service_id contains invalid characters');
            }
            
            $conditions[] = $tableAlias . $serviceColumn . ' = ?';
            $params[] = $serviceId;
        }
        
        return [
            'conditions' => $conditions,
            'params' => $params,
            'fellowship_id' => $fellowshipId,
            'service_id' => $serviceId
        ];
    }
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $dateRange = getDateRange();
    $filters = getFilterConditions();
    
    // Get attendance trends by date
    $whereConditions = ['service_date BETWEEN ? AND ?'];
    $params = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions[] = 'fellowship_id = ?';
        $params[] = $scopeId;
    } elseif (!empty($filters['conditions'])) {
        $whereConditions = array_merge($whereConditions, $filters['conditions']);
        $params = array_merge($params, $filters['params']);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE(service_date) as date,
            COUNT(DISTINCT fellowship_id) as fellowship_count,
            COUNT(DISTINCT service_id) as service_count,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
            COUNT(CASE WHEN status = 'excused' THEN 1 END) as excused_count,
            COUNT(DISTINCT member_id) as unique_members,
            ROUND((COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / COUNT(*)), 2) as attendance_rate
        FROM attendance 
        WHERE $whereClause
        GROUP BY DATE(service_date)
        ORDER BY date
    ");
    $stmt->execute($params);
    $dailyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get weekly trends
    $whereConditions2 = ['service_date BETWEEN ? AND ?'];
    $params2 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions2 = array_merge($whereConditions2, $filters['conditions']);
        $params2 = array_merge($params2, $filters['params']);
    }
    
    $whereClause2 = implode(' AND ', $whereConditions2);
    
    $stmt = $pdo->prepare("
        SELECT 
            YEARWEEK(service_date, 1) as week_number,
            MIN(DATE(service_date)) as week_start,
            MAX(DATE(service_date)) as week_end,
            COUNT(DISTINCT DATE(service_date)) as days_with_services,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
            COUNT(DISTINCT member_id) as unique_members,
            ROUND(AVG(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100, 2) as avg_attendance_rate
        FROM attendance 
        WHERE $whereClause2
        GROUP BY YEARWEEK(service_date, 1)
        ORDER BY week_number
    ");
    $stmt->execute($params2);
    $weeklyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get monthly trends
    $whereConditions3 = ['service_date BETWEEN ? AND ?'];
    $params3 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions3 = array_merge($whereConditions3, $filters['conditions']);
        $params3 = array_merge($params3, $filters['params']);
    }
    
    $whereClause3 = implode(' AND ', $whereConditions3);
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(service_date, '%Y-%m') as month,
            DATE_FORMAT(service_date, '%M %Y') as month_name,
            COUNT(DISTINCT DATE(service_date)) as days_with_services,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
            COUNT(DISTINCT member_id) as unique_members,
            COUNT(DISTINCT fellowship_id) as active_fellowships,
            ROUND(AVG(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100, 2) as avg_attendance_rate
        FROM attendance 
        WHERE $whereClause3
        GROUP BY DATE_FORMAT(service_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute($params3);
    $monthlyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get service type trends - only apply fellowship filter (service is the grouping dimension)
    $whereConditions4 = ['a.service_date BETWEEN ? AND ?'];
    $params4 = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions4[] = 'a.fellowship_id = ?';
        $params4[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions4[] = 'a.fellowship_id = ?';
        $params4[] = $filters['fellowship_id'];
    }
    
    $whereClause4 = implode(' AND ', $whereConditions4);
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(s.name, a.service_id) as service_name,
            DATE_FORMAT(service_date, '%Y-%m') as month,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
            COUNT(*) as total_count,
            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*)), 2) as attendance_rate,
            COUNT(DISTINCT a.fellowship_id) as fellowship_count
        FROM attendance a
        LEFT JOIN services s ON a.service_id = s.id
        WHERE $whereClause4
        GROUP BY a.service_id, DATE_FORMAT(service_date, '%Y-%m')
        ORDER BY service_name, month
    ");
    $stmt->execute($params4);
    $serviceTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get fellowship trends - only apply service filter (fellowship is the grouping dimension)
    $whereConditions5 = ['a.service_date BETWEEN ? AND ?', 'f.id IS NOT NULL'];
    $params5 = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions5[] = 'a.fellowship_id = ?';
        $params5[] = $scopeId;
    }
    
    if ($filters['service_id'] !== null) {
        $whereConditions5[] = 'a.service_id = ?';
        $params5[] = $filters['service_id'];
    }
    
    $whereClause5 = implode(' AND ', $whereConditions5);
    
    $stmt = $pdo->prepare("
        SELECT 
            f.name as fellowship_name,
            DATE_FORMAT(a.service_date, '%Y-%m') as month,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
            COUNT(*) as total_count,
            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*)), 2) as attendance_rate,
            COUNT(DISTINCT a.member_id) as unique_members
        FROM attendance a
        LEFT JOIN fellowships f ON a.fellowship_id = f.id
        WHERE $whereClause5
        GROUP BY a.fellowship_id, DATE_FORMAT(service_date, '%Y-%m')
        ORDER BY fellowship_name, month
    ");
    $stmt->execute($params5);
    $fellowshipTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate growth metrics
    $currentMonth = date('Y-m');
    $previousMonth = date('Y-m', strtotime('-1 month'));
    
    $whereConditions6 = ['DATE_FORMAT(service_date, \'%Y-%m\') = ?'];
    $params6 = [$currentMonth];
    
    if (!empty($filters['conditions'])) {
        $whereConditions6 = array_merge($whereConditions6, $filters['conditions']);
        $params6 = array_merge($params6, $filters['params']);
    }
    
    $whereClause6 = implode(' AND ', $whereConditions6);
    
    $whereConditions7 = ['DATE_FORMAT(service_date, \'%Y-%m\') = ?'];
    $params7 = [$previousMonth];
    
    if (!empty($filters['conditions'])) {
        $whereConditions7 = array_merge($whereConditions7, $filters['conditions']);
        $params7 = array_merge($params7, $filters['params']);
    }
    
    $whereClause7 = implode(' AND ', $whereConditions7);
    
    $stmt = $pdo->prepare("
        SELECT 
            'current' as period,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
            COUNT(DISTINCT member_id) as unique_members,
            COUNT(DISTINCT fellowship_id) as fellowship_count
        FROM attendance 
        WHERE $whereClause6
        UNION ALL
        SELECT 
            'previous' as period,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
            COUNT(DISTINCT member_id) as unique_members,
            COUNT(DISTINCT fellowship_id) as fellowship_count
        FROM attendance 
        WHERE $whereClause7
    ");
    $stmt->execute(array_merge($params6, $params7));
    $growthData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $growthMetrics = [];
    if (count($growthData) == 2) {
        $current = $growthData[0];
        $previous = $growthData[1];
        
        $growthMetrics['attendance_growth'] = $previous['present_count'] > 0 
            ? round((($current['present_count'] - $previous['present_count']) / $previous['present_count']) * 100, 2)
            : 0;
        
        $growthMetrics['member_growth'] = $previous['unique_members'] > 0
            ? round((($current['unique_members'] - $previous['unique_members']) / $previous['unique_members']) * 100, 2)
            : 0;
        
        $growthMetrics['fellowship_growth'] = $previous['fellowship_count'] > 0
            ? round((($current['fellowship_count'] - $previous['fellowship_count']) / $previous['fellowship_count']) * 100, 2)
            : 0;
    }
    
    echo json_encode([
        'success' => true,
        'daily_trends' => $dailyTrends,
        'weekly_trends' => $weeklyTrends,
        'monthly_trends' => $monthlyTrends,
        'service_trends' => $serviceTrends,
        'fellowship_trends' => $fellowshipTrends,
        'growth_metrics' => $growthMetrics,
        'summary' => [
            'total_days' => count($dailyTrends),
            'total_attendance' => array_sum(array_column($dailyTrends, 'present_count')),
            'avg_daily_attendance' => count($dailyTrends) > 0 ? round(array_sum(array_column($dailyTrends, 'present_count')) / count($dailyTrends), 2) : 0,
            'avg_attendance_rate' => count($dailyTrends) > 0 ? round(array_sum(array_column($dailyTrends, 'attendance_rate')) / count($dailyTrends), 2) : 0
        ]
    ]);
    
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => 'INVALID_FILTER'
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