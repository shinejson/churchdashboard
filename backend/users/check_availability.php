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
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
    require_once '../config/database.php';
    
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get parameters
    $field = $_GET['field'] ?? '';
    $value = $_GET['value'] ?? '';
    $current_id = $_GET['current_id'] ?? 0;

    // Validate field
    $allowed_fields = ['username', 'email'];
    if (!in_array($field, $allowed_fields)) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid field']);
        exit();
    }

    if (empty($value)) {
        http_response_code(400);
        echo json_encode(['message' => 'Value is required']);
        exit();
    }

    // Check availability
    $query = "SELECT id FROM users WHERE $field = :value";
    $params = [':value' => $value];

    // Exclude current user when editing
    if (!empty($current_id) && $current_id != '0') {
        $query .= " AND id != :current_id";
        $params[':current_id'] = (int)$current_id;
    }

    $stmt = $conn->prepare($query);
    
    foreach ($params as $key => $param_value) {
        $stmt->bindValue($key, $param_value);
    }
    
    $stmt->execute();

    $available = $stmt->rowCount() === 0;

    echo json_encode([
        'available' => $available,
        'field' => $field,
        'value' => $value,
        'message' => $available ? 'Available' : 'Already exists'
    ]);

} catch (PDOException $e) {
    error_log("Database error in check_availability.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'message' => 'Database error occurred',
        'available' => false
    ]);
} catch (Exception $e) {
    error_log("Error in check_availability.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'message' => 'Server error occurred',
        'available' => false
    ]);
}
?>