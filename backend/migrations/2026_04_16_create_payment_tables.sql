-- Migration: Create Payment System Tables
-- Date: 2026-04-16
-- Description: Create tables for payment transactions, recurring payments, and verification workflow

-- Payment Transactions Table (for tracking all payments)
CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `fellowship_id` int(11) DEFAULT NULL,
  `payment_type` enum('tithe','partnership','offering') NOT NULL,
  `partnership_arm_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_date` date NOT NULL,
  `reference` varchar(100) NOT NULL,
  `espees_reference` varchar(100) DEFAULT NULL,
  `payment_url` text DEFAULT NULL,
  `status` enum('pending','verified','failed','rejected') NOT NULL DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verification_notes` text DEFAULT NULL,
  `espees_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reference` (`reference`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_fellowship_id` (`fellowship_id`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_payment_type` (`payment_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recurring Payments Table
CREATE TABLE IF NOT EXISTS `recurring_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `fellowship_id` int(11) DEFAULT NULL,
  `payment_type` enum('tithe','partnership','offering') NOT NULL,
  `partnership_arm_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `frequency` enum('daily','weekly','monthly','yearly') NOT NULL DEFAULT 'monthly',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `next_payment_date` date NOT NULL,
  `last_payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'online',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `total_payments` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_fellowship_id` (`fellowship_id`),
  KEY `idx_next_payment_date` (`next_payment_date`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recurring Payment History Table
CREATE TABLE IF NOT EXISTS `recurring_payment_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recurring_payment_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `status` enum('success','failed','skipped') NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recurring_payment_id` (`recurring_payment_id`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment Receipts Table
CREATE TABLE IF NOT EXISTS `payment_receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `receipt_number` varchar(100) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_via_email` tinyint(1) NOT NULL DEFAULT 0,
  `email_sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_receipt_number` (`receipt_number`),
  KEY `idx_transaction_id` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Partnership Arms Table (if not exists)
CREATE TABLE IF NOT EXISTS `partnership_arms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `target_amount` decimal(10,2) DEFAULT NULL,
  `current_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add missing columns if they don't exist
ALTER TABLE `partnership_arms` ADD COLUMN IF NOT EXISTS `target_amount` decimal(10,2) DEFAULT NULL AFTER `description`;
ALTER TABLE `partnership_arms` ADD COLUMN IF NOT EXISTS `current_amount` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `target_amount`;
ALTER TABLE `partnership_arms` ADD COLUMN IF NOT EXISTS `start_date` date DEFAULT NULL AFTER `current_amount`;
ALTER TABLE `partnership_arms` ADD COLUMN IF NOT EXISTS `end_date` date DEFAULT NULL AFTER `start_date`;
ALTER TABLE `partnership_arms` ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;


-- Insert default partnership arms if table is empty
INSERT INTO `partnership_arms` (`name`, `description`, `target_amount`, `is_active`)
SELECT * FROM (
    SELECT 'Building Project' as name, 'Church building expansion and renovation' as description, 100000.00 as target_amount, 1 as is_active
    UNION ALL
    SELECT 'Missions', 'Support for missionary work and outreach programs', 50000.00, 1
    UNION ALL
    SELECT 'Youth Ministry', 'Youth programs, camps, and activities', 30000.00, 1
    UNION ALL
    SELECT 'Benevolence Fund', 'Helping members and community in need', 20000.00, 1
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `partnership_arms` LIMIT 1);
