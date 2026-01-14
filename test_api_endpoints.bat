@echo off
REM =====================================================
REM TEST SCRIPT: Test API Endpoints (Windows)
REM =====================================================

echo ===================================
echo API Endpoints Test
echo ===================================
echo.

REM Set base URL
set BASE_URL=http://192.168.86.103/chamcongv2/api

echo Testing API endpoints...
echo.

REM Test 1: Register endpoint
echo [1/6] Testing register.php...
curl -s "%BASE_URL%/register.php?id=99&dept=IT" > nul
if %ERRORLEVEL% EQU 0 (
    echo   ✓ register.php accessible
) else (
    echo   ✗ register.php failed
)

REM Test 2: Checkin endpoint
echo [2/6] Testing checkin.php...
curl -s "%BASE_URL%/checkin.php?finger_id=1" > nul
if %ERRORLEVEL% EQU 0 (
    echo   ✓ checkin.php accessible
) else (
    echo   ✗ checkin.php failed
)

REM Test 3: Delete endpoint
echo [3/6] Testing delete.php...
curl -s "%BASE_URL%/delete.php?id=999" > nul
if %ERRORLEVEL% EQU 0 (
    echo   ✓ delete.php accessible
) else (
    echo   ✗ delete.php failed
)

REM Test 4: Poll commands endpoint
echo [4/6] Testing poll_commands.php...
curl -s "%BASE_URL%/poll_commands.php?dept=IT" > nul
if %ERRORLEVEL% EQU 0 (
    echo   ✓ poll_commands.php accessible
) else (
    echo   ✗ poll_commands.php failed
)

REM Test 5: Employees endpoint
echo [5/6] Testing employees.php...
curl -s "%BASE_URL%/employees.php" > nul
if %ERRORLEVEL% EQU 0 (
    echo   ✓ employees.php accessible
) else (
    echo   ✗ employees.php failed
)

REM Test 6: Dashboard endpoint
echo [6/6] Testing dashboard.php...
curl -s "%BASE_URL%/dashboard.php" > nul
if %ERRORLEVEL% EQU 0 (
    echo   ✓ dashboard.php accessible
) else (
    echo   ✗ dashboard.php failed
)

echo.
echo ===================================
echo Test completed!
echo ===================================
echo.
echo Next steps:
echo 1. Check XAMPP is running
echo 2. Verify database connection
echo 3. Test with actual Arduino device
echo.

pause
