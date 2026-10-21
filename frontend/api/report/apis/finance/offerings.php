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
    $periodGroup = getPeriodFilter('transaction_date');
    $filters = getFilterConditions();
    
    // Get offerings summary
    // Include both pure offerings and combined tithe+offering records so
    // the report shows all relevant giving, not just rows with the
    // special 'tithe_offering' category.
    $whereConditions = [
        "type = 'income'",
        "category IN ('offering','tithe_offering')",
        'transaction_date BETWEEN ? AND ?'
    ];
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
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as total_amount,
            AVG(amount) as average_amount,
            MIN(amount) as min_amount,
            MAX(amount) as max_amount
        FROM financial_transactions 
        WHERE $whereClause
        GROUP BY $periodGroup
        ORDER BY period
    ");
    $stmt->execute($params);
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get offerings by fellowship - only apply service filter (fellowship is the grouping dimension)
    $whereConditions2 = [
        "ft.type = 'income'",
        "ft.category IN ('offering','tithe_offering')",
        'ft.transaction_date BETWEEN ? AND ?'
    ];
    $params2 = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions2[] = 'ft.fellowship_id = ?';
        $params2[] = $scopeId;
    }
    
    if ($filters['service_id'] !== null) {
        $whereConditions2[] = 'ft.service_id = ?';
        $params2[] = $filters['service_id'];
    }
    
    $whereClause2 = implode(' AND ', $whereConditions2);
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(f.name, 'No Fellowship') as fellowship,
            COUNT(*) as transaction_count,
            COALESCE(SUM(ft.amount), 0) as total_amount,
            AVG(ft.amount) as average_amount
        FROM financial_transactions ft
        LEFT JOIN fellowships f ON ft.fellowship_id = f.id
        WHERE $whereClause2
        GROUP BY ft.fellowship_id
        ORDER BY total_amount DESC
    ");
    $stmt->execute($params2);
    $byFellowship = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get offerings by payment method
    $whereConditions3 = [
        "type = 'income'",
        "category IN ('offering','tithe_offering')",
        'transaction_date BETWEEN ? AND ?'
    ];
    $params3 = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions3[] = 'fellowship_id = ?';
        $params3[] = $scopeId;
    } elseif (!empty($filters['conditions'])) {
        $whereConditions3 = array_merge($whereConditions3, $filters['conditions']);
        $params3 = array_merge($params3, $filters['params']);
    }
    
    $whereClause3 = implode(' AND ', $whereConditions3);
    
    // Build subquery for percentage calculation
    $subqueryParams = array_merge($params3, $params3);
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(payment_method, 'Unknown') as payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as total_amount,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM financial_transactions WHERE $whereClause3)), 2) as percentage
        FROM financial_transactions 
        WHERE $whereClause3
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $stmt->execute($subqueryParams);
    $byPaymentMethod = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get detailed transactions
    $filtersWithAlias = getFilterConditions(['table_alias' => 'ft.']);
    $whereConditions4 = ['ft.type = \'income\'', 'ft.category = \'tithe_offering\'', 'ft.transaction_date BETWEEN ? AND ?'];
    $params4 = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions4[] = 'ft.fellowship_id = ?';
        $params4[] = $scopeId;
    } elseif (!empty($filtersWithAlias['conditions'])) {
        $whereConditions4 = array_merge($whereConditions4, $filtersWithAlias['conditions']);
        $params4 = array_merge($params4, $filtersWithAlias['params']);
    }
    
    $whereClause4 = implode(' AND ', $whereConditions4);
    
    $stmt = $pdo->prepare("
        SELECT 
            ft.id,
            ft.transaction_date,
            ft.amount,
            ft.payment_method,
            ft.reference,
            ft.description,
            ft.notes,
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            f.name as fellowship_name,
            s.name as service_name,
            u.username as recorded_by
        FROM financial_transactions ft
        LEFT JOIN members m ON ft.member_id = m.id
        LEFT JOIN fellowships f ON ft.fellowship_id = f.id
        LEFT JOIN services s ON ft.service_id = s.id
        LEFT JOIN users u ON ft.recorded_by = u.id
        WHERE $whereClause4
        ORDER BY ft.transaction_date DESC, ft.amount DESC
    ");
    $stmt->execute($params4);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'by_fellowship' => $byFellowship,
        'by_payment_method' => $byPaymentMethod,
        'transactions' => $transactions
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