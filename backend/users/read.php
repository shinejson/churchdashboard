<?php
header('Content-Type: application/json');

// Allow from any origin for development
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
} else {
    header("Access-Control-Allow-Origin: http://localhost");
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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

/**
 * Format role for display
 */
function formatRole($role) {
    $roleMap = [
        'admin'   => 'Administrator',
        'pastor'  => 'Pastor',
        'pcf'     => 'PCF Leader',
        'leader'  => 'Fellowship Leader'
    ];
    return $roleMap[$role] ?? $role;
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

    // Check if requesting a specific user
    if (isset($_GET['id'])) {
        $user_id = (int)$_GET['id'];
        
        $query = "SELECT id, username, email, first_name, last_name, role, fellowship_id, status, 
                         notes, created_at, last_login 
                  FROM users WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            // Apply role formatting
            $user['role_display'] = formatRole($user['role']);
            echo json_encode(['user' => $user]);
        } else {
            http_response_code(404);
            echo json_encode(['message' => 'User not found']);
        }
    } else {
        // Get all users with pagination and filtering
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';
        $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
        
        // Calculate offset
        $offset = ($page - 1) * $limit;
        
        // Build query with filters
        $query = "SELECT SQL_CALC_FOUND_ROWS 
                 u.id, u.username, u.email, u.first_name, u.last_name, u.role,
                 u.fellowship_id, u.status, u.notes, u.created_at, u.last_login,
                 f.name AS fellowship_name
          FROM users u
          LEFT JOIN fellowships f ON u.fellowship_id = f.id
          WHERE 1=1";
        
        $params = [];
        
        if (!empty($search)) {
            $query .= " AND (username LIKE :search OR email LIKE :search OR 
                             first_name LIKE :search OR last_name LIKE :search OR
                             CONCAT(first_name, ' ', last_name) LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        if (!empty($role_filter)) {
            $query .= " AND role = :role";
            $params[':role'] = $role_filter;
        }
        
        if (!empty($status_filter)) {
            $query .= " AND status = :status";
            $params[':status'] = $status_filter;
        }

        // Fellowship data isolation for users list:
        // non-admins only see users in their own fellowship
        $scopeId = current_fellowship_scope();
        if ($scopeId !== null) {
            $query .= " AND u.fellowship_id = :scope_fellowship_id";
            $params[':scope_fellowship_id'] = $scopeId;
        }
        
        // Add sorting
        $sort_field = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
        $sort_order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Validate and sanitize sort field
        $allowed_sort_fields = ['id', 'username', 'email', 'role', 'status', 'created_at', 'last_login', 'first_name', 'last_name'];
        if (!in_array($sort_field, $allowed_sort_fields)) {
            $sort_field = 'created_at';
        }
        
        $query .= " ORDER BY `$sort_field` $sort_order LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($query);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($users as &$user) {
            $user['role_display'] = formatRole($user['role']);
            $user['fellowship_display'] = $user['fellowship_name'] ?? '—';
        }
        unset($user);
        
        // Get total count
        $total_stmt = $conn->query("SELECT FOUND_ROWS()");
        $total_users = $total_stmt->fetchColumn();
        
        // Calculate total pages
        $total_pages = ceil($total_users / $limit);
        
        echo json_encode([
            'users' => $users,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_users' => (int)$total_users,
                'users_per_page' => $limit
            ]
        ]);
    }

} catch (PDOException $e) {
    error_log("Database error in read.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'message' => 'Database error occurred',
        'debug' => 'Check database connection and table structure'
    ]);
} catch (Exception $e) {
    error_log("Error in read.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'message' => 'Server error occurred'
    ]);
}
?>