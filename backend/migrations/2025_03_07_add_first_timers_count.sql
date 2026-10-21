-- Add first_timers_count column to attendance table
-- This stores the number of first-time visitors who are not registered members

ALTER TABLE attendance 
ADD COLUMN first_timers_count INT DEFAULT 0 
COMMENT 'Number of first-time visitors not in members list';

-- Update existing records to have 0 first-timers
UPDATE attendance SET first_timers_count = 0 WHERE first_timers_count IS NULL;
