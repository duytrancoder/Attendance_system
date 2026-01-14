-- =====================================================
-- DATABASE: Attendance Management System (Chamcongv2)
-- Created: 2026-01-15
-- Description: Complete database setup for employee attendance tracking with AS608 fingerprint integration
-- Version: 2.0 (Consolidated)
-- =====================================================

-- Create database
CREATE DATABASE IF NOT EXISTS cham_cong_db 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE cham_cong_db;

-- =====================================================
-- TABLE: employees
-- Description: Stores employee information
-- =====================================================
CREATE TABLE IF NOT EXISTS employees (
    id INT(11) NOT NULL AUTO_INCREMENT,
    fingerprint_id INT(11) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    department VARCHAR(50) DEFAULT 'Chờ cập nhật',
    position VARCHAR(50) DEFAULT 'Nhân viên',
    birth_year INT(11) DEFAULT NULL COMMENT 'Stored as YYYYMMDD integer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
    PRIMARY KEY (id),
    INDEX idx_fingerprint (fingerprint_id),
    INDEX idx_department (department),
    INDEX idx_deleted (deleted_at),
    INDEX idx_dept_deleted (department, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: shifts
-- Description: Work shift configurations
-- =====================================================
CREATE TABLE IF NOT EXISTS shifts (
    id INT(11) NOT NULL AUTO_INCREMENT,
    shift_name VARCHAR(50) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default shifts
INSERT INTO shifts (shift_name, start_time, end_time) VALUES
('Ca sáng', '08:00:00', '17:00:00'),
('Ca chiều', '13:00:00', '22:00:00'),
('Ca tối', '22:00:00', '06:00:00')
ON DUPLICATE KEY UPDATE shift_name=shift_name;

-- =====================================================
-- TABLE: attendance
-- Description: Employee attendance records
-- =====================================================
CREATE TABLE IF NOT EXISTS attendance (
    id INT(11) NOT NULL AUTO_INCREMENT,
    fingerprint_id INT(11) NOT NULL,
    shift_id INT(11) DEFAULT NULL,
    date DATE NOT NULL,
    check_in TIME DEFAULT NULL,
    check_out TIME DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'Đúng giờ',
    PRIMARY KEY (id),
    INDEX idx_fingerprint_date (fingerprint_id, date),
    INDEX idx_date (date),
    INDEX idx_shift (shift_id),
    INDEX idx_date_fingerprint (date, fingerprint_id),
    FOREIGN KEY (fingerprint_id) REFERENCES employees(fingerprint_id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: device_commands
-- Description: Command queue for ESP32 devices
-- =====================================================
CREATE TABLE IF NOT EXISTS device_commands (
    id INT(11) NOT NULL AUTO_INCREMENT,
    device_dept VARCHAR(50) NOT NULL COMMENT 'Device code (e.g., IT, HR)',
    command VARCHAR(50) NOT NULL COMMENT 'Command type (e.g., DELETE)',
    data VARCHAR(50) DEFAULT NULL COMMENT 'Command data (e.g., fingerprint_id)',
    status VARCHAR(20) DEFAULT 'pending' COMMENT 'Status: pending, completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    request_id VARCHAR(50) NULL COMMENT 'Unique request identifier',
    PRIMARY KEY (id),
    INDEX idx_dept_status (device_dept, status),
    INDEX idx_created (created_at),
    INDEX idx_request_id (request_id),
    INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: system_jobs
-- Description: Cleanup job tracking
-- =====================================================
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

-- =====================================================
-- TABLE: audit_log (OPTIONAL - For production tracking)
-- Description: Audit trail for critical operations
-- =====================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id INT(11) NOT NULL AUTO_INCREMENT,
    table_name VARCHAR(50) NOT NULL,
    action VARCHAR(20) NOT NULL,
    record_id INT(11) NOT NULL,
    old_data TEXT,
    new_data TEXT,
    changed_by VARCHAR(100) DEFAULT 'SYSTEM',
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_table_action (table_name, action),
    INDEX idx_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- VIEWS FOR REPORTING
-- =====================================================

-- View: Active employees (not soft-deleted)
CREATE OR REPLACE VIEW v_active_employees AS
SELECT 
    id,
    fingerprint_id,
    full_name,
    department,
    position,
    birth_year,
    created_at
FROM employees
WHERE deleted_at IS NULL;

-- View: Today's attendance summary
CREATE OR REPLACE VIEW v_today_attendance AS
SELECT 
    a.id,
    a.fingerprint_id,
    e.full_name,
    e.department,
    a.date,
    a.check_in,
    a.check_out,
    a.status,
    s.shift_name,
    s.start_time,
    s.end_time
FROM attendance a
JOIN employees e ON a.fingerprint_id = e.fingerprint_id
LEFT JOIN shifts s ON a.shift_id = s.id
WHERE a.date = CURDATE()
  AND e.deleted_at IS NULL
ORDER BY a.check_in DESC;

-- View: Pending delete commands
CREATE OR REPLACE VIEW v_pending_commands AS
SELECT 
    id,
    device_dept,
    command,
    data as fingerprint_id,
    created_at,
    TIMESTAMPDIFF(MINUTE, created_at, NOW()) as pending_minutes
FROM device_commands
WHERE status = 'pending'
ORDER BY created_at ASC;

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

DELIMITER //

-- Procedure: Clean up old data
DROP PROCEDURE IF EXISTS sp_cleanup_old_data//
CREATE PROCEDURE sp_cleanup_old_data()
BEGIN
    DECLARE deleted_commands INT DEFAULT 0;
    DECLARE deleted_pending INT DEFAULT 0;
    
    -- Clean up completed commands older than 7 days
    DELETE FROM device_commands 
    WHERE status = 'completed' 
      AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    SET deleted_commands = ROW_COUNT();
    
    -- Clean up pending commands older than 24 hours (device offline)
    DELETE FROM device_commands 
    WHERE status = 'pending' 
      AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY);
    SET deleted_pending = ROW_COUNT();
    
    -- Log results
    SELECT 
        deleted_commands as 'Completed Commands Deleted',
        deleted_pending as 'Stale Pending Commands Deleted',
        NOW() as 'Cleanup Time';
END//

DELIMITER ;

-- =====================================================
-- FUNCTIONS
-- =====================================================

DELIMITER //

-- Function: Calculate work hours
DROP FUNCTION IF EXISTS fn_calculate_work_hours//
CREATE FUNCTION fn_calculate_work_hours(
    check_in_time TIME,
    check_out_time TIME
) RETURNS DECIMAL(5,2)
DETERMINISTIC
BEGIN
    DECLARE hours DECIMAL(5,2);
    
    IF check_in_time IS NULL OR check_out_time IS NULL THEN
        RETURN 0.00;
    END IF;
    
    SET hours = TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time) / 60.0;
    
    RETURN IF(hours > 0, hours, 0.00);
END//

DELIMITER ;

-- =====================================================
-- TRIGGERS (OPTIONAL - Enable for audit logging)
-- =====================================================

DELIMITER //

-- Trigger: Log employee deletions
DROP TRIGGER IF EXISTS tr_employee_delete_log//
CREATE TRIGGER tr_employee_delete_log
BEFORE DELETE ON employees
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, action, record_id, old_data)
    VALUES (
        'employees',
        'DELETE',
        OLD.id,
        JSON_OBJECT(
            'fingerprint_id', OLD.fingerprint_id,
            'full_name', OLD.full_name,
            'department', OLD.department,
            'position', OLD.position,
            'deleted_at', OLD.deleted_at
        )
    );
END//

DELIMITER ;

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

-- Show all tables
SHOW TABLES;

-- Show all indexes
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'cham_cong_db'
  AND TABLE_NAME IN ('employees', 'attendance', 'device_commands')
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME;

-- Show all views
SHOW FULL TABLES IN cham_cong_db WHERE TABLE_TYPE = 'VIEW';

-- Show all procedures and functions
SELECT 
    ROUTINE_TYPE,
    ROUTINE_NAME
FROM INFORMATION_SCHEMA.ROUTINES
WHERE ROUTINE_SCHEMA = 'cham_cong_db';

-- =====================================================
-- NOTES & DOCUMENTATION
-- =====================================================
-- 
-- 1. CHARACTER SET:
--    - All tables use utf8mb4 for full Vietnamese support
--    - Collation: utf8mb4_unicode_ci for case-insensitive sorting
--
-- 2. SOFT DELETE:
--    - employees.deleted_at: NULL = active, NOT NULL = deleted
--    - All queries MUST filter: WHERE deleted_at IS NULL
--
-- 3. FINGERPRINT ID:
--    - Range: 1-127 (AS608 sensor limit)
--    - Unique across all employees
--    - Used as foreign key in attendance table
--
-- 4. BIRTH YEAR FORMAT:
--    - Stored as integer: YYYYMMDD
--    - Example: 19900115 = January 15, 1990
--
-- 5. DEPARTMENT MANAGEMENT:
--    - Department names stored in employees table
--    - Device codes mapped in api/departments.json
--    - Example: "IT" (device_code) → "Công nghệ thông tin" (name)
--
-- 6. ATTENDANCE STATUS VALUES:
--    - 'Đúng giờ' (On time)
--    - 'Đi muộn' (Late)
--    - 'Về sớm' (Early leave)
--    - Combinations: 'Đi muộn - Về sớm'
--
-- 7. DEVICE COMMANDS (Two-way sync):
--    - Web creates DELETE commands
--    - ESP32 polls for pending commands (every 2 seconds)
--    - ESP32 executes and confirms via ?done_id=X
--    - Web performs final hard delete
--
-- 8. CLEANUP JOBS:
--    - Run sp_cleanup_old_data() weekly via cron
--    - Removes completed commands > 7 days
--    - Removes stale pending commands > 24 hours
--
-- 9. INDEXES:
--    - Optimized for common queries
--    - Composite indexes for multi-column filters
--    - Foreign keys with proper CASCADE/SET NULL
--
-- 10. AUDIT LOG:
--     - Optional but recommended for production
--     - Tracks all employee deletions
--     - JSON format for flexible data storage
--
-- =====================================================
-- DEPLOYMENT CHECKLIST
-- =====================================================
--
-- [ ] 1. Backup existing database (if any)
-- [ ] 2. Run this script: SOURCE database.sql;
-- [ ] 3. Verify tables created: SHOW TABLES;
-- [ ] 4. Verify indexes: Check query above
-- [ ] 5. Verify views: SHOW FULL TABLES WHERE TABLE_TYPE = 'VIEW';
-- [ ] 6. Test stored procedure: CALL sp_cleanup_old_data();
-- [ ] 7. Test function: SELECT fn_calculate_work_hours('08:00:00', '17:00:00');
-- [ ] 8. Create first department in api/departments.json
-- [ ] 9. Test employee registration from Arduino
-- [ ] 10. Test attendance check-in/check-out
--
-- =====================================================
-- END OF DATABASE SETUP
-- =====================================================
