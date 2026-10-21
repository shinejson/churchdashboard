-- Migration: Create report_logs table
-- Purpose: Track all attendance reports sent via email/SMS

CREATE TABLE IF NOT EXISTS report_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_type VARCHAR(50) NOT NULL COMMENT 'overview, detailed, member',
    send_method VARCHAR(50) NOT NULL COMMENT 'email, sms, both',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    sent_by INT NOT NULL,
    recipients_count INT DEFAULT 0,
    results JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_sent_by (sent_by),
    INDEX idx_created_at (created_at),
    INDEX idx_report_type (report_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add email/phone columns to users table if not exists
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20);
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE;
