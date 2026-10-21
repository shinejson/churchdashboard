-- Migration: Create attendance_records table
-- Date: 2026-04-15
-- Description: Create attendance_records table for tracking member attendance

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create services table if it doesn't exist
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default services if table is empty
INSERT INTO `services` (`name`, `description`, `day_of_week`, `time`, `is_active`)
SELECT * FROM (
    SELECT 'Sunday Service' as name, 'Main Sunday worship service' as description, 'Sunday' as day_of_week, '09:00:00' as time, 1 as is_active
    UNION ALL
    SELECT 'Bible Study', 'Midweek Bible study', 'Wednesday', '18:00:00', 1
    UNION ALL
    SELECT 'Prayer Meeting', 'Weekly prayer meeting', 'Friday', '18:00:00', 1
    UNION ALL
    SELECT 'Youth Service', 'Youth fellowship service', 'Saturday', '16:00:00', 1
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `services` LIMIT 1);
