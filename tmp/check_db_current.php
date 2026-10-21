<?php
require_once 'c:/xampp/htdocs/church/backend/config/database.php';
echo "getenv('DB_NAME'): " . var_export(getenv('DB_NAME'), true) . "\n";
$db = new Database();

$conn = $db->getConnection();
$info = $db->getDatabaseInfo();
echo "Current Database: " . ($info['database_name'] ?? 'Unknown') . "\n";

try {
    $stmt = $conn->query("SELECT * FROM fellowships LIMIT 1");
    echo "Successfully queried fellowships\n";
} catch (Exception $e) {
    echo "Error querying fellowships: " . $e->getMessage() . "\n";
}
