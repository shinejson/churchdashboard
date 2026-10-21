<?php
// backend/attendance/reports.php

// Start session first
require_once __DIR__ . '/../config/security.php';
secure_session_start();

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost'); // Adjust to your domain
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');
header('Access-Control-Allow-Credentials: true');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Define environment if not already defined
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', isset($_SERVER['APP_ENV']) ? $_SERVER['APP_ENV'] : 'production');
}

// Get parameters from either GET or POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get from POST
    $type = $_POST['report_type'] ?? $_POST['type'] ?? 'overview';
    $start_date = $_POST['start_date'] ?? date('Y-m-01');
    $end_date = $_POST['end_date'] ?? date('Y-m-d');
    $fellowship_id = $_POST['fellowship_id'] ?? null;
    $category = $_POST['category'] ?? null;
} else {
    // Get from GET (for direct testing)
    $type = $_GET['type'] ?? 'overview';
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $fellowship_id = $_GET['fellowship_id'] ?? null;
    $category = $_GET['category'] ?? null;
}

// Validate required parameters
if (!$start_date || !$end_date) {
    echo json_encode(["success" => false, "message" => "Start date and end date are required"]);
    exit();
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    echo json_encode(["success" => false, "message" => "Invalid date format. Use YYYY-MM-DD"]);
    exit();
}

// Ensure end date is not before start date
if (strtotime($end_date) < strtotime($start_date)) {
    echo json_encode(["success" => false, "message" => "End date cannot be before start date"]);
    exit();
}

try {
    // Check if database.php exists
    $configPath = __DIR__ . '/../config/database.php';
    if (!file_exists($configPath)) {
        throw new Exception("Database configuration file not found at: " . $configPath);
    }
    
    require_once $configPath;
    
    // Check if we have a Database class or connection
    if (!class_exists('Database') && !isset($db)) {
        throw new Exception("Database class not found and no connection established");
    }
    
    // Create database connection
    if (class_exists('Database')) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    // Check connection
    if (!$db) {
        throw new Exception("Failed to establish database connection");
    }
    
    // Simple test query
    $test = $db->query("SELECT 1");
    if (!$test) {
        throw new Exception("Database connection test failed");
    }
    
    $response = ["success" => true];
    
    // Process based on report type
    switch($type) {
        case 'overview':
            $response['data'] = getOverviewReport($db, $start_date, $end_date, $fellowship_id, $category);
            break;
            
        case 'demographics':
            $response['data'] = getDemographicsReport($db, $start_date, $end_date, $fellowship_id, $category);
            break;
            
        case 'trends':
            $response['data'] = getTrendsReport($db, $start_date, $end_date, $fellowship_id, $category);
            break;
            
        case 'fellowship':
            $response['data'] = getFellowshipReport($db, $start_date, $end_date, $category);
            break;
            
        default:
            echo json_encode(["success" => false, "message" => "Invalid report type: " . $type]);
            exit();
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Report Error: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "message" => "An error occurred while generating the report",
        "error" => $e->getMessage(),
        "debug" => (ENVIRONMENT === 'development') ? [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ] : null
    ]);
}

// Helper function
function formatServiceIdPHP($serviceId) {
    if (empty($serviceId)) return 'General Service';
    
    $serviceMap = [
        'sunday_service' => 'Sunday Service',
        'bible_study' => 'Bible Study',
        'prayer_meeting' => 'Prayer Meeting',
        'youth_fellowship' => 'Youth Fellowship',
        'children_service' => 'Children Service'
    ];
    
    return $serviceMap[$serviceId] ?? ucwords(str_replace('_', ' ', $serviceId));
}

// Function to get overview report - SIMPLIFIED VERSION
function getOverviewReport($db, $start_date, $end_date, $fellowship_id = null, $category = null) {
    $data = [];
    
    // Build WHERE clause
    $whereClause = "WHERE a.service_date BETWEEN :start_date AND :end_date AND a.is_deleted = 0";
    $params = [
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];
    
    if ($fellowship_id && $fellowship_id !== '') {
        $whereClause .= " AND a.fellowship_id = :fellowship_id";
        $params[':fellowship_id'] = (int)$fellowship_id;
    }
    
    // Get total sessions - SIMPLIFIED
    $sql = "
        SELECT COUNT(DISTINCT DATE(a.service_date)) as total_sessions 
        FROM attendance a
        {$whereClause}
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $data['total_sessions'] = (int)($result['total_sessions'] ?? 0);
    
    // Get average attendance - SIMPLIFIED
    $sql = "
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as total_present,
            COUNT(DISTINCT a.member_id) as active_members
        FROM attendance a
        LEFT JOIN members m ON a.member_id = m.id
        {$whereClause}
    ";
    
    if ($category && $category !== '') {
        $sql .= " AND m.category = :category";
        $params[':category'] = $category;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_records = (int)($result['total_records'] ?? 0);
    $total_present = (int)($result['total_present'] ?? 0);
    
    $data['avg_attendance'] = $total_records > 0 ? round(($total_present / $total_records) * 100, 1) : 0;
    $data['total_present'] = $total_present;
    $data['active_members'] = (int)($result['active_members'] ?? 0);
    $data['total_records'] = $total_records;
    
    // Get recent records for table - SIMPLIFIED
    $sql = "
        SELECT 
            DATE(a.service_date) as date,
            COALESCE(s.name, a.service_id) as service_id,
            COALESCE(f.name, 'Unknown') as fellowship,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
            COUNT(*) as total_members
        FROM attendance a
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN fellowships f ON a.fellowship_id = f.id
        LEFT JOIN members m ON a.member_id = m.id
        {$whereClause}
    ";
    
    if ($category && $category !== '') {
        $sql .= " AND m.category = :category";
    }
    
    $sql .= " GROUP BY a.service_date, a.fellowship_id, a.service_id 
              ORDER BY a.service_date DESC LIMIT 20";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate percentages and format service names
    foreach ($records as &$record) {
        $total = (int)$record['total_members'];
        $present = (int)$record['present'];
        $record['percentage'] = $total > 0 ? round(($present / $total) * 100, 1) : 0;
        $record['service_name'] = formatServiceIdPHP($record['service_id']);
    }
    
    $data['records'] = $records;
    
    return $data;
}

// Function to get demographics report - SIMPLIFIED
function getDemographicsReport($db, $start_date, $end_date, $fellowship_id = null, $category = null) {
    $data = [];
    
    // Build WHERE clause
    $whereClause = "WHERE a.service_date BETWEEN :start_date AND :end_date AND a.is_deleted = 0";
    $params = [
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];
    
    if ($fellowship_id && $fellowship_id !== '') {
        $whereClause .= " AND a.fellowship_id = :fellowship_id";
        $params[':fellowship_id'] = (int)$fellowship_id;
    }
    
    // Get demographics by category - SIMPLIFIED
    $sql = "
        SELECT 
            COALESCE(m.category, 'unknown') as age_group,
            COUNT(DISTINCT m.id) as total,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
            COUNT(*) as attendance_count
        FROM attendance a
        INNER JOIN members m ON a.member_id = m.id
        {$whereClause}
    ";
    
    if ($category && $category !== '') {
        $sql .= " AND m.category = :category";
        $params[':category'] = $category;
    }
    
    $sql .= " GROUP BY m.category";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $demoData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare demographics
    $demographics = [
        "adults" => 0,
        "teens" => 0,
        "children" => 0,
        "unknown" => 0
    ];
    
    $demographicsTable = [];
    foreach ($demoData as $row) {
        $categoryKey = strtolower($row['age_group']);
        $total = (int)$row['total'];
        $present = (int)$row['present_count'];
        $attendanceCount = (int)$row['attendance_count'];
        
        if (isset($demographics[$categoryKey])) {
            $demographics[$categoryKey] = $total;
        } else {
            $demographics['unknown'] += $total;
            $categoryKey = 'unknown';
        }
        
        // Calculate attendance rate
        $attendanceRate = $attendanceCount > 0 ? round(($present / $attendanceCount) * 100, 1) : 0;
        
        $demographicsTable[] = [
            'age_group' => ucfirst($categoryKey),
            'male' => 0, // You need gender data for this
            'female' => 0, // You need gender data for this
            'total' => $total,
            'attendance_rate' => $attendanceRate
        ];
    }
    
    $data['demographics'] = $demographics;
    $data['records'] = $demographicsTable;
    $data['demographics_table'] = $demographicsTable;
    
    return $data;
}

// Function to get trends report - SIMPLIFIED
function getTrendsReport($db, $start_date, $end_date, $fellowship_id = null, $category = null) {
    $data = [];
    
    // Build WHERE clause
    $whereClause = "WHERE a.service_date BETWEEN :start_date AND :end_date AND a.is_deleted = 0";
    $params = [
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];
    
    if ($fellowship_id && $fellowship_id !== '') {
        $whereClause .= " AND a.fellowship_id = :fellowship_id";
        $params[':fellowship_id'] = (int)$fellowship_id;
    }
    
    // Get daily trends
    $sql = "
        SELECT 
            DATE(a.service_date) as date,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
            COUNT(*) as total,
            COUNT(DISTINCT a.member_id) as unique_members
        FROM attendance a
        LEFT JOIN members m ON a.member_id = m.id
        {$whereClause}
    ";
    
    if ($category && $category !== '') {
        $sql .= " AND m.category = :category";
        $params[':category'] = $category;
    }
    
    $sql .= " GROUP BY DATE(a.service_date) ORDER BY a.service_date";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $dailyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare data for chart
    $dates = [];
    $percentages = [];
    $records = [];
    
    foreach ($dailyTrends as $trend) {
        $date = $trend['date'];
        $present = (int)$trend['present'];
        $total = (int)$trend['total'];
        $percentage = $total > 0 ? round(($present / $total) * 100, 1) : 0;
        
        $dates[] = date('M d', strtotime($date));
        $percentages[] = $percentage;
        
        $records[] = [
            'date' => $date,
            'percentage' => $percentage,
            'present' => $present,
            'total' => $total,
            'unique_members' => (int)$trend['unique_members']
        ];
    }
    
    $data['dates'] = $dates;
    $data['percentages'] = $percentages;
    $data['records'] = $records;
    
    return $data;
}

// Function to get fellowship report - SIMPLIFIED
function getFellowshipReport($db, $start_date, $end_date, $category = null) {
    $data = [];
    
    // Build WHERE clause
    $whereClause = "WHERE a.service_date BETWEEN :start_date AND :end_date AND a.is_deleted = 0";
    $params = [
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];
    
    $sql = "
        SELECT 
            f.id as fellowship_id,
            f.name as fellowship_name,
            COUNT(DISTINCT DATE(a.service_date)) as total_sessions,
            COUNT(DISTINCT a.member_id) as unique_members,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as total_present,
            COUNT(*) as total_records
        FROM attendance a
        INNER JOIN fellowships f ON a.fellowship_id = f.id
        LEFT JOIN members m ON a.member_id = m.id
        {$whereClause}
    ";
    
    if ($category && $category !== '') {
        $sql .= " AND m.category = :category";
        $params[':category'] = $category;
    }
    
    $sql .= " GROUP BY f.id, f.name ORDER BY total_present DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $fellowshipData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate attendance rates
    foreach ($fellowshipData as &$fellowship) {
        $total_records = (int)$fellowship['total_records'];
        $total_present = (int)$fellowship['total_present'];
        $fellowship['attendance_rate'] = $total_records > 0 ? round(($total_present / $total_records) * 100, 1) : 0;
    }
    
    $data['fellowships'] = $fellowshipData;
    $data['records'] = $fellowshipData;
    
    // Calculate overall statistics
    if (count($fellowshipData) > 0) {
        $data['total_fellowships'] = count($fellowshipData);
        $data['total_sessions'] = array_sum(array_column($fellowshipData, 'total_sessions'));
        $data['total_unique_members'] = array_sum(array_column($fellowshipData, 'unique_members'));
        $data['overall_attendance'] = round(array_sum(array_column($fellowshipData, 'attendance_rate')) / count($fellowshipData), 1);
    }
    
    return $data;
}
?>