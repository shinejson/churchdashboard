<?php
header('Content-Type: application/json');

// Allow from any origin for development
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
} else {
    header("Access-Control-Allow-Origin: http://localhost");
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit();
}

try {
    // Include database configuration
    require_once '../config/database.php';
    require_once '../config/security.php';
    
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get current user info
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit();
    }

    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['role'] ?? 'pastor';
    $userFellowshipId = $_SESSION['fellowship_id'] ?? null;

    // Fetch fellowships
    $fellowshipsResult = [];
    try {
        $query = "SELECT id, name FROM fellowships ORDER BY name ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $fellowshipsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fellowships table might not exist, continue without it
        $fellowshipsResult = [];
    }

    // Fetch users with email addresses
    // Filter based on user role
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

    // If user is not admin, only show users from their fellowship
    if ($userRole !== 'admin' && $userFellowshipId) {
        $usersQuery .= " AND (fellowship_id = ? OR role = 'admin')";
    }

    $usersQuery .= " ORDER BY first_name ASC, last_name ASC";

    $stmt = $conn->prepare($usersQuery);
    
    if ($userRole !== 'admin' && $userFellowshipId) {
        $stmt->execute([$userFellowshipId]);
    } else {
        $stmt->execute();
    }

    $usersResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get current user's fellowship name for display
    $currentUserFellowship = null;
    if ($userFellowshipId) {
        $query = "SELECT id, name FROM fellowships WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$userFellowshipId]);
        $currentUserFellowship = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Return data
    echo json_encode([
        'success' => true,
        'fellowships' => $fellowshipsResult,
        'users' => $usersResult,
        'current_user' => [
            'id' => $userId,
            'role' => $userRole,
            'fellowship_id' => $userFellowshipId,
            'fellowship_name' => $currentUserFellowship['name'] ?? null
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
