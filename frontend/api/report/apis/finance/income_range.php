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
    
    // Get income summary by period
    $whereConditions = ['type = \'income\'', 'transaction_date BETWEEN ? AND ?'];
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
            COALESCE(SUM(amount), 0) as total_income,
            AVG(amount) as average_amount,
            MIN(amount) as min_amount,
            MAX(amount) as max_amount,
            COUNT(DISTINCT member_id) as unique_contributors,
            COUNT(DISTINCT fellowship_id) as fellowship_count
        FROM financial_transactions 
        WHERE $whereClause
        GROUP BY $periodGroup
        ORDER BY period
    ");
    $stmt->execute($params);
    $incomeSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get income breakdown by category
    $whereConditions2 = ['type = \'income\'', 'transaction_date BETWEEN ? AND ?'];
    $params2 = [$dateRange['start'], $dateRange['end']];
    $subqueryParams2 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions2 = array_merge($whereConditions2, $filters['conditions']);
        $params2 = array_merge($params2, $filters['params']);
        $subqueryParams2 = array_merge($subqueryParams2, $filters['params']);
    }
    
    $whereClause2 = implode(' AND ', $whereConditions2);
    
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN category = 'tithe_offering' THEN 'Tithes & Offerings'
                WHEN category IS NULL THEN 'Other Income'
                ELSE category 
            END as income_type,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as total_amount,
            ROUND((COALESCE(SUM(amount), 0) * 100.0 / NULLIF((SELECT COALESCE(SUM(amount), 1) FROM financial_transactions WHERE $whereClause2), 0)), 2) as percentage,
            AVG(amount) as average_amount
        FROM financial_transactions 
        WHERE $whereClause2
        GROUP BY 
            CASE 
                WHEN category = 'tithe_offering' THEN 'Tithes & Offerings'
                WHEN category IS NULL THEN 'Other Income'
                ELSE category 
            END
        ORDER BY total_amount DESC
    ");
    $stmt->execute(array_merge($subqueryParams2, $params2));
    $incomeBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get income by fellowship - only apply service filter (fellowship is the grouping dimension)
    $filtersWithAlias = getFilterConditions(['table_alias' => 'ft.']);
    $whereConditions3 = ['ft.type = \'income\'', 'ft.transaction_date BETWEEN ? AND ?'];
    $params3 = [$dateRange['start'], $dateRange['end']];
    $subqueryParams3 = [$dateRange['start'], $dateRange['end']];
    
    if ($filters['service_id'] !== null) {
        $whereConditions3[] = 'ft.service_id = ?';
        $params3[] = $filters['service_id'];
        $subqueryParams3[] = $filters['service_id'];
    }
    
    $whereClause3 = implode(' AND ', $whereConditions3);
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(f.name, 'No Fellowship') as fellowship_name,
            COUNT(*) as transaction_count,
            COALESCE(SUM(ft.amount), 0) as total_income,
            AVG(ft.amount) as average_amount,
            COUNT(DISTINCT ft.member_id) as unique_contributors,
            ROUND((COALESCE(SUM(ft.amount), 0) * 100.0 / NULLIF((SELECT COALESCE(SUM(amount), 1) FROM financial_transactions WHERE $whereClause3), 0)), 2) as percentage
        FROM financial_transactions ft
        LEFT JOIN fellowships f ON ft.fellowship_id = f.id
        WHERE $whereClause3
        GROUP BY ft.fellowship_id
        ORDER BY total_income DESC
    ");
    $stmt->execute(array_merge($subqueryParams3, $params3));
    $incomeByFellowship = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get income by payment method
    $whereConditions4 = ['type = \'income\'', 'transaction_date BETWEEN ? AND ?'];
    $params4 = [$dateRange['start'], $dateRange['end']];
    $subqueryParams4 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions4 = array_merge($whereConditions4, $filters['conditions']);
        $params4 = array_merge($params4, $filters['params']);
        $subqueryParams4 = array_merge($subqueryParams4, $filters['params']);
    }
    
    $whereClause4 = implode(' AND ', $whereConditions4);
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(payment_method, 'cash') as payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as total_amount,
            ROUND((COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM financial_transactions WHERE $whereClause4), 0)), 2) as transaction_percentage,
            ROUND((COALESCE(SUM(amount), 0) * 100.0 / NULLIF((SELECT COALESCE(SUM(amount), 1) FROM financial_transactions WHERE $whereClause4), 0)), 2) as amount_percentage,
            AVG(amount) as average_amount
        FROM financial_transactions 
        WHERE $whereClause4
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $stmt->execute(array_merge($subqueryParams4, $subqueryParams4, $params4));
    $incomeByPaymentMethod = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get daily income trends
    $whereConditions5 = ['type = \'income\'', 'transaction_date BETWEEN ? AND ?'];
    $params5 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions5 = array_merge($whereConditions5, $filters['conditions']);
        $params5 = array_merge($params5, $filters['params']);
    }
    
    $whereClause5 = implode(' AND ', $whereConditions5);
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE(transaction_date) as date,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as daily_income,
            AVG(amount) as average_transaction,
            COUNT(DISTINCT member_id) as daily_contributors,
            GROUP_CONCAT(DISTINCT COALESCE(category, 'Other') ORDER BY category SEPARATOR ', ') as income_types
        FROM financial_transactions 
        WHERE $whereClause5
        GROUP BY DATE(transaction_date)
        ORDER BY date
    ");
    $stmt->execute($params5);
    $dailyIncome = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get monthly income comparison
    $currentMonth = date('Y-m');
    $previousMonth = date('Y-m', strtotime('-1 month'));
    
    $whereConditions6 = ['type = \'income\'', 'DATE_FORMAT(transaction_date, \'%Y-%m\') IN (?, ?)'];
    $params6 = [$currentMonth, $previousMonth];
    
    if (!empty($filters['conditions'])) {
        $whereConditions6 = array_merge($whereConditions6, $filters['conditions']);
        $params6 = array_merge($params6, $filters['params']);
    }
    
    $whereClause6 = implode(' AND ', $whereConditions6);
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(transaction_date, '%Y-%m') as month,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as monthly_income,
            COUNT(DISTINCT member_id) as unique_contributors,
            COUNT(DISTINCT fellowship_id) as active_fellowships
        FROM financial_transactions 
        WHERE $whereClause6
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute($params6);
    $monthlyComparison = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate growth metrics
    $growthMetrics = [];
    if (count($monthlyComparison) == 2) {
        $current = $monthlyComparison[0];
        $previous = $monthlyComparison[1];
        
        $growthMetrics['income_growth'] = $previous['monthly_income'] > 0 
            ? round((($current['monthly_income'] - $previous['monthly_income']) / $previous['monthly_income']) * 100, 2)
            : ($current['monthly_income'] > 0 ? 100 : 0);
        
        $growthMetrics['contributor_growth'] = $previous['unique_contributors'] > 0
            ? round((($current['unique_contributors'] - $previous['unique_contributors']) / $previous['unique_contributors']) * 100, 2)
            : ($current['unique_contributors'] > 0 ? 100 : 0);
        
        $growthMetrics['fellowship_growth'] = $previous['active_fellowships'] > 0
            ? round((($current['active_fellowships'] - $previous['active_fellowships']) / $previous['active_fellowships']) * 100, 2)
            : ($current['active_fellowships'] > 0 ? 100 : 0);
    }
    
    // Get top income days
    $whereConditions7 = ['ft.type = \'income\'', 'ft.transaction_date BETWEEN ? AND ?'];
    $params7 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filtersWithAlias['conditions'])) {
        $whereConditions7 = array_merge($whereConditions7, $filtersWithAlias['conditions']);
        $params7 = array_merge($params7, $filtersWithAlias['params']);
    }
    
    $whereClause7 = implode(' AND ', $whereConditions7);
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE(ft.transaction_date) as date,
            COUNT(*) as transaction_count,
            COALESCE(SUM(ft.amount), 0) as daily_income,
            COUNT(DISTINCT ft.member_id) as contributors_count,
            GROUP_CONCAT(DISTINCT COALESCE(f.name, 'No Fellowship') ORDER BY f.name SEPARATOR ', ') as fellowships
        FROM financial_transactions ft
        LEFT JOIN fellowships f ON ft.fellowship_id = f.id
        WHERE $whereClause7
        GROUP BY DATE(ft.transaction_date)
        ORDER BY daily_income DESC
        LIMIT 10
    ");
    $stmt->execute($params7);
    $topIncomeDays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'income_summary' => $incomeSummary,
        'income_breakdown' => $incomeBreakdown,
        'income_by_fellowship' => $incomeByFellowship,
        'income_by_payment_method' => $incomeByPaymentMethod,
        'daily_income' => $dailyIncome,
        'monthly_comparison' => $monthlyComparison,
        'growth_metrics' => $growthMetrics,
        'top_income_days' => $topIncomeDays,
        'total_income' => array_sum(array_column($incomeSummary, 'total_income')),
        'avg_daily_income' => count($dailyIncome) > 0 ? round(array_sum(array_column($dailyIncome, 'daily_income')) / count($dailyIncome), 2) : 0,
        'largest_single_transaction' => count($incomeSummary) > 0 ? max(array_column($incomeSummary, 'max_amount')) : 0
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