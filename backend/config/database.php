<?php
// backend/config/database.php

// Ensure config.php is loaded first to get database constants
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    public $conn;
    private static $instance = null;

    public function __construct() {
        // Use constants from config.php
        $this->host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $this->db_name = defined('DB_NAME') ? DB_NAME : 'church_management';
        $this->username = defined('DB_USER') ? DB_USER : 'root';
        $this->password = defined('DB_PASS') ? DB_PASS : '';
        $this->charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
    }

    // Singleton pattern to ensure only one connection
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        try {
            // Check if connection already exists and is alive
            if ($this->conn !== null) {
                try {
                    $this->conn->query('SELECT 1');
                    return $this->conn;
                } catch (PDOException $e) {
                    // Connection is dead, reconnect
                    $this->conn = null;
                }
            }

            // Create new connection
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false, // Set to true for persistent connections
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}",
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_TIMEOUT            => 30 // 30 seconds timeout
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
            // Set timezone - use UTC offset for compatibility
            // Note: MySQL timezone names like 'Africa/Lagos' require timezone tables to be loaded
            // Using offset format: '+HH:MM' or '-HH:MM' is safer
            try {
                // Try to use the configured timezone offset if available
                $offset = getenv('TIMEZONE_OFFSET') ?: '+00:00';
                $this->conn->exec("SET time_zone = '" . $offset . "'");
            } catch (PDOException $e) {
                // If timezone setting fails, continue anyway (non-critical)
                error_log("Warning: Could not set MySQL timezone: " . $e->getMessage());
            }

            return $this->conn;

        } catch(PDOException $exception) {
            // Log error with more context
            $error_message = "Database Connection Error: " . $exception->getMessage();
            error_log($error_message);

            // Do not terminate execution here; let callers (e.g., login.php)
            // decide how to present errors (JSON vs redirect with flash message).
            throw $exception;
        }
    }

    // Test connection
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query('SELECT 1 as test');
            $result = $stmt->fetch();
            return isset($result['test']) && $result['test'] == 1;
        } catch (Exception $e) {
            error_log("Connection Test Failed: " . $e->getMessage());
            return false;
        }
    }

    // Close connection
    public function closeConnection() {
        $this->conn = null;
    }

    // Get database info
    public function getDatabaseInfo() {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query('SELECT VERSION() as version, DATABASE() as database_name');
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }

    // Execute query with parameters (helper method)
    public function executeQuery($sql, $params = []) {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query Execution Error: " . $e->getMessage() . "\nSQL: " . $sql);
            throw $e;
        }
    }

    // Get last insert ID
    public function lastInsertId() {
        if ($this->conn) {
            return $this->conn->lastInsertId();
        }
        return null;
    }

    // Begin transaction
    public function beginTransaction() {
        if ($this->conn) {
            return $this->conn->beginTransaction();
        }
        return false;
    }

    // Commit transaction
    public function commit() {
        if ($this->conn) {
            return $this->conn->commit();
        }
        return false;
    }

    // Rollback transaction
    public function rollback() {
        if ($this->conn) {
            return $this->conn->rollback();
        }
        return false;
    }
}
?>