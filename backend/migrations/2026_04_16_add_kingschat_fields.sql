-- Migration: Add KingsChat OAuth Fields
-- Date: 2026-04-16
-- Description: Add fields to users table for KingsChat authentication

-- Add KingsChat fields to users table
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `kingschat_id` varchar(100) DEFAULT NULL AFTER `password`,
ADD COLUMN IF NOT EXISTS `kingschat_token` text DEFAULT NULL AFTER `kingschat_id`,
ADD COLUMN IF NOT EXISTS `email` varchar(255) DEFAULT NULL AFTER `username`,
ADD COLUMN IF NOT EXISTS `avatar` varchar(255) DEFAULT NULL AFTER `email`;

-- Add unique index on kingschat_id
ALTER TABLE `users` 
ADD UNIQUE KEY IF NOT EXISTS `unique_kingschat_id` (`kingschat_id`);

-- Add index on email
ALTER TABLE `users` 
ADD KEY IF NOT EXISTS `idx_email` (`email`);
