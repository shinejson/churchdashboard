<?php
// backend/users/update.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/security.php';

allow_cors();
secure_session_start();
require_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['message' => 'User ID is required']);
        exit();
    }

    $user_id = (int)$data['id'];

    // Sanitize all inputs
    $username     = sanitize_string($data['username'] ?? null);
    $email        = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $first_name   = sanitize_string($data['first_name'] ?? null);
    $last_name    = sanitize_string($data['last_name'] ?? null);
    $role         = sanitize_string($data['role'] ?? null);
    $fellowship_id = isset($data['fellowship_id']) ? (int)$data['fellowship_id'] : null;
    $status       = sanitize_string($data['status'] ?? null);
    $notes        = sanitize_string($data['notes'] ?? null);

    // Validate email
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid email format']);
        exit();
    }

    // Validate role
    $allowed_roles = ['admin', 'pastor', 'pcf', 'leader'];
    if ($role && !in_array($role, $allowed_roles)) {
        http_response_code(400);
        echo json_encode([
            'message' => 'Invalid role',
            'allowed' => $allowed_roles
        ]);
        exit();
    }

    // Validate status
    $allowed_statuses = ['active', 'inactive', 'suspended'];
    if ($status && !in_array($status, $allowed_statuses)) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid status']);
        exit();
    }

    $db = (new Database())->getConnection();

    // Check user exists
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['message' => 'User not found']);
        exit();
    }

    // Check duplicates (only if changed)
    if ($username || $email) {
        $sql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$username ?? '', $email ?? '', $user_id]);
        if ($stmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode(['message' => 'Username or email already exists']);
            exit();
        }
    }

    // Build update
    $fields = [];
    $params = [':id' => $user_id];

    if ($username !== null)     { $fields[] = 'username = :username';     $params[':username'] = $username; }
    if ($email !== null)        { $fields[] = 'email = :email';           $params[':email'] = $email; }
    if ($first_name !== null)   { $fields[] = 'first_name = :first_name'; $params[':first_name'] = $first_name; }
    if ($last_name !== null)    { $fields[] = 'last_name = :last_name';   $params[':last_name'] = $last_name; }
    if ($role !== null)         { $fields[] = 'role = :role';             $params[':role'] = $role; }
    if ($status !== null)       { $fields[] = 'status = :status';         $params[':status'] = $status; }
    if ($notes !== null)        { $fields[] = 'notes = :notes';           $params[':notes'] = $notes; }
    if ($fellowship_id !== null) {
        $fields[] = 'fellowship_id = :fellowship_id';
        $params[':fellowship_id'] = $fellowship_id;
    }

    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['message' => 'No fields to update']);
        exit();
    }

    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($sql);

    foreach ($params as $key => $value) {
        $type = $key === ':fellowship_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['message' => 'Update failed']);
        exit();
    }

    // Return updated user
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'message' => 'User updated successfully',
        'user' => $user
    ]);

} catch (Exception $e) {
    error_log("Update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Server error']);
}