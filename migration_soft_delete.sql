-- Migration: Add soft delete support and optimize schema
-- Run this AFTER the main database.sql

USE cham_cong_db;

-- Add deleted_at column for soft delete
ALTER TABLE employees 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
ADD INDEX idx_deleted (deleted_at);

-- Add cleanup job tracking
CREATE TABLE IF NOT EXISTS system_jobs (
    id INT(11) NOT NULL AUTO_INCREMENT,
    job_name VARCHAR(50) NOT NULL,
    last_run TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_job_name (job_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial job records
INSERT INTO system_jobs (job_name, last_run) VALUES
('cleanup_commands', NOW()),
('cleanup_deleted_employees', NOW())
ON DUPLICATE KEY UPDATE last_run = last_run;

-- Add request_id for tracking delete operations
ALTER TABLE device_commands
ADD COLUMN request_id VARCHAR(50) NULL COMMENT 'Unique request identifier',
ADD INDEX idx_request_id (request_id);
