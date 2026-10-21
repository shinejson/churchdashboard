<?php
// frontend/api/config/functions.php

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is authenticated and has permission
 */
function checkAuth($requiredRole = null) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    if ($requiredRole && $_SESSION['user_role'] !== $requiredRole) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit();
    }
    
    return true;
}

/**
 * Verify CSRF token
 */
function verifyCSRF() {
    $csrf_token = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    if (!$csrf_token || $csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }
}

/**
 * Get date range from request
 */
function getDateRange() {
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    // Validate dates
    if (!strtotime($startDate) || !strtotime($endDate)) {
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-d');
    }
    
    return [
        'start' => $startDate,
        'end' => $endDate
    ];
}

/**
 * Get time period filter
 */
function getPeriodFilter() {
    $period = $_GET['period'] ?? 'monthly';
    $dateRange = getDateRange();
    
    switch($period) {
        case 'daily':
            $groupBy = "DATE(a.service_date)";
            break;
        case 'weekly':
            $groupBy = "YEARWEEK(a.service_date)";
            break;
        case 'monthly':
            $groupBy = "DATE_FORMAT(a.service_date, '%Y-%m')";
            break;
        case 'quarterly':
            $groupBy = "CONCAT(YEAR(a.service_date), '-Q', QUARTER(a.service_date))";
            break;
        case 'yearly':
            $groupBy = "YEAR(a.service_date)";
            break;
        default:
            $groupBy = "DATE_FORMAT(a.service_date, '%Y-%m')";
    }
    
    return $groupBy;
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)));
}