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
    
    // Get specific service details if service_id is provided
    $serviceId = $_GET['service_id'] ?? null;
    $serviceDate = $_GET['service_date'] ?? null;
    
    if ($serviceId && $serviceDate) {
        // Get specific service day details
        $whereConditions = ['a.service_id = ?', 'a.service_date = ?'];
        $params = [$serviceId, $serviceDate];
        
        if ($filters['fellowship_id'] !== null) {
            $whereConditions[] = 'a.fellowship_id = ?';
            $params[] = $filters['fellowship_id'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $pdo->prepare("
            SELECT 
                s.name as service_name,
                DATE(a.service_date) as service_date,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_attendance,
                COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused_count,
                COUNT(DISTINCT a.fellowship_id) as fellowship_count,
                ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate
            FROM attendance a
            JOIN services s ON a.service_id = s.id
            WHERE $whereClause
            GROUP BY a.service_id, a.service_date
        ");
        $stmt->execute($params);
        $serviceDay = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$serviceDay) {
            echo json_encode([
                'success' => false, 
                'message' => 'Service not found for the specified date'
            ]);
            exit();
        }
        
        // Get attendance by category with member names
        $stmt = $pdo->prepare("
            SELECT 
                m.category,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as attendance_count,
                GROUP_CONCAT(DISTINCT CONCAT(m.first_name, ' ', m.last_name) 
                    ORDER BY m.first_name, m.last_name SEPARATOR ', ') as member_names
            FROM attendance a
            JOIN members m ON a.member_id = m.id
            WHERE $whereClause
            GROUP BY m.category
            ORDER BY m.category
        ");
        $stmt->execute($params);
        $attendanceByCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get attendance by age category with member names
        $stmt = $pdo->prepare("
            SELECT 
                m.age_category,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as attendance_count,
                GROUP_CONCAT(DISTINCT CONCAT(m.first_name, ' ', m.last_name) 
                    ORDER BY m.first_name, m.last_name SEPARATOR ', ') as member_names
            FROM attendance a
            JOIN members m ON a.member_id = m.id
            WHERE $whereClause
            GROUP BY m.age_category
            ORDER BY m.age_category
        ");
        $stmt->execute($params);
        $attendanceByAge = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get first timers (members with first attendance)
        $stmt = $pdo->prepare("
            SELECT 
                m.id as member_id,
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                m.category,
                m.age_category,
                f.name as fellowship_name,
                m.phone,
                m.email
            FROM attendance a
            JOIN members m ON a.member_id = m.id
            LEFT JOIN fellowships f ON m.fellowship_id = f.id
            WHERE $whereClause
            AND a.status = 'present'
            AND a.service_date = (
                SELECT MIN(a2.service_date)
                FROM attendance a2
                WHERE a2.member_id = m.id
                AND a2.status = 'present'
            )
            ORDER BY m.first_name, m.last_name
        ");
        $stmt->execute($params);
        $firstTimers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all attendees with details
        $stmt = $pdo->prepare("
            SELECT 
                m.id as member_id,
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                m.category,
                m.age_category,
                m.status,
                f.name as fellowship_name,
                a.status as attendance_status,
                a.notes
            FROM attendance a
            JOIN members m ON a.member_id = m.id
            LEFT JOIN fellowships f ON m.fellowship_id = f.id
            WHERE $whereClause
            ORDER BY m.category, m.first_name, m.last_name
        ");
        $stmt->execute($params);
        $allAttendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get financial offerings with member names
        $whereConditions2 = ['ft.service_id = ?', 'ft.transaction_date = ?', "ft.type = 'income'"];
        $params2 = [$serviceId, $serviceDate];
        
        if ($filters['fellowship_id'] !== null) {
            $whereConditions2[] = 'ft.fellowship_id = ?';
            $params2[] = $filters['fellowship_id'];
        }
        
        $whereClause2 = implode(' AND ', $whereConditions2);
        
        $stmt = $pdo->prepare("
            SELECT 
                ft.id,
                ft.amount,
                ft.payment_method,
                ft.reference,
                ft.description,
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                m.category,
                f.name as fellowship_name
            FROM financial_transactions ft
            LEFT JOIN members m ON ft.member_id = m.id
            LEFT JOIN fellowships f ON m.fellowship_id = f.id
            WHERE $whereClause2
            ORDER BY ft.amount DESC
        ");
        $stmt->execute($params2);
        $offerings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get partnership arm contributions with member names
        $whereConditions3 = ['ft.service_id = ?', 'ft.transaction_date = ?'];
        $params3 = [$serviceId, $serviceDate];
        
        if ($filters['fellowship_id'] !== null) {
            $whereConditions3[] = 'ft.fellowship_id = ?';
            $params3[] = $filters['fellowship_id'];
        }
        
        $whereClause3 = implode(' AND ', $whereConditions3);
        
        $stmt = $pdo->prepare("
            SELECT 
                pa.name as partnership_arm,
                pc.amount,
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                f.name as fellowship_name,
                ft.payment_method
            FROM partnership_contributions pc
            JOIN partnership_arms pa ON pc.partnership_arm_id = pa.id
            JOIN financial_transactions ft ON pc.financial_transaction_id = ft.id
            LEFT JOIN members m ON pc.member_id = m.id
            LEFT JOIN fellowships f ON m.fellowship_id = f.id
            WHERE $whereClause3
            ORDER BY pa.name, pc.amount DESC
        ");
        $stmt->execute($params3);
        $partnershipContributions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate summary
        $totalOfferings = array_sum(array_column($offerings, 'amount'));
        $totalPartnership = array_sum(array_column($partnershipContributions, 'amount'));
        
        $serviceDay['total_offerings'] = $totalOfferings;
        $serviceDay['total_partnership'] = $totalPartnership;
        $serviceDay['total_income'] = $totalOfferings + $totalPartnership;
        
        echo json_encode([
            'success' => true,
            'service_day' => $serviceDay,
            'attendance_by_category' => $attendanceByCategory,
            'attendance_by_age' => $attendanceByAge,
            'first_timers' => $firstTimers,
            'all_attendees' => $allAttendees,
            'offerings' => $offerings,
            'partnership_contributions' => $partnershipContributions,
            'summary' => [
                'total_attendance' => $serviceDay['total_attendance'],
                'total_offerings' => $totalOfferings,
                'total_partnership' => $totalPartnership,
                'first_timers_count' => count($firstTimers),
                'offerings_count' => count($offerings),
                'partnership_contributions_count' => count($partnershipContributions),
                'fellowships_present' => $serviceDay['fellowship_count']
            ]
        ]);
        
    } else {
        // Get all service days in date range
        $whereConditions = ['a.service_date BETWEEN ? AND ?'];
        $params = [$dateRange['start'], $dateRange['end']];
        
        if (!empty($filters['conditions'])) {
            $whereConditions = array_merge($whereConditions, $filters['conditions']);
            $params = array_merge($params, $filters['params']);
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Build subquery filters for financial data
        $ftFilters = ['service_id = a.service_id', 'transaction_date = a.service_date', "type = 'income'"];
        if ($filters['fellowship_id'] !== null) {
            $ftFilters[] = 'fellowship_id = ?';
        }
        $ftWhereClause = implode(' AND ', $ftFilters);
        
        $stmt = $pdo->prepare("
            SELECT 
                s.name as service_name,
                a.service_id,
                DATE(a.service_date) as service_date,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_attendance,
                COUNT(DISTINCT a.fellowship_id) as fellowship_count,
                (SELECT COALESCE(SUM(amount), 0) FROM financial_transactions WHERE $ftWhereClause) as total_offerings,
                (SELECT COUNT(DISTINCT member_id) FROM financial_transactions WHERE $ftWhereClause) as contributor_count,
                ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate
            FROM attendance a
            JOIN services s ON a.service_id = s.id
            WHERE $whereClause
            GROUP BY a.service_id, DATE(a.service_date)
            ORDER BY a.service_date DESC, s.name
            LIMIT 100
        ");
        
        // Build execution params
        $execParams = $params;
        if ($filters['fellowship_id'] !== null) {
            // Add fellowship_id for both subqueries
            $execParams[] = $filters['fellowship_id'];
            $execParams[] = $filters['fellowship_id'];
        }
        
        $stmt->execute($execParams);
        $serviceDays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'service_days' => $serviceDays,
            'summary' => [
                'total_service_days' => count($serviceDays),
                'total_attendance' => array_sum(array_column($serviceDays, 'total_attendance')),
                'total_offerings' => array_sum(array_column($serviceDays, 'total_offerings')),
                'avg_attendance_per_service' => count($serviceDays) > 0 ? 
                    round(array_sum(array_column($serviceDays, 'total_attendance')) / count($serviceDays), 2) : 0,
                'avg_offerings_per_service' => count($serviceDays) > 0 ? 
                    round(array_sum(array_column($serviceDays, 'total_offerings')) / count($serviceDays), 2) : 0
            ]
        ]);
    }
    
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