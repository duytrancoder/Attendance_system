<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();

// 1. Nhận ID từ ESP32
if (!isset($_GET['finger_id'])) {
    json_response(['error' => 'Missing ID'], 400);
}
$fid = (int)$_GET['finger_id'];

// Validate fingerprint_id range (AS608 supports 1-127 or 1-999 depending on model)
if ($fid < 1 || $fid > 127) {
    json_response([
        'status' => 'ERROR',
        'message' => 'ID van tay khong hop le (1-127)',
        'name' => 'Unknown'
    ]);
}

$today = date('Y-m-d');
$now = date('H:i:s');

// 2. Tìm tên nhân viên (exclude soft-deleted)
$stmt = $pdo->prepare("SELECT full_name FROM employees WHERE fingerprint_id = ? AND deleted_at IS NULL");
$stmt->execute([$fid]);
$user = $stmt->fetch();

if (!$user) {
    // Không tìm thấy nhân viên
    json_response(['status' => 'ERROR', 'message' => 'Chua dang ky', 'name' => 'Unknown']);
}

// 3. Lấy danh sách ca làm để so sánh giờ
$shifts = $pdo->query("SELECT id, shift_name, start_time, end_time FROM shifts ORDER BY start_time ASC")->fetchAll();
if (!$shifts) {
    json_response(['status' => 'ERROR', 'message' => 'Chua cau hinh ca lam', 'name' => $user['full_name']]);
}

$selectShiftForNow = function (array $shifts, string $currentTime) {
    // Đang trong ca hiện tại
    foreach ($shifts as $shift) {
        if ($currentTime >= $shift['start_time'] && $currentTime <= $shift['end_time']) {
            return $shift;
        }
    }
    // Chưa tới giờ vào -> lấy ca sắp tới
    foreach ($shifts as $shift) {
        if ($currentTime < $shift['start_time']) {
            return $shift;
        }
    }
    // Sau giờ ra của ca cuối -> mặc định dùng ca cuối (tránh null)
    return end($shifts);
};

// 4. Kiểm tra bản ghi chưa checkout trong ngày (để quyết định Check OUT)
$stmt = $pdo->prepare("
    SELECT a.id, a.shift_id, a.check_in, a.check_out, a.status, s.start_time, s.end_time
    FROM attendance a
    JOIN shifts s ON s.id = a.shift_id
    WHERE a.fingerprint_id = ? AND a.date = ? AND a.check_out IS NULL
    ORDER BY a.check_in DESC
    LIMIT 1
");
$stmt->execute([$fid, $today]);
$log = $stmt->fetch();

$action = "";

if ($log) {
    // Đã có bản ghi chưa checkout -> kiểm tra xem có phải cả 2 lần đều ngoài ca không
    if ($log['check_out']) {
        $action = "DA XONG";
    } else {
        // Kiểm tra: Cả check_in cũ VÀ giờ hiện tại đều NGOÀI ca làm việc?
        $oldCheckInOutside = ($log['check_in'] < $log['start_time'] || $log['check_in'] > $log['end_time']);
        $nowOutside = ($now < $log['start_time'] || $now > $log['end_time']);
        
        if ($oldCheckInOutside && $nowOutside) {
            // CẢ 2 LẦN ĐỀU NGOÀI CA -> Cập nhật check_in thay vì check_out
            // Điều này cho phép chấm lần 3 trong ca sẽ là check_out thực sự
            $pdo->prepare("UPDATE attendance SET check_in = ? WHERE id = ?")->execute([$now, $log['id']]);
            $action = "CAP NHAT GIO VAO";
        } else {
            // Bình thường: Đây là CHECK OUT
            $isEarlyLeave = $now < $log['end_time'];
            $status = $log['status'] ?? '';
            if ($isEarlyLeave && stripos($status, 'Về sớm') === false) {
                $status = $status ? ($status . ' - Về sớm') : 'Về sớm';
            }

            $pdo->prepare("UPDATE attendance SET check_out = ?, status = ? WHERE id = ?")->execute([$now, $status, $log['id']]);
            $action = "CHECK OUT";
        }
    }
} else {
    // Không có bản ghi mở -> xác định ca hiện tại/tiếp theo để Check IN
    $shift = $selectShiftForNow($shifts, $now);

    // Nếu trong ngày đã có bản ghi đủ check in/out cho ca này thì coi như đã xong
    $stmt = $pdo->prepare("SELECT id, check_out FROM attendance WHERE fingerprint_id = ? AND date = ? AND shift_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$fid, $today, $shift['id']]);
    $existing = $stmt->fetch();
    if ($existing && $existing['check_out']) {
        $action = "DA XONG";
    } else {
        $status = ($now <= $shift['start_time']) ? 'Đúng giờ' : 'Đi muộn';
        $pdo->prepare("INSERT INTO attendance (fingerprint_id, shift_id, date, check_in, status) VALUES (?, ?, ?, ?, ?)")->execute([$fid, $shift['id'], $today, $now, $status]);
        $action = "CHECK IN";
    }
}

// 5. Trả về JSON cho ESP32
json_response([
    'status' => 'OK',
    'name' => $user['full_name'], // Tên lấy từ DB
    'action' => $action
]);
?>