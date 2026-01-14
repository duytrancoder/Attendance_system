<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();

// 1. Receive ID from ESP32
if (!isset($_GET['id'])) {
    json_response(['error' => 'Thiếu ID vân tay'], 400);
}

$fingerId = (int)$_GET['id'];

// 2. Check if this ID already exists
$stmt = $pdo->prepare("SELECT id FROM employees WHERE fingerprint_id = ?");
$stmt->execute([$fingerId]);

if ($stmt->fetch()) {
    // ID already exists -> Return OK so ESP32 doesn't worry
    json_response(['message' => 'ID đã tồn tại, không cần thêm mới.']);
} else {
    // 3. Not exists -> Create new employee with temp name
    $tempName = "Nhân viên mới #" . $fingerId;
    $deptCode = isset($_GET['dept']) ? $_GET['dept'] : 'Chờ cập nhật';
    
    // CRITICAL: Map device_code to department NAME and validate
    $deptName = $deptCode; // Default to code if not found
    if ($deptCode !== 'Chờ cập nhật') {
        $jsonFile = __DIR__ . '/departments.json';
        
        // Validate department file exists
        if (!file_exists($jsonFile)) {
            error_log("ERROR: departments.json not found at: $jsonFile");
            json_response(['error' => 'File cấu hình phòng ban không tồn tại'], 500);
        }
        
        $jsonContent = file_get_contents($jsonFile);
        $depts = json_decode($jsonContent, true);
        
        // Validate JSON format
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ERROR: Invalid JSON in departments.json: " . json_last_error_msg());
            json_response(['error' => 'File cấu hình phòng ban bị lỗi'], 500);
        }
        
        if (empty($depts) || !is_array($depts)) {
            error_log("ERROR: departments.json is empty or invalid");
            json_response(['error' => 'Danh sách phòng ban trống'], 500);
        }
        
        // Find and validate department code
        $validDept = false;
        foreach ($depts as $d) {
            if (isset($d['device_code']) && strcasecmp($d['device_code'], $deptCode) === 0) {
                $deptName = $d['name'];
                $validDept = true;
                error_log("Validated department: device_code='$deptCode' -> name='$deptName'");
                break;
            }
        }
        
        // Reject if department code not found
        if (!$validDept) {
            error_log("ERROR: Invalid department code: $deptCode");
            $availableCodes = array_column($depts, 'device_code');
            json_response([
                'error' => 'Mã phòng ban không hợp lệ: ' . $deptCode,
                'available_codes' => $availableCodes
            ], 400);
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO employees (fingerprint_id, full_name, department, position) VALUES (?, ?, ?, ?)");
    $stmt->execute([$fingerId, $tempName, $deptName, 'Nhân viên']);
    
    json_response(['message' => 'Đã tạo bản ghi chờ cập nhật cho ID ' . $fingerId, 'department' => $deptName]);
}
?>