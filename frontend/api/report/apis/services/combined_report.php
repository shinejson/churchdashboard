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

if (!function_exists('getFilterConditions')) {
    function getFilterConditions($options = []) {
        $fellowshipColumn = $options['fellowship_column'] ?? 'fellowship_id';
        $serviceColumn = $options['service_column'] ?? 'service_id';
        $tableAlias = $options['table_alias'] ?? '';
        
        $conditions = [];
        $params = [];
        $fellowshipId = null;
        $serviceId = null;
        
        if (isset($_GET['fellowship_id']) && $_GET['fellowship_id'] !== '') {
            if (!is_numeric($_GET['fellowship_id'])) {
                throw new InvalidArgumentException('Invalid filter parameter: fellowship_id must be numeric');
            }
            $fellowshipId = (int)$_GET['fellowship_id'];
            $conditions[] = $tableAlias . $fellowshipColumn . ' = ?';
            $params[] = $fellowshipId;
        }
        
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
    
    // Build WHERE conditions for attendance
    $whereConditions = ['a.service_date BETWEEN ? AND ?'];
    $params = [$dateRange['start'], $dateRange['end']];
    
    if (!empty($filters['conditions'])) {
        $whereConditions = array_merge($whereConditions, $filters['conditions']);
        $params = array_merge($params, $filters['params']);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get all service days with fellowship and service information
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            a.service_date,
            a.fellowship_id,
            a.service_id,
            f.name as fellowship_name,
            s.name as service_type
        FROM attendance a
        LEFT JOIN fellowships f ON a.fellowship_id = f.id
        LEFT JOIN services s ON a.service_id = s.id
        WHERE $whereClause
        ORDER BY a.service_date DESC, f.name, s.name
    ");
    $stmt->execute($params);
    $serviceDays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $combinedReport = [];
    
    foreach ($serviceDays as $serviceDay) {
        $serviceDate = $serviceDay['service_date'];
        $fellowshipId = $serviceDay['fellowship_id'];
        $serviceId = $serviceDay['service_id'];
        $fellowshipName = $serviceDay['fellowship_name'] ?? 'No Fellowship';
        $serviceType = $serviceDay['service_type'] ?? 'Unknown Service';
        
        // Get attendance breakdown by category
        $stmt = $pdo->prepare("
            SELECT 
                m.category,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count
            FROM attendance a
            JOIN members m ON a.member_id = m.id
            WHERE a.service_date = ?
            AND a.fellowship_id = ?
            AND a.service_id = ?
            AND a.status = 'present'
            GROUP BY m.category
        ");
        $stmt->execute([$serviceDate, $fellowshipId, $serviceId]);
        $categoryBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $adults = 0;
        $teens = 0;
        $children = 0;
        
        foreach ($categoryBreakdown as $cat) {
            switch (strtolower($cat['category'])) {
                case 'adult':
                    $adults = $cat['present_count'];
                    break;
                case 'teen':
                    $teens = $cat['present_count'];
                    break;
                case 'children':
                case 'child':
                    $children = $cat['present_count'];
                    break;
            }
        }
        
        $totalPresent = $adults + $teens + $children;
        
        // Get first timers for this service
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT a.member_id) as first_timer_count
            FROM attendance a
            WHERE a.service_date = ?
            AND a.fellowship_id = ?
            AND a.service_id = ?
            AND a.status = 'present'
            AND a.service_date = (
                SELECT MIN(a2.service_date)
                FROM attendance a2
                WHERE a2.member_id = a.member_id
                AND a2.status = 'present'
            )
        ");
        $stmt->execute([$serviceDate, $fellowshipId, $serviceId]);
        $firstTimers = $stmt->fetch(PDO::FETCH_ASSOC)['first_timer_count'] ?? 0;
        
        // Get offering for this service
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_offering
            FROM financial_transactions
            WHERE transaction_date = ?
            AND fellowship_id = ?
            AND service_id = ?
            AND type = 'income'
        ");
        $stmt->execute([$serviceDate, $fellowshipId, $serviceId]);
        $offering = $stmt->fetch(PDO::FETCH_ASSOC)['total_offering'] ?? 0;
        
        // Get partnership contributions with member names
        $stmt = $pdo->prepare("
            SELECT 
                pa.name as partnership_arm,
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                pc.amount
            FROM partnership_contributions pc
            JOIN partnership_arms pa ON pc.partnership_arm_id = pa.id
            JOIN members m ON pc.member_id = m.id
            JOIN financial_transactions ft ON pc.financial_transaction_id = ft.id
            WHERE ft.transaction_date = ?
            AND ft.fellowship_id = ?
            AND ft.service_id = ?
            ORDER BY pa.name, m.last_name, m.first_name
        ");
        $stmt->execute([$serviceDate, $fellowshipId, $serviceId]);
        $partnerships = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $combinedReport[] = [
            'fellowship_name' => $fellowshipName,
            'service_type' => $serviceType,
            'service_date' => $serviceDate,
            'attendance' => [
                'adults' => $adults,
                'teens' => $teens,
                'children' => $children,
                'total' => $totalPresent,
                'first_timers' => $firstTimers
            ],
            'offering' => $offering,
            'partnerships' => $partnerships
        ];
    }
    
    echo json_encode([
        'success' => true,
        'report' => $combinedReport,
        'summary' => [
            'total_services' => count($combinedReport),
            'total_attendance' => array_sum(array_column(array_column($combinedReport, 'attendance'), 'total')),
            'total_offerings' => array_sum(array_column($combinedReport, 'offering')),
            'total_first_timers' => array_sum(array_column(array_column($combinedReport, 'attendance'), 'first_timers'))
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
