<?php
/**
 * Recurring Payments Management
 * Allows members to set up automatic recurring payments
 */

require_once '../config/security.php';
require_once '../config/database.php';

secure_session_start();
allow_cors();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['payment_type']) || empty($data['amount']) || empty($data['frequency'])) {
            throw new Exception('Payment type, amount, and frequency are required');
        }
        
        // Get member info
        $userStmt = $db->prepare("SELECT member_id, fellowship_id FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !$user['member_id']) {
            throw new Exception('Member profile not found');
        }
        
        $member_id = $user['member_id'];
        $fellowship_id = $user['fellowship_id'];
        
        // Create recurring payment schedule
        $stmt = $db->prepare("
            INSERT INTO recurring_payments 
            (member_id, fellowship_id, payment_type, partnership_arm_id, amount, 
             frequency, start_date, next_payment_date, payment_method, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $start_date = $data['start_date'] ?? date('Y-m-d');
        $next_payment_date = calculateNextPaymentDate($start_date, $data['frequency']);
        
        $stmt->execute([
            $member_id,
            $fellowship_id,
            $data['payment_type'],
            $data['partnership_arm_id'] ?? null,
            $data['amount'],
            $data['frequency'], // daily, weekly, monthly, yearly
            $start_date,
            $next_payment_date,
            $data['payment_method'] ?? 'online'
        ]);
        
        $recurring_id = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Recurring payment set up successfully',
            'data' => [
                'recurring_id' => $recurring_id,
                'next_payment_date' => $next_payment_date
            ]
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get member's recurring payments
        $userStmt = $db->prepare("SELECT member_id FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !$user['member_id']) {
            throw new Exception('Member profile not found');
        }
        
        $stmt = $db->prepare("
            SELECT rp.*, pa.name as partnership_arm_name
            FROM recurring_payments rp
            LEFT JOIN partnership_arms pa ON rp.partnership_arm_id = pa.id
            WHERE rp.member_id = ?
            ORDER BY rp.is_active DESC, rp.next_payment_date ASC
        ");
        $stmt->execute([$user['member_id']]);
        $recurring_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $recurring_payments
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        require_csrf_token();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['recurring_id'])) {
            throw new Exception('Recurring payment ID is required');
        }
        
        // Verify ownership
        $userStmt = $db->prepare("SELECT member_id FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        $checkStmt = $db->prepare("
            SELECT id FROM recurring_payments 
            WHERE id = ? AND member_id = ?
        ");
        $checkStmt->execute([$data['recurring_id'], $user['member_id']]);
        
        if ($checkStmt->rowCount() === 0) {
            throw new Exception('Recurring payment not found or unauthorized');
        }
        
        // Update recurring payment
        if (isset($data['is_active'])) {
            $stmt = $db->prepare("
                UPDATE recurring_payments 
                SET is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$data['is_active'], $data['recurring_id']]);
            
            $action = $data['is_active'] ? 'activated' : 'paused';
            
            echo json_encode([
                'success' => true,
                'message' => "Recurring payment $action successfully"
            ]);
        } else {
            throw new Exception('No update data provided');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        require_csrf_token();
        
        $recurring_id = $_GET['id'] ?? null;
        
        if (!$recurring_id) {
            throw new Exception('Recurring payment ID is required');
        }
        
        // Verify ownership
        $userStmt = $db->prepare("SELECT member_id FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("
            DELETE FROM recurring_payments 
            WHERE id = ? AND member_id = ?
        ");
        $stmt->execute([$recurring_id, $user['member_id']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Recurring payment cancelled successfully'
            ]);
        } else {
            throw new Exception('Recurring payment not found or unauthorized');
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Calculate next payment date based on frequency
 */
function calculateNextPaymentDate($start_date, $frequency) {
    $date = new DateTime($start_date);
    
    switch ($frequency) {
        case 'daily':
            $date->modify('+1 day');
            break;
        case 'weekly':
            $date->modify('+1 week');
            break;
        case 'monthly':
            $date->modify('+1 month');
            break;
        case 'yearly':
            $date->modify('+1 year');
            break;
        default:
            $date->modify('+1 month');
    }
    
    return $date->format('Y-m-d');
}
