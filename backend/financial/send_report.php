<?php
/**
 * Send Financial Report via Email or SMS
 * Generates and sends financial reports to recipients
 */

// Prevent any output before JSON
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/EmailService.php';
require_once __DIR__ . '/../lib/SMSService.php';

// Start session
session_start();

// Set headers
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit();
}

// Custom error handler
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    if (empty($data['report_type']) || empty($data['send_method'])) {
        throw new Exception("Report type and send method are required");
    }
    
    $reportType = $data['report_type']; // 'overview', 'detailed'
    $sendMethod = $data['send_method']; // 'email', 'sms', 'both'
    $startDate = $data['start_date'] ?? date('Y-m-01');
    $endDate = $data['end_date'] ?? date('Y-m-d');
    $recipientIds = $data['recipient_ids'] ?? [];
    $fellowshipId = $data['fellowship_id'] ?? null;
    
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Generate report data
    $reportData = generateFinancialReport($db, $reportType, $startDate, $endDate, $fellowshipId);
    
    // Get recipients
    $recipients = getRecipients($db, $recipientIds);
    
    if (empty($recipients)) {
        throw new Exception("No valid recipients found");
    }
    
    $sendResults = ['email' => [], 'sms' => []];
    
    // Send via Email
    if (in_array($sendMethod, ['email', 'both'])) {
        $emailService = new EmailService();
        $dateRangeDisplay = date('M d, Y', strtotime($startDate)) . " to " . date('M d, Y', strtotime($endDate));
        
        foreach ($recipients as $recipient) {
            if (!empty($recipient['email'])) {
                $subject = "Financial Report - " . $dateRangeDisplay;
                $htmlBody = formatEmailReport($reportData, $recipient, $startDate, $endDate);
                $textBody = formatTextReport($reportData, $recipient, $startDate, $endDate);
                
                $result = $emailService->send($recipient['email'], $subject, $htmlBody, $textBody);
                
                $sendResults['email'][] = [
                    'recipient' => $recipient['email'],
                    'success' => $result
                ];
            }
        }
    }
    
    // Log the report sending (using same table as attendance for consistency)
    logReportSending($db, "financial_" . $reportType, $sendMethod, $startDate, $endDate, $sendResults);
    
    echo json_encode([
        "success" => true,
        "message" => "Financial report sent successfully",
        "results" => $sendResults
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

/**
 * Generate financial report data
 */
function generateFinancialReport($db, $type, $startDate, $endDate, $fellowshipId = null) {
    // 1. Get Transaction Summary
    $query = "SELECT 
                SUM(amount) as total_amount,
                COUNT(*) as transaction_count
              FROM financial_transactions 
              WHERE transaction_date BETWEEN :start AND :end";
    if ($fellowshipId) $query .= " AND fellowship_id = :fellowship";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start', $startDate);
    $stmt->bindParam(':end', $endDate);
    if ($fellowshipId) $stmt->bindParam(':fellowship', $fellowshipId);
    $stmt->execute();
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Get Tithe Summary
    $query = "SELECT SUM(tt.amount) as total_tithe FROM tithe_transactions tt
              JOIN financial_transactions ft ON tt.financial_transaction_id = ft.id
              WHERE ft.transaction_date BETWEEN :start AND :end";
    if ($fellowshipId) $query .= " AND ft.fellowship_id = :fellowship";
    $stmt = $db->prepare($query);
    $stmt->execute(['start' => $startDate, 'end' => $endDate] + ($fellowshipId ? ['fellowship' => $fellowshipId] : []));
    $tithe = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Get Partnership Summary by Arm
    $query = "SELECT pa.name as arm_name, SUM(pc.amount) as total_amount
              FROM partnership_contributions pc
              JOIN partnership_arms pa ON pc.partnership_arm_id = pa.id
              JOIN financial_transactions ft ON pc.financial_transaction_id = ft.id
              WHERE ft.transaction_date BETWEEN :start AND :end";
    if ($fellowshipId) $query .= " AND ft.fellowship_id = :fellowship";
    $query .= " GROUP BY pa.id";
    $stmt = $db->prepare($query);
    $stmt->execute(['start' => $startDate, 'end' => $endDate] + ($fellowshipId ? ['fellowship' => $fellowshipId] : []));
    $partnerships = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $report = [
        'summary' => $summary,
        'tithe_total' => $tithe['total_tithe'] ?? 0,
        'partnership_summary' => $partnerships,
        'offering_total' => ($summary['total_amount'] ?? 0) - ($tithe['total_tithe'] ?? 0) - array_sum(array_column($partnerships, 'total_amount'))
    ];

    if ($type === 'detailed') {
        $query = "SELECT ft.*, f.name as fellowship_name, s.name as service_name
                  FROM financial_transactions ft
                  LEFT JOIN fellowships f ON ft.fellowship_id = f.id
                  LEFT JOIN services s ON ft.service_id = s.id
                  WHERE ft.transaction_date BETWEEN :start AND :end";
        if ($fellowshipId) $query .= " AND ft.fellowship_id = :fellowship";
        $query .= " ORDER BY ft.transaction_date DESC";
        $stmt = $db->prepare($query);
        $stmt->execute(['start' => $startDate, 'end' => $endDate] + ($fellowshipId ? ['fellowship' => $fellowshipId] : []));
        $report['transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $report;
}

function getRecipients($db, $ids) {
    if (empty($ids)) return [];
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $db->prepare("SELECT id, username, email FROM users WHERE id IN ($placeholders) AND status = 'active'");
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function formatEmailReport($data, $user, $start, $end) {
    $htmlArr = [
        "<h2>Financial Report</h2>",
        "<p>Period: $start to $end</p>",
        "<p>Recipient: {$user['username']}</p>",
        "<h3>Summary</h3>",
        "<ul>",
        "<li>Total Recorded: GHS " . number_format($data['summary']['total_amount'], 2) . "</li>",
        "<li>Total Offering: GHS " . number_format($data['offering_total'], 2) . "</li>",
        "<li>Total Tithe: GHS " . number_format($data['tithe_total'], 2) . "</li>",
        "</ul>",
        "<h4>Partnership Breakdown</h4>",
        "<ul>"
    ];
    foreach ($data['partnership_summary'] as $p) {
        $htmlArr[] = "<li>{$p['arm_name']}: GHS " . number_format($p['total_amount'], 2) . "</li>";
    }
    $htmlArr[] = "</ul>";

    if (!empty($data['transactions'])) {
        $htmlArr[] = "<h3>Transaction List</h3>";
        $htmlArr[] = "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        $htmlArr[] = "<tr><th>Date</th><th>Fellowship</th><th>Service</th><th>Amount</th><th>Description</th></tr>";
        foreach ($data['transactions'] as $t) {
            $htmlArr[] = "<tr>
                            <td>{$t['transaction_date']}</td>
                            <td>{$t['fellowship_name']}</td>
                            <td>{$t['service_name']}</td>
                            <td>GHS " . number_format($t['amount'], 2) . "</td>
                            <td>{$t['description']}</td>
                          </tr>";
        }
        $htmlArr[] = "</table>";
    }

    return implode("\n", $htmlArr);
}

function formatTextReport($data, $user, $start, $end) {
    $text = "Financial Report ($start to $end)\n";
    $text .= "Total: GHS " . number_format($data['summary']['total_amount'], 2) . "\n";
    $text .= "Offering: GHS " . number_format($data['offering_total'], 2) . "\n";
    $text .= "Tithe: GHS " . number_format($data['tithe_total'], 2) . "\n";
    return $text;
}

function logReportSending($db, $type, $method, $start, $end, $results) {
    $sql = "INSERT INTO report_logs (report_type, send_method, start_date, end_date, sent_by, recipients_count, results) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $db->prepare($sql)->execute([
        $type, $method, $start, $end, $_SESSION['user_id'], count($results['email']), json_encode($results)
    ]);
}
