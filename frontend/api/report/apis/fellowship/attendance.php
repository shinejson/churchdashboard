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
    
    // Get fellowship attendance trends
    $whereConditions = ['a.service_date BETWEEN ? AND ?', "f.status = 'active'"];
    $params = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions = array_merge($whereConditions, $filters['conditions']);
        $params = array_merge($params, $filters['params']);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            f.id as fellowship_id,
            f.name as fellowship_name,
            DATE(a.service_date) as service_date,
            s.name as service_name,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
            COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused_count,
            COUNT(*) as total_members,
            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate,
            COUNT(DISTINCT a.member_id) as unique_attendees
        FROM attendance a
        JOIN fellowships f ON a.fellowship_id = f.id
        LEFT JOIN services s ON a.service_id = s.id
        WHERE $whereClause
        GROUP BY f.id, DATE(a.service_date), a.service_id
        ORDER BY f.name, a.service_date DESC
    ");
    $stmt->execute($params);
    $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by fellowship for easier processing
    $fellowshipAttendance = [];
    foreach ($attendanceRecords as $record) {
        $fellowshipId = $record['fellowship_id'];
        
        if (!isset($fellowshipAttendance[$fellowshipId])) {
            $fellowshipAttendance[$fellowshipId] = [
                'fellowship_id' => $fellowshipId,
                'fellowship_name' => $record['fellowship_name'],
                'attendance_days' => [],
                'summary' => [
                    'total_days' => 0,
                    'total_present' => 0,
                    'total_absent' => 0,
                    'total_excused' => 0,
                    'overall_attendance_rate' => 0,
                    'unique_attendees' => []
                ]
            ];
        }
        
        $fellowshipAttendance[$fellowshipId]['attendance_days'][] = $record;
        
        // Update summary
        $fellowshipAttendance[$fellowshipId]['summary']['total_days']++;
        $fellowshipAttendance[$fellowshipId]['summary']['total_present'] += $record['present_count'];
        $fellowshipAttendance[$fellowshipId]['summary']['total_absent'] += $record['absent_count'];
        $fellowshipAttendance[$fellowshipId]['summary']['total_excused'] += $record['excused_count'];
        
        // Add unique attendees
        $fellowshipAttendance[$fellowshipId]['summary']['unique_attendees'] = array_unique(
            array_merge(
                $fellowshipAttendance[$fellowshipId]['summary']['unique_attendees'],
                [$record['unique_attendees']]
            )
        );
    }
    
    // Calculate overall attendance rate for each fellowship
    foreach ($fellowshipAttendance as &$fellowship) {
        $totalMembers = $fellowship['summary']['total_present'] + 
                       $fellowship['summary']['total_absent'] + 
                       $fellowship['summary']['total_excused'];
        
        $fellowship['summary']['overall_attendance_rate'] = $totalMembers > 0 ? 
            round(($fellowship['summary']['total_present'] / $totalMembers) * 100, 2) : 0;
        
        $fellowship['summary']['unique_attendees_count'] = array_sum($fellowship['summary']['unique_attendees']);
    }
    
    // Get member attendance by fellowship
    $whereConditions2 = ['a.service_date BETWEEN ? AND ?', "f.status = 'active'"];
    $params2 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions2 = array_merge($whereConditions2, $filters['conditions']);
        $params2 = array_merge($params2, $filters['params']);
    }
    
    $whereClause2 = implode(' AND ', $whereConditions2);
    
    $stmt = $pdo->prepare("
        SELECT 
            f.id as fellowship_id,
            f.name as fellowship_name,
            m.id as member_id,
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            m.category,
            m.age_category,
            m.status,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
            COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused_count,
            COUNT(*) as total_services,
            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as member_attendance_rate,
            MIN(a.service_date) as first_attendance,
            MAX(a.service_date) as last_attendance
        FROM attendance a
        JOIN fellowships f ON a.fellowship_id = f.id
        JOIN members m ON a.member_id = m.id
        WHERE $whereClause2
        GROUP BY f.id, m.id
        ORDER BY f.name, member_attendance_rate DESC, present_count DESC
    ");
    $stmt->execute($params2);
    $memberAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group member attendance by fellowship
    $membersByFellowship = [];
    foreach ($memberAttendance as $member) {
        $fellowshipId = $member['fellowship_id'];
        
        if (!isset($membersByFellowship[$fellowshipId])) {
            $membersByFellowship[$fellowshipId] = [
                'fellowship_id' => $fellowshipId,
                'fellowship_name' => $member['fellowship_name'],
                'members' => []
            ];
        }
        
        $membersByFellowship[$fellowshipId]['members'][] = $member;
    }
    
    // Get attendance trends by week/month
    $periodGroup = getPeriodFilter();
    $whereConditions3 = ['a.service_date BETWEEN ? AND ?', "f.status = 'active'"];
    $params3 = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions3 = array_merge($whereConditions3, $filters['conditions']);
        $params3 = array_merge($params3, $filters['params']);
    }
    
    $whereClause3 = implode(' AND ', $whereConditions3);
    
    $stmt = $pdo->prepare("
        SELECT 
            f.id as fellowship_id,
            f.name as fellowship_name,
            $periodGroup as period,
            COUNT(DISTINCT DATE(a.service_date)) as service_days,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate,
            COUNT(DISTINCT a.member_id) as unique_attendees
        FROM attendance a
        JOIN fellowships f ON a.fellowship_id = f.id
        WHERE $whereClause3
        GROUP BY f.id, $periodGroup
        ORDER BY f.name, period
    ");
    $stmt->execute($params3);
    $attendanceTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group trends by fellowship
    $trendsByFellowship = [];
    foreach ($attendanceTrends as $trend) {
        $fellowshipId = $trend['fellowship_id'];
        
        if (!isset($trendsByFellowship[$fellowshipId])) {
            $trendsByFellowship[$fellowshipId] = [
                'fellowship_id' => $fellowshipId,
                'fellowship_name' => $trend['fellowship_name'],
                'trends' => []
            ];
        }
        
        $trendsByFellowship[$fellowshipId]['trends'][] = $trend;
    }
    
    // Calculate fellowship rankings
    $fellowshipRankings = [];
    foreach ($fellowshipAttendance as $fellowshipId => $fellowship) {
        $fellowshipRankings[] = [
            'fellowship_id' => $fellowshipId,
            'fellowship_name' => $fellowship['fellowship_name'],
            'overall_attendance_rate' => $fellowship['summary']['overall_attendance_rate'],
            'unique_attendees_count' => $fellowship['summary']['unique_attendees_count'],
            'total_present' => $fellowship['summary']['total_present'],
            'total_days' => $fellowship['summary']['total_days']
        ];
    }
    
    // Sort by attendance rate
    usort($fellowshipRankings, function($a, $b) {
        return $b['overall_attendance_rate'] <=> $a['overall_attendance_rate'];
    });
    
    $rank = 1;
    foreach ($fellowshipRankings as &$ranking) {
        $ranking['rank'] = $rank++;
    }
    
    echo json_encode([
        'success' => true,
        'fellowship_attendance' => array_values($fellowshipAttendance),
        'members_by_fellowship' => array_values($membersByFellowship),
        'attendance_trends' => array_values($trendsByFellowship),
        'fellowship_rankings' => $fellowshipRankings,
        'summary' => [
            'total_fellowships' => count($fellowshipAttendance),
            'total_attendance_days' => array_sum(array_column(array_column($fellowshipAttendance, 'summary'), 'total_days')),
            'total_present_count' => array_sum(array_column(array_column($fellowshipAttendance, 'summary'), 'total_present')),
            'avg_attendance_rate' => count($fellowshipRankings) > 0 ? 
                round(array_sum(array_column($fellowshipRankings, 'overall_attendance_rate')) / count($fellowshipRankings), 2) : 0,
            'top_fellowship' => count($fellowshipRankings) > 0 ? $fellowshipRankings[0]['fellowship_name'] : 'N/A'
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