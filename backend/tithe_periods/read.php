<?php
// backend/tithe_periods/read.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/security.php';

allow_cors();
secure_session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $sql = "SELECT * FROM tithe_periods ORDER BY start_date DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "data" => $periods,
        "periods" => $periods
    ]);

} catch (Exception $e) {
    error_log("Tithe periods read error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Server error"]);
}
?>
