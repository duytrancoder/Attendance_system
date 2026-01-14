-- =====================================================
-- OPTIMIZATION SCRIPT: Add indexes and improve performance
-- Run this AFTER database.sql and migration_soft_delete.sql
-- =====================================================

USE cham_cong_db;

-- =====================================================
-- 1. ADD MISSING INDEXES FOR BETTER PERFORMANCE
-- =====================================================

-- Index for faster employee lookups by department
ALTER TABLE employees 
ADD INDEX IF NOT EXISTS idx_dept_deleted (department, deleted_at);

-- Composite index for attendance queries (common filter combination)
ALTER TABLE attendance 
ADD INDEX IF NOT EXISTS idx_date_fingerprint (date, fingerprint_id);

-- Index for faster command queue queries
ALTER TABLE device_commands 
ADD INDEX IF NOT EXISTS idx_status_created (status, created_at);

-- =====================================================
-- 2. OPTIMIZE TABLE STRUCTURE
-- =====================================================

-- Ensure proper character set for Vietnamese text
ALTER TABLE employees CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE attendance CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE device_commands CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================
-- 3. ADD USEFUL VIEWS FOR REPORTING
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
-- 4. ADD STORED PROCEDURE FOR CLEANUP
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
-- 5. ADD FUNCTION FOR ATTENDANCE STATISTICS
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
-- 6. CREATE TRIGGER FOR AUDIT LOG (OPTIONAL)
-- =====================================================

-- Table for audit log
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

-- Trigger: Log employee deletions
DELIMITER //

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
-- 7. VERIFY INSTALLATION
-- =====================================================

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
    ROUTINE_NAME,
    ROUTINE_DEFINITION
FROM INFORMATION_SCHEMA.ROUTINES
WHERE ROUTINE_SCHEMA = 'cham_cong_db';

-- =====================================================
-- NOTES
-- =====================================================
-- 1. Run this script AFTER setting up the main database
-- 2. The stored procedure sp_cleanup_old_data() can be scheduled via cron
-- 3. Views provide faster access to common queries
-- 4. Audit log is optional but recommended for production
-- 5. All indexes are designed to improve query performance
-- =====================================================
