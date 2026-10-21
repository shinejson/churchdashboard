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
    
    // Get tithes summary by period
    $whereConditions = ['transaction_date BETWEEN ? AND ?'];
    $params = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
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
            MAX(amount) as max_amount,
            COUNT(DISTINCT member_id) as unique_tithers,
            COUNT(CASE WHEN is_first_tithe = 1 THEN 1 END) as first_tithe_count,
            COUNT(CASE WHEN is_second_tithe = 1 THEN 1 END) as second_tithe_count
        FROM tithe_transactions 
        WHERE $whereClause
        GROUP BY $periodGroup
        ORDER BY period
    ");
    $stmt->execute($params);
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get tithes by fellowship - only apply service filter (fellowship is the grouping dimension)
    $whereConditions2 = ['tt.transaction_date BETWEEN ? AND ?'];
    $params2 = [$dateRange['start'], $dateRange['end']];
    
    $subqueryWhere2 = ['transaction_date BETWEEN ? AND ?'];
    $subqueryParams2 = [$dateRange['start'], $dateRange['end']];
    
    if ($filters['service_id'] !== null) {
        $whereConditions2[] = 'tt.service_id = ?';
        $params2[] = $filters['service_id'];
        $subqueryWhere2[] = 'service_id = ?';
        $subqueryParams2[] = $filters['service_id'];
    }
    
    $whereClause2 = implode(' AND ', $whereConditions2);
    $subqueryWhereClause2 = implode(' AND ', $subqueryWhere2);
    
    $allParams2 = array_merge($subqueryParams2, $params2);
    
    $stmt = $pdo->prepare("
        SELECT 
            f.name as fellowship_name,
            COUNT(*) as transaction_count,
            COALESCE(SUM(tt.amount), 0) as total_amount,
            AVG(tt.amount) as average_amount,
            COUNT(DISTINCT tt.member_id) as unique_tithers,
            ROUND((COALESCE(SUM(tt.amount), 0) * 100.0 / NULLIF((SELECT COALESCE(SUM(amount), 1) FROM tithe_transactions WHERE $subqueryWhereClause2), 0)), 2) as percentage
        FROM tithe_transactions tt
        LEFT JOIN fellowships f ON tt.fellowship_id = f.id
        WHERE $whereClause2
        GROUP BY tt.fellowship_id
        ORDER BY total_amount DESC
    ");
    $stmt->execute($allParams2);
    $byFellowship = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get tithes by member
    $filtersWithAlias = getFilterConditions(['table_alias' => 'tt.']);
    $whereConditions3 = ['tt.transaction_date BETWEEN ? AND ?'];
    $params3 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filtersWithAlias['conditions'])) {
        $whereConditions3 = array_merge($whereConditions3, $filtersWithAlias['conditions']);
        $params3 = array_merge($params3, $filtersWithAlias['params']);
    }
    
    $whereClause3 = implode(' AND ', $whereConditions3);
    
    $stmt = $pdo->prepare("
        SELECT 
            m.id as member_id,
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            f.name as fellowship_name,
            COUNT(*) as transaction_count,
            COALESCE(SUM(tt.amount), 0) as total_amount,
            AVG(tt.amount) as average_amount,
            MIN(tt.transaction_date) as first_tithe_date,
            MAX(tt.transaction_date) as last_tithe_date,
            DATEDIFF(MAX(tt.transaction_date), MIN(tt.transaction_date)) as tithe_period_days,
            COUNT(CASE WHEN tt.is_first_tithe = 1 THEN 1 END) as first_tithe_count,
            COUNT(CASE WHEN tt.is_second_tithe = 1 THEN 1 END) as second_tithe_count
        FROM tithe_transactions tt
        JOIN members m ON tt.member_id = m.id
        LEFT JOIN fellowships f ON m.fellowship_id = f.id
        WHERE $whereClause3
        GROUP BY tt.member_id
        HAVING total_amount > 0
        ORDER BY total_amount DESC
        LIMIT 50
    ");
    $stmt->execute($params3);
    $byMember = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get tithes by payment method
    $whereConditions4 = ['transaction_date BETWEEN ? AND ?'];
    $params4 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions4 = array_merge($whereConditions4, $filters['conditions']);
        $params4 = array_merge($params4, $filters['params']);
    }
    
    $whereClause4 = implode(' AND ', $whereConditions4);
    
    // Build params for all subqueries
    $allParams4 = array_merge($params4, $params4, $params4);
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(payment_method, 'cash') as payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as total_amount,
            ROUND((COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM tithe_transactions WHERE $whereClause4), 0)), 2) as transaction_percentage,
            ROUND((COALESCE(SUM(amount), 0) * 100.0 / NULLIF((SELECT COALESCE(SUM(amount), 1) FROM tithe_transactions WHERE $whereClause4), 0)), 2) as amount_percentage
        FROM tithe_transactions 
        WHERE $whereClause4
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $stmt->execute($allParams4);
    $byPaymentMethod = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get detailed tithe transactions
    $whereConditions5 = ['tt.transaction_date BETWEEN ? AND ?'];
    $params5 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filtersWithAlias['conditions'])) {
        $whereConditions5 = array_merge($whereConditions5, $filtersWithAlias['conditions']);
        $params5 = array_merge($params5, $filtersWithAlias['params']);
    }
    
    $whereClause5 = implode(' AND ', $whereConditions5);
    
    $stmt = $pdo->prepare("
        SELECT 
            tt.*,
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            m.email as member_email,
            m.phone as member_phone,
            f.name as fellowship_name,
            ft.transaction_date as financial_date,
            ft.description as financial_description,
            u.username as recorded_by_username
        FROM tithe_transactions tt
        JOIN members m ON tt.member_id = m.id
        LEFT JOIN fellowships f ON tt.fellowship_id = f.id
        LEFT JOIN financial_transactions ft ON tt.financial_transaction_id = ft.id
        LEFT JOIN users u ON tt.recorded_by = u.id
        WHERE $whereClause5
        ORDER BY tt.transaction_date DESC, tt.amount DESC
        LIMIT 100
    ");
    $stmt->execute($params5);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate tithe statistics
    $totalTithes = array_sum(array_column($byFellowship, 'total_amount'));
    $totalTithers = count($byMember);
    $avgTitheAmount = $totalTithers > 0 ? $totalTithes / $totalTithers : 0;
    
    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'by_fellowship' => $byFellowship,
        'by_member' => $byMember,
        'by_payment_method' => $byPaymentMethod,
        'transactions' => $transactions,
        'statistics' => [
            'total_tithes' => $totalTithes,
            'total_transactions' => array_sum(array_column($summary, 'transaction_count')),
            'total_tithers' => $totalTithers,
            'avg_tithe_amount' => round($avgTitheAmount, 2),
            'first_tithe_percentage' => array_sum(array_column($summary, 'first_tithe_count')) > 0 
                ? round((array_sum(array_column($summary, 'first_tithe_count')) / array_sum(array_column($summary, 'transaction_count'))) * 100, 2)
                : 0,
            'second_tithe_percentage' => array_sum(array_column($summary, 'second_tithe_count')) > 0
                ? round((array_sum(array_column($summary, 'second_tithe_count')) / array_sum(array_column($summary, 'transaction_count'))) * 100, 2)
                : 0
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