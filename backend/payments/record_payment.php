<?php
/**
 * Record Payment - Member Self-Service
 * Allows members to record their own payments (tithe, partnership, offerings)
 */

require_once '../config/security.php';
require_once '../config/database.php';

secure_session_start();
allow_cors();
header('Content-Type: application/json');

// Check authentication
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
        if (empty($data['payment_type']) || empty($data['amount']) || empty($data['payment_method'])) {
            throw new Exception('Payment type, amount, and payment method are required');
        }
        
        $payment_type = $data['payment_type']; // tithe, partnership, offering
        $amount = floatval($data['amount']);
        $payment_method = $data['payment_method'];
        $payment_date = !empty($data['payment_date']) ? $data['payment_date'] : date('Y-m-d');
        $reference = !empty($data['reference']) ? $data['reference'] : null;
        $notes = !empty($data['notes']) ? $data['notes'] : null;
        
        // Validate amount
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than zero');
        }
        
        // Get member_id from user
        $userStmt = $db->prepare("SELECT member_id, fellowship_id FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !$user['member_id']) {
            throw new Exception('Member profile not found');
        }
        
        $member_id = $user['member_id'];
        $fellowship_id = $user['fellowship_id'];
        
        $db->beginTransaction();
        
        if ($payment_type === 'tithe') {
            // Record tithe payment
            $stmt = $db->prepare("
                INSERT INTO tithe_transactions 
                (member_id, fellowship_id, amount, transaction_date, payment_method, reference, notes, recorded_by, recorded_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$member_id, $fellowship_id, $amount, $payment_date, $payment_method, $reference, $notes, $user_id]);
            $payment_id = $db->lastInsertId();
            
        } elseif ($payment_type === 'partnership') {
            // Partnership payment requires partnership_arm_id
            if (empty($data['partnership_arm_id'])) {
                throw new Exception('Partnership arm is required');
            }
            
            $partnership_arm_id = (int)$data['partnership_arm_id'];
            
            // Try with reference_number first, fallback to reference if column doesn't exist
            try {
                $stmt = $db->prepare("
                    INSERT INTO partnership_contributions 
                    (member_id, partnership_arm_id, amount, contribution_date, payment_method, reference_number, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$member_id, $partnership_arm_id, $amount, $payment_date, $payment_method, $reference, $notes]);
                $payment_id = $db->lastInsertId();
            } catch (PDOException $e) {
                // If reference_number column doesn't exist, try with reference
                if (strpos($e->getMessage(), 'reference_number') !== false) {
                    $stmt = $db->prepare("
                        INSERT INTO partnership_contributions 
                        (member_id, partnership_arm_id, amount, contribution_date, payment_method, notes, recorded_by, recorded_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$member_id, $partnership_arm_id, $amount, $payment_date, $payment_method, $notes, $user_id]);
                    $payment_id = $db->lastInsertId();
                } else {
                    throw $e;
                }
            }
            
        } elseif ($payment_type === 'offering') {
            // Record offering/other payment
            // Check if offerings table exists, if not use a general payments table
            try {
                $stmt = $db->prepare("
                    INSERT INTO offerings 
                    (member_id, fellowship_id, amount, offering_date, payment_method, reference, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$member_id, $fellowship_id, $amount, $payment_date, $payment_method, $reference, $notes]);
                $payment_id = $db->lastInsertId();
            } catch (PDOException $e) {
                // If offerings table doesn't exist, create it
                if (strpos($e->getMessage(), "doesn't exist") !== false) {
                    $db->exec("
                        CREATE TABLE IF NOT EXISTS `offerings` (
                          `id` int(11) NOT NULL AUTO_INCREMENT,
                          `member_id` int(11) NOT NULL,
                          `fellowship_id` int(11) DEFAULT NULL,
                          `amount` decimal(10,2) NOT NULL,
                          `offering_date` date NOT NULL,
                          `payment_method` varchar(50) DEFAULT NULL,
                          `reference` varchar(100) DEFAULT NULL,
                          `notes` text DEFAULT NULL,
                          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                          PRIMARY KEY (`id`),
                          KEY `idx_member_id` (`member_id`),
                          KEY `idx_fellowship_id` (`fellowship_id`),
                          KEY `idx_offering_date` (`offering_date`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                    
                    // Retry insert
                    $stmt = $db->prepare("
                        INSERT INTO offerings 
                        (member_id, fellowship_id, amount, offering_date, payment_method, reference, notes, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$member_id, $fellowship_id, $amount, $payment_date, $payment_method, $reference, $notes]);
                    $payment_id = $db->lastInsertId();
                } else {
                    throw $e;
                }
            }
        } else {
            throw new Exception('Invalid payment type');
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => ucfirst($payment_type) . ' payment recorded successfully',
            'data' => [
                'payment_id' => $payment_id,
                'payment_type' => $payment_type,
                'amount' => $amount,
                'payment_date' => $payment_date
            ]
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get payment options (partnership arms, etc.)
        
        // Get partnership arms
        $armsStmt = $db->query("
            SELECT id, name, description, target_amount
            FROM partnership_arms
            WHERE is_active = 1
            ORDER BY name
        ");
        $partnership_arms = $armsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get payment methods
        $payment_methods = [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'mobile_money' => 'Mobile Money',
            'cheque' => 'Cheque',
            'card' => 'Card Payment',
            'online' => 'Online Payment'
        ];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'partnership_arms' => $partnership_arms,
                'payment_methods' => $payment_methods
            ]
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
