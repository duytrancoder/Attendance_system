<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();

// 1. Nhận ID từ ESP32
if (!isset($_GET['id'])) {
    json_response(['error' => 'Thiếu ID vân tay'], 400);
}

$fingerId = (int)$_GET['id'];

// 2. Kiểm tra xem ID này đã tồn tại chưa
$stmt = $pdo->prepare("SELECT id FROM employees WHERE fingerprint_id = ?");
$stmt->execute([$fingerId]);

if ($stmt->fetch()) {
    // Nếu ID đã có rồi -> Báo OK để ESP32 không lo lắng (hoặc báo lỗi tùy cậu)
    json_response(['message' => 'ID đã tồn tại, không cần thêm mới.']);
} else {
    // 3. Chưa có -> Tạo nhân viên mới với tên tạm
    // Tên tạm: "New #ID" để Admin dễ nhận biết
    $tempName = "Nhân viên mới #" . $fingerId;
    
    $stmt = $pdo->prepare("INSERT INTO employees (fingerprint_id, full_name, department, position) VALUES (?, ?, ?, ?)");
    // Để trống Phòng ban và Chức vụ, hoặc để "Chờ cập nhật"
    $stmt->execute([$fingerId, $tempName, 'Chờ cập nhật', 'Nhân viên']);
    
    json_response(['message' => 'Đã tạo bản ghi chờ cập nhật cho ID ' . $fingerId]);
}
?>