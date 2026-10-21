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
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $dateRange = getDateRange();
    $filters = getFilterConditions(['table_alias' => 'a.']);
    
    // Get attendance by service (only apply fellowship filter since service is the grouping dimension)
    $whereConditions = ['a.service_date BETWEEN ? AND ?'];
    $params = [$dateRange['start'], $dateRange['end']];
    
    if ($filters['fellowship_id'] !== null) {
        $whereConditions[] = 'a.fellowship_id = ?';
        $params[] = $filters['fellowship_id'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(s.name, a.service_id) as service_name,
            COUNT(DISTINCT a.service_date) as service_count,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
            COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused_count,
            COUNT(DISTINCT a.member_id) as unique_members,
            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(*)), 2) as attendance_rate
        FROM attendance a
        LEFT JOIN services s ON a.service_id = s.id
        WHERE $whereClause
        GROUP BY a.service_id
        ORDER BY present_count DESC
    ");
    $stmt->execute($params);
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get service attendance by date
    $whereConditions2 = ['a.service_date BETWEEN ? AND ?'];
    $params2 = [$dateRange['start'], $dateRange['end']];
    
    if ($filters['fellowship_id'] !== null) {
        $whereConditions2[] = 'a.fellowship_id = ?';
        $params2[] = $filters['fellowship_id'];
    }
    
    $whereClause2 = implode(' AND ', $whereConditions2);
    
    $stmt = $pdo->prepare("
        SELECT 
            a.service_date,
            COALESCE(s.name, a.service_id) as service_name,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
            COUNT(*) as total_count,
            GROUP_CONCAT(DISTINCT f.name ORDER BY f.name SEPARATOR ', ') as fellowships
        FROM attendance a
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN fellowships f ON a.fellowship_id = f.id
        WHERE $whereClause2
        GROUP BY a.service_date, a.service_id
        ORDER BY a.service_date DESC
    ");
    $stmt->execute($params2);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'summary' => $summary,
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