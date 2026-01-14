<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
$pdo = db();

header('Content-Type: application/json');

// === COMPREHENSIVE LOGGING ===
$logFile = __DIR__ . '/../delete_requests.log';
$logEntry = sprintf(
    "[%s] Method: %s | GET: %s | POST: %s | Body: %s\n",
    date('Y-m-d H:i:s'),
    $_SERVER['REQUEST_METHOD'],
    json_encode($_GET),
    json_encode($_POST),
    file_get_contents('php://input')
);
file_put_contents($logFile, $logEntry, FILE_APPEND);

// === HANDLE ARDUINO MANUAL DELETE (GET with fingerprint_id) ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $fingerprintId = (int)$_GET['id'];
    
    // Validate fingerprint_id
    if ($fingerprintId < 1 || $fingerprintId > 127) {
        echo json_encode(['status' => 'ERROR', 'message' => 'ID khong hop le']);
        exit();
    }
    
    try {
        error_log("Arduino DELETE request: fingerprint_id = $fingerprintId");
        
        // Check if employee exists and is not already deleted
        $checkStmt = $pdo->prepare("SELECT id, full_name, deleted_at FROM employees WHERE fingerprint_id = ?");
        $checkStmt->execute([$fingerprintId]);
        $employee = $checkStmt->fetch();
        
        if (!$employee) {
            error_log("Employee with fingerprint_id = $fingerprintId not found");
            echo json_encode(['status' => 'OK', 'message' => 'Khong tim thay (co the da xoa)']);
            exit();
        }
        
        if ($employee['deleted_at'] !== null) {
            error_log("Employee already soft-deleted, performing hard delete");
        }
        
        // HARD DELETE: Delete attendance records first
        $stmtAtt = $pdo->prepare("DELETE FROM attendance WHERE fingerprint_id = ?");
        $stmtAtt->execute([$fingerprintId]);
        $attendanceDeleted = $stmtAtt->rowCount();
        error_log("Deleted $attendanceDeleted attendance records for fingerprint_id = $fingerprintId");
        
        // Then delete employee (hard delete)
        $stmt = $pdo->prepare("DELETE FROM employees WHERE fingerprint_id = ?");
        $stmt->execute([$fingerprintId]);
        
        if ($stmt->rowCount() > 0) {
            error_log("Successfully deleted employee with fingerprint_id = $fingerprintId");
            echo json_encode([
                'status' => 'OK',
                'message' => 'Da xoa',
                'fingerprint_id' => $fingerprintId,
                'attendance_deleted' => $attendanceDeleted
            ]);
        } else {
            error_log("Employee with fingerprint_id = $fingerprintId not found");
            echo json_encode(['status' => 'ERROR', 'message' => 'Khong tim thay']);
        }
    } catch (Exception $e) {
        error_log("Error deleting employee: " . $e->getMessage());
        echo json_encode(['status' => 'ERROR', 'message' => 'Loi: ' . $e->getMessage()]);
    }
    exit();
}

// === HANDLE DELETE ALL (GET with all=true) ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['all']) && $_GET['all'] === 'true') {
    $dept = isset($_GET['dept']) ? trim($_GET['dept']) : '';
    
    try {
        error_log("=== DELETE ALL REQUEST ===");
        error_log("Arduino DELETE ALL request for device_code: $dept");
        
        if (empty($dept)) {
            error_log("ERROR: Missing department code");
            echo json_encode(['status' => 'ERROR', 'message' => 'Thieu thong tin phong ban']);
            exit();
        }
        
        // Get department name from device_code
        $deptName = '';
        $jsonFile = __DIR__ . '/departments.json';
        
        if (!file_exists($jsonFile)) {
            error_log("ERROR: departments.json not found at: $jsonFile");
            echo json_encode(['status' => 'ERROR', 'message' => 'File cau hinh phong ban khong ton tai']);
            exit();
        }
        
        $jsonContent = file_get_contents($jsonFile);
        $depts = json_decode($jsonContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ERROR: Invalid JSON in departments.json: " . json_last_error_msg());
            echo json_encode(['status' => 'ERROR', 'message' => 'File cau hinh phong ban bi loi']);
            exit();
        }
        
        if (empty($depts) || !is_array($depts)) {
            error_log("ERROR: departments.json is empty or invalid");
            echo json_encode(['status' => 'ERROR', 'message' => 'Danh sach phong ban trong']);
            exit();
        }
        
        // Find department name from device_code (CASE-INSENSITIVE)
        foreach ($depts as $d) {
            if (isset($d['device_code']) && strcasecmp($d['device_code'], $dept) === 0) {
                $deptName = $d['name'];
                error_log("Found mapping: device_code='$dept' -> department_name='$deptName'");
                break;
            }
        }
        
        // If no mapping found, try direct match
        if (empty($deptName)) {
            error_log("ERROR: No department mapping found for device_code: $dept");
            
            $checkStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM employees WHERE department = ? AND deleted_at IS NULL");
            $checkStmt->execute([$dept]);
            $directMatch = $checkStmt->fetch()['cnt'];
            
            if ($directMatch > 0) {
                $deptName = $dept;
                error_log("WARNING: Using device_code as department name (found $directMatch employees)");
            } else {
                echo json_encode([
                    'status' => 'ERROR', 
                    'message' => "Khong tim thay phong ban voi ma: $dept",
                    'device_code' => $dept,
                    'available_codes' => array_column($depts, 'device_code')
                ]);
                exit();
            }
        }
        
        error_log("Deleting all employees from department: '$deptName' (device_code: '$dept')");
        
        // Count employees before delete (exclude already soft-deleted)
        $countStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM employees WHERE department = ? AND deleted_at IS NULL");
        $countStmt->execute([$deptName]);
        $beforeCount = $countStmt->fetch()['cnt'];
        error_log("Found $beforeCount employees to delete");
        
        if ($beforeCount == 0) {
            error_log("WARNING: No employees found with department = '$deptName'");
            echo json_encode([
                'status' => 'OK',
                'message' => 'Khong co nhan vien nao de xoa',
                'employees_deleted' => 0,
                'attendance_deleted' => 0,
                'department' => $deptName
            ]);
            exit();
        }
        
        // HARD DELETE: Delete attendance records first
        $stmtAtt = $pdo->prepare("
            DELETE a FROM attendance a
            INNER JOIN employees e ON a.fingerprint_id = e.fingerprint_id
            WHERE e.department = ? AND e.deleted_at IS NULL
        ");
        $stmtAtt->execute([$deptName]);
        $attendanceDeleted = $stmtAtt->rowCount();
        error_log("Deleted $attendanceDeleted attendance records");
        
        // Then delete employees (hard delete)
        $stmtEmp = $pdo->prepare("DELETE FROM employees WHERE department = ? AND deleted_at IS NULL");
        $stmtEmp->execute([$deptName]);
        $employeesDeleted = $stmtEmp->rowCount();
        error_log("Deleted $employeesDeleted employees");
        
        if ($employeesDeleted != $beforeCount) {
            error_log("WARNING: Expected to delete $beforeCount but deleted $employeesDeleted");
        }
        
        error_log("=== DELETE ALL COMPLETED SUCCESSFULLY ===");
        
        echo json_encode([
            'status' => 'OK',
            'message' => 'Da xoa tat ca',
            'employees_deleted' => $employeesDeleted,
            'attendance_deleted' => $attendanceDeleted,
            'department' => $deptName,
            'device_code' => $dept
        ]);
        
    } catch (Exception $e) {
        error_log("EXCEPTION in DELETE ALL: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode(['status' => 'ERROR', 'message' => 'Loi: ' . $e->getMessage()]);
    }
    exit();
}

// === HANDLE WEB DELETE (POST with employee database id) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = file_get_contents('php://input');
    
    // Try JSON first, then form-encoded
    $data = json_decode($input, true);
    if (!$data) {
        parse_str($input, $data);
        if (empty($data)) {
            $data = $_POST;
        }
    }
    
    $employeeId = isset($data['id']) ? (int)$data['id'] : 0;
    
    if (!$employeeId) {
        echo json_encode(['status' => 'error', 'message' => 'Missing employee ID']);
        exit();
    }
    
    // Get employee details (fingerprint_id and department)
    $stmt = $pdo->prepare("SELECT fingerprint_id, department, full_name, deleted_at FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        echo json_encode(['status' => 'error', 'message' => 'Employee not found']);
        exit();
    }
    
    // Check if already soft-deleted
    if ($employee['deleted_at'] !== null) {
        echo json_encode(['status' => 'warning', 'message' => 'Nhân viên đã được đánh dấu xóa, đang chờ thiết bị xác nhận']);
        exit();
    }
    
    $fingerId = $employee['fingerprint_id'];
    $deptName = $employee['department'];
    
    // Convert department NAME to device_code (CASE-INSENSITIVE)
    $deviceCode = '';
    $jsonFile = __DIR__ . '/departments.json';
    if (file_exists($jsonFile)) {
        $depts = json_decode(file_get_contents($jsonFile), true) ?: [];
        foreach ($depts as $d) {
            if (isset($d['name']) && strcasecmp($d['name'], $deptName) === 0) {
                $deviceCode = $d['device_code'];
                break;
            }
        }
    }
    
    // If no device_code found, use department name as fallback
    if (empty($deviceCode)) {
        $deviceCode = $deptName;
    }
    
    // Generate unique request ID
    $requestId = uniqid('del_', true);
    
    // STEP 1: SOFT DELETE - Mark as deleted immediately
    $softDeleteStmt = $pdo->prepare("UPDATE employees SET deleted_at = NOW() WHERE id = ?");
    $softDeleteStmt->execute([$employeeId]);
    
    error_log("Soft-deleted employee ID $employeeId (fingerprint_id: $fingerId, name: {$employee['full_name']})");
    
    // STEP 2: Create delete command in queue
    // Check if command already exists (prevent duplicates)
    $check = $pdo->prepare("SELECT id FROM device_commands WHERE device_dept = ? AND data = ? AND status = 'pending'");
    $check->execute([$deviceCode, $fingerId]);
    
    if ($check->rowCount() > 0) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Đã đánh dấu xóa. Lệnh xóa đang chờ ESP32 xử lý...',
            'soft_deleted' => true
        ]);
        exit();
    }
    
    // Create new delete command in queue using device_code
    $sql = "INSERT INTO device_commands (device_dept, command, data, status, request_id) VALUES (?, 'DELETE', ?, 'pending', ?)";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$deviceCode, $fingerId, $requestId])) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Đã đánh dấu xóa và gửi lệnh xuống thiết bị. Nhân viên sẽ biến mất sau khi thiết bị xác nhận (5-10 giây).',
            'fingerprint_id' => $fingerId,
            'device_code' => $deviceCode,
            'request_id' => $requestId,
            'soft_deleted' => true
        ]);
    } else {
        // Rollback soft delete if command creation failed
        $rollbackStmt = $pdo->prepare("UPDATE employees SET deleted_at = NULL WHERE id = ?");
        $rollbackStmt->execute([$employeeId]);
        
        echo json_encode(['status' => 'error', 'message' => 'Lỗi database khi tạo lệnh']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
?>