<?php
// frontend/api/services/read.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

try {
    include_once '../../../backend/config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    // Only select columns that actually exist in your table
    $query = "SELECT id, name FROM services WHERE is_active = 1 ORDER BY name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();

    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Optional: Add a label for display
    foreach ($services as &$service) {
        $service['label'] = $service['name'];
    }

    echo json_encode($services);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Failed to load services",
        "details" => $e->getMessage()
    ]);
}
?>