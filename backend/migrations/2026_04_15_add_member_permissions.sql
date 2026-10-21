-- Migration: Add member role permissions
-- Date: 2026-04-15
-- Description: Create permissions table and set up member role with profile access

-- Create permissions table if it doesn't exist
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` varchar(50) NOT NULL,
  `module` varchar(50) NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_module` (`role`, `module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default permissions for all roles
-- Admin permissions (full access)
INSERT INTO `permissions` (`role`, `module`, `allowed`) VALUES
('admin', 'dashboard', 1),
('admin', 'users', 1),
('admin', 'fellowships', 1),
('admin', 'attendance', 1),
('admin', 'financial', 1),
('admin', 'reports', 1),
('admin', 'settings', 1),
('admin', 'profile', 1),
('admin', 'members', 1),
('admin', 'cells', 1),
('admin', 'departments', 1)
ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`);

-- Pastor permissions
INSERT INTO `permissions` (`role`, `module`, `allowed`) VALUES
('pastor', 'dashboard', 1),
('pastor', 'users', 0),
('pastor', 'fellowships', 1),
('pastor', 'attendance', 1),
('pastor', 'financial', 1),
('pastor', 'reports', 1),
('pastor', 'settings', 0),
('pastor', 'profile', 1),
('pastor', 'members', 1),
('pastor', 'cells', 1),
('pastor', 'departments', 1)
ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`);

-- PCF permissions
INSERT INTO `permissions` (`role`, `module`, `allowed`) VALUES
('pcf', 'dashboard', 1),
('pcf', 'users', 0),
('pcf', 'fellowships', 1),
('pcf', 'attendance', 1),
('pcf', 'financial', 0),
('pcf', 'reports', 0),
('pcf', 'settings', 0),
('pcf', 'profile', 1),
('pcf', 'members', 1),
('pcf', 'cells', 1),
('pcf', 'departments', 0)
ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`);

-- Leader permissions
INSERT INTO `permissions` (`role`, `module`, `allowed`) VALUES
('leader', 'dashboard', 1),
('leader', 'users', 0),
('leader', 'fellowships', 0),
('leader', 'attendance', 1),
('leader', 'financial', 0),
('leader', 'reports', 0),
('leader', 'settings', 0),
('leader', 'profile', 1),
('leader', 'members', 0),
('leader', 'cells', 0),
('leader', 'departments', 0)
ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`);

-- Member permissions (profile access only)
INSERT INTO `permissions` (`role`, `module`, `allowed`) VALUES
('member', 'dashboard', 0),
('member', 'users', 0),
('member', 'fellowships', 0),
('member', 'attendance', 0),
('member', 'financial', 0),
('member', 'reports', 0),
('member', 'settings', 0),
('member', 'profile', 1),
('member', 'members', 0),
('member', 'cells', 0),
('member', 'departments', 0)
ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`);

-- Add member_id column to users table if it doesn't exist
-- This links user accounts to member records
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `member_id` int(11) DEFAULT NULL AFTER `fellowship_id`,
ADD KEY IF NOT EXISTS `idx_member_id` (`member_id`);

-- Add user_id column to members table if it doesn't exist
-- This creates a bidirectional link
ALTER TABLE `members` 
ADD COLUMN IF NOT EXISTS `user_id` int(11) DEFAULT NULL AFTER `id`,
ADD KEY IF NOT EXISTS `idx_user_id` (`user_id`);

-- Update existing records to link users and members where possible
-- This assumes that members with matching IDs should be linked
UPDATE `users` u
INNER JOIN `members` m ON u.id = m.id
SET u.member_id = m.id, m.user_id = u.id
WHERE u.member_id IS NULL AND m.user_id IS NULL;
