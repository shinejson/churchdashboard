<?php
require_once '../config/security.php';
secure_session_start();
allow_cors();
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

require_csrf_token();

// Handle both JSON and form data
if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
    $input = json_decode(file_get_contents('php://input'), true);
} else {
    $input = $_POST;
}

// GET ALL VARIABLES FIRST
$id = intval($input['id'] ?? 0);
$first_name = trim($input['first_name'] ?? '');
$last_name = trim($input['last_name'] ?? '');
$phone = trim($input['phone'] ?? '');
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $input['email'] : null;
$address = trim($input['address'] ?? '');
$fellowship_id = intval($input['fellowship_id'] ?? 0);
$status = $input['status'] ?? 'active';
$join_date = $input['join_date'] ?? date('Y-m-d');
$birth_date = $input['birth_date'] ?? null;
$notes = trim($input['notes'] ?? '');
$category = $input['category'] ?? 'adult';
$department_id = !empty($input['department_id']) ? intval($input['department_id']) : null;
$role_in_church = trim($input['role_in_church'] ?? '');
$cell_id = !empty($input['cell_id']) ? intval($input['cell_id']) : null;

// NOW LOG THEM
error_log("📥 UPDATE - Received status: " . $status);
error_log("📥 UPDATE - Received category: " . $category);
error_log("📥 UPDATE - All input data: " . print_r($input, true));

// Validate category
if (!in_array($category, ['adult', 'children', 'teen'])) {
    error_log("⚠️ Invalid category '$category', defaulting to 'adult'");
    $category = 'adult';
}

// Validate status
if (!in_array($status, ['active', 'inactive', 'visitor'])) {
    error_log("⚠️ Invalid status '$status', defaulting to 'active'");
    $status = 'active';
}

if (empty($id) || empty($first_name) || empty($last_name)) {
    http_response_code(400);
    echo json_encode(["error" => "ID, first name and last name are required"]);
    exit();
}

// Handle photo upload
$uploadDir = __DIR__ . '/../../uploads/members/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$photoPath = null;

if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === 0) {
    $file = $_FILES['photo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
        echo json_encode(["error" => "Only JPG/PNG allowed"]);
        exit();
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(["error" => "Photo max 2MB"]);
        exit();
    }

    $newName = uniqid('member_') . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    $dest = $uploadDir . $newName;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $photoPath = "uploads/members/" . $newName;
    }
}

require_once '../config/database.php';
$db = (new Database())->getConnection();

// Check if user has permission to update this member
$checkQuery = "SELECT fellowship_id FROM members WHERE id = ?";
$checkStmt = $db->prepare($checkQuery);
$checkStmt->execute([$id]);
$member = $checkStmt->fetch();

if (!$member) {
    http_response_code(404);
    echo json_encode(["error" => "Member not found"]);
    exit();
}

// Role restriction
if (in_array($_SESSION['role'] ?? '', ['fellowship_leader', 'pcf_leader']) 
    && $member['fellowship_id'] != ($_SESSION['fellowship_id'] ?? 0)) {
    http_response_code(403);
    echo json_encode(["error" => "Not authorized to update this member"]);
    exit();
}

// Update member
$query = "UPDATE members SET 
    first_name = :first_name, 
    last_name = :last_name, 
    email = :email, 
    phone = :phone, 
    address = :address,
    fellowship_id = :fellowship_id,
    status = :status,
    category = :category,
    join_date = :join_date,
    birth_date = :birth_date,
    notes = :notes,
    department_id = :department_id,
    role_in_church = :role_in_church,
    cell_id = :cell_id";

if ($photoPath) {
    $query .= ", photo = :photo";
}

$query .= " WHERE id = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':first_name', $first_name);
$stmt->bindParam(':last_name', $last_name);
$stmt->bindParam(':email', $email);
$stmt->bindParam(':phone', $phone);
$stmt->bindParam(':address', $address);
$stmt->bindParam(':fellowship_id', $fellowship_id);
$stmt->bindParam(':status', $status);
$stmt->bindParam(':category', $category);
$stmt->bindParam(':join_date', $join_date);
$stmt->bindParam(':birth_date', $birth_date);
$stmt->bindParam(':notes', $notes);
$stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
$stmt->bindParam(':role_in_church', $role_in_church);
$stmt->bindParam(':cell_id', $cell_id, PDO::PARAM_INT);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
if ($photoPath) {
    $stmt->bindParam(':photo', $photoPath);
}

error_log("🔍 About to execute UPDATE with status: $status, category: $category");

if ($stmt->execute()) {
    error_log("✅ Member updated with ID: $id, status: $status, category: $category");
    
    echo json_encode([
        "message" => "Member updated successfully",
        "data" => [
            "id" => $id,
            "first_name" => $first_name,
            "last_name" => $last_name,
            "status" => $status,
            "category" => $category,
            "photo" => $photoPath
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to update member"]);
}
?>