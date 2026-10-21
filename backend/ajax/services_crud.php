<?php
// Prevent any output before headers
ob_start();

session_start();

// Set headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Include database
try {
    include_once '../config/database.php';
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database configuration error"
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Could not connect to database"
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            getServices($db);
            break;
        case 'POST':
            createService($db);
            break;
        case 'PUT':
            updateService($db);
            break;
        case 'DELETE':
            deleteService($db);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                "success" => false,
                "message" => "Method not allowed"
            ]);
            break;
    }
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}

ob_end_flush();

function getServices($db) {
    try {
        $checkTable = $db->query("SHOW TABLES LIKE 'services'");
        
        if ($checkTable->rowCount() === 0) {
            echo json_encode([
                "success" => true,
                "data" => [
                    ["id" => "sunday_service", "name" => "Sunday Service"],
                    ["id" => "bible_study", "name" => "Bible Study"],
                    ["id" => "prayer_meeting", "name" => "Prayer Meeting"],
                    ["id" => "youth_fellowship", "name" => "Youth Fellowship"],
                    ["id" => "children_service", "name" => "Children Service"]
                ],
                "message" => "Using sample data - services table not found"
            ]);
            return;
        }

        $stmt = $db->query("SELECT id, name FROM services WHERE is_active = 1 ORDER BY name");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($services) === 0) {
            echo json_encode([
                "success" => true,
                "data" => [
                    ["id" => "sunday_service", "name" => "Sunday Service"],
                    ["id" => "bible_study", "name" => "Bible Study"],
                    ["id" => "prayer_meeting", "name" => "Prayer Meeting"],
                    ["id" => "youth_fellowship", "name" => "Youth Fellowship"],
                    ["id" => "children_service", "name" => "Children Service"]
                ],
                "message" => "Using sample data - no services in database"
            ]);
            return;
        }
        
        echo json_encode([
            "success" => true,
            "data" => $services
        ]);
    } catch (Exception $e) {
        echo json_encode([
            "success" => true,
            "data" => [
                ["id" => "sunday_service", "name" => "Sunday Service"],
                ["id" => "bible_study", "name" => "Bible Study"],
                ["id" => "prayer_meeting", "name" => "Prayer Meeting"],
                ["id" => "youth_fellowship", "name" => "Youth Fellowship"],
                ["id" => "children_service", "name" => "Children Service"]
            ],
            "message" => "Using sample data - database error"
        ]);
    }
}

function createService($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['name']) || empty(trim($input['name']))) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Service name is required"
        ]);
        return;
    }

    try {
        $checkTable = $db->query("SHOW TABLES LIKE 'services'");
        
        if ($checkTable->rowCount() === 0) {
            $createTable = "CREATE TABLE IF NOT EXISTS services (
                id VARCHAR(50) PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $db->exec($createTable);
        }

        $name = trim($input['name']);
        $id = strtolower(str_replace(' ', '_', $name));
        
        // Check for duplicates
        $checkStmt = $db->prepare("SELECT id, name FROM services WHERE LOWER(name) = LOWER(?) OR id = ?");
        $checkStmt->execute([$name, $id]);
        
        if ($checkStmt->rowCount() > 0) {
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            http_response_code(409);
            echo json_encode([
                "success" => false,
                "message" => "A service with this name already exists: '{$existing['name']}'"
            ]);
            return;
        }

        $stmt = $db->prepare("INSERT INTO services (id, name) VALUES (?, ?)");
        $stmt->execute([$id, $name]);
        
        echo json_encode([
            "success" => true,
            "message" => "Service created successfully",
            "data" => ["id" => $id, "name" => $name]
        ]);
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(409);
            echo json_encode([
                "success" => false,
                "message" => "Service already exists"
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Database error: " . $e->getMessage()
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Error creating service: " . $e->getMessage()
        ]);
    }
}

function updateService($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id']) || !isset($input['name'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Service ID and name are required"
        ]);
        return;
    }

    try {
        $checkTable = $db->query("SHOW TABLES LIKE 'services'");
        
        if ($checkTable->rowCount() === 0) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Services table not found"
            ]);
            return;
        }

        $id = $input['id'];
        $name = trim($input['name']);
        
        // FIRST: Check if the service exists
        $checkExists = $db->prepare("SELECT id, name FROM services WHERE id = ?");
        $checkExists->execute([$id]);
        
        if ($checkExists->rowCount() === 0) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Service not found"
            ]);
            return;
        }
        
        $currentService = $checkExists->fetch(PDO::FETCH_ASSOC);
        
        // Check if the name is actually changing
        if (strtolower($currentService['name']) === strtolower($name)) {
            // Name hasn't changed, return success
            echo json_encode([
                "success" => true,
                "message" => "Service updated successfully (no changes)"
            ]);
            return;
        }
        
        // Check if another service already has this name
        $checkStmt = $db->prepare("SELECT id FROM services WHERE LOWER(name) = LOWER(?) AND id != ?");
        $checkStmt->execute([$name, $id]);
        
        if ($checkStmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode([
                "success" => false,
                "message" => "Another service with this name already exists"
            ]);
            return;
        }

        // Update the service
        $stmt = $db->prepare("UPDATE services SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        
        echo json_encode([
            "success" => true,
            "message" => "Service updated successfully"
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Error updating service: " . $e->getMessage()
        ]);
    }
}

function deleteService($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Service ID is required"
        ]);
        return;
    }

    try {
        $checkTable = $db->query("SHOW TABLES LIKE 'services'");
        
        if ($checkTable->rowCount() === 0) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Services table not found"
            ]);
            return;
        }

        // Check if service exists first
        $checkExists = $db->prepare("SELECT id FROM services WHERE id = ?");
        $checkExists->execute([$input['id']]);
        
        if ($checkExists->rowCount() === 0) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Service not found"
            ]);
            return;
        }

        // Soft delete
        $stmt = $db->prepare("UPDATE services SET is_active = 0 WHERE id = ?");
        $stmt->execute([$input['id']]);
        
        echo json_encode([
            "success" => true,
            "message" => "Service deleted successfully"
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Error deleting service: " . $e->getMessage()
        ]);
    }
}
?>