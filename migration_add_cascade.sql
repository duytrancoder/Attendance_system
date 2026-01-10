-- =====================================================
-- MIGRATION: Add CASCADE Foreign Key to attendance table
-- Date: 2026-01-10
-- Purpose: Fix delete issue - attendance records should be deleted when employee is deleted
-- =====================================================

USE cham_cong_db;

-- Step 1: Drop existing foreign key if exists (may not exist in current schema)
-- First, get the constraint name
SET @constraint_name = (
    SELECT CONSTRAINT_NAME 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'cham_cong_db' 
    AND TABLE_NAME = 'attendance' 
    AND COLUMN_NAME = 'fingerprint_id' 
    AND REFERENCED_TABLE_NAME = 'employees'
    LIMIT 1
);

-- Drop it if exists
SET @drop_stmt = IF(@constraint_name IS NOT NULL, 
    CONCAT('ALTER TABLE attendance DROP FOREIGN KEY ', @constraint_name),
    'SELECT "No FK to drop" as info'
);

PREPARE stmt FROM @drop_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Add the CASCADE foreign key
ALTER TABLE attendance 
ADD CONSTRAINT fk_attendance_fingerprint 
FOREIGN KEY (fingerprint_id) 
REFERENCES employees(fingerprint_id) 
ON DELETE CASCADE;

-- Verify the change
SELECT 
    rc.CONSTRAINT_NAME,
    rc.TABLE_NAME,
    rc.REFERENCED_TABLE_NAME,
    rc.DELETE_RULE,
    rc.UPDATE_RULE
FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
WHERE rc.CONSTRAINT_SCHEMA = 'cham_cong_db' 
AND rc.TABLE_NAME = 'attendance'
AND rc.CONSTRAINT_NAME = 'fk_attendance_fingerprint';

SELECT '✅ Migration completed successfully!' as status;
