<?php
header('Content-Type: application/json');
require_once dirname(dirname(__FILE__)) . '/config/security.php';
require_once dirname(dirname(__FILE__)) . '/lib/EmailService.php';

secure_session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate CSRF token
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

// Validate required fields
if (empty($input['to']) || empty($input['subject']) || empty($input['report_data'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$to = filter_var($input['to'], FILTER_VALIDATE_EMAIL);
if (!$to) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

$subject = htmlspecialchars($input['subject']);
$message = htmlspecialchars($input['message'] ?? '');
$reportData = $input['report_data'];

try {
    // Build HTML email content
    $htmlContent = buildServiceReportEmail($reportData, $message);
    
    // Send email using EmailService
    $emailService = new EmailService();
    $result = $emailService->sendEmail($to, $subject, $htmlContent);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Email sent successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Email sending error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error sending email: ' . $e->getMessage()
    ]);
}

/**
 * Build HTML email content for service report
 */
function buildServiceReportEmail($reportData, $userMessage = '') {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                background-color: #007bff;
                color: white;
                padding: 20px;
                text-align: center;
                border-radius: 5px 5px 0 0;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
            }
            .message-box {
                background-color: #f8f9fa;
                padding: 15px;
                margin: 20px 0;
                border-left: 4px solid #007bff;
            }
            .service-block {
                border: 2px solid #dee2e6;
                margin: 20px 0;
                padding: 20px;
                border-radius: 5px;
                background-color: #fff;
            }
            .service-header {
                background-color: #e9ecef;
                padding: 15px;
                margin: -20px -20px 20px -20px;
                border-radius: 3px 3px 0 0;
            }
            .service-header h3 {
                margin: 5px 0;
                color: #495057;
            }
            .attendance-table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
            }
            .attendance-table td {
                padding: 10px;
                border-bottom: 1px solid #dee2e6;
            }
            .attendance-table td:first-child {
                font-weight: bold;
                width: 40%;
            }
            .attendance-table td:last-child {
                text-align: right;
            }
            .attendance-table tr.total {
                background-color: #f8f9fa;
                font-weight: bold;
                border-top: 2px solid #495057;
            }
            .partnerships {
                background-color: #f8f9fa;
                padding: 15px;
                margin-top: 15px;
                border-radius: 5px;
            }
            .partnerships h4 {
                margin-top: 0;
                color: #495057;
            }
            .partnership-item {
                padding: 5px 0;
                margin-left: 20px;
            }
            .footer {
                text-align: center;
                padding: 20px;
                color: #6c757d;
                font-size: 12px;
                border-top: 1px solid #dee2e6;
                margin-top: 30px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Church Management System</h1>
            <p>Service Report (Combined)</p>
        </div>
    ';
    
    if (!empty($userMessage)) {
        $html .= '
        <div class="message-box">
            <strong>Message:</strong><br>
            ' . nl2br($userMessage) . '
        </div>
        ';
    }
    
    foreach ($reportData as $service) {
        $html .= '
        <div class="service-block">
            <div class="service-header">
                <h3>Fellowship: ' . htmlspecialchars($service['fellowship_name']) . '</h3>
                <h3>Service Type: ' . htmlspecialchars($service['service_type']) . '</h3>
                <h3>Date: ' . date('F j, Y', strtotime($service['service_date'])) . '</h3>
            </div>
            
            <h4>ATTENDANCE</h4>
            <table class="attendance-table">
                <tr>
                    <td>Adults</td>
                    <td>' . $service['attendance']['adults'] . '</td>
                </tr>
                <tr>
                    <td>Teens</td>
                    <td>' . $service['attendance']['teens'] . '</td>
                </tr>
                <tr>
                    <td>Children</td>
                    <td>' . $service['attendance']['children'] . '</td>
                </tr>
                <tr class="total">
                    <td>Total Present</td>
                    <td>' . $service['attendance']['total'] . '</td>
                </tr>
                <tr>
                    <td>First Timers</td>
                    <td>' . $service['attendance']['first_timers'] . '</td>
                </tr>
                <tr class="total">
                    <td>Offering</td>
                    <td>GHS ' . number_format($service['offering'], 2) . '</td>
                </tr>
            </table>
        ';
        
        if (!empty($service['partnerships'])) {
            $html .= '
            <div class="partnerships">
                <h4>PARTNERSHIP CONTRIBUTIONS</h4>
            ';
            
            foreach ($service['partnerships'] as $partnership) {
                $html .= '
                <div class="partnership-item">
                    • ' . htmlspecialchars($partnership['partnership_arm']) . ': 
                    ' . htmlspecialchars($partnership['member_name']) . ' - 
                    GHS ' . number_format($partnership['amount'], 2) . '
                </div>
                ';
            }
            
            $html .= '
            </div>
            ';
        }
        
        $html .= '
        </div>
        ';
    }
    
    $html .= '
        <div class="footer">
            <p>This report was generated on ' . date('F j, Y \a\t g:i A') . '</p>
            <p>Church Management System - Automated Report</p>
        </div>
    </body>
    </html>
    ';
    
    return $html;
}
