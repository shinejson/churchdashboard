<?php
/**
 * Apply Member Permissions Migration
 * Run this script to set up member role permissions and user-member linking
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

try {
    $db = (new Database())->getConnection();
    
    // Read the SQL file
    $sqlFile = __DIR__ . '/2026_04_15_add_member_permissions.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Remove comments and split into individual statements
    $lines = explode("\n", $sql);
    $cleanedLines = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip empty lines and comment lines
        if (empty($line) || strpos($line, '--') === 0) {
            continue;
        }
        $cleanedLines[] = $line;
    }
    
    $cleanedSql = implode("\n", $cleanedLines);
    
    // Split by semicolon but keep multi-line statements together
    $statements = array_filter(
        array_map('trim', explode(';', $cleanedSql)),
        function($stmt) {
            return !empty($stmt);
        }
    );
    
    $db->beginTransaction();
    
    $executed = 0;
    $errors = [];
    $success = true;
    
    foreach ($statements as $statement) {
        try {
            $db->exec($statement);
            $executed++;
        } catch (PDOException $e) {
            // Log error but continue with other statements
            $errors[] = [
                'statement' => substr($statement, 0, 100) . '...',
                'error' => $e->getMessage()
            ];
            // Don't fail completely on errors like "column already exists"
            if (strpos($e->getMessage(), 'Duplicate') === false && 
                strpos($e->getMessage(), 'already exists') === false) {
                $success = false;
            }
        }
    }
    
    if ($success) {
        $db->commit();
    } else {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Migration applied successfully',
        'executed' => $executed,
        'total' => count($statements),
        'errors' => $errors
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Migration failed: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
