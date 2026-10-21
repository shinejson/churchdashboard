<?php
// backend/members/create.php - FIXED VERSION
require_once '../config/security.php';
secure_session_start();
allow_cors();

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set header FIRST to avoid any output issues
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

// Check authentication
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized - please login"]);
    exit();
}

// CRITICAL: Validate CSRF token
try {
    require_csrf_token();
} catch (Exception $e) {
    error_log("CSRF validation failed: " . $e->getMessage());
    http_response_code(403);
    echo json_encode(["error" => "CSRF token invalid or expired"]);
    exit();
}

try {
    $uploadDir = __DIR__ . '/../../uploads/members/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    // Get form data - MOVED BEFORE ERROR LOGGING
    $first_name     = trim($_POST['first_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $email          = !empty($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL) : null;
    $address        = trim($_POST['address'] ?? '');
    $join_date      = $_POST['join_date'] ?? date('Y-m-d');
    $fellowship_id  = intval($_POST['fellowship_id'] ?? 0);
    $status         = $_POST['status'] ?? 'active';
    $birth_date     = $_POST['birth_date'] ?? null;
    $notes          = trim($_POST['notes'] ?? '');
    $category       = $_POST['category'] ?? 'adult';
    $department_id  = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $role_in_church = trim($_POST['role_in_church'] ?? '');
    $cell_id        = !empty($_POST['cell_id']) ? intval($_POST['cell_id']) : null;
    $photoPath      = null;

    // NOW log them - MOVED AFTER DECLARATION
    error_log("📥 Received status: " . $status);
    error_log("📥 Received category: " . $category);
    error_log("📥 All POST data: " . print_r($_POST, true));

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

    // Validation
    $errors = [];
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    if ($fellowship_id <= 0) $errors[] = "Fellowship is required";

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(["error" => implode(", ", $errors)]);
        exit();
    }

    // Role restriction
    if (in_array($_SESSION['role'] ?? '', ['leader', 'pcf']) && $fellowship_id !== ($_SESSION['fellowship_id'] ?? 0)) {
        http_response_code(403);
        echo json_encode(["error" => "You can only add members to your own fellowship"]);
        exit();
    }

    // Photo upload
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === 0) {
        $file = $_FILES['photo'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            echo json_encode(["error" => "Only JPG/PNG allowed"]);
            exit();
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(["error" => "Photo max 2MB"]);
            exit();
        }

        $newName = uniqid('member_') . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $dest    = $uploadDir . $newName;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $photoPath = "uploads/members/" . $newName;
        }
    }

    // Save member
    require_once '../config/database.php';
    $db = (new Database())->getConnection();

    $query = "INSERT INTO members 
        (first_name, last_name, email, phone, address, fellowship_id, join_date, birth_date, notes, photo, status, category, department_id, role_in_church, cell_id) 
        VALUES 
        (:first_name, :last_name, :email, :phone, :address, :fellowship_id, :join_date, :birth_date, :notes, :photo, :status, :category, :department_id, :role_in_church, :cell_id)";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':last_name', $last_name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':address', $address);
    $stmt->bindParam(':fellowship_id', $fellowship_id, PDO::PARAM_INT);
    $stmt->bindParam(':join_date', $join_date);
    $stmt->bindParam(':birth_date', $birth_date);
    $stmt->bindParam(':notes', $notes);
    $stmt->bindParam(':photo', $photoPath);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':department_id', $department_id, PDO::PARAM_INT);
    $stmt->bindParam(':role_in_church', $role_in_church);
    $stmt->bindParam(':cell_id', $cell_id, PDO::PARAM_INT);

    error_log("🔍 About to execute INSERT with status: $status, category: $category");

    if ($stmt->execute()) {
        $member_id = $db->lastInsertId();
        
        error_log("✅ Member created with ID: $member_id, status: $status, category: $category");
        
        echo json_encode([
            "message" => "Member created successfully",
            "data" => [
                "id" => $member_id,
                "first_name" => $first_name,
                "last_name" => $last_name,
                "status" => $status,
                "category" => $category,
                "photo" => $photoPath ? "/church/" . $photoPath : null
            ]
        ]);
    } else {
        $errorInfo = $stmt->errorInfo();
        throw new Exception("Database error: " . ($errorInfo[2] ?? 'Unknown error'));
    }

} catch (Exception $e) {
    error_log("Create member error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Server error: " . $e->getMessage()]);
}
?>