<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();

// 1. Xóa tất cả (Khi chọn Xoa Van Tay -> Xoa TAT CA)
if (isset($_GET['all']) && $_GET['all'] == 'true') {
    // Xóa toàn bộ dữ liệu nhân viên và chấm công
    $pdo->exec("DELETE FROM employees");
    // Lệnh trên sẽ tự động xóa bảng attendance nếu cậu đã set FOREIGN KEY ON DELETE CASCADE
    // Nếu chưa set cascade, cậu bỏ comment dòng dưới:
    // $pdo->exec("DELETE FROM attendance"); 
    
    $pdo->exec("ALTER TABLE employees AUTO_INCREMENT = 1");
    
    json_response(['message' => 'Da xoa toan bo du lieu']);
}

// 2. Xóa theo ID (Khi chọn Xoa Van Tay -> Xoa theo ID)
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    // Xóa nhân viên có fingerprint_id tương ứng
    $stmt = $pdo->prepare("DELETE FROM employees WHERE fingerprint_id = ?");
    $stmt->execute([$id]);
    
    json_response(['message' => "Da xoa ID $id"]);
}
?>