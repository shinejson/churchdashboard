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
    $filters = getFilterConditions(['table_alias' => 'a.']);
    
    // Get service summary
    $whereConditions = ['s.is_active = 1'];
    $params = [];
    
    if ($filters['service_id'] !== null) {
        $whereConditions[] = 's.id = ?';
        $params[] = $filters['service_id'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Build attendance filter conditions
    $attendanceFilters = ['a.service_date BETWEEN ? AND ?'];
    $attendanceParams = [$dateRange['start'], $dateRange['end']];
    
    if ($filters['fellowship_id'] !== null) {
        $attendanceFilters[] = 'a.fellowship_id = ?';
        $attendanceParams[] = $filters['fellowship_id'];
    }
    
    if ($filters['service_id'] !== null) {
        $attendanceFilters[] = 'a.service_id = ?';
        $attendanceParams[] = $filters['service_id'];
    }
    
    $attendanceWhereClause = implode(' AND ', $attendanceFilters);
    
    $stmt = $pdo->prepare("
        SELECT 
            s.id as service_id,
            s.name as service_name,
            COUNT(DISTINCT a.service_date) as total_services,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_attendance,
            COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.member_id END) as unique_attendees,
            COUNT(DISTINCT a.fellowship_id) as participating_fellowships,
            MIN(a.service_date) as first_service_date,
            MAX(a.service_date) as last_service_date,
            ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 2) as avg_attendance_rate
        FROM services s
        LEFT JOIN attendance a ON s.id = a.service_id 
            AND $attendanceWhereClause
        WHERE $whereClause
        GROUP BY s.id
        ORDER BY s.name
    ");
    $stmt->execute(array_merge($params, $attendanceParams));
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get service-by-service details
    $serviceDetails = [];
    foreach ($services as &$service) {
        // Get attendance by category for this service
        $whereConditions2 = ['a.service_id = ?', 'a.service_date BETWEEN ? AND ?'];
        $params2 = [$service['service_id'], $dateRange['start'], $dateRange['end']];
        
        if ($filters['fellowship_id'] !== null) {
            $whereConditions2[] = 'a.fellowship_id = ?';
            $params2[] = $filters['fellowship_id'];
        }
        
        $whereClause2 = implode(' AND ', $whereConditions2);
        
        $stmt = $pdo->prepare("
            SELECT 
                m.category,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as attendance_count,
                COUNT(DISTINCT a.member_id) as unique_members
            FROM attendance a
            JOIN members m ON a.member_id = m.id
            WHERE $whereClause2
            GROUP BY m.category
            ORDER BY m.category
        ");
        $stmt->execute($params2);
        $attendanceByCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get attendance by age category
        $stmt = $pdo->prepare("
            SELECT 
                m.age_category,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as attendance_count,
                COUNT(DISTINCT a.member_id) as unique_members
            FROM attendance a
            JOIN members m ON a.member_id = m.id
            WHERE $whereClause2
            GROUP BY m.age_category
            ORDER BY m.age_category
        ");
        $stmt->execute($params2);
        $attendanceByAge = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get first timers (members with first attendance in this service)
        $stmt = $pdo->prepare("
            SELECT 
                m.id as member_id,
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                m.category,
                m.age_category,
                f.name as fellowship_name,
                a.service_date as first_service_date
            FROM attendance a
            JOIN members m ON a.member_id = m.id
            LEFT JOIN fellowships f ON m.fellowship_id = f.id
            WHERE $whereClause2
            AND a.status = 'present'
            AND a.service_date = (
                SELECT MIN(a2.service_date)
                FROM attendance a2
                WHERE a2.member_id = m.id
                AND a2.status = 'present'
            )
            ORDER BY a.service_date DESC
        ");
        $stmt->execute($params2);
        $firstTimers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get financial contributions for this service
        $whereConditions3 = ['service_id = ?', "type = 'income'", 'transaction_date BETWEEN ? AND ?'];
        $params3 = [$service['service_id'], $dateRange['start'], $dateRange['end']];
        
        if ($filters['fellowship_id'] !== null) {
            $whereConditions3[] = 'fellowship_id = ?';
            $params3[] = $filters['fellowship_id'];
        }
        
        $whereClause3 = implode(' AND ', $whereConditions3);
        
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(amount), 0) as total_offerings,
                COUNT(*) as offering_count,
                AVG(amount) as average_offering,
                COUNT(DISTINCT member_id) as unique_contributors,
                GROUP_CONCAT(DISTINCT payment_method ORDER BY payment_method SEPARATOR ', ') as payment_methods_used
            FROM financial_transactions 
            WHERE $whereClause3
        ");
        $stmt->execute($params3);
        $financialSummary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get partnership arm contributions for this service
        $whereConditions4 = ['ft.service_id = ?', 'ft.transaction_date BETWEEN ? AND ?'];
        $params4 = [$service['service_id'], $dateRange['start'], $dateRange['end']];
        
        if ($filters['fellowship_id'] !== null) {
            $whereConditions4[] = 'ft.fellowship_id = ?';
            $params4[] = $filters['fellowship_id'];
        }
        
        $whereClause4 = implode(' AND ', $whereConditions4);
        
        $stmt = $pdo->prepare("
            SELECT 
                pa.name as partnership_arm,
                COUNT(pc.id) as contribution_count,
                COALESCE(SUM(pc.amount), 0) as total_amount,
                COUNT(DISTINCT pc.member_id) as unique_contributors,
                AVG(pc.amount) as average_contribution
            FROM partnership_contributions pc
            JOIN partnership_arms pa ON pc.partnership_arm_id = pa.id
            JOIN financial_transactions ft ON pc.financial_transaction_id = ft.id
            WHERE $whereClause4
            GROUP BY pc.partnership_arm_id
            ORDER BY total_amount DESC
        ");
        $stmt->execute($params4);
        $partnershipContributions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get detailed service days
        $whereConditions5 = ['a.service_id = ?', 'a.service_date BETWEEN ? AND ?'];
        $params5 = [$service['service_id'], $dateRange['start'], $dateRange['end']];
        
        if ($filters['fellowship_id'] !== null) {
            $whereConditions5[] = 'a.fellowship_id = ?';
            $params5[] = $filters['fellowship_id'];
        }
        
        $whereClause5 = implode(' AND ', $whereConditions5);
        
        // Build subquery filters for financial data
        $ftFilters = ['service_id = ?', 'transaction_date = a.service_date', "type = 'income'"];
        $ftParams = [$service['service_id']];
        
        if ($filters['fellowship_id'] !== null) {
            $ftFilters[] = 'fellowship_id = ?';
        }
        
        $ftWhereClause = implode(' AND ', $ftFilters);
        
        $stmt = $pdo->prepare("
            SELECT 
                a.service_date,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as attendance_count,
                COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused_count,
                COUNT(DISTINCT a.fellowship_id) as fellowship_count,
                COUNT(DISTINCT a.member_id) as unique_attendees,
                ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate,
                (SELECT COALESCE(SUM(amount), 0) FROM financial_transactions WHERE $ftWhereClause) as daily_offerings,
                (SELECT COUNT(DISTINCT member_id) FROM financial_transactions WHERE $ftWhereClause) as daily_contributors
            FROM attendance a
            WHERE $whereClause5
            GROUP BY a.service_date
            ORDER BY a.service_date DESC
        ");
        
        // Build execution params: service_id for subqueries (2x), then main query params, then fellowship_id for subqueries if needed (2x)
        $execParams = array_merge(
            $ftParams, // First subquery service_id
            $ftParams, // Second subquery service_id
            $params5   // Main query params
        );
        
        if ($filters['fellowship_id'] !== null) {
            // Add fellowship_id for both subqueries
            $execParams[] = $filters['fellowship_id'];
            $execParams[] = $filters['fellowship_id'];
        }
        
        $stmt->execute($execParams);
        $serviceDays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get top attendees for this service
        $stmt = $pdo->prepare("
            SELECT 
                m.id as member_id,
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                m.category,
                f.name as fellowship_name,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as attendance_count,
                MIN(a.service_date) as first_attendance,
                MAX(a.service_date) as last_attendance
            FROM attendance a
            JOIN members m ON a.member_id = m.id
            LEFT JOIN fellowships f ON m.fellowship_id = f.id
            WHERE $whereClause5
            GROUP BY a.member_id
            HAVING attendance_count > 0
            ORDER BY attendance_count DESC
            LIMIT 10
        ");
        $stmt->execute($params5);
        $topAttendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get top contributors for this service
        $stmt = $pdo->prepare("
            SELECT 
                m.id as member_id,
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                f.name as fellowship_name,
                COUNT(ft.id) as contribution_count,
                COALESCE(SUM(ft.amount), 0) as total_contributed,
                AVG(ft.amount) as average_contribution
            FROM financial_transactions ft
            JOIN members m ON ft.member_id = m.id
            LEFT JOIN fellowships f ON m.fellowship_id = f.id
            WHERE $whereClause3
            GROUP BY ft.member_id
            HAVING total_contributed > 0
            ORDER BY total_contributed DESC
            LIMIT 10
        ");
        $stmt->execute($params3);
        $topContributors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $serviceDetails[] = [
            'service_info' => $service,
            'attendance_by_category' => $attendanceByCategory,
            'attendance_by_age' => $attendanceByAge,
            'first_timers' => $firstTimers,
            'financial_summary' => $financialSummary ?: [
                'total_offerings' => 0,
                'offering_count' => 0,
                'average_offering' => 0,
                'unique_contributors' => 0,
                'payment_methods_used' => ''
            ],
            'partnership_contributions' => $partnershipContributions,
            'service_days' => $serviceDays,
            'top_attendees' => $topAttendees,
            'top_contributors' => $topContributors
        ];
    }
    
    // Calculate service rankings
    $serviceRankings = [];
    foreach ($serviceDetails as $service) {
        $info = $service['service_info'];
        $financial = $service['financial_summary'];
        
        // Calculate ranking score (weighted: 40% attendance, 40% offerings, 20% unique participants)
        $attendanceScore = $info['total_attendance'] * 0.4;
        $offeringScore = $financial['total_offerings'] * 0.4;
        $participationScore = ($info['unique_attendees'] + $financial['unique_contributors']) * 0.2;
        
        $rankingScore = $attendanceScore + $offeringScore + $participationScore;
        
        $serviceRankings[] = [
            'service_id' => $info['service_id'],
            'service_name' => $info['service_name'],
            'total_attendance' => $info['total_attendance'],
            'total_offerings' => $financial['total_offerings'],
            'unique_attendees' => $info['unique_attendees'],
            'unique_contributors' => $financial['unique_contributors'],
            'attendance_rate' => $info['avg_attendance_rate'],
            'ranking_score' => $rankingScore
        ];
    }
    
    // Sort by ranking score
    usort($serviceRankings, function($a, $b) {
        return $b['ranking_score'] <=> $a['ranking_score'];
    });
    
    $rank = 1;
    foreach ($serviceRankings as &$ranking) {
        $ranking['rank'] = $rank++;
    }
    
    // Get overall summary
    $overallSummary = [
        'total_services' => count($services),
        'total_attendance' => array_sum(array_column($services, 'total_attendance')),
        'total_offerings' => array_sum(array_map(function($service) {
            return $service['financial_summary']['total_offerings'];
        }, $serviceDetails)),
        'total_first_timers' => array_sum(array_map(function($service) {
            return count($service['first_timers']);
        }, $serviceDetails)),
        'avg_attendance_per_service' => count($services) > 0 ? 
            round(array_sum(array_column($services, 'total_attendance')) / count($services), 2) : 0,
        'avg_offerings_per_service' => count($services) > 0 ? 
            round(array_sum(array_map(function($service) {
                return $service['financial_summary']['total_offerings'];
            }, $serviceDetails)) / count($services), 2) : 0,
        'most_attended_service' => count($serviceRankings) > 0 ? $serviceRankings[0]['service_name'] : 'N/A',
        'highest_offering_service' => count($serviceDetails) > 0 ? 
            array_reduce($serviceDetails, function($carry, $item) {
                if (!$carry || $item['financial_summary']['total_offerings'] > $carry['financial_summary']['total_offerings']) {
                    return $item;
                }
                return $carry;
            })['service_info']['service_name'] : 'N/A'
    ];
    
    echo json_encode([
        'success' => true,
        'service_details' => $serviceDetails,
        'service_rankings' => $serviceRankings,
        'overall_summary' => $overallSummary,
        'summary' => [
            'active_services' => count($services),
            'total_service_days' => array_sum(array_column($services, 'total_services')),
            'unique_attendees_across_services' => array_sum(array_column($services, 'unique_attendees')),
            'top_performing_service' => count($serviceRankings) > 0 ? $serviceRankings[0]['service_name'] : 'N/A',
            'best_attendance_rate' => count($services) > 0 ? max(array_column($services, 'avg_attendance_rate')) : 0
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