<?php
// frontend/api/users/read.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    include_once '../../../backend/config/config.php';
    include_once '../../../backend/config/database.php';
    session_start();
    
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception("Database connection failed");
    }

    // Get current user info
    $userId = $_SESSION['user_id'] ?? null;
    $userRole = $_SESSION['role'] ?? 'pastor';
    $userFellowshipId = $_SESSION['fellowship_id'] ?? null;

    // Fetch users with email addresses
    $usersQuery = "SELECT 
                        id, 
                        CONCAT(first_name, ' ', last_name) as full_name,
                        email,
                        phone,
                        role,
                        fellowship_id,
                        status
                    FROM users 
                    WHERE email IS NOT NULL 
                        AND email != ''
                        AND status = 'active'";

    // If user is not admin, only show users from their fellowship + admins
    if ($userRole !== 'admin' && $userFellowshipId) {
        $usersQuery .= " AND (fellowship_id = :fellowship_id OR role = 'admin')";
    }

    $usersQuery .= " ORDER BY first_name ASC, last_name ASC";

    $stmt = $db->prepare($usersQuery);
    
    if ($userRole !== 'admin' && $userFellowshipId) {
        $stmt->bindParam(':fellowship_id', $userFellowshipId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($users);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Failed to load users",
        "message" => $e->getMessage()
    ]);
}
?>
