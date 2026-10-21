<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$fellowship_id = $_SESSION['fellowship_id'];
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$type = $_GET['type'] ?? '';

if($_SESSION['role'] == 'pastor') {
    $query = "SELECT fr.*, m.first_name, m.last_name, f.name as fellowship_name 
              FROM financial_records fr 
              LEFT JOIN members m ON fr.member_id = m.id 
              JOIN fellowships f ON fr.fellowship_id = f.id 
              WHERE fr.payment_date BETWEEN ? AND ?";
              
    if(!empty($type)) {
        $query .= " AND fr.type = ?";
    }
    
    $query .= " ORDER BY fr.payment_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $start_date);
    $stmt->bindParam(2, $end_date);
    if(!empty($type)) {
        $stmt->bindParam(3, $type);
    }
} else {
    $query = "SELECT fr.*, m.first_name, m.last_name 
              FROM financial_records fr 
              LEFT JOIN members m ON fr.member_id = m.id 
              WHERE fr.fellowship_id = ? AND fr.payment_date BETWEEN ? AND ?";
              
    if(!empty($type)) {
        $query .= " AND fr.type = ?";
    }
    
    $query .= " ORDER BY fr.payment_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $fellowship_id);
    $stmt->bindParam(2, $start_date);
    $stmt->bindParam(3, $end_date);
    if(!empty($type)) {
        $stmt->bindParam(4, $type);
    }
}

$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get financial summary
$summaryQuery = "SELECT type, SUM(amount) as total 
                 FROM financial_records 
                 WHERE payment_date BETWEEN ? AND ? 
                 AND fellowship_id = ? 
                 GROUP BY type";
$summaryStmt = $db->prepare($summaryQuery);
$summaryStmt->bindParam(1, $start_date);
$summaryStmt->bindParam(2, $end_date);
$summaryStmt->bindParam(3, $fellowship_id);
$summaryStmt->execute();
$summary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

$totalQuery = "SELECT SUM(amount) as grand_total 
               FROM financial_records 
               WHERE payment_date BETWEEN ? AND ? 
               AND fellowship_id = ?";
$totalStmt = $db->prepare($totalQuery);
$totalStmt->bindParam(1, $start_date);
$totalStmt->bindParam(2, $end_date);
$totalStmt->bindParam(3, $fellowship_id);
$totalStmt->execute();
$grandTotal = $totalStmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'records' => $records,
    'summary' => $summary,
    'grand_total' => $grandTotal['grand_total'] ?? 0,
    'period' => ['start_date' => $start_date, 'end_date' => $end_date]
]);
?>