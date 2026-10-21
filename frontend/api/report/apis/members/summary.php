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
    
    // Debug logging
    error_log("Members Summary API - Date Range: " . json_encode($dateRange));
    error_log("Members Summary API - Filters: " . json_encode($filters));
    
    // Get detailed member summary - only apply fellowship filter (members table has fellowship_id)
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
    
    error_log("Members Summary API - WHERE clause: $whereClause");
    error_log("Members Summary API - Params: " . json_encode($params));
    
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            CONCAT(m.first_name, ' ', m.last_name) as name,
            m.email,
            m.phone,
            m.status,
            m.category,
            m.join_date,
            f.name as fellowship_name,
            (SELECT COUNT(*) FROM attendance WHERE member_id = m.id AND status = 'present' AND service_date BETWEEN ? AND ?) as attendance_count,
            (SELECT COALESCE(SUM(amount), 0) FROM financial_transactions WHERE member_id = m.id AND type = 'income' AND transaction_date BETWEEN ? AND ?) as total_contributions,
            (SELECT COALESCE(SUM(amount), 0) FROM tithe_transactions WHERE member_id = m.id AND transaction_date BETWEEN ? AND ?) as total_tithes
        FROM members m
        LEFT JOIN fellowships f ON m.fellowship_id = f.id
        WHERE $whereClause
        ORDER BY m.join_date DESC
    ");
    $stmt->execute(array_merge($params, [$dateRange['start'], $dateRange['end'], $dateRange['start'], $dateRange['end'], $dateRange['start'], $dateRange['end']]));
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Members Summary API - Found " . count($members) . " members");
    
    // Get summary stats
    $stats = [];
    
    // By status
    $whereConditions2 = ['1=1'];
    $params2 = [];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions2[] = 'fellowship_id = ?';
        $params2[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions2[] = 'fellowship_id = ?';
        $params2[] = $filters['fellowship_id'];
    }
    
    $whereClause2 = implode(' AND ', $whereConditions2);
    
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM members 
        WHERE $whereClause2
        GROUP BY status
    ");
    $stmt->execute($params2);
    $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // By category
    $stmt = $pdo->prepare("
        SELECT category, COUNT(*) as count 
        FROM members 
        WHERE $whereClause2
        GROUP BY category
    ");
    $stmt->execute($params2);
    $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // By fellowship - no additional filter needed (fellowship is the grouping dimension)
    $whereConditions_bf = ['1=1'];
    $params_bf = [];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions_bf[] = 'm.fellowship_id = ?';
        $params_bf[] = $scopeId;
    }
    
    $whereClause_bf = implode(' AND ', $whereConditions_bf);
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(f.name, 'No Fellowship') as fellowship,
            COUNT(*) as count 
        FROM members m
        LEFT JOIN fellowships f ON m.fellowship_id = f.id
        WHERE $whereClause_bf
        GROUP BY m.fellowship_id
    ");
    $stmt->execute($params_bf);
    $stats['by_fellowship'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // New members by month
    $whereConditions3 = ['join_date BETWEEN ? AND ?'];
    $params3 = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions3[] = 'fellowship_id = ?';
        $params3[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions3[] = 'fellowship_id = ?';
        $params3[] = $filters['fellowship_id'];
    }
    
    $whereClause3 = implode(' AND ', $whereConditions3);
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(join_date, '%Y-%m') as month,
            COUNT(*) as count
        FROM members 
        WHERE $whereClause3
        GROUP BY DATE_FORMAT(join_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute($params3);
    $stats['new_by_month'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'members' => $members,
        'stats' => $stats
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