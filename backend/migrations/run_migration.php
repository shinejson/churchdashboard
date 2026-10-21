<?php
/**
 * Simple Migration Runner
 * Run this file once to add the first_timers_count column to the attendance table
 */

require_once '../config/database.php';

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    echo "Starting migration...\n";
    
    // Check if column already exists
    $checkColumn = $db->query("SHOW COLUMNS FROM attendance LIKE 'first_timers_count'");
    
    if ($checkColumn->rowCount() > 0) {
        echo "✓ Column 'first_timers_count' already exists. No migration needed.\n";
        exit(0);
    }
    
    // Read and execute migration
    $sql = file_get_contents(__DIR__ . '/2025_03_07_add_first_timers_count.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        $db->exec($statement);
        echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
    }
    
    echo "\n✓ Migration completed successfully!\n";
    echo "The 'first_timers_count' column has been added to the attendance table.\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
