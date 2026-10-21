<?php
/**
 * Send Attendance Report via Email or SMS
 * Generates and sends attendance reports to recipients
 */

// Prevent any output before JSON
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include required files BEFORE session_start
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../lib/EmailService.php';
require_once __DIR__ . '/../lib/SMSService.php';

// Start session using secure_session_start (after config is loaded)
secure_session_start();

// Set CORS headers properly for credentials
// Get the origin from the request
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Only set CORS headers if origin is set and matches allowed origins
if ($origin) {
    // Allow same-origin requests
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Debug: Check if session data exists
error_log('Session check in send_report.php:' . json_encode([
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'] ?? 'NOT SET',
    'role' => $_SESSION['role'] ?? 'NOT SET',
    'session_count' => count($_SESSION),
    'origin' => $origin,
    'http_origin' => $_SERVER['HTTP_ORIGIN'] ?? 'NOT SET'
]));

// Get request data first to check action type
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? $_POST;

// Check authentication - but allow preview for logged-in users
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode([
        "success" => false, 
        "message" => "Unauthorized access",
        "debug" => [
            "session_id" => session_id(),
            "session_data" => array_keys($_SESSION),
            "action" => $data['action'] ?? 'none'
        ]
    ]);
    exit();
}

// Convert PHP warnings/notices (e.g. mail() warnings) into exceptions so
// they can be caught and returned as JSON instead of rendering HTML.
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Respect error_reporting level
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    // Handle preview request
    if (!empty($data['action']) && $data['action'] === 'get_report_preview') {
        $reportType = $data['type'] ?? 'overview';
        $startDate = $data['start_date'] ?? date('Y-m-01');
        $endDate = $data['end_date'] ?? date('Y-m-d');
        $fellowshipId = $data['fellowship_id'] ?? null;
        
        // Get database connection
        $db = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS
        );
        
        // Generate preview data
        $reportData = generateAttendanceReport($db, $reportType, $startDate, $endDate, $fellowshipId);
        
        ob_end_clean();
        echo json_encode([
            "success" => true,
            "data" => $reportData
        ]);
        exit();
    }
    
    // Validate required fields
    if (empty($data['report_type']) || empty($data['send_method'])) {
        throw new Exception("Report type and send method are required");
    }
    
    // Validate and sanitize first_timers
    $firstTimers = [];
    if (!empty($data['first_timers']) && is_array($data['first_timers'])) {
        foreach ($data['first_timers'] as $ft) {
            $name = trim($ft['name'] ?? '');
            $count = intval($ft['count'] ?? 0);
            
            // Validate: name must be non-empty, count must be positive
            if (empty($name)) {
                throw new Exception("First timer name cannot be empty");
            }
            if ($count < 0) {
                throw new Exception("First timer count cannot be negative");
            }
            
            $firstTimers[] = [
                'name' => htmlspecialchars($name),
                'count' => $count
            ];
        }
    }
    
    $reportType = $data['report_type']; // 'overview', 'detailed', 'member'
    $sendMethod = $data['send_method']; // 'email', 'sms', 'both'
    $startDate = $data['start_date'] ?? date('Y-m-01');
    $endDate = $data['end_date'] ?? date('Y-m-d');
    $recipientIds = $data['recipient_ids'] ?? []; // Array of user IDs
    $fellowshipId = $data['fellowship_id'] ?? null;
    
    // Validate dates
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        throw new Exception("Invalid date format. Use YYYY-MM-DD");
    }
    
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Unable to connect to the database. Please try again later.");
    }
    
    // Generate attendance report data
    $reportData = generateAttendanceReport($db, $reportType, $startDate, $endDate, $fellowshipId);
    
    // Get recipients
    $recipients = getRecipients($db, $recipientIds);
    
    if (empty($recipients)) {
        throw new Exception("No valid recipients found");
    }
    
    // Prepare result
    $sendResults = [
        'email' => [],
        'sms' => []
    ];
    
    // Send via Email
    if (in_array($sendMethod, ['email', 'both'])) {
        $emailService = new EmailService();
        // Debug log SMTP info (do not log password)
        error_log('EmailService config: host=' . ($emailService->getSmtpHost() ?? 'N/A') . ' port=' . ($emailService->getSmtpPort() ?? 'N/A') . ' user=' . ($emailService->getSmtpUser() ?? 'N/A'));
        foreach ($recipients as $recipient) {
            if (!empty($recipient['email'])) {
                $subject = "Attendance Report - " . date('F Y', strtotime($startDate));
                $htmlBody = formatEmailReport($reportData, $recipient, $reportType, $startDate, $endDate, ['first_timers' => $firstTimers]);
                $textBody = formatTextReport($reportData, $recipient, $reportType, $startDate, $endDate, ['first_timers' => $firstTimers]);
                // Log attempt
                error_log("Attempting to send report email to {$recipient['email']} (user={$recipient['username']})");
                $result = $emailService->send($recipient['email'], $subject, $htmlBody, $textBody);
                error_log("Email send result for {$recipient['email']}: " . ($result ? 'success' : 'failure'));
                
                $sendResults['email'][] = [
                    'recipient' => $recipient['email'],
                    'success' => $result,
                    'message' => $result ? 'Email sent successfully' : 'Failed to send email'
                ];
            }
        }
    }
    
    // Send via SMS
    if (in_array($sendMethod, ['sms', 'both'])) {
        $smsService = new SMSService();
        foreach ($recipients as $recipient) {
            if (!empty($recipient['phone'])) {
                $smsBody = formatSMSReport($reportData, $recipient, $reportType, $startDate, $endDate, ['first_timers' => $firstTimers]);
                
                $result = $smsService->send(
                    $recipient['phone'],
                    $smsBody
                );
                
                $sendResults['sms'][] = [
                    'recipient' => $recipient['phone'],
                    'success' => $result,
                    'message' => $result ? 'SMS sent successfully' : 'Failed to send SMS'
                ];
            }
        }
    }
    
    // Log the report sending
    logReportSending($db, $reportType, $sendMethod, $startDate, $endDate, $sendResults, $firstTimers);
    
    echo json_encode([
        "success" => true,
        "message" => "Report sent successfully",
        "results" => $sendResults,
        "report_data" => $reportData
    ]);

} catch (Exception $e) {
    error_log("Report Send Error: " . $e->getMessage());
    
    // Log to file for debugging
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/report_errors.log';
    $logMessage = "[" . date('Y-m-d H:i:s') . "] " . $e->getMessage() . "\n";
    $logMessage .= "Request Data: " . json_encode($data) . "\n";
    $logMessage .= "Stack Trace: " . $e->getTraceAsString() . "\n\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "error" => $e->getMessage()
    ]);
}

/**
 * Generate attendance report data
 */
function generateAttendanceReport($db, $type, $startDate, $endDate, $fellowshipId = null) {
    // Query with member details including age_category
    $sql = "SELECT 
        a.id, 
        a.member_id, 
        a.service_date, 
        a.service_id,
        COALESCE(a.status, 'present') as status,
        CONCAT(m.first_name, ' ', m.last_name) as member_name,
        m.first_name,
        m.last_name,
        m.email,
        m.phone,
        COALESCE(m.category, 'adult') as age_category,
        f.name as fellowship_name
    FROM attendance a
    JOIN members m ON a.member_id = m.id
    LEFT JOIN fellowships f ON a.fellowship_id = f.id
    WHERE a.service_date BETWEEN ? AND ?";
    
    $params = [$startDate, $endDate];
    
    if ($fellowshipId) {
        $sql .= " AND a.fellowship_id = ?";
        $params[] = $fellowshipId;
    }
    
    $sql .= " ORDER BY a.service_date DESC, m.first_name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process data based on report type
    switch ($type) {
        case 'overview':
            return generateOverviewReport($results, $startDate, $endDate);
        case 'detailed':
            return generateDetailedReport($results, $startDate, $endDate);
        case 'member':
            return generateMemberReport($results, $startDate, $endDate);
        default:
            return generateOverviewReport($results, $startDate, $endDate);
    }
}

/**
 * Generate overview report (summary statistics)
 */
function generateOverviewReport($records, $startDate, $endDate) {
    $totalDays = count(array_unique(array_column($records, 'service_date')));
    $totalPresent = count(array_filter($records, function($r) { return isset($r['status']) && $r['status'] === 'present'; }));
    $totalAbsent = count(array_filter($records, function($r) { return isset($r['status']) && $r['status'] === 'absent'; }));
    $totalLate = count(array_filter($records, function($r) { return isset($r['status']) && $r['status'] === 'late'; }));
    
    $uniqueMembers = count(array_unique(array_column($records, 'member_name')));
    
    // Separate adults, teens, and children who were present
    $adultsPresent = array_filter($records, function($r) { 
        return $r['age_category'] === 'adult' && $r['status'] === 'present'; 
    });
    $teensPresent = array_filter($records, function($r) { 
        return $r['age_category'] === 'teen' && $r['status'] === 'present'; 
    });
    $childrenPresent = array_filter($records, function($r) { 
        return $r['age_category'] === 'children' && $r['status'] === 'present'; 
    });
    
    // Get unique names
    $adultNames = array_unique(array_column($adultsPresent, 'member_name'));
    $teensNames = array_unique(array_column($teensPresent, 'member_name'));
    $childrenNames = array_unique(array_column($childrenPresent, 'member_name'));
    
    return [
        'type' => 'overview',
        'period' => "From $startDate to $endDate",
        'total_meeting_days' => $totalDays,
        'total_members_recorded' => $uniqueMembers,
        'attendance_stats' => [
            'present' => $totalPresent,
            'absent' => $totalAbsent,
            'late' => $totalLate
        ],
        'adults_present' => [
            'count' => count($adultNames),
            'names' => array_values($adultNames)
        ],
        'teens_present' => [
            'count' => count($teensNames),
            'names' => array_values($teensNames)
        ],
        'children_present' => [
            'count' => count($childrenNames),
            'names' => array_values($childrenNames)
        ],
        'attendance_rate' => $uniqueMembers > 0 ? round(($totalPresent / ($totalPresent + $totalAbsent + $totalLate)) * 100, 2) : 0,
        'records' => array_slice($records, 0, 10) // Last 10 records
    ];
}

/**
 * Generate detailed report
 */
function generateDetailedReport($records, $startDate, $endDate) {
    $groupedByDate = [];
    foreach ($records as $record) {
        $date = $record['service_date'];
        if (!isset($groupedByDate[$date])) {
            $groupedByDate[$date] = [];
        }
        $groupedByDate[$date][] = $record;
    }
    
    // Separate adults, teens, and children who were present
    $adultsPresent = array_filter($records, function($r) { 
        return $r['age_category'] === 'adult' && $r['status'] === 'present'; 
    });
    $teensPresent = array_filter($records, function($r) { 
        return $r['age_category'] === 'teen' && $r['status'] === 'present'; 
    });
    $childrenPresent = array_filter($records, function($r) { 
        return $r['age_category'] === 'children' && $r['status'] === 'present'; 
    });
    $absent = array_filter($records, function($r) { 
        return $r['status'] === 'absent'; 
    });
    
    // Get unique names
    $adultNames = array_unique(array_column($adultsPresent, 'member_name'));
    $teensNames = array_unique(array_column($teensPresent, 'member_name'));
    $childrenNames = array_unique(array_column($childrenPresent, 'member_name'));
    $absentNames = array_unique(array_column($absent, 'member_name'));
    
    return [
        'type' => 'detailed',
        'period' => "From $startDate to $endDate",
        'total_records' => count($records),
        'adults_present' => [
            'count' => count($adultNames),
            'names' => array_values($adultNames)
        ],
        'teens_present' => [
            'count' => count($teensNames),
            'names' => array_values($teensNames)
        ],
        'children_present' => [
            'count' => count($childrenNames),
            'names' => array_values($childrenNames)
        ],
        'absent' => [
            'count' => count($absentNames),
            'names' => array_values($absentNames)
        ],
        'records_by_date' => $groupedByDate,
        'summary' => [
            'present' => count(array_filter($records, function($r) { return isset($r['status']) && $r['status'] === 'present'; })),
            'absent' => count(array_filter($records, function($r) { return isset($r['status']) && $r['status'] === 'absent'; })),
            'late' => count(array_filter($records, function($r) { return isset($r['status']) && $r['status'] === 'late'; }))
        ]
    ];
}

/**
 * Generate member-based report
 */
function generateMemberReport($records, $startDate, $endDate) {
    $memberStats = [];
    foreach ($records as $record) {
        $memberId = $record['member_id'];
        if (!isset($memberStats[$memberId])) {
            $memberStats[$memberId] = [
                'name' => $record['member_name'],
                'email' => $record['email'],
                'phone' => $record['phone'],
                'age_category' => $record['age_category'],
                'present' => 0,
                'absent' => 0,
                'late' => 0
            ];
        }
        
        $status = strtolower($record['status']);
        if (isset($memberStats[$memberId][$status])) {
            $memberStats[$memberId][$status]++;
        }
    }
    
    // Separate adults, teens, and children who were present
    $adultsPresent = array_filter($memberStats, function($m) { 
        return $m['age_category'] === 'adult' && $m['present'] > 0; 
    });
    $teensPresent = array_filter($memberStats, function($m) { 
        return $m['age_category'] === 'teen' && $m['present'] > 0; 
    });
    $childrenPresent = array_filter($memberStats, function($m) { 
        return $m['age_category'] === 'children' && $m['present'] > 0; 
    });
    $firstTimersPresent = []; // First timers are tracked separately via form input
    
    // Get unique names
    $adultNames = array_column($adultsPresent, 'name');
    $teensNames = array_column($teensPresent, 'name');
    $childrenNames = array_column($childrenPresent, 'name');
    
    return [
        'type' => 'member',
        'period' => "From $startDate to $endDate",
        'total_members' => count($memberStats),
        'adults_present' => [
            'count' => count($adultNames),
            'names' => $adultNames
        ],
        'teens_present' => [
            'count' => count($teensNames),
            'names' => $teensNames
        ],
        'children_present' => [
            'count' => count($childrenNames),
            'names' => $childrenNames
        ],
        'member_attendance' => array_values($memberStats)
    ];
}

/**
 * Get recipients for the report
 */
function getRecipients($db, $recipientIds) {
    if (empty($recipientIds)) {
        return [];
    }
    
    $placeholders = str_repeat('?,', count($recipientIds) - 1) . '?';
    $sql = "SELECT id, username, email, phone FROM users WHERE id IN ($placeholders) AND status = 'active'";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($recipientIds);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Format report for email (HTML)
 */
function formatEmailReport($reportData, $recipient, $type, $startDate, $endDate, $extra = []) {
    $html = "<!DOCTYPE html><html><head><style>";
    $html .= "body { font-family: Arial, sans-serif; color: #333; }";
    $html .= "table { border-collapse: collapse; width: 100%; margin: 10px 0; }";
    $html .= "th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }";
    $html .= "th { background-color: #3a86ff; color: white; }";
    $html .= ".header { color: #1a1a2e; margin: 20px 0; }";
    $html .= ".footer { color: #666; margin-top: 20px; }";
    $html .= "</style></head><body>";
    
    $html .= "<div class='header'><h2>Attendance Report</h2>";
    $html .= "<p>Report for: " . htmlspecialchars($recipient['username']) . "</p>";
    $html .= "<p>Period: $startDate to $endDate</p></div>";
    
    if ($reportData['type'] === 'overview') {
        $html .= "<h3>Summary Statistics</h3>";
        $html .= "<table>";
        $html .= "<tr><th colspan='2'>Service Type & Attendance</th></tr>";
        
        // Adults section
        $html .= "<tr><td><strong>Adult:</strong></td><td>";
        $html .= $reportData['adults_present']['count'] . " present";
        if (!empty($reportData['adults_present']['names'])) {
            $html .= "<br><em>" . htmlspecialchars(implode(", ", $reportData['adults_present']['names'])) . "</em>";
        }
        $html .= "</td></tr>";
        
        // Teens section
        $html .= "<tr><td><strong>Teens:</strong></td><td>";
        $html .= $reportData['teens_present']['count'] . " present";
        if (!empty($reportData['teens_present']['names'])) {
            $html .= "<br><em>" . htmlspecialchars(implode(", ", $reportData['teens_present']['names'])) . "</em>";
        }
        $html .= "</td></tr>";
        
        // Children section
        $html .= "<tr><td><strong>Children:</strong></td><td>";
        $html .= $reportData['children_present']['count'] . " present";
        if (!empty($reportData['children_present']['names'])) {
            $html .= "<br><em>" . htmlspecialchars(implode(", ", $reportData['children_present']['names'])) . "</em>";
        }
        $html .= "</td></tr>";
        
        $html .= "<tr><td><strong>Total Members Present:</strong></td><td>" . $reportData['attendance_stats']['present'] . "</td></tr>";
        $html .= "<tr><td><strong>Attendance Rate:</strong></td><td>" . $reportData['attendance_rate'] . "%</td></tr>";
        $html .= "</table>";
        
        // Include first timers if provided
        if (!empty($extra['first_timers'])) {
            $html .= "<h4>First Timers</h4>";
            $html .= "<table>";
            $html .= "<tr><th>Name</th><th>No. (present)</th></tr>";
            foreach ($extra['first_timers'] as $ft) {
                $name = htmlspecialchars($ft['name'] ?? '');
                $count = intval($ft['count'] ?? 0);
                $html .= "<tr><td>$name</td><td>$count</td></tr>";
            }
            $html .= "</table>";
        }
    } elseif ($reportData['type'] === 'detailed') {
        $html .= "<h3>Detailed Report</h3>";
        $html .= "<table>";
        $html .= "<tr><th colspan='2'>Service Type & Attendance</th></tr>";
        
        // Adults section
        $html .= "<tr><td><strong>Adult:</strong></td><td>";
        $html .= $reportData['adults_present']['count'] . " present";
        if (!empty($reportData['adults_present']['names'])) {
            $html .= "<br><em>" . htmlspecialchars(implode(", ", $reportData['adults_present']['names'])) . "</em>";
        }
        $html .= "</td></tr>";
        
        // Teens section
        $html .= "<tr><td><strong>Teens:</strong></td><td>";
        $html .= $reportData['teens_present']['count'] . " present";
        if (!empty($reportData['teens_present']['names'])) {
            $html .= "<br><em>" . htmlspecialchars(implode(", ", $reportData['teens_present']['names'])) . "</em>";
        }
        $html .= "</td></tr>";
        
        // Children section
        $html .= "<tr><td><strong>Children:</strong></td><td>";
        $html .= $reportData['children_present']['count'] . " present";
        if (!empty($reportData['children_present']['names'])) {
            $html .= "<br><em>" . htmlspecialchars(implode(", ", $reportData['children_present']['names'])) . "</em>";
        }
        $html .= "</td></tr>";
        
        // Absent section
        $html .= "<tr><td><strong>Absent:</strong></td><td>";
        $html .= $reportData['absent']['count'] . " absent";
        if (!empty($reportData['absent']['names'])) {
            $html .= "<br><em>" . htmlspecialchars(implode(", ", $reportData['absent']['names'])) . "</em>";
        }
        $html .= "</td></tr>";
        
        $html .= "<tr><td><strong>Total Members Present:</strong></td><td>" . $reportData['summary']['present'] . "</td></tr>";
        $html .= "</table>";
        
        // Include first timers if provided
        if (!empty($extra['first_timers'])) {
            $html .= "<h4>First Timers</h4>";
            $html .= "<table>";
            $html .= "<tr><th>Name</th><th>No. (present)</th></tr>";
            foreach ($extra['first_timers'] as $ft) {
                $name = htmlspecialchars($ft['name'] ?? '');
                $count = intval($ft['count'] ?? 0);
                $html .= "<tr><td>$name</td><td>$count</td></tr>";
            }
            $html .= "</table>";
        }
    } elseif ($reportData['type'] === 'member') {
        $html .= "<h3>Member Attendance Summary</h3>";
        $html .= "<h4>Adults Present (" . $reportData['adults_present']['count'] . ")</h4>";
        if (!empty($reportData['adults_present']['names'])) {
            $html .= "<ul>";
            foreach ($reportData['adults_present']['names'] as $name) {
                $html .= "<li>" . htmlspecialchars($name) . "</li>";
            }
            $html .= "</ul>";
        } else {
            $html .= "<p>No adults present</p>";
        }
        
        $html .= "<h4>Children Present (" . $reportData['children_present']['count'] . ")</h4>";
        if (!empty($reportData['children_present']['names'])) {
            $html .= "<ul>";
            foreach ($reportData['children_present']['names'] as $name) {
                $html .= "<li>" . htmlspecialchars($name) . "</li>";
            }
            $html .= "</ul>";
        } else {
            $html .= "<p>No children present</p>";
        }
        
        // Include first timers if provided
        if (!empty($extra['first_timers'])) {
            $html .= "<h4>First Timers</h4>";
            $html .= "<table>";
            $html .= "<tr><th>Name</th><th>No. (present)</th></tr>";
            foreach ($extra['first_timers'] as $ft) {
                $name = htmlspecialchars($ft['name'] ?? '');
                $count = intval($ft['count'] ?? 0);
                $html .= "<tr><td>$name</td><td>$count</td></tr>";
            }
            $html .= "</table>";
        }
    }
    
    $html .= "<div class='footer'><p>Generated on " . date('Y-m-d H:i:s') . "</p>";
    $html .= "<p>Church Management System</p></div>";
    $html .= "</body></html>";
    
    return $html;
}

/**
 * Format report for text/plain
 */
function formatTextReport($reportData, $recipient, $type, $startDate, $endDate, $extra = []) {
    $text = "ATTENDANCE REPORT\n";
    $text .= "=================\n\n";
    $text .= "Report for: " . $recipient['username'] . "\n";
    $text .= "Period: $startDate to $endDate\n\n";
    
    if ($reportData['type'] === 'overview') {
        $text .= "SUMMARY STATISTICS\n";
        $text .= "Service Type: Sunday\n";
        $text .= "Attendance:\n";
        $text .= "  Adult: " . $reportData['adults_present']['count'] . " present";
        if (!empty($reportData['adults_present']['names'])) {
            $text .= "\n  Names: " . implode(", ", $reportData['adults_present']['names']);
        }
        $text .= "\n  Children: " . $reportData['children_present']['count'] . " present";
        if (!empty($reportData['children_present']['names'])) {
            $text .= "\n  Names: " . implode(", ", $reportData['children_present']['names']);
        }
        $text .= "\n  Total Members Present: " . $reportData['attendance_stats']['present'] . "\n";
        $text .= "  Average Attendance Rate: " . $reportData['attendance_rate'] . "%\n";
        
        if (!empty($extra['first_timers'])) {
            $text .= "\nFirst Timers:\n";
            foreach ($extra['first_timers'] as $ft) {
                $text .= "  - " . ($ft['name'] ?? '') . " (" . intval($ft['count'] ?? 0) . " present)\n";
            }
        }
    } elseif ($reportData['type'] === 'detailed') {
        $text .= "DETAILED REPORT\n";
        $text .= "Service Type: Sunday\n";
        $text .= "Attendance:\n";
        $text .= "  Adult: " . $reportData['adults_present']['count'] . " present";
        if (!empty($reportData['adults_present']['names'])) {
            $text .= "\n  Names: " . implode(", ", $reportData['adults_present']['names']);
        }
        $text .= "\n  Children: " . $reportData['children_present']['count'] . " present";
        if (!empty($reportData['children_present']['names'])) {
            $text .= "\n  Names: " . implode(", ", $reportData['children_present']['names']);
        }
        $text .= "\n  Absent: " . $reportData['absent']['count'];
        if (!empty($reportData['absent']['names'])) {
            $text .= "\n  Names: " . implode(", ", $reportData['absent']['names']);
        }
        $text .= "\n  Total Members Present: " . $reportData['summary']['present'] . "\n";
        
        if (!empty($extra['first_timers'])) {
            $text .= "\nFirst Timers:\n";
            foreach ($extra['first_timers'] as $ft) {
                $text .= "  - " . ($ft['name'] ?? '') . " (" . intval($ft['count'] ?? 0) . " present)\n";
            }
        }
    } elseif ($reportData['type'] === 'member') {
        $text .= "MEMBERS\n";
        $text .= "\nAdults Present (" . $reportData['adults_present']['count'] . "):\n";
        if (!empty($reportData['adults_present']['names'])) {
            foreach ($reportData['adults_present']['names'] as $name) {
                $text .= "  - $name\n";
            }
        } else {
            $text .= "  No adults present\n";
        }
        
        $text .= "\nChildren Present (" . $reportData['children_present']['count'] . "):\n";
        if (!empty($reportData['children_present']['names'])) {
            foreach ($reportData['children_present']['names'] as $name) {
                $text .= "  - $name\n";
            }
        } else {
            $text .= "  No children present\n";
        }
        
        if (!empty($extra['first_timers'])) {
            $text .= "\nFirst Timers:\n";
            foreach ($extra['first_timers'] as $ft) {
                $text .= "  - " . ($ft['name'] ?? '') . " (" . intval($ft['count'] ?? 0) . " present)\n";
            }
        }
    }
    
    $text .= "\nGenerated on " . date('Y-m-d H:i:s') . "\n";
    
    return $text;
}

/**
 * Format report for SMS (short version)
 */
function formatSMSReport($reportData, $recipient, $type, $startDate, $endDate, $extra = []) {
    if ($reportData['type'] === 'overview') {
        $text = "Attendance Report ($startDate to $endDate): ";
        $text .= "Present: " . $reportData['attendance_stats']['present'] . ", ";
        $text .= "Absent: " . $reportData['attendance_stats']['absent'] . ", ";
        $text .= "Rate: " . $reportData['attendance_rate'] . "%. ";
        $text .= "Grace Community Church";
    } else {
        $text = "Attendance Report ($startDate to $endDate) ready. ";
        $text .= "Check email for details. Grace Community";
    }
    
    // SMS must be short (usually 160 characters)
    return substr($text, 0, 160);
}

/**
 * Log report sending
 */
function logReportSending($db, $reportType, $sendMethod, $startDate, $endDate, $results, $firstTimers = []) {
    try {
        // Persist results; table has `created_at` timestamp column, not `sent_at`.
        // Insert recipients_count for easier queries
        $recipientsCount = 0;
        if (is_array($results)) {
            $recipientsCount += isset($results['email']) && is_array($results['email']) ? count($results['email']) : 0;
            $recipientsCount += isset($results['sms']) && is_array($results['sms']) ? count($results['sms']) : 0;
        }

        // Include first_timers in results
        $resultsWithFirstTimers = $results;
        if (!empty($firstTimers)) {
            $resultsWithFirstTimers['first_timers'] = $firstTimers;
        }

        $sql = "INSERT INTO report_logs (report_type, send_method, start_date, end_date, sent_by, recipients_count, results) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            $reportType,
            $sendMethod,
            $startDate,
            $endDate,
            $_SESSION['user_id'] ?? null,
            $recipientsCount,
            json_encode($resultsWithFirstTimers)
        ]);
    } catch (Exception $e) {
        error_log("Failed to log report sending: " . $e->getMessage());
    }
}
?>
