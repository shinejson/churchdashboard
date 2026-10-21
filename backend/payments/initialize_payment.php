<?php
/**
 * Initialize Online Payment with Espees
 */

require_once '../config/security.php';
require_once '../config/database.php';
require_once 'espees_config.php';

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
    require_csrf_token();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($data['payment_type']) || empty($data['amount'])) {
        throw new Exception('Payment type and amount are required');
    }
    
    // Get member info
    $userStmt = $db->prepare("
        SELECT u.member_id, u.fellowship_id, m.first_name, m.last_name, m.email, m.phone
        FROM users u
        LEFT JOIN members m ON u.member_id = m.id
        WHERE u.id = ?
    ");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['member_id']) {
        throw new Exception('Member profile not found');
    }
    
    // Generate unique reference
    $reference = 'PAY-' . strtoupper(uniqid()) . '-' . time();
    
    // Create pending payment record
    $stmt = $db->prepare("
        INSERT INTO payment_transactions 
        (member_id, fellowship_id, payment_type, partnership_arm_id, amount, 
         payment_method, reference, status, payment_date, created_at)
        VALUES (?, ?, ?, ?, ?, 'online', ?, 'pending', ?, NOW())
    ");
    
    $stmt->execute([
        $user['member_id'],
        $user['fellowship_id'],
        $data['payment_type'],
        $data['partnership_arm_id'] ?? null,
        $data['amount'],
        $reference,
        date('Y-m-d')
    ]);
    
    $transaction_id = $db->lastInsertId();
    
    // Initialize Espees payment
    $paymentData = [
        'amount' => $data['amount'],
        'reference' => $reference,
        'email' => $user['email'],
        'name' => $user['first_name'] . ' ' . $user['last_name'],
        'phone' => $user['phone'],
        'payment_type' => $data['payment_type'],
        'member_id' => $user['member_id'],
        'fellowship_id' => $user['fellowship_id'],
        'partnership_arm_id' => $data['partnership_arm_id'] ?? null
    ];
    
    $espeesResponse = initializeEspeesPayment($paymentData);
    
    if ($espeesResponse['success']) {
        // Update transaction with Espees reference
        $updateStmt = $db->prepare("
            UPDATE payment_transactions 
            SET espees_reference = ?, payment_url = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $espeesResponse['data']['reference'] ?? $reference,
            $espeesResponse['data']['payment_url'] ?? null,
            $transaction_id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment initialized successfully',
            'data' => [
                'transaction_id' => $transaction_id,
                'reference' => $reference,
                'payment_url' => $espeesResponse['data']['payment_url'],
                'amount' => $data['amount']
            ]
        ]);
    } else {
        throw new Exception($espeesResponse['message'] ?? 'Payment initialization failed');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
