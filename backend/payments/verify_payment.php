<?php
/**
 * Payment Verification Workflow
 * Allows admins to verify and approve member payments
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
$user_role = $_SESSION['role'] ?? 'member';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get pending payments for verification
        if ($user_role !== 'admin' && $user_role !== 'pastor') {
            throw new Exception('Unauthorized access');
        }
        
        $status = $_GET['status'] ?? 'pending';
        
        $stmt = $db->prepare("
            SELECT pt.*, 
                   m.first_name, m.last_name, m.email, m.phone,
                   f.name as fellowship_name,
                   pa.name as partnership_arm_name,
                   u.username as verified_by_username
            FROM payment_transactions pt
            LEFT JOIN members m ON pt.member_id = m.id
            LEFT JOIN fellowships f ON pt.fellowship_id = f.id
            LEFT JOIN partnership_arms pa ON pt.partnership_arm_id = pa.id
            LEFT JOIN users u ON pt.verified_by = u.id
            WHERE pt.status = ?
            ORDER BY pt.created_at DESC
            LIMIT 100
        ");
        $stmt->execute([$status]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $payments
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token();
        
        // Verify/Approve payment
        if ($user_role !== 'admin' && $user_role !== 'pastor') {
            throw new Exception('Unauthorized access');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['transaction_id']) || empty($data['action'])) {
            throw new Exception('Transaction ID and action are required');
        }
        
        $transaction_id = $data['transaction_id'];
        $action = $data['action']; // approve, reject
        $notes = $data['notes'] ?? '';
        
        // Get transaction details
        $stmt = $db->prepare("SELECT * FROM payment_transactions WHERE id = ?");
        $stmt->execute([$transaction_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            throw new Exception('Transaction not found');
        }
        
        $db->beginTransaction();
        
        if ($action === 'approve') {
            // Update transaction status
            $updateStmt = $db->prepare("
                UPDATE payment_transactions 
                SET status = 'verified',
                    verified_by = ?,
                    verified_at = NOW(),
                    verification_notes = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$_SESSION['user_id'], $notes, $transaction_id]);
            
            // Record payment in appropriate table
            if ($transaction['payment_type'] === 'tithe') {
                $insertStmt = $db->prepare("
                    INSERT INTO tithe_transactions 
                    (member_id, fellowship_id, amount, transaction_date, payment_method, 
                     reference_number, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $insertStmt->execute([
                    $transaction['member_id'],
                    $transaction['fellowship_id'],
                    $transaction['amount'],
                    $transaction['payment_date'],
                    $transaction['payment_method'],
                    $transaction['reference'],
                    'Verified by admin: ' . $notes
                ]);
                
            } elseif ($transaction['payment_type'] === 'partnership') {
                $insertStmt = $db->prepare("
                    INSERT INTO partnership_contributions 
                    (member_id, partnership_arm_id, amount, contribution_date, payment_method, 
                     reference_number, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $insertStmt->execute([
                    $transaction['member_id'],
                    $transaction['partnership_arm_id'],
                    $transaction['amount'],
                    $transaction['payment_date'],
                    $transaction['payment_method'],
                    $transaction['reference'],
                    'Verified by admin: ' . $notes
                ]);
                
            } elseif ($transaction['payment_type'] === 'offering') {
                $insertStmt = $db->prepare("
                    INSERT INTO offerings 
                    (member_id, fellowship_id, amount, offering_date, payment_method, 
                     reference_number, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $insertStmt->execute([
                    $transaction['member_id'],
                    $transaction['fellowship_id'],
                    $transaction['amount'],
                    $transaction['payment_date'],
                    $transaction['payment_method'],
                    $transaction['reference'],
                    'Verified by admin: ' . $notes
                ]);
            }
            
            $db->commit();
            
            // Generate receipt and send email
            require_once 'generate_receipt.php';
            $receiptPath = generatePaymentReceipt($transaction_id);
            sendPaymentConfirmationEmail($transaction_id, $receiptPath);
            
            echo json_encode([
                'success' => true,
                'message' => 'Payment approved and verified successfully'
            ]);
            
        } elseif ($action === 'reject') {
            // Reject payment
            $updateStmt = $db->prepare("
                UPDATE payment_transactions 
                SET status = 'rejected',
                    verified_by = ?,
                    verified_at = NOW(),
                    verification_notes = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$_SESSION['user_id'], $notes, $transaction_id]);
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Payment rejected'
            ]);
            
        } else {
            throw new Exception('Invalid action');
        }
        
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
