<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = db();

// Nhận ID và Phòng ban cần xóa từ Web Admin
$fid = $_POST['finger_id']; 
$dept = $_POST['dept'];

// Thay vì xóa ngay, hãy tạo lệnh chờ cho Arduino
$sql = "INSERT INTO command_queue (device_dept, command_type, finger_id) VALUES (?, 'DELETE', ?)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dept, $fid]);

echo "Đã gửi lệnh xóa xuống máy chấm công phòng $dept. Vui lòng chờ máy đồng bộ...";
?>