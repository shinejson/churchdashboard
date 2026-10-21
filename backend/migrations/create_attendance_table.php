<?php
/**
 * Create Attendance Records Table
 * Run this script to create the attendance_records and services tables
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Creating Attendance Records Table</h1>";
echo "<pre>";

try {
    $db = (new Database())->getConnection();
    echo "✓ Database connected\n\n";
    
    // 1. Create services table
    echo "1. Creating services table...\n";
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `services` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(100) NOT NULL,
              `description` text DEFAULT NULL,
              `day_of_week` varchar(20) DEFAULT NULL,
              `time` time DEFAULT NULL,
              `fellowship_id` int(11) DEFAULT NULL,
              `is_active` tinyint(1) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_fellowship_id` (`fellowship_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "   ✓ Services table created\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "   ⚠ Services table already exists\n";
        } else {
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // 2. Insert default services
    echo "\n2. Inserting default services...\n";
    try {
        $checkStmt = $db->query("SELECT COUNT(*) as count FROM services");
        $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count == 0) {
            $services = [
                ['Sunday Service', 'Main Sunday worship service', 'Sunday', '09:00:00'],
                ['Bible Study', 'Midweek Bible study', 'Wednesday', '18:00:00'],
                ['Prayer Meeting', 'Weekly prayer meeting', 'Friday', '18:00:00'],
                ['Youth Service', 'Youth fellowship service', 'Saturday', '16:00:00']
            ];
            
            $stmt = $db->prepare("
                INSERT INTO services (name, description, day_of_week, time, is_active)
                VALUES (?, ?, ?, ?, 1)
            ");
            
            foreach ($services as $service) {
                $stmt->execute($service);
            }
            echo "   ✓ Inserted " . count($services) . " default services\n";
        } else {
            echo "   ⚠ Services already exist ($count services found)\n";
        }
    } catch (PDOException $e) {
        echo "   ⚠ " . $e->getMessage() . "\n";
    }
    
    // 3. Create attendance_records table
    echo "\n3. Creating attendance_records table...\n";
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `attendance_records` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `member_id` int(11) NOT NULL,
              `service_id` int(11) DEFAULT NULL,
              `fellowship_id` int(11) DEFAULT NULL,
              `date` date NOT NULL,
              `status` enum('present','absent','excused') NOT NULL DEFAULT 'present',
              `notes` text DEFAULT NULL,
              `recorded_by` int(11) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_member_id` (`member_id`),
              KEY `idx_service_id` (`service_id`),
              KEY `idx_fellowship_id` (`fellowship_id`),
              KEY `idx_date` (`date`),
              KEY `idx_status` (`status`),
              UNIQUE KEY `unique_attendance` (`member_id`, `service_id`, `date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "   ✓ Attendance_records table created\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "   ⚠ Attendance_records table already exists\n";
        } else {
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // 4. Verify tables exist
    echo "\n4. Verifying tables...\n";
    $tables = ['services', 'attendance_records'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "   ✓ $table exists\n";
        } else {
            echo "   ✗ $table NOT found\n";
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✓ Migration completed successfully!\n";
    echo str_repeat("=", 50) . "\n\n";
    
    echo "Tables created:\n";
    echo "- services (for service types like Sunday Service, Bible Study, etc.)\n";
    echo "- attendance_records (for tracking member attendance)\n\n";
    
    echo "Next steps:\n";
    echo "1. Run test_member_setup.php to verify everything is set up\n";
    echo "2. Start recording attendance in the Attendance page\n";
    echo "3. Members will see their attendance history in their profile\n";
    
} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
