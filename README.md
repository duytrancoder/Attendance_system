# Hệ Thống Chấm Công - Chamcong v2

Ứng dụng quản lý chấm công vân tay với giao diện web admin và kết nối ESP32 (hỗ trợ cảm biến AS608).

---

## 📁 Cấu Trúc Thư Mục

```
chamcongv2/
├── config.php           # Cấu hình cơ sở dữ liệu MySQL
├── README.md            # Tệp này
├── api/                 # Các API endpoint
│   ├── checkin.php      # 🔴 API chấm công từ ESP32 (Check IN/OUT)
│   ├── register.php     # 🔴 API đăng ký vân tay từ ESP32
│   ├── delete.php       # 🔴 API xóa vân tay từ ESP32
│   ├── attendance.php   # API quản lý/xem lịch sử chấm công
│   ├── dashboard.php    # API thống kê dashboard
│   ├── employees.php    # API quản lý nhân viên
│   └── settings.php     # API quản lý ca làm việc (shifts)
├── includes/            # Các file dùng chung
│   ├── db.php           # Kết nối cơ sở dữ liệu
│   └── helpers.php      # Hàm tiện ích
└── public/              # Thư mục công cộng (frontend)
    ├── index.php        # Giao diện admin chính
    └── assets/
        ├── app.js       # JavaScript frontend
        └── styles.css   # CSS styling
```

---

## 📋 Chi Tiết Các File

### � **QUAN TRỌNG - Các API Dành Cho ESP32**

Ba file này được ESP32 gọi trực tiếp, **CỰC KỲ QUAN TRỌNG** khi tích hợp ESPEW:

---

### 🔴 **api/checkin.php** - API Chấm Công (Check IN/OUT)
**Chức năng:** Nhận fingerprint_id từ ESP32 và xử lý chấm công tự động  

**Endpoint:**
```
GET /api/checkin.php?finger_id={ID}
```

**Luồng xử lý:**
1. Nhận `finger_id` từ ESP32
2. Tìm nhân viên trong database
3. Lấy danh sách ca làm việc (shifts)
4. Xác định ca hiện tại dựa vào thời gian
5. Kiểm tra đã có bản ghi chấm công chưa:
   - **Nếu chưa có check_in:** Tạo bản ghi mới → **CHECK IN**
   - **Nếu có check_in nhưng chưa check_out:** Cập nhật check_out → **CHECK OUT**
   - **Nếu đã đủ:** Trả về **DA XONG**
6. Tính toán trạng thái:
   - `Đúng giờ` - Check in đúng/trước giờ ca
   - `Đi muộn` - Check in sau giờ ca
   - `Về sớm` - Check out trước giờ ra

**Response:**
```json
{
  "status": "OK",
  "name": "Nguyễn Văn A",
  "action": "CHECK IN" // hoặc "CHECK OUT", "DA XONG"
}
```

**⭐ Mức độ quan trọng:** 🔥🔥🔥 **CỰC KỲ QUAN TRỌNG**  
**🔗 ESPEW Integration:** ⚠️ **PRIORITY 1 - CRITICAL**
- **Phải sửa:** Webhook nhận dữ liệu từ ESPEW thay vì GET parameter
- **Thêm:** Xác thực webhook signature
- **Thêm:** Logging mọi giao dịch
- **Thay đổi format:** Nhận JSON body thay vì query string
- **Thêm error handling:** Retry logic, timeout

---

### 🔴 **api/register.php** - API Đăng Ký Vân Tay
**Chức năng:** Đăng ký fingerprint_id mới từ ESP32  

**Endpoint:**
```
GET /api/register.php?id={ID}
```

**Luồng xử lý:**
1. Nhận `id` từ ESP32
2. Kiểm tra ID đã tồn tại trong database chưa
3. Nếu chưa tồn tại:
   - Tạo nhân viên mới với tên tạm: `"Nhân viên mới #ID"`
   - Phòng ban: `"Chờ cập nhật"`
   - Chức vụ: `"Nhân viên"`
4. Admin sau đó vào web để cập nhật thông tin đầy đủ

**Response:**
```json
{
  "message": "Đã tạo bản ghi chờ cập nhật cho ID 5"
}
```

**⭐ Mức độ quan trọng:** 🔥🔥 **RẤT QUAN TRỌNG**  
**🔗 ESPEW Integration:** ⚠️ **PRIORITY 2**
- **Phải sửa:** Đồng bộ với ESPEW fingerprint database
- **Thêm:** Validation fingerprint_id từ ESPEW
- **Thêm:** Auto-sync thông tin nhân viên
- **Cân nhắc:** Có cần lưu ESPEW device_id không?

---

### 🔴 **api/delete.php** - API Xóa Vân Tay
**Chức năng:** Xóa dữ liệu vân tay/nhân viên từ ESP32  

**Endpoint:**
```
GET /api/delete.php?all=true          # Xóa toàn bộ
GET /api/delete.php?id={ID}           # Xóa theo ID
```

**Luồng xử lý:**
- **Xóa toàn bộ (`?all=true`):**
  - Xóa tất cả nhân viên (`DELETE FROM employees`)
  - Xóa tất cả chấm công (nếu có CASCADE)
  - Reset AUTO_INCREMENT về 1

- **Xóa theo ID (`?id=5`):**
  - Xóa nhân viên có `fingerprint_id = 5`
  - Các bản ghi attendance liên quan cũng bị xóa (nếu có CASCADE)

**Response:**
```json
{
  "message": "Da xoa toan bo du lieu"
}
```

**⭐ Mức độ quan trọng:** 🔥 **QUAN TRỌNG**  
**🔗 ESPEW Integration:** ⚠️ **PRIORITY 3**
- **Thêm:** Đồng bộ xóa với ESPEW API
- **Thêm:** Confirmation/security check
- **Cân nhắc:** Soft delete thay vì hard delete?
- **Thêm:** Backup trước khi xóa

---

### �🔧 **config.php** - Cấu Hình
**Chức năng:** Chứa thông tin kết nối MySQL  
**Nội dung:**
- Host: `127.0.0.1`
- Port: `3306`
- Database: `cham_cong_db`
- User: `root`
- Charset: `utf8mb4`

**⚠️ Quan trọng:** File này cần được bảo mật (không push lên repo công cộng)  
**Sửa khi nào:**
- Thay đổi thông tin truy cập database
- Chuyển sang server production
- Cấu hình database mới

---

### 📊 **includes/db.php** - Kết Nối Cơ Sở Dữ Liệu
**Chức năng:** Singleton PDO connection để chia sẻ kết nối database  
**Export:**
- `db()` - Hàm trả về kết nối PDO singleton

**Chi tiết:**
- Sử dụng singleton pattern để tránh multiple connections
- Cấu hình error mode: ERRMODE_EXCEPTION
- Default fetch mode: FETCH_ASSOC
- Tự động load config.php

**⭐ Quan trọng:** YES - Tất cả API endpoint đều phụ thuộc vào file này  
**Sửa khi nào:**
- Thêm middleware xác thực
- Tối ưu connection pooling
- Thêm retry logic

---

### 🛠️ **includes/helpers.php** - Hàm Tiện Ích
**Chức năng:** Các hàm helper dùng chung trong API  
**Export:**
- `json_response()` - Trả về response JSON với status code
- `read_json_body()` - Đọc JSON từ request body
- `sanitize_string()` - Làm sạch và trim string
- `require_fields()` - Kiểm tra required fields

**⭐ Quan trọng:** YES - Dùng trong tất cả API  
**Sửa khi nào:**
- Thêm validation function
- Thêm authorization check
- Thêm logging/error handling

---

### 📊 **api/attendance.php** - API Xem Lịch Sử Chấm Công
**Chức năng:** Xem, tìm kiếm và export lịch sử chấm công (KHÔNG dùng để ghi chấm công)  

**API Endpoint:**
```
GET /api/attendance.php
```

**Query Parameters:**
- `name` (optional) - Lọc theo tên nhân viên (LIKE)
- `date` (optional) - Lọc theo ngày (YYYY-MM-DD)
- `export` (optional) - Export ra CSV

**Dữ liệu trả về:**
```json
[
  {
    "full_name": "Nguyễn Văn A",
    "department": "IT",
    "date": "2025-12-16",
    "check_in": "08:30:00",
    "check_out": "17:30:00",
    "status": "Đúng giờ"
  }
]
```

**⚠️ Chỉ hỗ trợ GET** - Dữ liệu được ghi bởi `checkin.php`  

**⭐ Mức độ quan trọng:** ⭐⭐ **QUAN TRỌNG** (cho admin xem báo cáo)  
**🔗 ESPEW Integration:** ✅ **Không cần sửa nhiều**
- Dữ liệu vẫn lấy từ database local
- Có thể thêm filter theo ESPEW device_id nếu cần

---

### 👥 **api/employees.php** - API Nhân Viên
**Chức năng:** CRUD nhân viên  

**API Endpoints:**
```
GET    /api/employees.php          # Lấy danh sách nhân viên
POST   /api/employees.php          # Thêm nhân viên mới
PUT    /api/employees.php          # Cập nhật nhân viên
DELETE /api/employees.php          # Xóa nhân viên
```

**Request/Response:**
```json
{
  "fingerprint_id": 1,
  "full_name": "Nguyễn Văn A",
  "department": "IT",
  "position": "Developer",
  "birth_year": 1995
}
```

**Lỗi xảy ra:**
- `422` - Missing required field
- `409` - Fingerprint ID đã tồn tại
- `404` - Nhân viên không tìm thấy

**⭐ Quan trọng:** YES - Quản lý dữ liệu nhân viên  
**Sửa khi nào:**
- Thêm validation role
- Thêm soft delete
- **ESPEW:** Đồng bộ fingerprint_id với ESPEW database

---

### 📈 **api/dashboard.php** - API Dashboard/Thống Kê
**Chức năng:** Lấy số liệu thống kê và danh sách chấm công hôm nay cho dashboard  

**API Endpoint:**
```
GET /api/dashboard.php
```

**Dữ liệu trả về:**
```json
{
  "cards": {
    "totalEmployees": 50,
    "present": 45,
    "late": 5,
    "absent": 5
  },
  "todayLogs": [
    {
      "id": 123,
      "full_name": "Nguyễn Văn A",
      "department": "IT",
      "date": "2025-12-16",
      "check_in": "08:00:00",
      "check_out": "17:30:00",
      "status": "Đúng giờ",
      "shift_name": "Ca Sáng"
    }
  ]
}
```

**Thống kê được tính:**
- `totalEmployees` - Tổng số nhân viên
- `present` - Số người đã chấm công hôm nay
- `late` - Số người đi muộn (check_in > start_time)
- `absent` - Số người vắng (totalEmployees - present)
- `todayLogs` - Danh sách chi tiết chấm công hôm nay

**⭐ Mức độ quan trọng:** ⭐⭐⭐ **RẤT QUAN TRỌNG** (trang chủ admin)  
**🔗 ESPEW Integration:** ⚠️ **PRIORITY 3**
- **Có thể thêm:** Trạng thái kết nối ESPEW
- **Có thể thêm:** Số liệu từ nhiều thiết bị ESPEW

---

### ⚙️ **api/settings.php** - API Quản Lý Ca Làm Việc (Shifts)
**Chức năng:** CRUD ca làm việc (shifts)  

**API Endpoints:**
```
GET    /api/settings.php          # Lấy danh sách ca làm việc
POST   /api/settings.php          # Thêm ca mới
PUT    /api/settings.php          # Cập nhật ca
DELETE /api/settings.php          # Xóa ca
```

**Dữ liệu ca làm việc:**
```json
{
  "id": 1,
  "shift_name": "Ca Sáng",
  "start_time": "08:00:00",
  "end_time": "12:00:00"
}
```

**Ví dụ request:**
```json
// POST - Thêm ca mới
{
  "shift_name": "Ca Chiều",
  "start_time": "13:00:00",
  "end_time": "17:00:00"
}

// PUT - Cập nhật ca
{
  "id": 1,
  "shift_name": "Ca Sáng (Mới)",
  "start_time": "07:30:00",
  "end_time": "11:30:00"
}

// DELETE - Xóa ca
{
  "id": 1
}
```

**⭐ Mức độ quan trọng:** ⭐⭐⭐ **RẤT QUAN TRỌNG**  
**Lý do:** `checkin.php` dùng shifts để tính toán trạng thái chấm công  
**🔗 ESPEW Integration:** ✅ **Không cần sửa** - Chỉ là cấu hình local

---

### 🖥️ **public/index.php** - Giao Diện Admin
**Chức năng:** Trang admin dashboard chính  

**Các Section:**
- **Dashboard** - Tổng quan thống kê
- **Nhân viên** - Quản lý danh sách nhân viên
- **Lịch sử** - Xem lịch sử chấm công
- **Cấu hình** - Cài đặt hệ thống

**Hiển thị:**
- Trạng thái AS608 sensor
- Trạng thái ESP32 online/offline
- Thống kê chấm công hôm nay

**⭐ Quan trọng:** YES - Giao diện chính người dùng  
**Sửa khi nào:**
- Thêm UI components
- **ESPEW:** Thêm section kết nối ESPEW, hiển thị trạng thái

---

### 📱 **public/assets/app.js** - JavaScript Frontend
**Chức năng:** Logic giao diện, gọi API, xử lý events  

**Chứa:**
- Gọi API endpoints
- Load dữ liệu dashboard, nhân viên, chấm công
- Event listeners cho buttons
- AJAX requests

**⭐ Quan trọng:** YES - Điều khiển giao diện  
**Sửa khi nào:**
- Thêm event handlers
- Thêm validation form
- **ESPEW:** Thêm function gọi ESPEW APIs, handle response, update UI

---

### 🎨 **public/assets/styles.css** - CSS Styling
**Chức năng:** Styling UI dashboard  

**⭐ Quan trọng:** NO - Chỉ dùng cho giao diện  
**Sửa khi nào:**
- Đổi màu sắc, theme
- Tối ưu responsive
- Thêm animation

---

## 🔗 Tích Hợp ESPEW - Roadmap Chi Tiết

### 📋 Tổng Quan Thay Đổi

Hiện tại hệ thống dùng **ESP32 gọi trực tiếp** qua HTTP GET:
```
ESP32 → checkin.php?finger_id=5
ESP32 → register.php?id=5
ESP32 → delete.php?id=5
```

Khi chuyển sang **ESPEW**, cần đổi sang:
```
ESPEW → Webhook POST với JSON body
Server → ESPEW API (để đồng bộ, xóa, v.v.)
```

---

### 🎯 Các File Cần Sửa (Theo Ưu Tiên)

#### **🔥 PRIORITY 1 - CRITICAL (Phải sửa ngay)**

**1. `api/checkin.php` - Webhook Chấm Công**
- ❌ **Hiện tại:** Nhận GET request từ ESP32
- ✅ **Cần sửa:**
  - Đổi sang nhận POST với JSON body từ ESPEW
  - Thêm webhook signature verification
  - Parse JSON thay vì $_GET
  - Logging mọi transaction
  - Error handling và retry

**Ví dụ payload từ ESPEW:**
```json
{
  "event": "fingerprint_detected",
  "device_id": "espew_001",
  "fingerprint_id": 5,
  "timestamp": "2025-12-16T08:30:00Z",
  "signature": "abc123..."
}
```

**2. `api/register.php` - Đăng Ký Vân Tay**
- ❌ **Hiện tại:** Nhận GET từ ESP32
- ✅ **Cần sửa:**
  - Đổi sang webhook POST từ ESPEW
  - Verify signature
  - Lưu ESPEW device_id
  - Sync ngược lại với ESPEW API (nếu cần)

**3. `api/delete.php` - Xóa Vân Tay**
- ❌ **Hiện tại:** Nhận GET từ ESP32
- ✅ **Cần sửa:**
  - Gọi ESPEW API để xóa vân tay trên thiết bị
  - Sau đó mới xóa trong database local
  - Handle lỗi nếu ESPEW API fail
  - Thêm confirmation

---

#### **⚠️ PRIORITY 2 - IMPORTANT**

**4. `includes/db.php` hoặc tạo file mới**
- ✅ **Thêm:**
  - Functions gọi ESPEW API
  - `espew_api_call($endpoint, $method, $data)`
  - Lưu ESPEW config (API key, base URL)
  - Cache response từ ESPEW

**5. `api/employees.php` - Quản Lý Nhân Viên**
- ✅ **Thêm:**
  - Khi tạo/sửa/xóa nhân viên → sync với ESPEW
  - Thêm field `espew_device_id` vào employees table
  - Validate fingerprint_id tồn tại trên ESPEW

**6. Tạo file mới: `api/espew_config.php`**
- ✅ **Mục đích:**
  - Lưu/lấy ESPEW API key, webhook URL
  - Test kết nối ESPEW
  - Xem danh sách devices
  - Sync toàn bộ dữ liệu

---

#### **📊 PRIORITY 3 - ENHANCEMENT**

**7. `api/dashboard.php`**
- ✅ **Thêm:** Trạng thái kết nối ESPEW (online/offline)

**8. `public/index.php` + `public/assets/app.js`**
- ✅ **Thêm:** Tab "Cấu hình ESPEW" trong admin panel
- ✅ **Thêm:** Hiển thị status ESPEW trên dashboard

---

### 🛠️ Database Schema Mở Rộng Cho ESPEW

```sql
-- Thêm cột vào bảng employees
ALTER TABLE employees ADD COLUMN espew_device_id VARCHAR(100);
ALTER TABLE employees ADD COLUMN espew_synced_at TIMESTAMP NULL;

-- Bảng mới: Lưu config ESPEW
CREATE TABLE espew_config (
  id INT PRIMARY KEY AUTO_INCREMENT,
  config_key VARCHAR(100) UNIQUE NOT NULL,
  config_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Bảng mới: Log webhook từ ESPEW
CREATE TABLE espew_webhook_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  event_type VARCHAR(50),
  device_id VARCHAR(100),
  fingerprint_id INT,
  payload TEXT,
  status VARCHAR(20),
  error_message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

### 🔐 Bảo Mật Webhook

Khi nhận webhook từ ESPEW:

```php
// Trong checkin.php, register.php, delete.php
function verify_espew_signature($payload, $signature) {
    $secret = get_espew_config('webhook_secret');
    $computed = hash_hmac('sha256', $payload, $secret);
    return hash_equals($computed, $signature);
}

$raw_body = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_ESPEW_SIGNATURE'] ?? '';

if (!verify_espew_signature($raw_body, $signature)) {
    json_response(['error' => 'Invalid signature'], 401);
}
```

---

## 🚀 Database Schema (Required)

```sql
-- Bảng nhân viên
CREATE TABLE employees (
  id INT PRIMARY KEY AUTO_INCREMENT,
  fingerprint_id INT UNIQUE NOT NULL,
  full_name VARCHAR(255),
  department VARCHAR(100),
  position VARCHAR(100),
  birth_year INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bảng ca làm việc
CREATE TABLE shifts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  shift_name VARCHAR(100) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bảng chấm công
CREATE TABLE attendance (
  id INT PRIMARY KEY AUTO_INCREMENT,
  fingerprint_id INT NOT NULL,
  shift_id INT,
  date DATE NOT NULL,
  check_in TIME,
  check_out TIME,
  status VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (fingerprint_id) REFERENCES employees(fingerprint_id) ON DELETE CASCADE,
  FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE SET NULL
);

-- Index để tăng tốc query
CREATE INDEX idx_attendance_date ON attendance(date);
CREATE INDEX idx_attendance_fingerprint ON attendance(fingerprint_id);
CREATE INDEX idx_attendance_shift ON attendance(shift_id);
```

**⚠️ Quan trọng:**
- `ON DELETE CASCADE` trên `employees.fingerprint_id` - Khi xóa nhân viên sẽ tự động xóa lịch sử chấm công
- `ON DELETE SET NULL` trên `shifts.id` - Khi xóa ca làm việc, attendance vẫn giữ nhưng shift_id = NULL

---

## 📝 Lưu Ý Quan Trọng

### 🔒 Bảo Mật:
- ❌ **Không commit** `config.php` với mật khẩu thật lên GitHub
- ✅ Sử dụng `.env` file cho production
- ✅ Thêm CORS headers cho API
- ✅ Validate/sanitize tất cả input
- ✅ **CRITICAL:** Verify webhook signature từ ESPEW
- ✅ Rate limiting cho API endpoints
- ✅ HTTPS bắt buộc khi production

### 🔗 ESPEW Integration Checklist:
- [ ] Đổi `checkin.php` từ GET sang POST webhook
- [ ] Đổi `register.php` từ GET sang POST webhook
- [ ] Đổi `delete.php` sang gọi ESPEW API
- [ ] Thêm webhook signature verification
- [ ] Tạo bảng `espew_config` và `espew_webhook_logs`
- [ ] Implement error handling và retry logic
- [ ] Log tất cả ESPEW transactions
- [ ] Test với ESPEW sandbox/test environment
- [ ] Thêm UI config ESPEW trong admin panel
- [ ] Documentation API cho ESPEW team

### ⚡ Performance:
- ⚡ Thêm pagination cho `attendance.php`
- ⚡ Cache dashboard stats (Redis/Memcached)
- ⚡ Index database đã có trong schema
- ⚡ Implement request throttling
- ⚡ Optimize JOIN queries
- ⚡ Cân nhắc queue cho webhook processing (nếu volume lớn)

### 🐛 Debug & Logging:
- 📝 Log mọi webhook request từ ESPEW
- 📝 Log mọi API call đến ESPEW
- 📝 Lưu error stack trace
- 📝 Monitor response time
- 📝 Alert khi ESPEW offline

### 🧪 Testing:
- ✅ Test webhook với fake ESPEW payload
- ✅ Test xử lý duplicate requests
- ✅ Test khi ESPEW API timeout
- ✅ Test với invalid signature
- ✅ Load testing với nhiều concurrent requests

---

## 🔄 Flow Luồng Chính

### Hiện Tại (ESP32):
```
1. Nhân viên quẹt vân tay
    ↓
2. ESP32 đọc fingerprint_id
    ↓
3. ESP32 → GET /api/checkin.php?finger_id=5
    ↓
4. Server xử lý chấm công
    ↓
5. Server trả JSON → ESP32 hiển thị LCD
    ↓
6. Admin xem: Frontend → GET /api/dashboard.php
```

### Sau Khi Tích Hợp ESPEW:
```
1. Nhân viên quẹt vân tay
    ↓
2. ESPEW Device đọc fingerprint_id
    ↓
3. ESPEW → POST /api/checkin.php (webhook với JSON)
    ↓
4. Server verify signature
    ↓
5. Server xử lý chấm công + lưu database
    ↓
6. Server response → ESPEW hiển thị
    ↓
7. Admin xem: Frontend → GET /api/dashboard.php

// Khi admin thao tác (xóa, sửa):
Admin → POST /api/employees.php
    ↓
Server → Call ESPEW API để sync
    ↓
ESPEW cập nhật thiết bị
```

---

## � Tóm Tắt File Theo Mức Độ Quan Trọng

### 🔥 CỰC KỲ QUAN TRỌNG (Core System):
1. **`api/checkin.php`** - ❗ Phải sửa cho ESPEW
2. **`api/register.php`** - ❗ Phải sửa cho ESPEW
3. **`api/delete.php`** - ❗ Phải sửa cho ESPEW
4. **`includes/db.php`** - Core database connection
5. **`includes/helpers.php`** - Utilities cho tất cả APIs

### ⭐ RẤT QUAN TRỌNG:
6. **`api/dashboard.php`** - Hiển thị trang chủ
7. **`api/employees.php`** - Quản lý nhân viên
8. **`api/settings.php`** - Quản lý ca làm việc
9. **`public/index.php`** - Giao diện admin
10. **`public/assets/app.js`** - Logic frontend

### ⚙️ QUAN TRỌNG:
11. **`api/attendance.php`** - Xem lịch sử (read-only)
12. **`public/assets/styles.css`** - UI styling
13. **`config.php`** - Database config

---

## 🎯 Quick Start Guide

### 1. Setup Database:
```sql
-- Chạy schema trong phần "Database Schema (Required)"
-- Tạo 3 bảng: employees, shifts, attendance
```

### 2. Config Database:
```php
// Sửa config.php với thông tin MySQL của bạn
'host' => '127.0.0.1',
'database' => 'cham_cong_db',
'username' => 'root',
'password' => '',
```

### 3. Thêm Ca Làm Việc:
```bash
# POST /api/settings.php
curl -X POST http://localhost/chamcongv2/api/settings.php \
  -H "Content-Type: application/json" \
  -d '{"shift_name":"Ca Sáng","start_time":"08:00:00","end_time":"12:00:00"}'
```

### 4. Test Với ESP32:
```bash
# Đăng ký vân tay ID = 1
curl "http://localhost/chamcongv2/api/register.php?id=1"

# Chấm công
curl "http://localhost/chamcongv2/api/checkin.php?finger_id=1"
```

### 5. Mở Admin Panel:
```
http://localhost/chamcongv2/public/
```

---

## 🔧 Troubleshooting

### Lỗi thường gặp:

**1. "Database connection failed"**
- Kiểm tra `config.php`
- Đảm bảo MySQL đang chạy (XAMPP)
- Kiểm tra tên database đã tạo chưa

**2. "Missing field: shift_name"**
- Chưa có ca làm việc trong database
- Thêm ít nhất 1 shift qua `api/settings.php`

**3. "Chua dang ky" khi chấm công**
- Fingerprint ID chưa có trong database
- Gọi `register.php` trước khi `checkin.php`

**4. ESP32 không kết nối được**
- Kiểm tra firewall/port
- Đảm bảo ESP32 và server cùng network
- Thử ping IP của server từ ESP32

**5. ESPEW webhook không hoạt động**
- Kiểm tra webhook URL đã config đúng chưa
- Verify signature có khớp không
- Check `espew_webhook_logs` table

---

## 📞 Hỗ Trợ & Resources

### Khi tích hợp ESPEW:
- 📚 Đọc ESPEW API documentation
- 🧪 Test webhook với Postman/curl
- 🔍 Check logs trong `espew_webhook_logs` table
- 💬 Liên hệ ESPEW support team

### Testing Tools:
```bash
# Test webhook giả lập ESPEW
curl -X POST http://localhost/chamcongv2/api/checkin.php \
  -H "Content-Type: application/json" \
  -H "X-ESPEW-Signature: your_signature_here" \
  -d '{"event":"fingerprint_detected","device_id":"espew_001","fingerprint_id":5,"timestamp":"2025-12-16T08:30:00Z"}'
```

### Logs Locations:
- Apache error log: `C:\xampp\apache\logs\error.log`
- PHP error log: Check `php.ini` → `error_log`
- Custom logs: Tự tạo trong `logs/` folder

---

## 📅 Version History

**v2.0 (Current)**
- ✅ Hỗ trợ nhiều ca làm việc (shifts)
- ✅ Tự động tính trạng thái (đúng giờ, muộn, sớm)
- ✅ API đầy đủ cho ESP32
- 🔄 Chuẩn bị tích hợp ESPEW

**v1.0**
- Chức năng cơ bản chấm công
- Quản lý nhân viên
- Dashboard admin

---

## 🚀 Next Steps

1. ✅ **Hoàn thành setup cơ bản** - Đảm bảo hệ thống chạy với ESP32
2. 🔄 **Tích hợp ESPEW** - Theo roadmap ở trên
3. 📊 **Thêm báo cáo** - Export Excel, PDF
4. 📱 **Mobile app** - Flutter/React Native cho nhân viên xem chấm công
5. 🔔 **Notifications** - Email/SMS khi đi muộn
6. 📈 **Analytics** - Dashboard nâng cao với charts

---

**Made with ❤️ for Attendance Management**
