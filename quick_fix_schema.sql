-- =====================================================
-- CRITICAL FIX: Add deleted_at column to employees table
-- Run this in phpMyAdmin or MySQL command line
-- =====================================================

USE cham_cong_db;

-- Check if deleted_at column exists
SELECT COUNT(*) as column_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'cham_cong_db' 
  AND TABLE_NAME = 'employees' 
  AND COLUMN_NAME = 'deleted_at';

-- If column doesn't exist, add it
ALTER TABLE employees 
ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
ADD INDEX IF NOT EXISTS idx_deleted (deleted_at);

-- Verify the column was added
DESCRIBE employees;

-- Also add to device_commands if not exists
ALTER TABLE device_commands
ADD COLUMN IF NOT EXISTS request_id VARCHAR(50) NULL COMMENT 'Unique request identifier',
ADD INDEX IF NOT EXISTS idx_request_id (request_id);

SELECT 'Migration completed successfully!' as status;
