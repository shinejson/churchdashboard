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
    $periodGroup = getPeriodFilter();
    $filters = getFilterConditions();
    
    // Get attendance summary
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
            $periodGroup as period,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
            COUNT(CASE WHEN status = 'excused' THEN 1 END) as excused_count,
            COUNT(*) as total_count,
            ROUND((COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / COUNT(*)), 2) as attendance_rate
        FROM attendance 
        WHERE $whereClause
        GROUP BY $periodGroup
        ORDER BY period
    ");
    $stmt->execute($params);
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attendance by fellowship
    $whereConditions2 = ['a.service_date BETWEEN ? AND ?'];
    $params2 = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions2[] = 'a.fellowship_id = ?';
        $params2[] = $scopeId;
    }
    
    // Only apply service filter for fellowship grouping (fellowship is the grouping dimension)
    if ($filters['service_id'] !== null) {
        $whereConditions2[] = 'a.service_id = ?';
        $params2[] = $filters['service_id'];
    }
    
    $whereClause2 = implode(' AND ', $whereConditions2);
    
    $stmt = $pdo->prepare("
        SELECT 
            f.name as fellowship,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
            COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused_count,
            COUNT(*) as total_count,
            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*)), 2) as attendance_rate
        FROM attendance a
        LEFT JOIN fellowships f ON a.fellowship_id = f.id
        WHERE $whereClause2
        GROUP BY a.fellowship_id
        ORDER BY present_count DESC
    ");
    $stmt->execute($params2);
    $byFellowship = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attendance by service type
    $whereConditions3 = ['a.service_date BETWEEN ? AND ?'];
    $params3 = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions3[] = 'a.fellowship_id = ?';
        $params3[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions3[] = 'a.fellowship_id = ?';
        $params3[] = $filters['fellowship_id'];
    }
    
    $whereClause3 = implode(' AND ', $whereConditions3);
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(s.name, a.service_id) as service_name,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
            COUNT(*) as total_count,
            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*)), 2) as attendance_rate
        FROM attendance a
        LEFT JOIN services s ON a.service_id = s.id
        WHERE $whereClause3
        GROUP BY a.service_id
        ORDER BY present_count DESC
    ");
    $stmt->execute($params3);
    $byService = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get top attendees
    $filtersWithAlias = getFilterConditions(['table_alias' => 'a.']);
    $whereConditions4 = ['a.service_date BETWEEN ? AND ?'];
    $params4 = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions4[] = 'a.fellowship_id = ?';
        $params4[] = $scopeId;
    } elseif (!empty($filtersWithAlias['conditions'])) {
        $whereConditions4 = array_merge($whereConditions4, $filtersWithAlias['conditions']);
        $params4 = array_merge($params4, $filtersWithAlias['params']);
    }
    
    $whereClause4 = implode(' AND ', $whereConditions4);
    
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            f.name as fellowship_name,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
            COUNT(*) as total_services,
            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*)), 2) as attendance_rate
        FROM attendance a
        JOIN members m ON a.member_id = m.id
        LEFT JOIN fellowships f ON m.fellowship_id = f.id
        WHERE $whereClause4
        GROUP BY a.member_id
        HAVING total_services > 0
        ORDER BY attendance_rate DESC, present_count DESC
        LIMIT 20
    ");
    $stmt->execute($params4);
    $topAttendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get detailed attendance records
    $whereConditions5 = ['a.service_date BETWEEN ? AND ?'];
    $params5 = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions5[] = 'a.fellowship_id = ?';
        $params5[] = $scopeId;
    } elseif (!empty($filtersWithAlias['conditions'])) {
        $whereConditions5 = array_merge($whereConditions5, $filtersWithAlias['conditions']);
        $params5 = array_merge($params5, $filtersWithAlias['params']);
    }
    
    $whereClause5 = implode(' AND ', $whereConditions5);
    
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.service_date,
            a.status,
            a.notes,
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            f.name as fellowship_name,
            COALESCE(s.name, a.service_id) as service_name,
            u.username as recorded_by,
            a.created_at
        FROM attendance a
        JOIN members m ON a.member_id = m.id
        LEFT JOIN fellowships f ON a.fellowship_id = f.id
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN users u ON a.created_by = u.id
        WHERE $whereClause5
        ORDER BY a.service_date DESC, a.fellowship_id, a.member_id
        LIMIT 100
    ");
    $stmt->execute($params5);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'by_fellowship' => $byFellowship,
        'by_service' => $byService,
        'top_attendees' => $topAttendees,
        'details' => $details
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