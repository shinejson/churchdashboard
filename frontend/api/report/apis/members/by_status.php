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
    $filters = getFilterConditions(['table_alias' => 'm.']);
    
    // Get members by status with details - only apply fellowship filter
    $whereConditions = ['1=1'];
    $params = [];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions[] = 'm.fellowship_id = ?';
        $params[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions[] = 'm.fellowship_id = ?';
        $params[] = $filters['fellowship_id'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            f.name as fellowship_name,
            (SELECT COUNT(*) FROM attendance WHERE member_id = m.id AND status = 'present' AND service_date BETWEEN ? AND ?) as attendance_count,
            (SELECT COALESCE(SUM(amount), 0) FROM financial_transactions WHERE member_id = m.id AND type = 'income' AND transaction_date BETWEEN ? AND ?) as total_contributions
        FROM members m
        LEFT JOIN fellowships f ON m.fellowship_id = f.id
        WHERE $whereClause
        ORDER BY 
            FIELD(m.status, 'active', 'visitor', 'inactive'),
            m.first_name, m.last_name
    ");
    $stmt->execute(array_merge($params, [$dateRange['start'], $dateRange['end'], $dateRange['start'], $dateRange['end']]));
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get status summary
    $whereConditions2 = ['1=1'];
    $params2 = [];
    $subqueryParams2 = [];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions2[] = 'fellowship_id = ?';
        $params2[] = $scopeId;
        $subqueryParams2[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions2[] = 'fellowship_id = ?';
        $params2[] = $filters['fellowship_id'];
        $subqueryParams2[] = $filters['fellowship_id'];
    }
    
    $whereClause2 = implode(' AND ', $whereConditions2);
    
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM members WHERE $whereClause2)), 2) as percentage
        FROM members 
        WHERE $whereClause2
        GROUP BY status
        ORDER BY FIELD(status, 'active', 'visitor', 'inactive')
    ");
    $stmt->execute(array_merge($subqueryParams2, $params2));
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'members' => $members,
        'summary' => $summary
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