<?php
header('Content-Type: application/json');
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/backend/config/database.php';
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/backend/config/security.php';

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
    
    // Get summary data
    $summary = [];
    
    // Total Members (apply fellowship filter only)
    $whereConditions = [];
    $params = [];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions[] = 'fellowship_id = ?';
        $params[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions[] = 'fellowship_id = ?';
        $params[] = $filters['fellowship_id'];
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM members $whereClause");
    $stmt->execute($params);
    $summary['total_members'] = $stmt->fetch()['total'];
    
    // Active Members (apply fellowship filter only)
    $whereConditions2 = ["status = 'active'"];
    $params2 = [];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions2[] = 'fellowship_id = ?';
        $params2[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions2[] = 'fellowship_id = ?';
        $params2[] = $filters['fellowship_id'];
    }
    
    $whereClause2 = implode(' AND ', $whereConditions2);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM members WHERE $whereClause2");
    $stmt->execute($params2);
    $summary['active_members'] = $stmt->fetch()['total'];
    
    // Total Offerings (apply both filters)
    // Treat all income transactions as "offerings" here so the summary
    // card actually reflects total financial inflow. Tithes are still
    // tracked separately from the tithe_transactions table below.
    $whereConditions3 = ["type = 'income'", 'transaction_date BETWEEN ? AND ?'];
    $params3 = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions3[] = 'fellowship_id = ?';
        $params3[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions3[] = 'fellowship_id = ?';
        $params3[] = $filters['fellowship_id'];
    }
    
    if ($filters['service_id'] !== null) {
        $whereConditions3[] = 'service_id = ?';
        $params3[] = $filters['service_id'];
    }
    
    $whereClause3 = implode(' AND ', $whereConditions3);
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM financial_transactions 
        WHERE $whereClause3
    ");
    $stmt->execute($params3);
    $summary['total_offerings'] = (float)$stmt->fetch()['total'];
    
    // Total Tithes (apply both filters)
    $whereConditions4 = ['transaction_date BETWEEN ? AND ?'];
    $params4 = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions4[] = 'fellowship_id = ?';
        $params4[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions4[] = 'fellowship_id = ?';
        $params4[] = $filters['fellowship_id'];
    }
    
    if ($filters['service_id'] !== null) {
        $whereConditions4[] = 'service_id = ?';
        $params4[] = $filters['service_id'];
    }
    
    $whereClause4 = implode(' AND ', $whereConditions4);
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM tithe_transactions 
        WHERE $whereClause4
    ");
    $stmt->execute($params4);
    $summary['total_tithes'] = (float)$stmt->fetch()['total'];
    
    // Total Attendance (apply both filters)
    $whereConditions5 = ["status = 'present'", 'service_date BETWEEN ? AND ?'];
    $params5 = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions5[] = 'fellowship_id = ?';
        $params5[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions5[] = 'fellowship_id = ?';
        $params5[] = $filters['fellowship_id'];
    }
    
    if ($filters['service_id'] !== null) {
        $whereConditions5[] = 'service_id = ?';
        $params5[] = $filters['service_id'];
    }
    
    $whereClause5 = implode(' AND ', $whereConditions5);
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM attendance 
        WHERE $whereClause5
    ");
    $stmt->execute($params5);
    $summary['total_attendance'] = $stmt->fetch()['total'];
    
    // Total Events (Services) (apply both filters)
    $whereConditions6 = ['service_date BETWEEN ? AND ?'];
    $params6 = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions6[] = 'fellowship_id = ?';
        $params6[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions6[] = 'fellowship_id = ?';
        $params6[] = $filters['fellowship_id'];
    }
    
    if ($filters['service_id'] !== null) {
        $whereConditions6[] = 'service_id = ?';
        $params6[] = $filters['service_id'];
    }
    
    $whereClause6 = implode(' AND ', $whereConditions6);
    
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT service_date) as total 
        FROM attendance 
        WHERE $whereClause6
    ");
    $stmt->execute($params6);
    $summary['total_events'] = $stmt->fetch()['total'];
    
    // Total Fellowships (apply fellowship filter if provided)
    $whereConditions_f = [];
    $params_f = [];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions_f[] = 'id = ?';
        $params_f[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions_f[] = 'id = ?';
        $params_f[] = $filters['fellowship_id'];
    }
    
    $whereClause_f = !empty($whereConditions_f) ? 'WHERE ' . implode(' AND ', $whereConditions_f) : '';
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM fellowships $whereClause_f");
    $stmt->execute($params_f);
    $summary['total_fellowships'] = $stmt->fetch()['total'];
    
    // Active Fellowships (apply fellowship filter if provided)
    $whereConditions_af = ["status = 'active'"];
    $params_af = [];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions_af[] = 'id = ?';
        $params_af[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions_af[] = 'id = ?';
        $params_af[] = $filters['fellowship_id'];
    }
    
    $whereClause_af = implode(' AND ', $whereConditions_af);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM fellowships WHERE $whereClause_af");
    $stmt->execute($params_af);
    $summary['active_fellowships'] = $stmt->fetch()['total'];
    
    // Calculate Attendance Rate (percentage of members who attended)
    if ($summary['total_members'] > 0 && $summary['total_events'] > 0) {
        // Get unique members who attended
        $whereConditions_ar = ["status = 'present'", 'service_date BETWEEN ? AND ?'];
        $params_ar = [$dateRange['start'], $dateRange['end']];
        
        // Apply fellowship scope for non-admins
        if ($scopeId !== null) {
            $whereConditions_ar[] = 'fellowship_id = ?';
            $params_ar[] = $scopeId;
        } elseif ($filters['fellowship_id'] !== null) {
            $whereConditions_ar[] = 'fellowship_id = ?';
            $params_ar[] = $filters['fellowship_id'];
        }
        
        if ($filters['service_id'] !== null) {
            $whereConditions_ar[] = 'service_id = ?';
            $params_ar[] = $filters['service_id'];
        }
        
        $whereClause_ar = implode(' AND ', $whereConditions_ar);
        
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT member_id) as unique_attendees 
            FROM attendance 
            WHERE $whereClause_ar
        ");
        $stmt->execute($params_ar);
        $unique_attendees = $stmt->fetch()['unique_attendees'];
        
        // Attendance rate = (unique attendees / total members) * 100
        $summary['attendance_rate'] = round(($unique_attendees / $summary['total_members']) * 100, 1);
    } else {
        $summary['attendance_rate'] = 0;
    }
    
    // Get monthly data for chart (apply both filters to attendance)
    $filtersWithAlias = getFilterConditions(['table_alias' => 'a.']);
    $whereConditions7 = ["a.status = 'present'", 'a.service_date BETWEEN DATE_SUB(?, INTERVAL 6 MONTH) AND ?'];
    $params7 = [$dateRange['end'], $dateRange['end']];
    
    if (!empty($filtersWithAlias['conditions'])) {
        $whereConditions7 = array_merge($whereConditions7, $filtersWithAlias['conditions']);
        $params7 = array_merge($params7, $filtersWithAlias['params']);
    }
    
    $whereClause7 = implode(' AND ', $whereConditions7);
    
    // Build subquery filters for members (fellowship only)
    $memberFilter = '';
    if ($scopeId !== null) {
        $memberFilter = "AND fellowship_id = {$scopeId}";
    } elseif ($filters['fellowship_id'] !== null) {
        $memberFilter = "AND fellowship_id = {$filters['fellowship_id']}";
    }
    
    // Build subquery filters for financial transactions (both filters)
    $ftFilters = ["type = 'income'"];
    if ($scopeId !== null) {
        $ftFilters[] = "fellowship_id = {$scopeId}";
    } elseif ($filters['fellowship_id'] !== null) {
        $ftFilters[] = "fellowship_id = {$filters['fellowship_id']}";
    }
    if ($filters['service_id'] !== null) {
        $ftFilters[] = "service_id = {$filters['service_id']}";
    }
    $ftWhereClause = implode(' AND ', $ftFilters);
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(service_date, '%Y-%m') as month,
            COUNT(DISTINCT member_id) as attendance,
            (SELECT COUNT(*) FROM members WHERE DATE_FORMAT(join_date, '%Y-%m') = DATE_FORMAT(a.service_date, '%Y-%m') $memberFilter) as new_members,
            (SELECT COALESCE(SUM(amount), 0) FROM financial_transactions 
             WHERE $ftWhereClause AND DATE_FORMAT(transaction_date, '%Y-%m') = DATE_FORMAT(a.service_date, '%Y-%m')) as offerings
        FROM attendance a
        WHERE $whereClause7
        GROUP BY DATE_FORMAT(service_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute($params7);
    $chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format chart data
    $chart_labels = [];
    $chart_attendance = [];
    $chart_new_members = [];
    $chart_offerings = [];
    
    foreach($chartData as $row) {
        $chart_labels[] = date('M Y', strtotime($row['month'] . '-01'));
        $chart_attendance[] = (int)$row['attendance'];
        $chart_new_members[] = (int)$row['new_members'];
        $chart_offerings[] = (float)$row['offerings'];
    }
    
    // Get membership distribution (apply fellowship filter only)
    $whereConditions8 = [];
    $params8 = [];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions8[] = 'fellowship_id = ?';
        $params8[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions8[] = 'fellowship_id = ?';
        $params8[] = $filters['fellowship_id'];
    }
    
    $whereClause8 = !empty($whereConditions8) ? 'WHERE ' . implode(' AND ', $whereConditions8) : '';
    
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM members 
        $whereClause8
        GROUP BY status
    ");
    $stmt->execute($params8);
    $membershipData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $membership_labels = [];
    $membership_counts = [];
    $status_colors = [
        'active' => '#28a745',
        'inactive' => '#dc3545',
        'visitor' => '#ffc107'
    ];
    
    foreach($membershipData as $row) {
        $membership_labels[] = ucfirst($row['status']);
        $membership_counts[] = (int)$row['count'];
    }
    
    // Get recent transactions for table (apply both filters)
    $whereConditions9 = ['ft.transaction_date BETWEEN ? AND ?'];
    $params9 = [$dateRange['start'], $dateRange['end']];
    
    // Apply fellowship scope for non-admins
    if ($scopeId !== null) {
        $whereConditions9[] = 'ft.fellowship_id = ?';
        $params9[] = $scopeId;
    } elseif ($filters['fellowship_id'] !== null) {
        $whereConditions9[] = 'ft.fellowship_id = ?';
        $params9[] = $filters['fellowship_id'];
    }
    
    if ($filters['service_id'] !== null) {
        $whereConditions9[] = 'ft.service_id = ?';
        $params9[] = $filters['service_id'];
    }
    
    $whereClause9 = implode(' AND ', $whereConditions9);
    
    $stmt = $pdo->prepare("
        SELECT 
            ft.transaction_date as date,
            ft.category,
            ft.description,
            ft.amount,
            ft.payment_method,
            f.name as fellowship_name,
            CONCAT(m.first_name, ' ', m.last_name) as member_name
        FROM financial_transactions ft
        LEFT JOIN fellowships f ON ft.fellowship_id = f.id
        LEFT JOIN members m ON ft.member_id = m.id
        WHERE $whereClause9
        ORDER BY ft.transaction_date DESC
        LIMIT 50
    ");
    $stmt->execute($params9);
    $tableData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'chart_data' => [
            'labels' => $chart_labels,
            'attendance' => $chart_attendance,
            'new_members' => $chart_new_members,
            'offerings' => $chart_offerings
        ],
        'membership_data' => [
            'labels' => $membership_labels,
            'data' => $membership_counts,
            'colors' => array_values($status_colors)
        ],
        'table_data' => $tableData
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