<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

$fellowship_id = $_SESSION['fellowship_id'];

if(!empty($data->member_id) && !empty($data->service_date) && !empty($data->service_type)) {
    // Check if attendance already recorded for this member on this date
    $checkQuery = "SELECT id FROM attendance WHERE member_id = ? AND service_date = ? AND service_type = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(1, $data->member_id);
    $checkStmt->bindParam(2, $data->service_date);
    $checkStmt->bindParam(3, $data->service_type);
    $checkStmt->execute();
    
    if($checkStmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(array("message" => "Attendance already recorded for this service"));
        exit();
    }
    
    $query = "INSERT INTO attendance SET member_id=:member_id, service_date=:service_date, service_type=:service_type, fellowship_id=:fellowship_id";
    $stmt = $db->prepare($query);
    
    $stmt->bindParam(":member_id", $data->member_id);
    $stmt->bindParam(":service_date", $data->service_date);
    $stmt->bindParam(":service_type", $data->service_type);
    $stmt->bindParam(":fellowship_id", $fellowship_id);
    
    if($stmt->execute()) {
        http_response_code(201);
        echo json_encode(array("message" => "Attendance recorded successfully"));
    } else {
        http_response_code(503);
        echo json_encode(array("message" => "Unable to record attendance"));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Incomplete data"));
}
?>