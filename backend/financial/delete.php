<?php
// backend/financial/delete.php
ob_start();
session_start();
require_once '../config/security.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

try {
    include_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Fellowship data isolation
    $scopeId = current_fellowship_scope();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['id'])) {
        throw new Exception("Transaction ID is required");
    }
    
    $id = $input['id'];
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Check if transaction exists and, if scoped, belongs to this fellowship
        $checkQuery = "SELECT id, fellowship_id FROM financial_transactions WHERE id = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            throw new Exception("Transaction not found");
        }

        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($scopeId !== null && (int)$existing['fellowship_id'] !== $scopeId) {
            throw new Exception("You are not allowed to delete this transaction");
        }
        
        // Check if there are related tithe transactions
        $titheQuery = "SELECT id FROM tithe_transactions WHERE financial_transaction_id = :id";
        $titheStmt = $db->prepare($titheQuery);
        $titheStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $titheStmt->execute();
        
        if ($titheStmt->rowCount() > 0) {
            // Delete related tithe transactions first
            $deleteTitheQuery = "DELETE FROM tithe_transactions WHERE financial_transaction_id = :id";
            $deleteTitheStmt = $db->prepare($deleteTitheQuery);
            $deleteTitheStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $deleteTitheStmt->execute();
        }
        
        // Check if there are related partnership transactions
        $tableCheck = $db->query("SHOW TABLES LIKE 'partnership_transactions'");
        if ($tableCheck->rowCount() > 0) {
            $partnershipQuery = "SELECT id FROM partnership_transactions WHERE financial_transaction_id = :id";
            $partnershipStmt = $db->prepare($partnershipQuery);
            $partnershipStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $partnershipStmt->execute();
            
            if ($partnershipStmt->rowCount() > 0) {
                $deletePartnershipQuery = "DELETE FROM partnership_transactions WHERE financial_transaction_id = :id";
                $deletePartnershipStmt = $db->prepare($deletePartnershipQuery);
                $deletePartnershipStmt->bindParam(':id', $id, PDO::PARAM_INT);
                $deletePartnershipStmt->execute();
            }
        }
        
        // Delete the main transaction
        $query = "DELETE FROM financial_transactions WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $db->commit();
            
            ob_end_clean();
            echo json_encode([
                "success" => true,
                "message" => "Transaction and related records deleted successfully"
            ]);
        } else {
            throw new Exception("Failed to delete transaction");
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>