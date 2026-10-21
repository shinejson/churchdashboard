<?php
// frontend/api/fellowships/read.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

try {
    include_once '../../../backend/config/config.php';
    include_once '../../../backend/config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }

    $query = "SELECT id, name FROM fellowships ORDER BY name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();

    $fellowships = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($fellowships);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Failed to load fellowships",
        "message" => $e->getMessage()
    ]);
}
?>