@echo off
REM =====================================================
REM CLEANUP SCRIPT: Remove obsolete and debug files
REM Run this after backing up your project
REM =====================================================

echo ===================================
echo Project Cleanup Script
echo ===================================
echo.

echo This script will DELETE the following files:
echo.
echo LEGACY ARDUINO FILES (replaced by codearduino.ino):
echo   - arduino_attendance.ino
echo   - arduino_delete.ino
echo   - arduino_enrollment.ino
echo   - arduino_enrollment_patch.ino
echo   - arduino_helpers.ino
echo.
echo DEBUG/TEST FILES:
echo   - clear_delete_log.php
echo   - debug.php
echo   - debug_delete_log.php
echo   - list_employees.php
echo   - monitor_delete.php
echo   - test_delete.php
echo.
echo OLD MIGRATION:
echo   - migration_add_cascade.sql
echo.
echo.
set /p CONFIRM="Are you sure you want to delete these files? (Y/N): "

if /i "%CONFIRM%" NEQ "Y" (
    echo.
    echo Cleanup cancelled.
    pause
    exit /b
)

echo.
echo Starting cleanup...
echo.

REM Delete legacy Arduino files
if exist "arduino_attendance.ino" (
    del "arduino_attendance.ino"
    echo [DELETED] arduino_attendance.ino
)

if exist "arduino_delete.ino" (
    del "arduino_delete.ino"
    echo [DELETED] arduino_delete.ino
)

if exist "arduino_enrollment.ino" (
    del "arduino_enrollment.ino"
    echo [DELETED] arduino_enrollment.ino
)

if exist "arduino_enrollment_patch.ino" (
    del "arduino_enrollment_patch.ino"
    echo [DELETED] arduino_enrollment_patch.ino
)

if exist "arduino_helpers.ino" (
    del "arduino_helpers.ino"
    echo [DELETED] arduino_helpers.ino
)

REM Delete debug files
if exist "clear_delete_log.php" (
    del "clear_delete_log.php"
    echo [DELETED] clear_delete_log.php
)

if exist "debug.php" (
    del "debug.php"
    echo [DELETED] debug.php
)

if exist "debug_delete_log.php" (
    del "debug_delete_log.php"
    echo [DELETED] debug_delete_log.php
)

if exist "list_employees.php" (
    del "list_employees.php"
    echo [DELETED] list_employees.php
)

if exist "monitor_delete.php" (
    del "monitor_delete.php"
    echo [DELETED] monitor_delete.php
)

if exist "test_delete.php" (
    del "test_delete.php"
    echo [DELETED] test_delete.php
)

REM Delete old migration
if exist "migration_add_cascade.sql" (
    del "migration_add_cascade.sql"
    echo [DELETED] migration_add_cascade.sql
)

REM Clear log file (keep the file but empty it)
if exist "delete_requests.log" (
    type nul > "delete_requests.log"
    echo [CLEARED] delete_requests.log
)

echo.
echo ===================================
echo Cleanup completed!
echo ===================================
echo.
echo Deleted obsolete files.
echo Cleared log file.
echo.
echo Your project is now clean and optimized!
echo.

pause
