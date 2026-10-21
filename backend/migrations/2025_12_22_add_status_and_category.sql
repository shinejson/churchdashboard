-- Migration: Add status and age_category columns
-- Purpose: Support attendance status tracking and member categorization

-- Add status column to attendance table if not exists
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS status ENUM('present', 'absent', 'late') DEFAULT 'present';

-- Add age_category column to members table if not exists
ALTER TABLE members ADD COLUMN IF NOT EXISTS age_category ENUM('adult', 'teens', 'children') DEFAULT 'adult';

-- Add service_id and service_type properly to attendance if needed
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS service_id INT;
