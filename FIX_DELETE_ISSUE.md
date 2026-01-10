# 🔧 FIX - Vấn đề xóa vân tay từ phần cứng

## ❌ Vấn Đề
Khi xóa vân tay từ phần cứng (Arduino/ESP32), nhân viên không biến mất khỏi web.

## 🔍 Nguyên Nhân
1. **Database thiếu CASCADE**: Bảng `attendance` không có foreign key CASCADE, nên khi xóa `employees` có thể gây lỗi nếu còn bản ghi `attendance`.
2. **Code không xóa attendance**: Code cũ chỉ xóa bảng `employees` mà không xóa `attendance` trước.

## ✅ Giải Pháp Đã Thực Hiện

### 1. Cập nhật code `api/delete.php`
**Thay đổi:**
- Xóa bản ghi `attendance` TRƯỚC khi xóa `employees`
- Thêm error logging để debug
- Thêm try-catch để bắt lỗi
- Response format chuẩn hóa: `status = 'OK'/'ERROR'`

**Code mới:**
```php
// Xóa attendance trước
$stmtAtt = $pdo->prepare("DELETE FROM attendance WHERE fingerprint_id = ?");
$stmtAtt->execute([$fingerprintId]);

// Sau đó xóa employee
$stmt = $pdo->prepare("DELETE FROM employees WHERE fingerprint_id = ?");
$stmt->execute([$fingerprintId]);
```

### 2. Cập nhật database schema
**Thêm CASCADE foreign key:**
```sql
FOREIGN KEY (fingerprint_id) REFERENCES employees(fingerprint_id) ON DELETE CASCADE
```

Từ giờ, khi xóa employee, tất cả attendance sẽ tự động bị xóa.

## 🚀 Cách Áp Dụng Fix

### Option 1: Chạy Migration (Khuyến Nghị)
Chạy file migration để cập nhật database hiện tại:

```bash
# Mở XAMPP MySQL Shell hoặc phpMyAdmin
# Chạy file migration_add_cascade.sql
```

**Hoặc trong phpMyAdmin:**
1. Vào database `cham_cong_db`
2. Tab "SQL"
3. Copy nội dung file `migration_add_cascade.sql`
4. Click "Go"

### Option 2: Tạo lại database
```bash
# Backup data cũ trước
mysqldump cham_cong_db > backup.sql

# Drop và tạo lại
mysql -u root -e "DROP DATABASE cham_cong_db;"
mysql -u root < database.sql

# Import lại data (nếu cần)
```

## 📝 Cách Test

### Test 1: Xóa từ phần cứng
1. Từ Arduino, xóa vân tay ID = 5
2. Arduino gọi: `GET /api/delete.php?id=5`
3. Kiểm tra web → Nhân viên ID=5 phải biến mất

### Test 2: Kiểm tra log
Xem file log PHP (thường ở `C:\xampp\php\logs\php_error_log`) để thấy:
```
Arduino DELETE request: fingerprint_id = 5
Deleted 3 attendance records for fingerprint_id = 5
Successfully deleted employee with fingerprint_id = 5
```

### Test 3: Response từ API
Gọi API thủ công:
```bash
curl "http://localhost/chamcongv2/api/delete.php?id=5"
```

Kết quả mong đợi:
```json
{
  "status": "OK",
  "message": "Da xoa",
  "fingerprint_id": 5,
  "attendance_deleted": 3
}
```

## 🎯 Kết Quả

Sau khi fix:
- ✅ Xóa từ phần cứng → Web cập nhật ngay (1 giây)
- ✅ Không còn lỗi database constraint
- ✅ Attendance records tự động xóa
- ✅ Log đầy đủ để debug

## ⚠️ Lưu Ý

1. **Auto-refresh**: Web có auto-refresh mỗi 1 giây, nên sau khi xóa, đợi tối đa 1 giây sẽ thấy nhân viên biến mất.

2. **Xóa từ web**: Vẫn hoạt động bình thường theo flow cũ (tạo command → ESP32 poll → ESP32 xóa → Web xóa database).

3. **Backup**: Nên backup database trước khi chạy migration.

## 📞 Debug Nếu Vẫn Lỗi

Kiểm tra:
1. File log PHP có message không?
2. Response từ API có status 'OK' không?
3. Bảng attendance đã có CASCADE chưa?
   ```sql
   SHOW CREATE TABLE attendance;
   ```
4. Web có auto-refresh không? (Check console.log)
