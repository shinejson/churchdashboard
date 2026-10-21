<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

try {
    include_once '../../../backend/config/database.php';  // ← FIXED
    $database = new Database();
    $db = $database->getConnection();

    $fellowship_id = $_GET['fellowship_id'] ?? null;
    $query = "SELECT id, first_name, last_name, phone, fellowship_id FROM members";
    
    if ($fellowship_id && is_numeric($fellowship_id)) {
        $query .= " WHERE fellowship_id = :fellowship_id";
    }
    $query .= " ORDER BY first_name, last_name ASC";

    $stmt = $db->prepare($query);
    if ($fellowship_id && is_numeric($fellowship_id)) {
        $stmt->bindParam(':fellowship_id', $fellowship_id, PDO::PARAM_INT);
    }
    $stmt->execute();

    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($members as &$member) {
        $member['full_name'] = trim($member['first_name'] . ' ' . $member['last_name']);
    }

    echo json_encode($members);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to load members"]);
}
?>