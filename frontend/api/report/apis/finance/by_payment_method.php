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
    $filters = getFilterConditions();
    
    // Get income by payment method
    $whereConditions = ['type = \'income\'', 'transaction_date BETWEEN ? AND ?'];
    $params = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions = array_merge($whereConditions, $filters['conditions']);
        $params = array_merge($params, $filters['params']);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(payment_method, 'cash') as payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as total_amount,
            AVG(amount) as average_amount,
            MIN(amount) as min_amount,
            MAX(amount) as max_amount,
            COUNT(DISTINCT member_id) as unique_payers,
            COUNT(DISTINCT fellowship_id) as fellowship_count
        FROM financial_transactions 
        WHERE $whereClause
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $stmt->execute($params);
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment method trends over time
    $periodGroup = getPeriodFilter('transaction_date');
    $whereConditions2 = ['type = \'income\'', 'transaction_date BETWEEN ? AND ?'];
    $params2 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions2 = array_merge($whereConditions2, $filters['conditions']);
        $params2 = array_merge($params2, $filters['params']);
    }
    
    $whereClause2 = implode(' AND ', $whereConditions2);
    
    $stmt = $pdo->prepare("
        SELECT 
            $periodGroup as period,
            COALESCE(payment_method, 'cash') as payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as period_amount
        FROM financial_transactions 
        WHERE $whereClause2
        GROUP BY $periodGroup, payment_method
        ORDER BY period, payment_method
    ");
    $stmt->execute($params2);
    $paymentTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment method by category
    $whereConditions3 = ['type = \'income\'', 'transaction_date BETWEEN ? AND ?'];
    $params3 = [$dateRange['start'], $dateRange['end']];
    $subqueryParams3 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions3 = array_merge($whereConditions3, $filters['conditions']);
        $params3 = array_merge($params3, $filters['params']);
        $subqueryParams3 = array_merge($subqueryParams3, $filters['params']);
    }
    
    $whereClause3 = implode(' AND ', $whereConditions3);
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(category, 'Other') as category,
            COALESCE(payment_method, 'cash') as payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as category_amount,
            ROUND((COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM financial_transactions WHERE $whereClause3), 0)), 2) as transaction_percentage,
            ROUND((COALESCE(SUM(amount), 0) * 100.0 / NULLIF((SELECT COALESCE(SUM(amount), 1) FROM financial_transactions WHERE $whereClause3), 0)), 2) as amount_percentage
        FROM financial_transactions 
        WHERE $whereClause3
        GROUP BY category, payment_method
        ORDER BY category, payment_method
    ");
    $stmt->execute(array_merge($subqueryParams3, $subqueryParams3, $params3));
    $byCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment method by fellowship - only apply service filter (fellowship is the grouping dimension)
    $filtersWithAlias = getFilterConditions(['table_alias' => 'ft.']);
    $whereConditions4 = ['ft.type = \'income\'', 'ft.transaction_date BETWEEN ? AND ?'];
    $params4 = [$dateRange['start'], $dateRange['end']];
    $subqueryParams4 = [$dateRange['start'], $dateRange['end']];
    
    if ($filters['service_id'] !== null) {
        $whereConditions4[] = 'ft.service_id = ?';
        $params4[] = $filters['service_id'];
        $subqueryParams4[] = $filters['service_id'];
    }
    
    $whereClause4 = implode(' AND ', $whereConditions4);
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(f.name, 'No Fellowship') as fellowship_name,
            COALESCE(ft.payment_method, 'cash') as payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(ft.amount), 0) as fellowship_amount,
            COUNT(DISTINCT ft.member_id) as unique_payers,
            ROUND((COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM financial_transactions WHERE $whereClause4), 0)), 2) as transaction_percentage
        FROM financial_transactions ft
        LEFT JOIN fellowships f ON ft.fellowship_id = f.id
        WHERE $whereClause4
        GROUP BY ft.fellowship_id, ft.payment_method
        ORDER BY fellowship_name, payment_method
    ");
    $stmt->execute(array_merge($subqueryParams4, $params4));
    $byFellowship = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get member payment preferences
    $whereConditions5 = ['ft.type = \'income\'', 'ft.transaction_date BETWEEN ? AND ?', 'ft.payment_method IS NOT NULL'];
    $params5 = [$dateRange['start'], $dateRange['end']];
    $subqueryParams5 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filtersWithAlias['conditions'])) {
        $whereConditions5 = array_merge($whereConditions5, $filtersWithAlias['conditions']);
        $params5 = array_merge($params5, $filtersWithAlias['params']);
        $subqueryParams5 = array_merge($subqueryParams5, $filtersWithAlias['params']);
    }
    
    $whereClause5 = implode(' AND ', $whereConditions5);
    $subqueryWhere5 = 'ft2.type = \'income\' AND ft2.transaction_date BETWEEN ? AND ?';
    if (!empty($filtersWithAlias['conditions'])) {
        $subqueryWhere5 .= ' AND ' . str_replace('ft.', 'ft2.', implode(' AND ', $filtersWithAlias['conditions']));
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            m.id as member_id,
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            f.name as fellowship_name,
            COALESCE(ft.payment_method, 'cash') as preferred_payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(ft.amount), 0) as total_paid,
            ROUND((COUNT(*) * 100.0 / (
                SELECT COUNT(*) 
                FROM financial_transactions ft2 
                WHERE ft2.member_id = m.id 
                AND $subqueryWhere5
            )), 2) as method_usage_percentage
        FROM financial_transactions ft
        JOIN members m ON ft.member_id = m.id
        LEFT JOIN fellowships f ON m.fellowship_id = f.id
        WHERE $whereClause5
        GROUP BY ft.member_id, ft.payment_method
        HAVING transaction_count >= 2
        ORDER BY member_name, transaction_count DESC
    ");
    $stmt->execute(array_merge($subqueryParams5, $params5));
    $memberPreferences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group member preferences by member
    $memberPaymentMethods = [];
    foreach ($memberPreferences as $preference) {
        $memberId = $preference['member_id'];
        
        if (!isset($memberPaymentMethods[$memberId])) {
            $memberPaymentMethods[$memberId] = [
                'member_id' => $memberId,
                'member_name' => $preference['member_name'],
                'fellowship_name' => $preference['fellowship_name'],
                'payment_methods' => [],
                'total_transactions' => 0,
                'total_paid' => 0
            ];
        }
        
        $memberPaymentMethods[$memberId]['payment_methods'][] = [
            'method' => $preference['preferred_payment_method'],
            'transaction_count' => $preference['transaction_count'],
            'total_paid' => $preference['total_paid'],
            'usage_percentage' => $preference['method_usage_percentage']
        ];
        
        $memberPaymentMethods[$memberId]['total_transactions'] += $preference['transaction_count'];
        $memberPaymentMethods[$memberId]['total_paid'] += $preference['total_paid'];
    }
    
    // Determine primary payment method for each member
    foreach ($memberPaymentMethods as &$member) {
        if (count($member['payment_methods']) > 0) {
            usort($member['payment_methods'], function($a, $b) {
                return $b['transaction_count'] <=> $a['transaction_count'];
            });
            $member['primary_payment_method'] = $member['payment_methods'][0]['method'];
            $member['primary_method_percentage'] = $member['payment_methods'][0]['usage_percentage'];
        }
    }
    
    // Get payment method distribution statistics
    $totalTransactions = array_sum(array_column($paymentMethods, 'transaction_count'));
    $totalAmount = array_sum(array_column($paymentMethods, 'total_amount'));
    
    $distribution = [];
    foreach ($paymentMethods as $method) {
        $distribution[] = [
            'payment_method' => $method['payment_method'],
            'transaction_count' => $method['transaction_count'],
            'total_amount' => $method['total_amount'],
            'transaction_percentage' => $totalTransactions > 0 ? 
                round(($method['transaction_count'] / $totalTransactions) * 100, 2) : 0,
            'amount_percentage' => $totalAmount > 0 ? 
                round(($method['total_amount'] / $totalAmount) * 100, 2) : 0,
            'avg_transaction_value' => $method['transaction_count'] > 0 ? 
                round($method['total_amount'] / $method['transaction_count'], 2) : 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'payment_methods' => $distribution,
        'payment_trends' => $paymentTrends,
        'by_category' => $byCategory,
        'by_fellowship' => $byFellowship,
        'member_preferences' => array_values($memberPaymentMethods),
        'summary' => [
            'total_payment_methods' => count($paymentMethods),
            'most_popular_method' => count($paymentMethods) > 0 ? $paymentMethods[0]['payment_method'] : 'N/A',
            'highest_value_method' => count($paymentMethods) > 0 ? $paymentMethods[0]['payment_method'] : 'N/A',
            'avg_transaction_by_cash' => count($paymentMethods) > 0 ? 
                ($paymentMethods[0]['payment_method'] === 'cash' ? 
                 round($paymentMethods[0]['total_amount'] / max(1, $paymentMethods[0]['transaction_count']), 2) : 0) : 0,
            'electronic_payment_percentage' => $totalAmount > 0 ? 
                round((array_sum(array_map(function($method) {
                    return in_array($method['payment_method'], ['bank_transfer', 'mobile_money', 'card']) ? 
                           $method['total_amount'] : 0;
                }, $paymentMethods)) / $totalAmount) * 100, 2) : 0
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