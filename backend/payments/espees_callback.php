<?php
/**
 * Espees Payment Callback Handler
 * Handles payment verification and updates transaction status
 */

require_once '../config/database.php';
require_once 'espees_config.php';
require_once 'generate_receipt.php';
require_once '../lib/EmailService.php';

header('Content-Type: application/json');

try {
    $db = (new Database())->getConnection();
    
    // Get callback data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || empty($data['reference'])) {
        throw new Exception('Invalid callback data');
    }
    
    $reference = $data['reference'];
    
    // Verify payment with Espees
    $verification = verifyEspeesPayment($reference);
    
    if (!$verification['success']) {
        throw new Exception('Payment verification failed');
    }
    
    $paymentStatus = $verification['data']['status'] ?? 'failed';
    $amount = $verification['data']['amount'] ?? 0;
    
    // Find transaction in database
    $stmt = $db->prepare("
        SELECT * FROM payment_transactions 
        WHERE reference = ? OR espees_reference = ?
    ");
    $stmt->execute([$reference, $reference]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        throw new Exception('Transaction not found');
    }
    
    $db->beginTransaction();
    
    if ($paymentStatus === 'success' || $paymentStatus === 'completed') {
        // Update transaction status
        $updateStmt = $db->prepare("
            UPDATE payment_transactions 
            SET status = 'verified', 
                verified_at = NOW(),
                espees_response = ?
            WHERE id = ?
        ");
        $updateStmt->execute([json_encode($verification['data']), $transaction['id']]);
        
        // Record payment in appropriate table
        if ($transaction['payment_type'] === 'tithe') {
            $insertStmt = $db->prepare("
                INSERT INTO tithe_transactions 
                (member_id, fellowship_id, amount, transaction_date, payment_method, 
                 reference_number, notes, created_at)
                VALUES (?, ?, ?, ?, 'online', ?, 'Online payment via Espees', NOW())
            ");
            $insertStmt->execute([
                $transaction['member_id'],
                $transaction['fellowship_id'],
                $amount,
                $transaction['payment_date'],
                $reference
            ]);
            
        } elseif ($transaction['payment_type'] === 'partnership') {
            $insertStmt = $db->prepare("
                INSERT INTO partnership_contributions 
                (member_id, partnership_arm_id, amount, contribution_date, payment_method, 
                 reference_number, notes, created_at)
                VALUES (?, ?, ?, ?, 'online', ?, 'Online payment via Espees', NOW())
            ");
            $insertStmt->execute([
                $transaction['member_id'],
                $transaction['partnership_arm_id'],
                $amount,
                $transaction['payment_date'],
                $reference
            ]);
            
        } elseif ($transaction['payment_type'] === 'offering') {
            $insertStmt = $db->prepare("
                INSERT INTO offerings 
                (member_id, fellowship_id, amount, offering_date, payment_method, 
                 reference_number, notes, created_at)
                VALUES (?, ?, ?, ?, 'online', ?, 'Online payment via Espees', NOW())
            ");
            $insertStmt->execute([
                $transaction['member_id'],
                $transaction['fellowship_id'],
                $amount,
                $transaction['payment_date'],
                $reference
            ]);
        }
        
        $db->commit();
        
        // Generate PDF receipt
        $receiptPath = generatePaymentReceipt($transaction['id']);
        
        // Send email confirmation
        sendPaymentConfirmationEmail($transaction['id'], $receiptPath);
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment verified and recorded successfully'
        ]);
        
    } else {
        // Payment failed
        $updateStmt = $db->prepare("
            UPDATE payment_transactions 
            SET status = 'failed',
                espees_response = ?
            WHERE id = ?
        ");
        $updateStmt->execute([json_encode($verification['data']), $transaction['id']]);
        
        $db->commit();
        
        echo json_encode([
            'success' => false,
            'message' => 'Payment failed or cancelled'
        ]);
    }
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log('Espees Callback Error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
