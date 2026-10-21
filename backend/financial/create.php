<?php
// backend/financial/create.php
// CRITICAL: Start output buffering FIRST, before ANY output
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/security.php';

// Disable ALL error output - log only
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Set headers
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

$response = [
    "success" => false,
    "message" => "",
    "transaction_id" => null
];

try {
    // Database connection
    $configPath = dirname(__DIR__) . '/config/database.php';
    if (!file_exists($configPath)) {
        throw new Exception("Database config not found");
    }
    
    require_once $configPath;
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Fellowship data isolation
    $scopeId = current_fellowship_scope();
    
    // Get input
    $rawInput = file_get_contents("php://input");
    if (empty($rawInput)) {
        throw new Exception("No input data");
    }
    
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON: " . json_last_error_msg());
    }
    
    // If scoped, force fellowship_id in payload to the scoped value
    if ($scopeId !== null) {
        $input['fellowship_id'] = $scopeId;
    }

    // Validate
    if (empty($input['date']) || empty($input['fellowship_id']) || empty($input['service_id'])) {
        throw new Exception("Missing required fields");
    }
    
    // Get user - check if session user exists, otherwise find first admin
    $recorded_by = null;
    if (isset($_SESSION['user_id'])) {
        // Verify the session user exists
        $userCheck = $db->prepare("SELECT id FROM users WHERE id = :user_id LIMIT 1");
        $userCheck->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $userCheck->execute();
        if ($userCheck->rowCount() > 0) {
            $recorded_by = $_SESSION['user_id'];
        }
    }
    
    // If no valid session user, try to get first admin user
    if ($recorded_by === null) {
        $adminQuery = $db->prepare("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        $adminQuery->execute();
        $admin = $adminQuery->fetch(PDO::FETCH_ASSOC);
        if ($admin) {
            $recorded_by = $admin['id'];
        } else {
            // If no admin exists, get any user
            $anyUserQuery = $db->prepare("SELECT id FROM users ORDER BY id ASC LIMIT 1");
            $anyUserQuery->execute();
            $anyUser = $anyUserQuery->fetch(PDO::FETCH_ASSOC);
            if ($anyUser) {
                $recorded_by = $anyUser['id'];
            } else {
                throw new Exception("No users found in database. Please create a user first.");
            }
        }
    }
    
    // Check for duplicate transaction (same date, fellowship, and service)
    $duplicateCheck = $db->prepare("
        SELECT id FROM financial_transactions 
        WHERE transaction_date = :date 
        AND fellowship_id = :fellowship 
        AND service_id = :service
        LIMIT 1
    ");
    $duplicateCheck->bindParam(':date', $input['date']);
    $duplicateCheck->bindParam(':fellowship', $input['fellowship_id'], PDO::PARAM_INT);
    $duplicateCheck->bindParam(':service', $input['service_id'], PDO::PARAM_INT);
    $duplicateCheck->execute();
    
    if ($duplicateCheck->rowCount() > 0) {
        throw new Exception("A financial transaction for this date, fellowship, and service already exists. Please edit the existing record instead.");
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // Calculate amounts
    $offeringAmount = isset($input['offering_amount']) ? floatval($input['offering_amount']) : 0;
    $titheTotal = isset($input['tithe_total']) ? floatval($input['tithe_total']) : 0;
    
    // Calculate partnership total from records
    $partnershipTotal = 0;
    if (!empty($input['partnership_records']) && is_array($input['partnership_records'])) {
        foreach ($input['partnership_records'] as $record) {
            if (isset($record['total'])) {
                $partnershipTotal += floatval($record['total']);
            }
        }
    }
    
    $totalAmount = $offeringAmount + $titheTotal + $partnershipTotal;
    
    if ($totalAmount <= 0) {
        throw new Exception("Total amount must be greater than 0");
    }
    
    // Build description
    $parts = [];
    if ($offeringAmount > 0) $parts[] = "Offering: GHS " . number_format($offeringAmount, 2);
    if ($titheTotal > 0) $parts[] = "Tithe: GHS " . number_format($titheTotal, 2);
    if ($partnershipTotal > 0) $parts[] = "Partnership: GHS " . number_format($partnershipTotal, 2);
    $description = implode(", ", $parts);
    
    // Category
    $category = 'offering';
    if ($titheTotal > 0 && $offeringAmount > 0) {
        $category = 'tithe_offering';
    } elseif ($titheTotal > 0) {
        $category = 'tithe';
    }
    
    // Insert main transaction
    $query = "
        INSERT INTO financial_transactions (
            transaction_date, fellowship_id, service_id, type, category,
            amount, description, payment_method, recorded_by, recorded_at
        ) VALUES (
            :date, :fellowship, :service, 'income', :category,
            :amount, :description, :payment, :recorded_by, NOW()
        )
    ";
    
    $stmt = $db->prepare($query);
    $payment = isset($input['payment_method']) ? $input['payment_method'] : 'cash';
    
    $stmt->bindParam(':date', $input['date']);
    $stmt->bindParam(':fellowship', $input['fellowship_id'], PDO::PARAM_INT);
    $stmt->bindParam(':service', $input['service_id'], PDO::PARAM_INT);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':amount', $totalAmount);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':payment', $payment);
    $stmt->bindParam(':recorded_by', $recorded_by, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert transaction");
    }
    
    $transactionId = $db->lastInsertId();
    
    // Insert tithe records
    if (!empty($input['tithe_members']) && is_array($input['tithe_members'])) {
        $titheQuery = "
            INSERT INTO tithe_transactions (
                financial_transaction_id, member_id, fellowship_id, amount,
                period, transaction_date, payment_method, notes, recorded_by, recorded_at
            ) VALUES (
                :trans_id, :member_id, :fellowship_id, :amount,
                :period, :date, :payment, :notes, :recorded_by, NOW()
            )
        ";
        
        $titheStmt = $db->prepare($titheQuery);
        $period = date('Y-m', strtotime($input['date']));
        
        foreach ($input['tithe_members'] as $tithe) {
            if (empty($tithe['memberId']) || empty($tithe['amount'])) continue;
            
            $titheStmt->bindValue(':trans_id', $transactionId, PDO::PARAM_INT);
            $titheStmt->bindValue(':member_id', $tithe['memberId'], PDO::PARAM_INT);
            $titheStmt->bindValue(':fellowship_id', $input['fellowship_id'], PDO::PARAM_INT);
            $titheStmt->bindValue(':amount', floatval($tithe['amount']));
            $titheStmt->bindValue(':period', $period);
            $titheStmt->bindValue(':date', $input['date']);
            $titheStmt->bindValue(':payment', $payment);
            $titheStmt->bindValue(':notes', 'Tithe payment');
            $titheStmt->bindValue(':recorded_by', $recorded_by, PDO::PARAM_INT);
            
            $titheStmt->execute();
        }
    }
    
    // Insert partnership contributions
    if (!empty($input['partnership_records']) && is_array($input['partnership_records'])) {
        $partQuery = "
            INSERT INTO partnership_contributions (
                financial_transaction_id, partnership_arm_id, member_id,
                amount, contribution_date, recorded_by, recorded_at
            ) VALUES (
                :trans_id, :arm_id, :member_id,
                :amount, :date, :recorded_by, NOW()
            )
        ";
        
        $partStmt = $db->prepare($partQuery);
        
        foreach ($input['partnership_records'] as $record) {
            if (empty($record['armId']) || empty($record['members'])) continue;
            
            foreach ($record['members'] as $member) {
                if (empty($member['memberId']) || empty($member['amount'])) continue;
                
                $partStmt->bindValue(':trans_id', $transactionId, PDO::PARAM_INT);
                $partStmt->bindValue(':arm_id', $record['armId'], PDO::PARAM_INT);
                $partStmt->bindValue(':member_id', $member['memberId'], PDO::PARAM_INT);
                $partStmt->bindValue(':amount', floatval($member['amount']));
                $partStmt->bindValue(':date', $input['date']);
                $partStmt->bindValue(':recorded_by', $recorded_by, PDO::PARAM_INT);
                
                $partStmt->execute();
            }
        }
    }
    
    // Commit
    $db->commit();
    
    $response['success'] = true;
    $response['message'] = "Transaction saved successfully";
    $response['transaction_id'] = $transactionId;
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Financial create error: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

// CRITICAL: Clean buffer and output JSON
ob_end_clean();
echo json_encode($response);
exit();