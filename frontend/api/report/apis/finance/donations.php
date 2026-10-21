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
    
    // Get donations by category/type
    $whereConditions = ['type = \'income\'', 'transaction_date BETWEEN ? AND ?', '(category IS NULL OR category != \'tithe_offering\')'];
    $params = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions = array_merge($whereConditions, $filters['conditions']);
        $params = array_merge($params, $filters['params']);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(category, 'Uncategorized') as donation_type,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as total_amount,
            AVG(amount) as average_amount,
            MIN(amount) as min_amount,
            MAX(amount) as max_amount,
            COUNT(DISTINCT member_id) as unique_donors,
            COUNT(DISTINCT fellowship_id) as fellowship_count
        FROM financial_transactions 
        WHERE $whereClause
        GROUP BY category
        ORDER BY total_amount DESC
    ");
    $stmt->execute($params);
    $byCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get donations by period
    $whereConditions2 = ['type = \'income\'', 'transaction_date BETWEEN ? AND ?', '(category IS NULL OR category != \'tithe_offering\')'];
    $params2 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions2 = array_merge($whereConditions2, $filters['conditions']);
        $params2 = array_merge($params2, $filters['params']);
    }
    
    $whereClause2 = implode(' AND ', $whereConditions2);
    
    $stmt = $pdo->prepare("
        SELECT 
            $periodGroup as period,
            COALESCE(category, 'Uncategorized') as donation_type,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as total_amount
        FROM financial_transactions 
        WHERE $whereClause2
        GROUP BY $periodGroup, category
        ORDER BY period, total_amount DESC
    ");
    $stmt->execute($params2);
    $byPeriod = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get donations by fellowship - only apply service filter (fellowship is the grouping dimension)
    $whereConditions3 = ['ft.type = \'income\'', 'ft.transaction_date BETWEEN ? AND ?', '(ft.category IS NULL OR ft.category != \'tithe_offering\')'];
    $params3 = [$dateRange['start'], $dateRange['end']];
    $subqueryParams3 = [$dateRange['start'], $dateRange['end']];
    
    if ($filters['service_id'] !== null) {
        $whereConditions3[] = 'ft.service_id = ?';
        $params3[] = $filters['service_id'];
        $subqueryParams3[] = $filters['service_id'];
    }
    
    $whereClause3 = implode(' AND ', $whereConditions3);
    $subqueryWhere3 = $whereClause3;
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(f.name, 'No Fellowship') as fellowship_name,
            COALESCE(ft.category, 'Uncategorized') as donation_type,
            COUNT(*) as transaction_count,
            COALESCE(SUM(ft.amount), 0) as total_amount,
            COUNT(DISTINCT ft.member_id) as unique_donors,
            ROUND((COALESCE(SUM(ft.amount), 0) * 100.0 / NULLIF((SELECT COALESCE(SUM(amount), 1) FROM financial_transactions WHERE $subqueryWhere3), 0)), 2) as percentage
        FROM financial_transactions ft
        LEFT JOIN fellowships f ON ft.fellowship_id = f.id
        WHERE $whereClause3
        GROUP BY ft.fellowship_id, ft.category
        ORDER BY fellowship_name, total_amount DESC
    ");
    $stmt->execute(array_merge($subqueryParams3, $params3));
    $byFellowship = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get donations by payment method
    $whereConditions4 = ['type = \'income\'', 'transaction_date BETWEEN ? AND ?', '(category IS NULL OR category != \'tithe_offering\')'];
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
            COALESCE(category, 'Uncategorized') as donation_type,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as total_amount,
            ROUND((COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM financial_transactions WHERE $whereClause4), 0)), 2) as transaction_percentage,
            ROUND((COALESCE(SUM(amount), 0) * 100.0 / NULLIF((SELECT COALESCE(SUM(amount), 1) FROM financial_transactions WHERE $whereClause4), 0)), 2) as amount_percentage
        FROM financial_transactions 
        WHERE $whereClause4
        GROUP BY payment_method, category
        ORDER BY payment_method, total_amount DESC
    ");
    $stmt->execute(array_merge($subqueryParams4, $subqueryParams4, $params4));
    $byPaymentMethod = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get top donors
    $filtersWithAlias = getFilterConditions(['table_alias' => 'ft.']);
    $whereConditions5 = ['ft.type = \'income\'', 'ft.transaction_date BETWEEN ? AND ?', '(ft.category IS NULL OR ft.category != \'tithe_offering\')'];
    $params5 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filtersWithAlias['conditions'])) {
        $whereConditions5 = array_merge($whereConditions5, $filtersWithAlias['conditions']);
        $params5 = array_merge($params5, $filtersWithAlias['params']);
    }
    
    $whereClause5 = implode(' AND ', $whereConditions5);
    
    $stmt = $pdo->prepare("
        SELECT 
            m.id as member_id,
            CONCAT(m.first_name, ' ', m.last_name) as donor_name,
            f.name as fellowship_name,
            COUNT(*) as donation_count,
            COALESCE(SUM(ft.amount), 0) as total_donated,
            AVG(ft.amount) as average_donation,
            MIN(ft.transaction_date) as first_donation_date,
            MAX(ft.transaction_date) as last_donation_date,
            GROUP_CONCAT(DISTINCT COALESCE(ft.category, 'Uncategorized') ORDER BY ft.category SEPARATOR ', ') as donation_types
        FROM financial_transactions ft
        JOIN members m ON ft.member_id = m.id
        LEFT JOIN fellowships f ON m.fellowship_id = f.id
        WHERE $whereClause5
        GROUP BY ft.member_id
        HAVING total_donated > 0
        ORDER BY total_donated DESC
        LIMIT 20
    ");
    $stmt->execute($params5);
    $topDonors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get detailed donation transactions
    $whereConditions6 = ['ft.type = \'income\'', 'ft.transaction_date BETWEEN ? AND ?', '(ft.category IS NULL OR ft.category != \'tithe_offering\')'];
    $params6 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filtersWithAlias['conditions'])) {
        $whereConditions6 = array_merge($whereConditions6, $filtersWithAlias['conditions']);
        $params6 = array_merge($params6, $filtersWithAlias['params']);
    }
    
    $whereClause6 = implode(' AND ', $whereConditions6);
    
    $stmt = $pdo->prepare("
        SELECT 
            ft.*,
            CONCAT(m.first_name, ' ', m.last_name) as donor_name,
            m.email as donor_email,
            m.phone as donor_phone,
            f.name as fellowship_name,
            s.name as service_name,
            u.username as recorded_by
        FROM financial_transactions ft
        LEFT JOIN members m ON ft.member_id = m.id
        LEFT JOIN fellowships f ON ft.fellowship_id = f.id
        LEFT JOIN services s ON ft.service_id = s.id
        LEFT JOIN users u ON ft.recorded_by = u.id
        WHERE $whereClause6
        ORDER BY ft.transaction_date DESC, ft.amount DESC
        LIMIT 100
    ");
    $stmt->execute($params6);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $totalDonations = array_sum(array_column($byCategory, 'total_amount'));
    $totalTransactions = array_sum(array_column($byCategory, 'transaction_count'));
    $uniqueDonors = count(array_unique(array_merge(
        array_column($byFellowship, 'unique_donors'),
        [count($topDonors)]
    )));
    
    echo json_encode([
        'success' => true,
        'by_category' => $byCategory,
        'by_period' => $byPeriod,
        'by_fellowship' => $byFellowship,
        'by_payment_method' => $byPaymentMethod,
        'top_donors' => $topDonors,
        'transactions' => $transactions,
        'statistics' => [
            'total_donations' => $totalDonations,
            'total_transactions' => $totalTransactions,
            'unique_donors' => $uniqueDonors,
            'avg_donation_amount' => $totalTransactions > 0 ? round($totalDonations / $totalTransactions, 2) : 0,
            'fellowships_with_donations' => count(array_unique(array_column($byFellowship, 'fellowship_name'))),
            'most_common_donation_type' => count($byCategory) > 0 ? $byCategory[0]['donation_type'] : 'N/A',
            'largest_donation' => count($transactions) > 0 ? max(array_column($transactions, 'amount')) : 0
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