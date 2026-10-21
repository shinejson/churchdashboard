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
    $filters = getFilterConditions(['fellowship_column' => 'm.fellowship_id', 'service_column' => 'ft.service_id']);
    
    // Build fellowship filter for subqueries (partnership contributions are filtered through members table)
    $fellowshipJoin = '';
    $fellowshipWhere = '';
    $fellowshipParams = [];
    if ($filters['fellowship_id'] !== null) {
        $fellowshipJoin = 'LEFT JOIN members m_filter ON pc.member_id = m_filter.id';
        $fellowshipWhere = ' AND m_filter.fellowship_id = ?';
        $fellowshipParams[] = $filters['fellowship_id'];
    }
    
    // Get partnership arms summary
    $stmt = $pdo->prepare("
        SELECT 
            pa.id,
            pa.name as partnership_arm,
            pa.description,
            (SELECT COUNT(*) FROM partnership_contributions WHERE partnership_arm_id = pa.id) as total_contributions,
            (SELECT COALESCE(SUM(pc.amount), 0) 
             FROM partnership_contributions pc 
             $fellowshipJoin
             WHERE pc.partnership_arm_id = pa.id 
             AND pc.contribution_date BETWEEN ? AND ?
             $fellowshipWhere) as period_amount,
            (SELECT COUNT(DISTINCT pc.member_id) 
             FROM partnership_contributions pc 
             $fellowshipJoin
             WHERE pc.partnership_arm_id = pa.id 
             AND pc.contribution_date BETWEEN ? AND ?
             $fellowshipWhere) as unique_contributors,
            (SELECT MAX(contribution_date) FROM partnership_contributions WHERE partnership_arm_id = pa.id) as last_contribution_date
        FROM partnership_arms pa
        WHERE pa.is_active = 1
        ORDER BY pa.name
    ");
    $stmt->execute(array_merge(
        [$dateRange['start'], $dateRange['end']], 
        $fellowshipParams,
        [$dateRange['start'], $dateRange['end']], 
        $fellowshipParams
    ));
    $partnershipArms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get detailed contributions for each partnership arm
    foreach ($partnershipArms as &$arm) {
        $whereConditions = ['pc.partnership_arm_id = ?', 'pc.contribution_date BETWEEN ? AND ?'];
        $params = [$arm['id'], $dateRange['start'], $dateRange['end']];
        
        if ($filters['fellowship_id'] !== null) {
            $whereConditions[] = 'm.fellowship_id = ?';
            $params[] = $filters['fellowship_id'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $pdo->prepare("
            SELECT 
                pc.*,
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                m.email as member_email,
                m.phone as member_phone,
                f.name as fellowship_name,
                ft.transaction_date,
                ft.payment_method,
                u.username as recorded_by
            FROM partnership_contributions pc
            LEFT JOIN members m ON pc.member_id = m.id
            LEFT JOIN fellowships f ON m.fellowship_id = f.id
            LEFT JOIN financial_transactions ft ON pc.financial_transaction_id = ft.id
            LEFT JOIN users u ON pc.recorded_by = u.id
            WHERE $whereClause
            ORDER BY pc.contribution_date DESC, pc.amount DESC
        ");
        $stmt->execute($params);
        $contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $arm['contributions'] = $contributions;
        $arm['contribution_count'] = count($contributions);
        
        // Get top contributors for this arm
        $whereConditions2 = ['pc.partnership_arm_id = ?', 'pc.contribution_date BETWEEN ? AND ?'];
        $params2 = [$arm['id'], $dateRange['start'], $dateRange['end']];
        
        if ($filters['fellowship_id'] !== null) {
            $whereConditions2[] = 'm.fellowship_id = ?';
            $params2[] = $filters['fellowship_id'];
        }
        
        $whereClause2 = implode(' AND ', $whereConditions2);
        
        $stmt = $pdo->prepare("
            SELECT 
                pc.member_id,
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                COUNT(*) as contribution_count,
                COALESCE(SUM(pc.amount), 0) as total_contributed,
                AVG(pc.amount) as average_contribution,
                MIN(pc.contribution_date) as first_contribution,
                MAX(pc.contribution_date) as last_contribution
            FROM partnership_contributions pc
            JOIN members m ON pc.member_id = m.id
            WHERE $whereClause2
            GROUP BY pc.member_id
            ORDER BY total_contributed DESC
            LIMIT 10
        ");
        $stmt->execute($params2);
        $topContributors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $arm['top_contributors'] = $topContributors;
    }
    
    // Get partnership contributions by period
    $periodGroup = getPeriodFilter('contribution_date');
    $whereConditions3 = ['pc.contribution_date BETWEEN ? AND ?'];
    $params3 = [$dateRange['start'], $dateRange['end']];
    
    if ($filters['fellowship_id'] !== null) {
        $whereConditions3[] = 'm.fellowship_id = ?';
        $params3[] = $filters['fellowship_id'];
    }
    
    $whereClause3 = implode(' AND ', $whereConditions3);
    
    $stmt = $pdo->prepare("
        SELECT 
            pa.name as partnership_arm,
            $periodGroup as period,
            COUNT(*) as contribution_count,
            COALESCE(SUM(pc.amount), 0) as period_amount,
            COUNT(DISTINCT pc.member_id) as unique_contributors,
            AVG(pc.amount) as average_contribution
        FROM partnership_contributions pc
        JOIN partnership_arms pa ON pc.partnership_arm_id = pa.id
        LEFT JOIN members m ON pc.member_id = m.id
        WHERE $whereClause3
        GROUP BY pc.partnership_arm_id, $periodGroup
        ORDER BY pa.name, period
    ");
    $stmt->execute($params3);
    $contributionsByPeriod = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get partnership contributions by fellowship - no additional filter needed (fellowship is the grouping dimension)
    $whereConditions4 = ['pc.contribution_date BETWEEN ? AND ?'];
    $params4 = [$dateRange['start'], $dateRange['end']];
    $subqueryParams4 = [$dateRange['start'], $dateRange['end']];
    
    $whereClause4 = implode(' AND ', $whereConditions4);
    
    $stmt = $pdo->prepare("
        SELECT 
            pa.name as partnership_arm,
            COALESCE(f.name, 'No Fellowship') as fellowship_name,
            COUNT(*) as contribution_count,
            COALESCE(SUM(pc.amount), 0) as fellowship_amount,
            COUNT(DISTINCT pc.member_id) as unique_contributors,
            ROUND((COALESCE(SUM(pc.amount), 0) * 100.0 / NULLIF((
                SELECT COALESCE(SUM(amount), 1) 
                FROM partnership_contributions 
                WHERE partnership_arm_id = pa.id 
                AND contribution_date BETWEEN ? AND ?
            ), 0)), 2) as percentage_of_arm
        FROM partnership_contributions pc
        JOIN partnership_arms pa ON pc.partnership_arm_id = pa.id
        LEFT JOIN members m ON pc.member_id = m.id
        LEFT JOIN fellowships f ON m.fellowship_id = f.id
        WHERE $whereClause4
        GROUP BY pc.partnership_arm_id, m.fellowship_id
        ORDER BY pa.name, fellowship_amount DESC
    ");
    $stmt->execute(array_merge($subqueryParams4, $params4));
    $contributionsByFellowship = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate rankings
    $armRankings = [];
    foreach ($partnershipArms as $arm) {
        $totalAmount = $arm['period_amount'];
        $contributionCount = $arm['contribution_count'];
        $uniqueContributors = $arm['unique_contributors'];
        
        // Calculate ranking score (weighted: 60% total amount, 30% contributor count, 10% contribution count)
        $rankingScore = ($totalAmount * 0.6) + ($uniqueContributors * 100 * 0.3) + ($contributionCount * 10 * 0.1);
        
        $armRankings[] = [
            'partnership_arm_id' => $arm['id'],
            'partnership_arm' => $arm['partnership_arm'],
            'total_amount' => $totalAmount,
            'contribution_count' => $contributionCount,
            'unique_contributors' => $uniqueContributors,
            'average_contribution' => $contributionCount > 0 ? round($totalAmount / $contributionCount, 2) : 0,
            'ranking_score' => $rankingScore
        ];
    }
    
    // Sort by ranking score
    usort($armRankings, function($a, $b) {
        return $b['ranking_score'] <=> $a['ranking_score'];
    });
    
    $rank = 1;
    foreach ($armRankings as &$ranking) {
        $ranking['rank'] = $rank++;
    }
    
    // Get overall statistics
    $totalPartnershipContributions = array_sum(array_column($armRankings, 'total_amount'));
    $totalUniqueContributors = array_sum(array_column($armRankings, 'unique_contributors'));
    
    echo json_encode([
        'success' => true,
        'partnership_arms' => $partnershipArms,
        'contributions_by_period' => $contributionsByPeriod,
        'contributions_by_fellowship' => $contributionsByFellowship,
        'arm_rankings' => $armRankings,
        'summary' => [
            'total_partnership_arms' => count($partnershipArms),
            'total_contributions_amount' => $totalPartnershipContributions,
            'total_contribution_count' => array_sum(array_column($armRankings, 'contribution_count')),
            'total_unique_contributors' => $totalUniqueContributors,
            'avg_contribution_per_arm' => count($partnershipArms) > 0 ? 
                round($totalPartnershipContributions / count($partnershipArms), 2) : 0,
            'top_partnership_arm' => count($armRankings) > 0 ? $armRankings[0]['partnership_arm'] : 'N/A',
            'most_supported_arm' => count($partnershipArms) > 0 ? 
                array_reduce($partnershipArms, function($carry, $item) {
                    if (!$carry || $item['unique_contributors'] > $carry['unique_contributors']) {
                        return $item;
                    }
                    return $carry;
                })['partnership_arm'] : 'N/A'
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