# 🔧 DEBUG - Vấn Đề Xóa Từ AS608

## ❌ Triệu Chứng
Xóa vân tay từ AS608 (phần cứng) nhưng thông tin trên web KHÔNG BỊ XÓA.

## 🔍 Các Nguyên Nhân Có Thể

### 1. Arduino KHÔNG GỌI API delete.php
- Arduino code có thể bị comment hoặc không có phần xóa
- URL trong Arduino sai
- Network issue (Arduino không kết nối được server)

### 2. Arduino GỌI SAI API hoặc SAI THAM SỐ
- Gọi POST thay vì GET
- Thiếu parameter `id`
- Gọi với `employee_id` (database ID) thay vì `fingerprint_id`

### 3. Server Không Nhận Được Request
- Firewall block
- XAMPP/Apache không chạy
- Wrong IP address

## ✅ CÁCH DEBUG

### Bước 1: Kiểm Tra Arduino Có Gọi API Không

**Mở trang monitor:**
```
http://localhost/chamcongv2/monitor_delete.php
```

**Trang này sẽ:**
- Auto-refresh mỗi 2 giây
- Hiển thị MỌI request đến `api/delete.php`
- Cho biết Arduino có gọi không, gọi với tham số gì

**Cách test:**
1. Mở trang monitor trên browser
2. Xóa vân tay trên AS608
3. Xem có request xuất hiện không?

**Kết quả:**
- ✅ **CÓ request** → Arduino gọi được API, chuyển sang Bước 2
- ❌ **KHÔNG có request** → Arduino KHÔNG gọi API, xem Bước 4

---

### Bước 2: Kiểm Tra Request Có Đúng Format Không

Trong monitor, request phải có dạng:
```
[2026-01-10 12:00:00] Method: GET | GET: {"id":"5"} | POST: [] | Body: 
```

**Kiểm tra:**
- ✅ Method = **GET** (không phải POST)
- ✅ Có parameter **id** trong GET
- ✅ id = fingerprint_id (1, 2, 3...), KHÔNG phải employee database id (10, 11, 12...)

**Nếu sai format:**
→ Sửa code Arduino (xem Bước 5)

---

### Bước 3: Test API Thủ Công

Giả sử bạn muốn xóa fingerprint_id = 5:

**Test trực tiếp:**
```
http://localhost/chamcongv2/test_delete.php?id=5
```

**Kết quả mong đợi:**
```
=== TEST DELETE API ===

Testing delete for fingerprint_id = 5

✅ Employee found:
   ID: 10
   Name: Nguyễn Văn A
   Department: IT

📊 Attendance records: 3

🔥 Calling DELETE API...
URL: http://localhost/chamcongv2/api/delete.php?id=5

HTTP Code: 200
Response: {"status":"OK","message":"Da xoa","fingerprint_id":5,"attendance_deleted":3}

✅ SUCCESS: Employee deleted from database!
📊 Attendance records after delete: 0

=== TEST COMPLETE ===
```

**Nếu test thành công** → API hoạt động OK
**Nếu test thất bại** → Có lỗi trong code PHP

---

### Bước 4: Kiểm Tra Arduino Code

**Arduino PHẢI GỌI API khi xóa vân tay:**

```cpp
void deleteFingerprint(int id) {
  // 1. Xóa vân tay khỏi AS608
  if (finger.deleteModel(id) == FINGERPRINT_OK) {
    
    // 2. QUAN TRỌNG: Gọi API để xóa khỏi database
    String url = "http://192.168.1.100/chamcongv2/api/delete.php?id=" + String(id);
    
    Serial.println("Calling: " + url);  // DEBUG: Xem URL
    
    http.begin(url);
    int httpCode = http.GET();
    String response = http.getString();
    
    Serial.println("Response: " + response);  // DEBUG: Xem response
    http.end();
    
    lcd.print("Da xoa ID " + String(id));
  }
}
```

**Kiểm tra:**
- ✅ Có gọi `http.begin(url)` và `http.GET()`?
- ✅ URL đúng định dạng: `/api/delete.php?id=X`?
- ✅ IP address đúng?
- ✅ Có log Serial để debug?

---

### Bước 5: Fix Arduino Code

**Nếu Arduino KHÔNG GỌI API**, thêm code sau vào hàm xóa vân tay:

```cpp
// THÊM VÀO HÀM XÓA VÂN TAY
void notifyServerDelete(int fingerprintId) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    
    // QUAN TRỌNG: Thay IP này bằng IP server của bạn
    String url = "http://192.168.1.100/chamcongv2/api/delete.php?id=" + String(fingerprintId);
    
    Serial.print("Deleting from server: ");
    Serial.println(url);
    
    http.begin(url);
    int httpCode = http.GET();
    
    if (httpCode > 0) {
      String response = http.getString();
      Serial.println("Server response: " + response);
      
      // Parse JSON nếu cần
      if (response.indexOf("\"status\":\"OK\"") > 0) {
        Serial.println("✅ Deleted from server!");
      } else {
        Serial.println("❌ Server error!");
      }
    } else {
      Serial.println("❌ HTTP Error: " + String(httpCode));
    }
    
    http.end();
  } else {
    Serial.println("❌ WiFi not connected!");
  }
}

// GỌI HÀM NÀY SAU KHI XÓA VÂN TAY KHỎI AS608
void deleteFingerprint(int id) {
  if (finger.deleteModel(id) == FINGERPRINT_OK) {
    Serial.println("Deleted from AS608: " + String(id));
    
    // XÓA KHỎI SERVER
    notifyServerDelete(id);
    
    lcd.print("Da xoa ID " + String(id));
  }
}
```

---

### Bước 6: Kiểm Tra Network

**Test từ browser:**
```
http://192.168.1.100/chamcongv2/api/delete.php?id=1
```

**Nếu browser báo lỗi:**
- Check XAMPP Apache đang chạy
- Check IP address đúng không
- Check firewall

**Nếu browser OK nhưng Arduino lỗi:**
- Arduino và server phải cùng mạng WiFi
- Ping từ Arduino: `WiFi.hostByName("192.168.1.100", ...)`

---

## 📊 Checklist Debug

- [ ] Mở monitor: `http://localhost/chamcongv2/monitor_delete.php`
- [ ] Xóa vân tay từ AS608
- [ ] Xem có request xuất hiện trong monitor không?
- [ ] Nếu KHÔNG → Check Arduino code có gọi HTTP GET không
- [ ] Nếu CÓ → Check request có đúng format GET `/api/delete.php?id=X` không
- [ ] Test API thủ công: `http://localhost/chamcongv2/test_delete.php?id=X`
- [ ] Nếu test OK → Vấn đề ở Arduino
- [ ] Nếu test FAIL → Vấn đề ở PHP code
- [ ] Check Serial Monitor Arduino để xem debug log
- [ ] Check file log: `chamcongv2/delete_requests.log`

---

## 🎯 Kết Luận

**99% trường hợp:** Arduino KHÔNG GỌI API `delete.php` khi xóa vân tay.

**Giải pháp:** Thêm code gọi HTTP GET sau khi xóa vân tay khỏi AS608.

**File cần sửa:** Arduino `.ino` file

**Code cần thêm:** Xem Bước 5 ở trên

---

## 📞 Tools Hỗ Trợ Debug

| Tool | URL | Mục đích |
|------|-----|----------|
| Monitor (Real-time) | `http://localhost/chamcongv2/monitor_delete.php` | Xem request từ Arduino |
| Test API | `http://localhost/chamcongv2/test_delete.php?id=X` | Test xóa thủ công |
| Debug Log | `http://localhost/chamcongv2/debug_delete_log.php` | Xem log chi tiết |
| Request Log File | `chamcongv2/delete_requests.log` | File log thô |

---

**Nếu vẫn không được, gửi cho tôi:**
1. Screenshot của monitor khi xóa vân tay
2. Serial output từ Arduino
3. Nội dung file `delete_requests.log`
