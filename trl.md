# Logic Chấm Công - Đúng Giờ / Đi Muộn

## Tổng Quan
Hệ thống chấm công dựa trên **ca làm việc** (shifts) được cấu hình trong bảng `shifts`. Mỗi ca có:
- `shift_name`: Tên ca (VD: "Ca sáng", "Ca chiều")
- `start_time`: Giờ vào (VD: "08:00:00")
- `end_time`: Giờ ra (VD: "17:00:00")

## Logic Xác Định Trạng Thái

### 1. **CHECK IN (Vào Làm)**

Khi nhân viên chấm công lần đầu trong ngày, hệ thống:

1. **Xác định ca hiện tại:**
   - Nếu giờ hiện tại nằm TRONG khoảng `[start_time, end_time]` → Chọn ca đang diễn ra
   - Nếu giờ hiện tại TRƯỚC `start_time` của tất cả các ca → Chọn ca sắp tới (ca đầu tiên)
   - Nếu giờ hiện tại SAU `end_time` của tất cả các ca → Chọn ca cuối cùng

2. **Đánh giá trạng thái:**
   ```php
   $status = ($now <= $shift['start_time']) ? 'Đúng giờ' : 'Đi muộn';
   ```

   - ✅ **Đúng giờ**: `check_in <= start_time` (chấm công trước hoặc đúng giờ vào)
   - ❌ **Đi muộn**: `check_in > start_time` (chấm công sau giờ vào)

**Ví dụ:**
- Ca sáng: 08:00 - 17:00
- Chấm công lúc 07:55 → "Đúng giờ"
- Chấm công lúc 08:00 → "Đúng giờ"
- Chấm công lúc 08:05 → "Đi muộn"

### 2. **CHECK OUT (Ra Về)**

Khi nhân viên chấm công lần thứ 2 trong ngày (đã có bản ghi check_in chưa có check_out):

1. **Kiểm tra đặc biệt - Cả 2 lần đều ngoài ca:**
   ```php
   $oldCheckInOutside = ($log['check_in'] < $log['start_time'] || $log['check_in'] > $log['end_time']);
   $nowOutside = ($now < $log['start_time'] || $now > $log['end_time']);
   ```
   
   - Nếu **CẢ 2 lần** chấm công đều NGOÀI khung giờ ca làm việc:
     - ✅ **CẬP NHẬT** `check_in = giờ_hiện_tại` (thay thế giờ vào cũ)
     - ✅ Lần chấm thứ 3 sẽ được tính là CHECK OUT bình thường
     - Hiển thị: "CAP NHAT GIO VAO"

2. **Cập nhật giờ ra (bình thường):**
   ```php
   UPDATE attendance SET check_out = $now WHERE id = ...
   ```

3. **Kiểm tra về sớm:**
   ```php
   $isEarlyLeave = $now < $log['end_time'];
   ```

   - Nếu `check_out < end_time` → Thêm " - Về sớm" vào trạng thái
   - Trạng thái ban đầu được giữ nguyên, chỉ thêm cảnh báo

**Ví dụ Đặc Biệt:**
- Ca sáng: 08:00 - 17:00
- Lần 1: Chấm lúc 07:30 (NGOÀI ca) → Tạo check_in
- Lần 2: Chấm lúc 07:50 (NGOÀI ca) → CẬP NHẬT check_in = 07:50
- Lần 3: Chấm lúc 17:05 (trong/sau ca) → CHECK OUT bình thường

**Ví dụ Bình Thường:**
- Ca sáng: 08:00 - 17:00
- Check-in lúc 08:05 → Status: "Đi muộn"
- Check-out lúc 16:30 → Status: "Đi muộn - Về sớm"

### 3. **Đặc Biệt: Chấm Công Nhiều Lần**

- Nếu đã có bản ghi HOÀN CHỈNH (có cả check_in và check_out) cho ca trong ngày → Hiển thị "DA XONG", không cho chấm thêm

## Tóm Tắt Công Thức

| Điều Kiện | Trạng Thái |
|-----------|-----------|
| `check_in <= start_time` | **Đúng giờ** |
| `check_in > start_time` | **Đi muộn** |
| `check_out < end_time` | **Về sớm** (thêm vào status hiện tại) |

## File Liên Quan

- **[checkin.php](file:///c:/xampp/htdocs/chamcongv2/api/checkin.php)** (dòng 87): Logic xác định "Đúng giờ" / "Đi muộn"
- **[dashboard.php](file:///c:/xampp/htdocs/chamcongv2/api/dashboard.php)** (dòng 18-25): Đếm số người đi muộn trong ngày
- **Bảng `shifts`**: Cấu hình giờ vào/ra của các ca làm việc
