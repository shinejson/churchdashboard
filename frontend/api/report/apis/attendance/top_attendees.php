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
    $filters = getFilterConditions(['table_alias' => 'a.']);
    
    // Get top attendees with ranking
    $whereConditions = ['a.service_date BETWEEN ? AND ?'];
    $params = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions[] = 'a.fellowship_id = ?';
        $params[] = $scopeId;
    } elseif (!empty($filters['conditions'])) {
        $whereConditions = array_merge($whereConditions, $filters['conditions']);
        $params = array_merge($params, $filters['params']);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            m.status,
            m.category,
            m.age_category,
            f.name as fellowship_name,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
            COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused_count,
            COUNT(*) as total_services,
            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate,
            MIN(a.service_date) as first_attendance,
            MAX(a.service_date) as last_attendance,
            DATEDIFF(MAX(a.service_date), MIN(a.service_date)) as attendance_period_days,
            COUNT(DISTINCT a.service_id) as different_service_types,
            COUNT(DISTINCT DATE(a.service_date)) as unique_days_attended
        FROM attendance a
        JOIN members m ON a.member_id = m.id
        LEFT JOIN fellowships f ON m.fellowship_id = f.id
        WHERE $whereClause
        GROUP BY a.member_id
        HAVING present_count > 0
        ORDER BY attendance_rate DESC, present_count DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $topAttendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add ranking
    $rank = 1;
    foreach ($topAttendees as &$attendee) {
        $attendee['rank'] = $rank++;
    }
    
    // Get attendance by category
    $whereConditions2 = ['a.service_date BETWEEN ? AND ?'];
    $params2 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions2 = array_merge($whereConditions2, $filters['conditions']);
        $params2 = array_merge($params2, $filters['params']);
    }
    
    $whereClause2 = implode(' AND ', $whereConditions2);
    
    $stmt = $pdo->prepare("
        SELECT 
            m.category,
            COUNT(DISTINCT m.id) as total_members,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_attendance,
            COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.member_id END) as attending_members,
            ROUND((COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.member_id END) * 100.0 / NULLIF(COUNT(DISTINCT m.id), 0)), 2) as member_participation_rate,
            ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 2) as attendance_rate
        FROM members m
        LEFT JOIN attendance a ON m.id = a.member_id 
            AND $whereClause2
        WHERE 1=1 GROUP BY m.category
        ORDER BY attendance_rate DESC
    ");
    $stmt->execute($params2);
    $byCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attendance by age category
    $whereConditions3 = ['a.service_date BETWEEN ? AND ?'];
    $params3 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions3 = array_merge($whereConditions3, $filters['conditions']);
        $params3 = array_merge($params3, $filters['params']);
    }
    
    $whereClause3 = implode(' AND ', $whereConditions3);
    
    $stmt = $pdo->prepare("
        SELECT 
            m.age_category,
            COUNT(DISTINCT m.id) as total_members,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_attendance,
            COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.member_id END) as attending_members,
            ROUND((COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.member_id END) * 100.0 / NULLIF(COUNT(DISTINCT m.id), 0)), 2) as member_participation_rate
        FROM members m
        LEFT JOIN attendance a ON m.id = a.member_id 
            AND $whereClause3
        WHERE 1=1 GROUP BY m.age_category
        ORDER BY total_attendance DESC
    ");
    $stmt->execute($params3);
    $byAgeCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get perfect attendance members (100% attendance)
    $whereConditions4 = ['service_date BETWEEN ? AND ?'];
    $params4 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions4 = array_merge($whereConditions4, $filters['conditions']);
        $params4 = array_merge($params4, $filters['params']);
    }
    
    $whereClause4 = implode(' AND ', $whereConditions4);
    
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            m.category,
            f.name as fellowship_name,
            a_stats.present_count,
            a_stats.total_services,
            a_stats.attendance_rate,
            a_stats.first_attendance,
            a_stats.last_attendance
        FROM (
            SELECT 
                member_id,
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                COUNT(*) as total_services,
                ROUND((COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate,
                MIN(service_date) as first_attendance,
                MAX(service_date) as last_attendance
            FROM attendance 
            WHERE $whereClause4
            GROUP BY member_id
            HAVING attendance_rate = 100
            AND total_services >= 3
        ) a_stats
        JOIN members m ON a_stats.member_id = m.id
        LEFT JOIN fellowships f ON m.fellowship_id = f.id
        ORDER BY a_stats.present_count DESC
    ");
    $stmt->execute($params4);
    $perfectAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attendance streaks
    $whereConditions5 = ['service_date BETWEEN ? AND ?', 'status = \'present\''];
    $params5 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions5 = array_merge($whereConditions5, $filters['conditions']);
        $params5 = array_merge($params5, $filters['params']);
    }
    
    $whereClause5 = implode(' AND ', $whereConditions5);
    
    $stmt = $pdo->prepare("
        SELECT 
            member_id,
            MAX(streak_length) as longest_streak,
            AVG(streak_length) as avg_streak_length
        FROM (
            SELECT 
                member_id,
                COUNT(*) as streak_length
            FROM (
                SELECT 
                    member_id,
                    service_date,
                    status,
                    DATE_SUB(service_date, INTERVAL ROW_NUMBER() OVER (PARTITION BY member_id ORDER BY service_date) DAY) as grp
                FROM attendance 
                WHERE $whereClause5
                ORDER BY member_id, service_date
            ) t
            GROUP BY member_id, grp
        ) streaks
        GROUP BY member_id
        HAVING longest_streak >= 3
        ORDER BY longest_streak DESC
        LIMIT 20
    ");
    $stmt->execute($params5);
    $attendanceStreaks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add streak information to top attendees
    foreach ($topAttendees as &$attendee) {
        foreach ($attendanceStreaks as $streak) {
            if ($streak['member_id'] == $attendee['id']) {
                $attendee['longest_streak'] = $streak['longest_streak'];
                $attendee['avg_streak_length'] = $streak['avg_streak_length'];
                break;
            }
        }
        if (!isset($attendee['longest_streak'])) {
            $attendee['longest_streak'] = 0;
            $attendee['avg_streak_length'] = 0;
        }
    }
    
    echo json_encode([
        'success' => true,
        'top_attendees' => $topAttendees,
        'by_category' => $byCategory,
        'by_age_category' => $byAgeCategory,
        'perfect_attendance' => $perfectAttendance,
        'summary' => [
            'total_top_attendees' => count($topAttendees),
            'total_perfect_attendance' => count($perfectAttendance),
            'avg_attendance_rate' => count($topAttendees) > 0 ? 
                round(array_sum(array_column($topAttendees, 'attendance_rate')) / count($topAttendees), 2) : 0,
            'best_attendance_rate' => count($topAttendees) > 0 ? $topAttendees[0]['attendance_rate'] : 0,
            'most_services_attended' => count($topAttendees) > 0 ? $topAttendees[0]['present_count'] : 0
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