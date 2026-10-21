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
    $filters = getFilterConditions(['table_alias' => 'm.']);
    
    // Get new members - only apply fellowship filter
    $whereConditions = ['m.join_date BETWEEN ? AND ?'];
    $params = [$dateRange['start'], $dateRange['end']];
    
    if ($filters['fellowship_id'] !== null) {
        $whereConditions[] = 'm.fellowship_id = ?';
        $params[] = $filters['fellowship_id'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            f.name as fellowship_name,
            DATEDIFF(CURDATE(), m.join_date) as days_since_joining,
            (SELECT COUNT(*) FROM attendance WHERE member_id = m.id AND status = 'present') as total_attendance,
            (SELECT service_date FROM attendance WHERE member_id = m.id AND status = 'present' ORDER BY service_date DESC LIMIT 1) as last_attendance_date
        FROM members m
        LEFT JOIN fellowships f ON m.fellowship_id = f.id
        WHERE $whereClause
        ORDER BY m.join_date DESC
    ");
    $stmt->execute($params);
    $newMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get new members summary by time period
    $periodGroup = getPeriodFilter('join_date');
    $whereConditions2 = ['join_date BETWEEN ? AND ?'];
    $params2 = [$dateRange['start'], $dateRange['end']];
    
    if ($filters['fellowship_id'] !== null) {
        $whereConditions2[] = 'fellowship_id = ?';
        $params2[] = $filters['fellowship_id'];
    }
    
    $whereClause2 = implode(' AND ', $whereConditions2);
    
    $stmt = $pdo->prepare("
        SELECT 
            $periodGroup as period,
            COUNT(*) as new_members_count,
            GROUP_CONCAT(CONCAT(first_name, ' ', last_name) ORDER BY join_date SEPARATOR ', ') as member_names
        FROM members 
        WHERE $whereClause2
        GROUP BY $periodGroup
        ORDER BY period DESC
    ");
    $stmt->execute($params2);
    $summaryByPeriod = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get new members by fellowship - no additional filter needed (fellowship is the grouping dimension)
    $whereConditions3 = ['m.join_date BETWEEN ? AND ?'];
    $params3 = [$dateRange['start'], $dateRange['end']];
    $subqueryParams3 = [$dateRange['start'], $dateRange['end']];
    
    $whereClause3 = implode(' AND ', $whereConditions3);
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(f.name, 'No Fellowship') as fellowship,
            COUNT(*) as new_members_count,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM members WHERE join_date BETWEEN ? AND ?)), 2) as percentage
        FROM members m
        LEFT JOIN fellowships f ON m.fellowship_id = f.id
        WHERE $whereClause3
        GROUP BY m.fellowship_id
        ORDER BY new_members_count DESC
    ");
    $stmt->execute(array_merge($subqueryParams3, $params3));
    $byFellowship = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get new members by status
    $whereConditions4 = ['join_date BETWEEN ? AND ?'];
    $params4 = [$dateRange['start'], $dateRange['end']];
    $subqueryParams4 = [$dateRange['start'], $dateRange['end']];
    
    if ($filters['fellowship_id'] !== null) {
        $whereConditions4[] = 'fellowship_id = ?';
        $params4[] = $filters['fellowship_id'];
        $subqueryParams4[] = $filters['fellowship_id'];
    }
    
    $whereClause4 = implode(' AND ', $whereConditions4);
    
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM members WHERE $whereClause4)), 2) as percentage
        FROM members 
        WHERE $whereClause4
        GROUP BY status
        ORDER BY count DESC
    ");
    $stmt->execute(array_merge($subqueryParams4, $params4));
    $byStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get new members by category (adult/children/teen)
    $stmt = $pdo->prepare("
        SELECT 
            category,
            COUNT(*) as count,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM members WHERE $whereClause4)), 2) as percentage
        FROM members 
        WHERE $whereClause4
        GROUP BY category
        ORDER BY count DESC
    ");
    $stmt->execute(array_merge($subqueryParams4, $params4));
    $byCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'new_members' => $newMembers,
        'summary_by_period' => $summaryByPeriod,
        'by_fellowship' => $byFellowship,
        'by_status' => $byStatus,
        'by_category' => $byCategory,
        'total_new_members' => count($newMembers)
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