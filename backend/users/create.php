<?php
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// IMPORTANT: Include security functions
require_once '../config/database.php';
require_once '../config/security.php';

// Setup CORS
allow_cors();

// Start secure session
secure_session_start();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit();
}

// CRITICAL: Validate CSRF token
require_csrf_token();

try {
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    error_log("=== CREATE USER REQUEST ===");
    error_log("Received data: " . print_r($data, true));
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid JSON data']);
        exit();
    }

    // Validate required fields
    $required = ['username', 'email', 'password', 'first_name', 'last_name', 'role'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['message' => "Missing required field: $field"]);
            exit();
        }
    }

    // Sanitize inputs
    $username = sanitize_string($data['username']);
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    $first_name = sanitize_string($data['first_name']);
    $last_name = sanitize_string($data['last_name']);
    $role = sanitize_string($data['role']);
    $status = sanitize_string($data['status'] ?? 'active');
    $notes = sanitize_string($data['notes'] ?? null);
    $password = $data['password']; // Don't sanitize password - will be hashed
    
    // Handle fellowship_id properly
    $fellowship_id = null;
    if (isset($data['fellowship_id']) && $data['fellowship_id'] !== '' && $data['fellowship_id'] !== 'null') {
        $fellowship_id = (int)$data['fellowship_id'];
    }

    error_log("Sanitized data - Username: '$username', Email: '$email', Role: '$role', Fellowship ID: " . ($fellowship_id ?? 'NULL'));

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid email format: ' . $email]);
        exit();
    }

    // Validate role - MATCHING DATABASE ENUM
    $allowed_roles = ['admin', 'pastor', 'pcf', 'leader'];
    if (!in_array($role, $allowed_roles)) {
        http_response_code(400);
        echo json_encode([
            'message' => 'Invalid role. Allowed values: ' . implode(', ', $allowed_roles),
            'received_role' => $role
        ]);
        exit();
    }

    // Validate status
    $allowed_statuses = ['active', 'inactive', 'suspended'];
    if (!in_array($status, $allowed_statuses)) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid status: ' . $status]);
        exit();
    }

    // Validate password strength
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['message' => 'Password must be at least 8 characters long']);
        exit();
    }

    // Database connection
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        http_response_code(500);
        echo json_encode(['message' => 'Database connection failed']);
        exit();
    }

    // Check if username or email already exists
    $check_query = "SELECT id, username, email FROM users WHERE username = :username OR email = :email";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':username', $username);
    $check_stmt->bindParam(':email', $email);
    
    if (!$check_stmt->execute()) {
        $errorInfo = $check_stmt->errorInfo();
        throw new Exception("Check query failed: " . $errorInfo[2]);
    }

    $existing_users = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($existing_users) > 0) {
        error_log("Duplicate found: " . print_r($existing_users, true));
        http_response_code(409);
        echo json_encode([
            'message' => 'Username or email already exists',
            'existing_users' => $existing_users
        ]);
        exit();
    }

    error_log("No duplicates found, proceeding with user creation");

    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    if (!$password_hash) {
        throw new Exception("Password hashing failed");
    }

    // Insert user - MATCHING YOUR DATABASE SCHEMA
    $query = "INSERT INTO users 
              (username, password, email, first_name, last_name, role, fellowship_id, status, notes) 
              VALUES 
              (:username, :password, :email, :first_name, :last_name, :role, :fellowship_id, :status, :notes)";

    error_log("Executing query: " . $query);

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $password_hash);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':last_name', $last_name);
    $stmt->bindParam(':role', $role);
    $stmt->bindParam(':fellowship_id', $fellowship_id, PDO::PARAM_INT);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':notes', $notes);

    if ($stmt->execute()) {
        $user_id = $conn->lastInsertId();
        error_log("User created successfully with ID: " . $user_id);
        
        // Get the created user data with fellowship info
        $select_query = "SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.role, 
                        u.fellowship_id, u.status, u.notes, u.created_at, u.last_login,
                        f.name as fellowship_display
                        FROM users u
                        LEFT JOIN fellowships f ON u.fellowship_id = f.id
                        WHERE u.id = :id";
        $select_stmt = $conn->prepare($select_query);
        $select_stmt->bindParam(':id', $user_id);
        
        if (!$select_stmt->execute()) {
            $errorInfo = $select_stmt->errorInfo();
            throw new Exception("Select query failed: " . $errorInfo[2]);
        }
        
        $user = $select_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception("Failed to retrieve created user");
        }
        
        // Remove password from response
        unset($user['password']);
        
        http_response_code(201);
        echo json_encode([
            'message' => 'User created successfully',
            'user' => $user,
            'user_id' => $user_id
        ]);
        error_log("Response sent: User created successfully");
        
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Database execution failed: " . print_r($errorInfo, true));
        
        // Check for specific MySQL errors
        if ($errorInfo[1] == 1062) { // Duplicate entry
            http_response_code(409);
            echo json_encode([
                'message' => 'Username or email already exists (database constraint)',
                'mysql_error' => $errorInfo[2]
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'message' => 'Failed to create user',
                'mysql_error_code' => $errorInfo[1],
                'mysql_error_message' => $errorInfo[2]
            ]);
        }
    }

} catch (PDOException $e) {
    error_log("PDO Exception in create.php: " . $e->getMessage());
    error_log("PDO Error Code: " . $e->getCode());
    
    http_response_code(500);
    echo json_encode([
        'message' => 'Database error occurred',
        'error_type' => 'PDOException',
        'error_code' => $e->getCode(),
        'error_message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General Exception in create.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'message' => 'Server error occurred',
        'error_type' => 'Exception',
        'error_message' => $e->getMessage()
    ]);
}
?>