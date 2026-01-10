<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
$pdo = db();

header('Content-Type: application/json');

// === HANDLE ARDUINO MANUAL DELETE (GET with fingerprint_id) ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $fingerprintId = (int)$_GET['id'];
    
    // Delete directly from database using fingerprint_id
    $stmt = $pdo->prepare("DELETE FROM employees WHERE fingerprint_id = ?");
    $stmt->execute([$fingerprintId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Đã xóa nhân viên khỏi hệ thống']);
    } else {
        echo json_encode(['status' => 'warning', 'message' => 'Không tìm thấy nhân viên']);
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