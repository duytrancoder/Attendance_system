# TỔNG HỢP CHỨC NĂNG HỆ THỐNG CHẤM CÔNG VÂN TAY

## 📌 TỔNG QUAN DỰ ÁN
Hệ thống chấm công vân tay tích hợp ESP32 và cảm biến AS608, bao gồm:
- Backend API (PHP + MySQL)
- Giao diện quản trị web (Single Page Application)
- Kết nối với thiết bị ESP32 để chấm công tự động

---

## 🔥 CÁC API CHO ESP32 (QUAN TRỌNG)

### 1. API Chấm Công (checkin.php)
**Endpoint:** `GET /api/checkin.php?finger_id={ID}`

**Chức năng:**
- Nhận fingerprint_id từ ESP32 khi nhân viên quẹt vân tay
- Xác định ca làm việc hiện tại dựa trên thời gian
- Tự động xác định hành động: CHECK IN / CHECK OUT / ĐÃ XONG
- Tính toán trạng thái: Đúng giờ / Đi muộn / Về sớm

**Luồng xử lý:**
1. Nhận finger_id từ ESP32
2. Tìm nhân viên trong database
3. Lấy danh sách ca làm việc (shifts)
4. Xác định ca hiện tại/sắp tới
5. Kiểm tra bản ghi chấm công trong ngày:
   - Chưa có check_in → Tạo bản ghi mới (CHECK IN)
   - Có check_in chưa check_out → Cập nhật check_out (CHECK OUT)
   - Đã đủ cả hai → Trả về ĐÃ XONG
6. Tính toán trạng thái dựa trên giờ vào/ra so với ca

**Response JSON:**
```json
{
  "status": "OK",
  "name": "Tên nhân viên",
  "action": "CHECK IN" // hoặc "CHECK OUT", "DA XONG"
}
```

---

### 2. API Đăng Ký Vân Tay (register.php)
**Endpoint:** `GET /api/register.php?id={ID}`

**Chức năng:**
- Đăng ký fingerprint_id mới từ ESP32
- Tự động tạo bản ghi nhân viên tạm thời
- Admin sau đó hoàn thiện thông tin qua giao diện web

**Luồng xử lý:**
1. Nhận ID vân tay từ ESP32
2. Kiểm tra ID đã tồn tại chưa
3. Nếu chưa tồn tại:
   - Tạo nhân viên mới với tên: "Nhân viên mới #{ID}"
   - Phòng ban: "Chờ cập nhật"
   - Chức vụ: "Nhân viên"
4. Nếu đã tồn tại: Trả về thông báo ID đã có

**Response JSON:**
```json
{
  "message": "Đã tạo bản ghi chờ cập nhật cho ID xxx"
}
```

---

### 3. API Xóa Vân Tay (delete.php)
**Endpoint:** 
- Xóa tất cả: `GET /api/delete.php?all=true`
- Xóa theo ID: `GET /api/delete.php?id={ID}`

**Chức năng:**
- Xóa dữ liệu nhân viên và vân tay
- Hỗ trợ xóa toàn bộ hoặc theo fingerprint_id

**Luồng xử lý:**
1. Nhận tham số all=true hoặc id=xxx
2. Xóa dữ liệu từ bảng employees
3. Tự động xóa attendance liên quan (nếu có CASCADE)
4. Reset AUTO_INCREMENT (khi xóa tất cả)

**Response JSON:**
```json
{
  "message": "Da xoa toan bo du lieu" // hoặc "Da xoa ID xxx"
}
```

---

## 📊 CÁC API CHO WEB ADMIN

### 4. API Dashboard (dashboard.php)
**Endpoint:** `GET /api/dashboard.php`

**Chức năng:**
- Hiển thị thống kê tổng quan
- Danh sách chấm công trong ngày

**Dữ liệu trả về:**
```json
{
  "cards": {
    "totalEmployees": 50,  // Tổng số nhân viên
    "present": 45,         // Số người đã đi làm hôm nay
    "late": 3,             // Số người đi muộn
    "absent": 5            // Số người vắng mặt
  },
  "todayLogs": [
    {
      "id": 1,
      "full_name": "Nguyễn Văn A",
      "department": "IT",
      "date": "2025-12-17",
      "check_in": "08:00:00",
      "check_out": "17:30:00",
      "status": "Đúng giờ",
      "shift_name": "Ca sáng"
    }
  ]
}
```

**Tính năng:**
- Đếm tổng nhân viên
- Đếm người có mặt (DISTINCT fingerprint_id trong ngày)
- Đếm người đi muộn (check_in > start_time)
- Tính người vắng (tổng - có mặt)
- Danh sách chi tiết chấm công hôm nay

---

### 5. API Quản Lý Nhân Viên (employees.php)
**Method:** GET, POST, PUT, DELETE

**Chức năng:**

#### GET - Lấy danh sách nhân viên
- `GET /api/employees.php` - Lấy tất cả nhân viên
- `GET /api/employees.php?pending=1` - Lấy nhân viên chờ hoàn thiện thông tin

**Response:**
```json
[
  {
    "id": 1,
    "fingerprint_id": 5,
    "full_name": "Nguyễn Văn A",
    "department": "IT",
    "position": "Developer",
    "birth_year": 1990,
    "created_at": "2025-12-17 10:00:00"
  }
]
```

#### POST - Thêm nhân viên mới
**Body:**
```json
{
  "fingerprint_id": 10,
  "full_name": "Trần Thị B",
  "department": "Marketing",
  "position": "Manager",
  "birth_year": 1988
}
```

#### PUT - Cập nhật thông tin nhân viên
**Body:**
```json
{
  "id": 1,
  "full_name": "Nguyễn Văn A - Updated",
  "department": "IT",
  "position": "Senior Developer",
  "birth_year": 1990
}
```

#### DELETE - Xóa nhân viên
**Body:** `id=1`

---

### 6. API Lịch Sử Chấm Công (attendance.php)
**Endpoint:** `GET /api/attendance.php`

**Chức năng:**
- Xem lịch sử chấm công
- Lọc theo tên nhân viên
- Lọc theo ngày
- Xuất file Excel/CSV

**Parameters:**
- `name` - Lọc theo tên (tìm kiếm LIKE)
- `date` - Lọc theo ngày cụ thể (YYYY-MM-DD)
- `export=1` - Xuất file CSV

**Response:**
```json
[
  {
    "full_name": "Nguyễn Văn A",
    "department": "IT",
    "date": "2025-12-17",
    "check_in": "08:00:00",
    "check_out": "17:30:00",
    "status": "Đúng giờ"
  }
]
```

**Tính năng xuất Excel:**
- Header: Tên, Phòng ban, Ngày, Giờ vào, Giờ ra, Trạng thái
- Format: CSV với UTF-8
- Tên file: attendance.csv

---

### 7. API Quản Lý Ca Làm Việc (settings.php)
**Method:** GET, POST, PUT, DELETE

**Chức năng:**

#### GET - Lấy danh sách ca làm việc
**Endpoint:** `GET /api/settings.php`

**Response:**
```json
[
  {
    "id": 1,
    "shift_name": "Ca sáng",
    "start_time": "08:00:00",
    "end_time": "17:00:00"
  }
]
```

#### POST - Thêm ca làm việc mới
**Body:**
```json
{
  "shift_name": "Ca chiều",
  "start_time": "13:00:00",
  "end_time": "22:00:00"
}
```

#### PUT - Cập nhật ca làm việc
**Body:**
```json
{
  "id": 1,
  "shift_name": "Ca sáng - Updated",
  "start_time": "08:30:00",
  "end_time": "17:30:00"
}
```

#### DELETE - Xóa ca làm việc
**Body:**
```json
{
  "id": 1
}
```

---

## 💻 GIAO DIỆN WEB ADMIN

### Trang Dashboard (index.php)
**Cấu trúc Single Page Application:**
- Sidebar navigation với 4 phần: Dashboard, Nhân viên, Lịch sử, Cấu hình
- Content area hiển thị động theo section được chọn

---

### 1. Phần Dashboard
**Chức năng:**
- Hiển thị 4 thẻ thống kê:
  - Tổng số nhân viên
  - Số người đi làm hôm nay
  - Số người đi muộn
  - Số người vắng mặt
- Bảng danh sách chấm công trong ngày
- Cột: Nhân viên, Phòng ban, Ca, Giờ vào, Giờ ra, Trạng thái
- Badge màu theo trạng thái:
  - Xanh (success): Đúng giờ
  - Vàng (warn): Đi muộn
  - Đỏ (danger): Về sớm
  - Xám (gray): Chưa xác định

---

### 2. Phần Quản Lý Nhân Viên
**Chức năng:**
- Hiển thị danh sách toàn bộ nhân viên
- Cột: Fingerprint ID, Họ tên, Phòng ban, Chức vụ, Năm sinh, Ngày tạo, Actions
- Nút "Vân tay mới được đăng ký": 
  - Hiển thị modal với dropdown chọn nhân viên pending
  - Form hoàn thiện thông tin: Họ tên, Phòng ban, Chức vụ, Năm sinh
  - Fingerprint ID không cho phép sửa
- Nút "Sửa": Mở modal chỉnh sửa thông tin nhân viên
- Nút "Xóa": Xóa nhân viên sau confirm

**Đặc biệt:**
- Tự động load nhân viên có department = "Chờ cập nhật"
- Dropdown cho phép chọn nhiều nhân viên pending
- Khi chọn dropdown thay đổi, form tự động cập nhật thông tin

---

### 3. Phần Lịch Sử Chấm Công
**Chức năng:**
- Bảng hiển thị lịch sử chấm công
- Cột: Tên, Phòng ban, Ngày, Giờ vào, Giờ ra, Trạng thái
- Filter:
  - Input text: Lọc theo tên
  - Input date: Lọc theo ngày
  - Nút "Lọc": Thực hiện filter
  - Nút "Xuất Excel": Download file CSV

**Tính năng:**
- Tìm kiếm real-time khi click nút Lọc
- Xuất Excel với tất cả bản ghi đã filter
- Badge trạng thái màu sắc tương tự Dashboard

---

### 4. Phần Cấu Hình Ca Làm
**Chức năng:**
- Bảng danh sách ca làm việc
- Cột: Tên ca, Giờ vào, Giờ ra, Actions
- Nút "+ Thêm ca": Mở modal thêm ca mới
- Form modal:
  - Tên ca (text)
  - Giờ vào (time picker)
  - Giờ ra (time picker)
- Nút "Sửa": Mở modal chỉnh sửa ca
- Nút "Xóa": Xóa ca sau confirm

---

## 🗄️ CẤU TRÚC CSDL

### Bảng: employees
```sql
- id (PRIMARY KEY, AUTO_INCREMENT)
- fingerprint_id (UNIQUE, INT)
- full_name (VARCHAR)
- department (VARCHAR)
- position (VARCHAR)
- birth_year (INT, nullable)
- created_at (TIMESTAMP)
```

### Bảng: attendance
```sql
- id (PRIMARY KEY, AUTO_INCREMENT)
- fingerprint_id (INT, FOREIGN KEY → employees.fingerprint_id)
- shift_id (INT, FOREIGN KEY → shifts.id)
- date (DATE)
- check_in (TIME, nullable)
- check_out (TIME, nullable)
- status (VARCHAR) - Đúng giờ / Đi muộn / Về sớm
```

### Bảng: shifts
```sql
- id (PRIMARY KEY, AUTO_INCREMENT)
- shift_name (VARCHAR)
- start_time (TIME)
- end_time (TIME)
```

---

## 🔧 CÁC HELPER FUNCTIONS

### Database Connection (db.php)
**Chức năng:**
- Kết nối MySQL qua PDO
- Singleton pattern (chỉ tạo 1 connection)
- Set timezone: Asia/Ho_Chi_Minh
- Error mode: Exception
- Fetch mode: Associative array
- Charset: UTF8MB4

### Helper Functions (helpers.php)
1. **json_response($data, $status)**: Trả về JSON và exit
2. **read_json_body()**: Đọc và parse JSON từ request body
3. **sanitize_string($value)**: Trim và làm sạch string
4. **require_fields($payload, $required)**: Validate required fields

---

## 🎨 FRONTEND (app.js)

### Các Module Chính:

#### 1. Navigation System
- Quản lý chuyển đổi giữa các section
- Active state cho sidebar buttons
- Show/hide sections tương ứng

#### 2. Modal System
- Hiển thị form modal động
- Submit handler với callback
- Close modal bằng nút X hoặc click backdrop

#### 3. Dashboard Module
- Load thống kê từ API
- Render cards với số liệu
- Render bảng chấm công hôm nay
- Format time (HH:MM)
- Badge trạng thái với màu sắc

#### 4. Employee Module
- Load danh sách nhân viên
- Modal thêm/sửa nhân viên
- Modal đặc biệt cho nhân viên pending:
  - Dropdown chọn nhân viên chờ cập nhật
  - Auto-fill form khi đổi dropdown
  - Không cho sửa fingerprint_id
- Xóa nhân viên với confirm

#### 5. Attendance Logs Module
- Load lịch sử chấm công
- Filter theo tên và ngày
- Export CSV

#### 6. Shifts Management Module
- Load danh sách ca làm việc
- Modal thêm/sửa ca
- Time picker cho giờ vào/ra
- Xóa ca với confirm

#### 7. Utility Functions
- formatTime(): Format time thành HH:MM
- statusBadge(): Tạo badge HTML theo trạng thái
- API path detection: Tự động xác định đường dẫn API

---

## 🎯 WORKFLOW HOÀN CHỈNH

### A. Quy Trình Đăng Ký Vân Tay Mới
1. Admin vào ESP32 chọn chế độ đăng ký vân tay
2. Nhân viên quẹt vân tay lên cảm biến AS608
3. ESP32 lưu vân tay và gọi API: `GET /api/register.php?id=10`
4. Backend tạo bản ghi tạm: "Nhân viên mới #10", phòng ban "Chờ cập nhật"
5. Admin vào web → Tab "Nhân viên" → Click "Vân tay mới được đăng ký"
6. Chọn ID từ dropdown, điền thông tin đầy đủ
7. Lưu → Nhân viên hoàn tất đăng ký

### B. Quy Trình Chấm Công Hàng Ngày
1. Nhân viên đặt tay lên cảm biến AS608
2. ESP32 nhận dạng vân tay, lấy được fingerprint_id (VD: 10)
3. ESP32 gọi API: `GET /api/checkin.php?finger_id=10`
4. Backend xử lý:
   - Tìm nhân viên theo ID
   - Xác định ca hiện tại (dựa trên giờ và danh sách shifts)
   - Kiểm tra đã có bản ghi chấm công chưa:
     * Chưa có → Tạo mới (CHECK IN)
     * Có check_in chưa check_out → Cập nhật (CHECK OUT)
     * Đã đủ → Báo ĐÃ XONG
   - Tính trạng thái: Đúng giờ / Đi muộn / Về sớm
5. Backend trả JSON về ESP32
6. ESP32 hiển thị trên màn hình: "Nguyễn Văn A - CHECK IN"
7. Admin vào web → Dashboard → Xem danh sách chấm công real-time

### C. Quy Trình Xem Báo Cáo
1. Admin vào tab "Lịch sử"
2. Nhập tên nhân viên hoặc chọn ngày
3. Click "Lọc"
4. Xem danh sách chi tiết
5. Click "Xuất Excel" → Download file CSV

### D. Quy Trình Cấu Hình Ca Làm
1. Admin vào tab "Cấu hình"
2. Xem danh sách ca hiện có
3. Click "+ Thêm ca"
4. Nhập: Tên ca, Giờ vào, Giờ ra
5. Lưu → Ca mới được áp dụng ngay cho chấm công

### E. Quy Trình Xóa Vân Tay
1. Admin vào ESP32 chọn chế độ xóa vân tay
2. Chọn:
   - Xóa tất cả: ESP32 gọi `GET /api/delete.php?all=true`
   - Xóa theo ID: ESP32 gọi `GET /api/delete.php?id=10`
3. Backend xóa dữ liệu tương ứng
4. Trả về thông báo cho ESP32

---

## 📋 TÍNH NĂNG NỔI BẬT

### 1. Tự động xác định ca làm việc
- Dựa trên giờ hiện tại so với danh sách ca
- Nếu trong giờ ca → Chọn ca đó
- Nếu trước giờ ca → Chọn ca sắp tới
- Nếu sau giờ ca cuối → Chọn ca cuối (fallback)

### 2. Tự động tính trạng thái chấm công
- **CHECK IN:**
  - Đúng giờ: check_in <= start_time
  - Đi muộn: check_in > start_time
- **CHECK OUT:**
  - Về sớm: check_out < end_time
  - Kết hợp với trạng thái cũ: "Đi muộn - Về sớm"

### 3. Quản lý nhân viên pending thông minh
- Nhân viên do ESP32 tạo có department = "Chờ cập nhật"
- Filter riêng với `?pending=1`
- Modal đặc biệt với dropdown chọn nhiều pending
- Không cho phép sửa fingerprint_id

### 4. Single Page Application
- Không reload trang
- Navigation mượt mà
- AJAX calls cho mọi thao tác
- Modal system linh hoạt

### 5. Responsive và User-friendly
- Badge màu sắc rõ ràng
- Confirm trước khi xóa
- Loading states (có thể thêm)
- Error handling

### 6. Export CSV
- Hỗ trợ UTF-8
- Filter trước khi export
- Download trực tiếp

---

## 🔐 BẢO MẬT VÀ VALIDATION

### Validation Backend:
- require_fields(): Kiểm tra required fields
- sanitize_string(): Làm sạch input
- PDO Prepared Statements: Chống SQL Injection
- UNIQUE constraint: fingerprint_id
- Foreign Key constraints: Đảm bảo tính toàn vẹn

### Error Handling:
- Try-catch cho DB operations
- HTTP status codes chuẩn (400, 404, 405, 409, 422, 500)
- JSON error responses

### Frontend Validation:
- HTML5 required attribute
- Confirm dialog trước khi xóa
- Check response status từ API

---

## 🚀 ĐIỂM MẠNH CỦA HỆ THỐNG

1. **Tự động hóa cao:** ESP32 tự xử lý vân tay, backend tự tính toán ca và trạng thái
2. **Linh hoạt:** Hỗ trợ nhiều ca làm việc, dễ dàng thêm/sửa/xóa
3. **User-friendly:** Giao diện đơn giản, trực quan, SPA mượt mà
4. **Scalable:** Cấu trúc module rõ ràng, dễ mở rộng
5. **Real-time:** Dashboard cập nhật ngay khi có chấm công mới
6. **Báo cáo:** Export Excel, filter linh hoạt
7. **Quản lý pending:** Xử lý thông minh nhân viên chờ cập nhật

---

## 📝 GHI CHÚ QUAN TRỌNG

### Timezone:
- Server timezone: Asia/Ho_Chi_Minh (+07:00)
- MySQL timezone: +07:00
- PHP date_default_timezone_set

### Database:
- Engine: InnoDB (để hỗ trợ Foreign Key)
- Charset: utf8mb4 (hỗ trợ emoji và ký tự đặc biệt)
- Collation: utf8mb4_unicode_ci

### API Response Format:
- Luôn trả về JSON
- Success: { "message": "...", data: ... }
- Error: { "error": "..." } với status code tương ứng

### Frontend API Path:
- Auto-detect: Kiểm tra pathname có /public không
- Development: `../api` (từ /public)
- Production: `/api` (từ root)

---

## 🔄 KẾ HOẠCH MỞ RỘNG (FUTURE)

1. **Authentication & Authorization:**
   - Đăng nhập admin
   - Phân quyền (admin, manager, user)
   - Session management

2. **ESPEW Integration:**
   - Webhook thay vì GET parameter
   - Signature verification
   - Retry logic
   - Rate limiting

3. **Advanced Reporting:**
   - Báo cáo theo tháng/quý
   - Chart visualization
   - Export PDF
   - Email reports

4. **Notifications:**
   - Email/SMS thông báo đi muộn
   - Push notification cho admin
   - Alert về sớm/muộn bất thường

5. **Mobile App:**
   - React Native / Flutter
   - Employee self-service
   - Check lịch sử cá nhân

6. **Advanced Features:**
   - Face recognition (thay vì vân tay)
   - GPS check-in
   - Leave management
   - Overtime calculation
   - Salary integration

---

## 📞 LIÊN HỆ & HỖ TRỢ

Hệ thống được xây dựng với mục đích quản lý chấm công đơn giản, hiệu quả cho doanh nghiệp vừa và nhỏ.

**Phiên bản:** 2.0  
**Ngày cập nhật:** 17/12/2025  
**Database:** MySQL 5.7+  
**PHP:** 7.4+  
**Thiết bị:** ESP32 + AS608 Fingerprint Sensor
