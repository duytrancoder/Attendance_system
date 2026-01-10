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
    
    try {
        // Log the delete request
        error_log("Arduino DELETE request: fingerprint_id = $fingerprintId");
        
        // IMPORTANT: Delete attendance records first (no CASCADE in current schema)
        $stmtAtt = $pdo->prepare("DELETE FROM attendance WHERE fingerprint_id = ?");
        $stmtAtt->execute([$fingerprintId]);
        $attendanceDeleted = $stmtAtt->rowCount();
        error_log("Deleted $attendanceDeleted attendance records for fingerprint_id = $fingerprintId");
        
        // Then delete employee
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

// === HANDLE WEB DELETE (POST with employee database id) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Handle both POST and DELETE methods
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
    $stmt = $pdo->prepare("SELECT fingerprint_id, department FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        echo json_encode(['status' => 'error', 'message' => 'Employee not found']);
        exit();
    }
    
    $fingerId = $employee['fingerprint_id'];
    $deptName = $employee['department'];
    
    // CRITICAL: Convert department NAME to device_code
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
    
    // Check if command already exists (prevent duplicates)
    $check = $pdo->prepare("SELECT id FROM device_commands WHERE device_dept = ? AND data = ? AND status = 'pending'");
    $check->execute([$deviceCode, $fingerId]);
    
    if ($check->rowCount() > 0) {
        echo json_encode(['status' => 'warning', 'message' => 'Lệnh xóa đang chờ ESP32 xử lý...']);
        exit();
    }
    
    // Create new delete command in queue using device_code
    $sql = "INSERT INTO device_commands (device_dept, command, data, status) VALUES (?, 'DELETE', ?, 'pending')";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$deviceCode, $fingerId])) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Đã gửi lệnh xóa xuống thiết bị. Vui lòng chờ đồng bộ...',
            'fingerprint_id' => $fingerId,
            'device_code' => $deviceCode
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi database khi tạo lệnh']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
?>