<?php
// backend/financial/update.php
ob_start();
session_start();
require_once '../config/security.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, POST, OPTIONS");
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
        throw new Exception("Invalid input data");
    }
    
    $id = $input['id'];
    
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
        throw new Exception("You are not allowed to modify this transaction");
    }
    
    // Build update query based on provided fields
    $updateFields = [];
    $params = [':id' => $id];
    
    if (isset($input['transaction_date'])) {
        $updateFields[] = "transaction_date = :transaction_date";
        $params[':transaction_date'] = $input['transaction_date'];
    }
    
    if (isset($input['fellowship_id'])) {
        // If scoped, force fellowship_id to the scoped value
        $updateFields[] = "fellowship_id = :fellowship_id";
        $params[':fellowship_id'] = $scopeId !== null ? $scopeId : $input['fellowship_id'];
    }
    
    if (isset($input['service_id'])) {
        $updateFields[] = "service_id = :service_id";
        $params[':service_id'] = $input['service_id'];
    }
    
    if (isset($input['type'])) {
        $updateFields[] = "type = :type";
        $params[':type'] = $input['type'];
    }
    
    if (isset($input['category'])) {
        $updateFields[] = "category = :category";
        $params[':category'] = $input['category'];
    }
    
    if (isset($input['amount'])) {
        $updateFields[] = "amount = :amount";
        $params[':amount'] = $input['amount'];
    }
    
    if (isset($input['description'])) {
        $updateFields[] = "description = :description";
        $params[':description'] = $input['description'];
    }
    
    if (isset($input['payment_method'])) {
        $updateFields[] = "payment_method = :payment_method";
        $params[':payment_method'] = $input['payment_method'];
    }
    
    if (isset($input['reference'])) {
        $updateFields[] = "reference = :reference";
        $params[':reference'] = $input['reference'];
    }
    
    if (isset($input['notes'])) {
        $updateFields[] = "notes = :notes";
        $params[':notes'] = $input['notes'];
    }
    
    if (empty($updateFields)) {
        throw new Exception("No fields to update");
    }
    
    // Add updated_at timestamp
    $updateFields[] = "updated_at = NOW()";
    
    $query = "UPDATE financial_transactions SET " . implode(", ", $updateFields) . " WHERE id = :id";
    
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode([
            "success" => true,
            "message" => "Transaction updated successfully"
        ]);
    } else {
        throw new Exception("Failed to update transaction");
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