<?php
/**
 * Create Payment System Tables
 * Run this script to set up all payment-related tables
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Creating Payment System Tables</h1>";
echo "<pre>";

try {
    $db = (new Database())->getConnection();
    echo "✓ Database connected\n\n";
    
    $tables = [
        'payment_transactions' => 'Payment transactions tracking',
        'recurring_payments' => 'Recurring payment schedules',
        'recurring_payment_history' => 'Recurring payment execution history',
        'payment_receipts' => 'Payment receipt records',
        'partnership_arms' => 'Partnership arms/projects'
    ];
    
    // Read and execute SQL file
    $sqlFile = __DIR__ . '/2026_04_16_create_payment_tables.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Remove multi-line comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    
    // Remove single-line comments but keep the newline for splitting if needed
    // We want to remove lines starting with --
    $lines = explode("\n", $sql);
    $cleanLines = array_filter($lines, function($line) {
        $trimmed = trim($line);
        return strpos($trimmed, '--') !== 0 && $trimmed !== '';
    });
    $sql = implode("\n", $cleanLines);
    
    // Split into individual statements
    $statements = array_filter(
        explode(';', $sql),
        'trim'
    );
    
    echo "Executing " . count($statements) . " SQL statements...\n\n";
    
    $executed = 0;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        try {
            $db->exec($statement);
            $executed++;
            
            // Check which table was created
            foreach ($tables as $table => $desc) {
                if (stripos($statement, "CREATE TABLE IF NOT EXISTS `$table`") !== false) {
                    echo "✓ Created table: $table ($desc)\n";
                    break;
                }
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "⚠ Table already exists\n";
            } else {
                echo "✗ Error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✓ Migration completed! Executed $executed statements\n";
    echo str_repeat("=", 60) . "\n\n";
    
    // Verify tables
    echo "Verifying tables...\n";
    foreach ($tables as $table => $desc) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✓ $table - OK\n";
        } else {
            echo "✗ $table - NOT FOUND\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Payment System Setup Complete!\n";
    echo str_repeat("=", 60) . "\n\n";
    
    echo "Features enabled:\n";
    echo "✓ Online payment processing with Espees\n";
    echo "✓ Payment verification workflow\n";
    echo "✓ Recurring payment schedules\n";
    echo "✓ PDF receipt generation\n";
    echo "✓ Email confirmations\n";
    echo "✓ Payment tracking and history\n\n";
    
    echo "Next steps:\n";
    echo "1. Configure Espees API credentials in backend/config/.env\n";
    echo "2. Test payment flow from member profile\n";
    echo "3. Set up recurring payment cron job\n";
    echo "4. Configure email settings for receipts\n";
    
} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
