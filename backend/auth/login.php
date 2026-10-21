<?php

// Set error handling to catch all issues
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Don't display errors to user
ini_set('log_errors', 1);

// Set JSON error handler  
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Login PHP Error [$errno]: $errstr in $errfile on line $errline");
    if (headers_sent() === false) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(500);
        echo json_encode(array("message" => "Server error occurred"));
    }
    return true;
});

try {
    require_once '../config/security.php';
    secure_session_start();
    allow_cors();
    
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isJsonRequest = stripos($contentType, 'application/json') !== false;

    if ($isJsonRequest) {
        header("Content-Type: application/json; charset=UTF-8");
    }

    // Handle preflight OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    include_once '../config/database.php';

    // Protect against brute-force: track failed attempts in session
    if (!isset($_SESSION['failed_login'])) {
        $_SESSION['failed_login'] = ['count' => 0, 'ts' => 0];
    }

    $database = new Database();
    $db = $database->getConnection();

    $username = '';
    $password = '';
    if ($isJsonRequest) {
        $data = json_decode(file_get_contents("php://input"));

        // Check if data was received properly
        if (!$data) {
            error_log("Login.php: No JSON data received or invalid JSON");
            http_response_code(400);
            echo json_encode(array("message" => "No data received"));
            exit();
        }
        $username = trim((string)($data->username ?? ''));
        $password = (string)($data->password ?? '');
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
    }

    // Check if database connection is valid
    if (!$db) {
        error_log("Login.php: Database connection failed");
        if ($isJsonRequest) {
            http_response_code(500);
            echo json_encode(array("message" => "Database connection error"));
        } else {
            $_SESSION['login_error'] = "Unable to connect to database. Please check server configuration.";
            header('Location: ' . SITE_URL . '/frontend/pages/login.php');
        }
        exit();
    }
if ($_SESSION['failed_login']['count'] >= 5 && (time() - $_SESSION['failed_login']['ts']) < 900) {
    // Log rate-limit hit
    if (function_exists('security_log')) {
        security_log('warning', 'Too many login attempts for remote IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
    if ($isJsonRequest) {
        http_response_code(429);
        echo json_encode(array("message" => "Too many login attempts. Try again later."));
    } else {
        $_SESSION['login_error'] = "Too many login attempts. Try again later.";
        header('Location: ' . SITE_URL . '/frontend/pages/login.php');
    }
    exit();
}

if(!empty($username) && !empty($password)) {
    error_log("Login attempt: username={$username}");
    
    $query = "SELECT id, username, password, role, fellowship_id FROM users WHERE username = ?";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $username);
    $stmt->execute();
    
    error_log("Query executed, rowCount=" . $stmt->rowCount());
    
    if($stmt->rowCount() == 1) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("User found: " . $row['username']);
        
        if(password_verify($password, $row['password'])) {
            error_log("Password verified for user: " . $row['username']);
            
            // Successful login: regenerate session id, reset failed attempts
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['fellowship_id'] = $row['fellowship_id'];
            $_SESSION['failed_login'] = ['count' => 0, 'ts' => 0];

            // ensure CSRF token is available for subsequent requests
            $csrf = get_csrf_token();

            // Debug: log session info to help verify persistence
            error_log('Login successful - session debug: ' . json_encode([
                'session_id' => session_id(),
                'session_name' => session_name(),
                'session_user_id' => $_SESSION['user_id'] ?? null,
                'session_keys' => array_keys($_SESSION),
                'http_host' => $_SERVER['HTTP_HOST'] ?? 'unknown'
            ]));

            // Update last_seen
            $updateLastSeen = "UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = ?";
            $stmtUpdate = $db->prepare($updateLastSeen);
            $stmtUpdate->execute([$row['id']]);

            // Include username in the response so frontend can display the profile name
            if ($isJsonRequest) {
                http_response_code(200);
                echo json_encode(array(
                    "message" => "Login successful",
                    "username" => $row['username'],
                    "role" => $row['role'],
                    "fellowship_id" => $row['fellowship_id'],
                    "csrf_token" => $csrf,
                    "debug" => [
                        "session_id" => session_id(),
                        "timestamp" => date('Y-m-d H:i:s')
                    ]
                ));
            } else {
                if ($row['role'] === 'member') {
                    header('Location: ' . SITE_URL . '/frontend/pages/profile.php');
                } else {
                    header('Location: ' . SITE_URL . '/index.php');
                }
                exit();
            }
        } else {
            error_log("Password verification failed for user: " . $row['username']);
            // Failed login
            $_SESSION['failed_login']['count'] += 1;
            $_SESSION['failed_login']['ts'] = time();
            if ($isJsonRequest) {
                http_response_code(401);
                echo json_encode(array("message" => "Invalid credentials"));
            } else {
                $_SESSION['login_error'] = "Invalid username or password.";
                header('Location: ' . SITE_URL . '/frontend/pages/login.php');
                exit();
            }
        }
    } else {
        error_log("User not found: " . $username);
        if ($isJsonRequest) {
            http_response_code(401);
            echo json_encode(array("message" => "Invalid credentials"));
        } else {
            $_SESSION['login_error'] = "Invalid username or password.";
            header('Location: ' . SITE_URL . '/frontend/pages/login.php');
            exit();
        }
    }
} else {
    error_log("Login.php: Missing username or password");
    if ($isJsonRequest) {
        http_response_code(400);
        echo json_encode(array("message" => "Username and password are required"));
    } else {
        $_SESSION['login_error'] = "Username and password are required.";
        header('Location: ' . SITE_URL . '/frontend/pages/login.php');
        exit();
    }
}

} catch (Exception $e) {
    error_log("Login.php Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    if (!empty($isJsonRequest)) {
        if (headers_sent() === false) {
            header("Content-Type: application/json; charset=UTF-8");
            http_response_code(500);
        }
        echo json_encode(array("message" => "An error occurred during login"));
    } else {
        if (headers_sent() === false) {
            $_SESSION['login_error'] = "Login failed due to server error. Please try again.";
            header('Location: ' . SITE_URL . '/frontend/pages/login.php');
            exit();
        }
        echo "Login failed due to server error.";
    }
}

?>