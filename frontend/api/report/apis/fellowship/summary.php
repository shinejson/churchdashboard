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
    $filters = getFilterConditions();
    
    // Get fellowship summary
    $whereConditions = ["f.status = 'active'"];
    $params = [];
    
    if ($filters['fellowship_id'] !== null) {
        $whereConditions[] = 'f.id = ?';
        $params[] = $filters['fellowship_id'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            f.*,
            CONCAT(ml.first_name, ' ', ml.last_name) as leader_name,
            CONCAT(mal.first_name, ' ', mal.last_name) as assistant_leader_name,
            (SELECT COUNT(*) FROM members WHERE fellowship_id = f.id) as total_members,
            (SELECT COUNT(*) FROM members WHERE fellowship_id = f.id AND status = 'active') as active_members,
            (SELECT COUNT(*) FROM members WHERE fellowship_id = f.id AND status = 'visitor') as visitor_members,
            (SELECT COUNT(*) FROM members WHERE fellowship_id = f.id AND category = 'adult') as adult_members,
            (SELECT COUNT(*) FROM members WHERE fellowship_id = f.id AND category = 'children') as children_members,
            (SELECT COUNT(*) FROM members WHERE fellowship_id = f.id AND category = 'teen') as teen_members
        FROM fellowships f
        LEFT JOIN members ml ON f.leader_id = ml.id
        LEFT JOIN members mal ON f.assistant_leader_id = mal.id
        WHERE $whereClause
        ORDER BY f.name
    ");
    $stmt->execute($params);
    $fellowships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attendance statistics for each fellowship
    foreach ($fellowships as &$fellowship) {
        $whereConditions2 = ['a.fellowship_id = ?', 'a.service_date BETWEEN ? AND ?'];
        $params2 = [$fellowship['id'], $dateRange['start'], $dateRange['end']];
        
        if ($filters['service_id'] !== null) {
            $whereConditions2[] = 'a.service_id = ?';
            $params2[] = $filters['service_id'];
        }
        
        $whereClause2 = implode(' AND ', $whereConditions2);
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_attendance,
                COUNT(DISTINCT a.service_date) as total_services,
                COUNT(DISTINCT a.member_id) as unique_attendees,
                ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate,
                MIN(a.service_date) as first_service_date,
                MAX(a.service_date) as last_service_date
            FROM attendance a
            WHERE $whereClause2
        ");
        $stmt->execute($params2);
        $attendanceStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $fellowship['attendance_stats'] = $attendanceStats ?: [
            'total_attendance' => 0,
            'total_services' => 0,
            'unique_attendees' => 0,
            'attendance_rate' => 0,
            'first_service_date' => null,
            'last_service_date' => null
        ];
        
        // Get financial statistics for each fellowship
        $whereConditions3 = ['fellowship_id = ?', "type = 'income'", 'transaction_date BETWEEN ? AND ?'];
        $params3 = [$fellowship['id'], $dateRange['start'], $dateRange['end']];
        
        if ($filters['service_id'] !== null) {
            $whereConditions3[] = 'service_id = ?';
            $params3[] = $filters['service_id'];
        }
        
        $whereClause3 = implode(' AND ', $whereConditions3);
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as transaction_count,
                COALESCE(SUM(amount), 0) as total_income,
                AVG(amount) as average_contribution,
                COUNT(DISTINCT member_id) as unique_contributors
            FROM financial_transactions 
            WHERE $whereClause3
        ");
        $stmt->execute($params3);
        $financialStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $fellowship['financial_stats'] = $financialStats ?: [
            'transaction_count' => 0,
            'total_income' => 0,
            'average_contribution' => 0,
            'unique_contributors' => 0
        ];
        
        // Get tithe statistics for each fellowship
        $whereConditions4 = ['fellowship_id = ?', 'transaction_date BETWEEN ? AND ?'];
        $params4 = [$fellowship['id'], $dateRange['start'], $dateRange['end']];
        
        if ($filters['service_id'] !== null) {
            $whereConditions4[] = 'service_id = ?';
            $params4[] = $filters['service_id'];
        }
        
        $whereClause4 = implode(' AND ', $whereConditions4);
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as tithe_count,
                COALESCE(SUM(amount), 0) as total_tithes,
                AVG(amount) as average_tithe,
                COUNT(DISTINCT member_id) as unique_tithers
            FROM tithe_transactions 
            WHERE $whereClause4
        ");
        $stmt->execute($params4);
        $titheStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $fellowship['tithe_stats'] = $titheStats ?: [
            'tithe_count' => 0,
            'total_tithes' => 0,
            'average_tithe' => 0,
            'unique_tithers' => 0
        ];
        
        // Calculate member engagement rate
        $totalMembers = $fellowship['total_members'] ?: 1;
        $activeMembers = $fellowship['active_members'] ?: 0;
        $uniqueAttendees = $fellowship['attendance_stats']['unique_attendees'] ?: 0;
        $uniqueContributors = $fellowship['financial_stats']['unique_contributors'] ?: 0;
        
        $fellowship['engagement_metrics'] = [
            'member_attendance_rate' => round(($uniqueAttendees / $totalMembers) * 100, 2),
            'member_contribution_rate' => round(($uniqueContributors / $totalMembers) * 100, 2),
            'active_member_percentage' => round(($activeMembers / $totalMembers) * 100, 2),
            'overall_engagement_score' => round((
                ($uniqueAttendees / $totalMembers) * 0.4 +
                ($uniqueContributors / $totalMembers) * 0.3 +
                ($activeMembers / $totalMembers) * 0.3
            ) * 100, 2)
        ];
    }
    
    // Calculate rankings
    usort($fellowships, function($a, $b) {
        return $b['engagement_metrics']['overall_engagement_score'] <=> 
               $a['engagement_metrics']['overall_engagement_score'];
    });
    
    $rank = 1;
    foreach ($fellowships as &$fellowship) {
        $fellowship['rank'] = $rank++;
    }
    
    // Sort back by name for display
    usort($fellowships, function($a, $b) {
        return $a['name'] <=> $b['name'];
    });
    
    // Get fellowship comparison statistics
    $comparisonStats = [
        'total_fellowships' => count($fellowships),
        'total_members' => array_sum(array_column($fellowships, 'total_members')),
        'total_active_members' => array_sum(array_column($fellowships, 'active_members')),
        'total_attendance' => array_sum(array_column($fellowships, 'attendance_stats.total_attendance')),
        'total_income' => array_sum(array_column($fellowships, 'financial_stats.total_income')),
        'total_tithes' => array_sum(array_column($fellowships, 'tithe_stats.total_tithes')),
        'avg_attendance_rate' => count($fellowships) > 0 ? 
            round(array_sum(array_column($fellowships, 'attendance_stats.attendance_rate')) / count($fellowships), 2) : 0,
        'avg_engagement_score' => count($fellowships) > 0 ? 
            round(array_sum(array_column($fellowships, 'engagement_metrics.overall_engagement_score')) / count($fellowships), 2) : 0,
        'most_engaged_fellowship' => count($fellowships) > 0 ? 
            max(array_column($fellowships, 'engagement_metrics.overall_engagement_score')) : 0,
        'highest_attendance_rate' => count($fellowships) > 0 ? 
            max(array_column($fellowships, 'attendance_stats.attendance_rate')) : 0
    ];
    
    echo json_encode([
        'success' => true,
        'fellowships' => $fellowships,
        'comparison_stats' => $comparisonStats,
        'summary' => [
            'active_fellowships' => count($fellowships),
            'total_members_across_fellowships' => $comparisonStats['total_members'],
            'average_members_per_fellowship' => count($fellowships) > 0 ? 
                round($comparisonStats['total_members'] / count($fellowships), 2) : 0,
            'top_performing_fellowship' => count($fellowships) > 0 ? 
                array_reduce($fellowships, function($carry, $item) {
                    if (!$carry || $item['engagement_metrics']['overall_engagement_score'] > $carry['engagement_metrics']['overall_engagement_score']) {
                        return $item;
                    }
                    return $carry;
                })['name'] : 'N/A'
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