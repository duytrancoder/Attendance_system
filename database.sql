-- =====================================================
-- DATABASE: Attendance Management System (Chamcongv2)
-- Created: 2026-01-10
-- Description: Employee attendance tracking with AS608 fingerprint integration
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
    PRIMARY KEY (id),
    INDEX idx_fingerprint (fingerprint_id),
    INDEX idx_department (department)
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
    PRIMARY KEY (id),
    INDEX idx_dept_status (device_dept, status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTES
-- =====================================================
-- 1. The 'birth_year' column in employees table stores dates as YYYYMMDD integer
--    Example: 19900115 represents January 15, 1990
--
-- 2. Department information (name, device_code) is stored in api/departments.json
--    This allows dynamic department management without schema changes
--
-- 3. The 'status' column in attendance can contain:
--    - 'Đúng giờ' (On time)
--    - 'Đi muộn' (Late)
--    - 'Về sớm' (Early leave)
--    - Combinations like 'Đi muộn - Về sớm'
--
-- 4. The device_commands table enables two-way sync between web and Arduino:
--    - Web creates commands (e.g., DELETE fingerprint)
--    - ESP32 polls for pending commands
--    - ESP32 executes and confirms completion
--    - Web performs final database cleanup
-- =====================================================
