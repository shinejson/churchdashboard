<?php
// backend/members/send_birthday_wish.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/security.php';
require_once '../lib/EmailService.php';

allow_cors();
secure_session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed. Use POST."]);
    exit();
}

try {
    require_csrf_token();
    
    $data = json_decode(file_get_contents("php://input"), true);
    $member_id = $data['member_id'] ?? null;
    $message = trim($data['message'] ?? '');
    
    if (!$member_id) {
        throw new Exception("Member ID is required");
    }

    if (empty($message)) {
        $message = "Happy Birthday! Wishing you abundant blessings and joy today and always. From Grace Community Church.";
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT first_name, last_name, email, phone FROM members WHERE id = ? AND status = 'active'");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        throw new Exception("Member not found or inactive");
    }

    $errors = [];
    $success = [];

    // Send Email
    if (!empty($member['email'])) {
        $emailService = new EmailService();
        $subject = "Happy Birthday, " . $member['first_name'] . "!";
        
        $htmlBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px; text-align: center; background-color: #f9f9f9;'>
                <div style='background: linear-gradient(135deg, #118ab2, #06d6a0); padding: 30px; border-radius: 10px 10px 0 0; color: white;'>
                    <h1 style='margin:0; font-size: 28px;'>Happy Birthday!</h1>
                </div>
                <div style='padding: 30px; background: white;'>
                    <h2 style='color: #2c3e50;'>Dear " . htmlspecialchars($member['first_name']) . ",</h2>
                    <p style='font-size: 16px; color: #555; line-height: 1.6; text-align: left;'>" . nl2br(htmlspecialchars($message)) . "</p>
                </div>
                <div style='background: #f1f5f9; padding: 15px; border-radius: 0 0 10px 10px; font-size: 14px; color: #777;'>
                    <p>God bless you abundantly,<br><strong>Grace Community Church</strong></p>
                </div>
            </div>
        ";
        
        $sent = $emailService->send($member['email'], $subject, $htmlBody, $message);
        if ($sent) {
            $success[] = "Email delivered";
        } else {
            $errors[] = "Failed to send email. Check SMTP configuration.";
        }
    } else {
        $errors[] = "Member has no email address on file.";
    }

    echo json_encode([
        "message" => !empty($success) ? "Wish sent successfully" : "Could not send wish",
        "success" => $success,
        "errors" => $errors
    ]);

} catch (Exception $e) {
    error_log("Send birthday wish error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
