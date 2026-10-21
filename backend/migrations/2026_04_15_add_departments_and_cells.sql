-- Migration: Add departments and cells tables and update members table
-- Created: 2026-04-15

-- Create departments table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Create cells table
CREATE TABLE IF NOT EXISTS cells (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    leader_id INT NULL, 
    fellowship_id INT NULL,
    location TEXT NULL,
    meeting_day VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Update members table with new tracking columns
-- We'll use PHP to check if columns exist before adding them to avoid errors on reapplying
-- But for the SQL file, these are the statements:
ALTER TABLE members ADD COLUMN department_id INT NULL AFTER fellowship_id;
ALTER TABLE members ADD COLUMN role_in_church VARCHAR(100) NULL AFTER department_id;
ALTER TABLE members ADD COLUMN cell_id INT NULL AFTER role_in_church;
