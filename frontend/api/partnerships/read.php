<?php
// frontend/api/partnerships/read.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

try {
    include_once '../../../backend/config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    // UPDATE: Include is_active and created_at in the SELECT statement
    $query = "SELECT id, name, description, is_active, created_at FROM partnership_arms ORDER BY name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();

    $arms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($arms);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to load partnership arms"]);
}
?>