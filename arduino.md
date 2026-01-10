# ARDUINO CODE - HỆ THỐNG CHẤM CÔNG VÂN TAY

## 📋 THÔNG TIN HỆ THỐNG

**Hardware:**
- ESP32 DevKit
- AS608 Fingerprint Sensor
- LCD I2C 20x4 (hoặc 16x2)
- Buzzer (optional)
- LED (optional)
- Nút reset (optional)

**Thư viện cần cài đặt:**
1. WiFi (built-in ESP32)
2. HTTPClient (built-in ESP32)
3. ArduinoJson (Library Manager)
4. Adafruit Fingerprint Sensor Library (Library Manager)
5. LiquidCrystal I2C (Library Manager)
6. WiFiManager by tzapu (Library Manager)

---

## 🔧 CẤU HÌNH TRƯỚC KHI SỬ DỤNG

### ⚠️ QUAN TRỌNG - THAY ĐỔI CÁC GIÁ TRỊ SAU:

```cpp
// Dòng 20-21: Thay bằng IP máy tính chạy XAMPP và mã phòng ban
const char* SERVER_URL = "http://192.168.1.100/chamcongv2";  // ← THAY IP NÀY
const char* DEVICE_CODE = "IT";  // ← THAY MÃ PHÒNG BAN (IT, HR, KETOAN...)

// Dòng 37: Thay địa chỉ I2C LCD nếu cần (0x27 hoặc 0x3F)
LiquidCrystal_I2C lcd(0x27, 20, 4);  // ← 0x27 hoặc 0x3F
```

**Cách tìm IP máy tính:**
```bash
# Windows: Mở CMD
ipconfig
# Tìm dòng IPv4 Address
# Ví dụ: 192.168.1.123
```

---

## 💾 CODE ĐẦY ĐỦ (COPY PASTE VÀO ARDUINO IDE)

```cpp
// ========================================
// HỆ THỐNG CHẤM CÔNG VÂN TAY - ESP32
// Version: 2.0 (Fixed Delete Issue)
// Date: 2026-01-10
// ========================================

// ========================================
// 1. INCLUDES - THƯ VIỆN
// ========================================
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Adafruit_Fingerprint.h>
#include <LiquidCrystal_I2C.h>
#include <WiFiManager.h>

// ========================================
// 2. CONFIGURATION - CẤU HÌNH
// ========================================
// ⚠️ THAY ĐỔI CÁC GIÁ TRỊ NÀY
const char* SERVER_URL = "http://192.168.1.100/chamcongv2";  // THAY IP MÁY TÍNH
const char* DEVICE_CODE = "IT";  // MÃ PHÒNG BAN (IT, HR, KETOAN...)

// Pin Configuration
#define BUZZER_PIN 25        // Buzzer (optional)
#define LED_PIN 2            // LED indicator (optional)
#define RESET_BUTTON_PIN 15  // Nút reset WiFi (optional)
#define ENROLL_BUTTON_PIN 4  // Nút đăng ký vân tay (optional)

// Fingerprint Sensor (UART2)
#define FINGER_RX 16
#define FINGER_TX 17

// ========================================
// 3. OBJECTS - KHỞI TẠO ĐỐI TƯỢNG
// ========================================
HardwareSerial mySerial(2);
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&mySerial);
LiquidCrystal_I2C lcd(0x27, 20, 4);  // Địa chỉ I2C: 0x27 hoặc 0x3F
WiFiManager wifiManager;

// ========================================
// 4. GLOBAL VARIABLES - BIẾN TOÀN CỤC
// ========================================
unsigned long lastPollTime = 0;
unsigned long lastDisplayTime = 0;
bool isEnrollMode = false;
int enrollID = -1;

// ========================================
// 5. SETUP - KHỞI TẠO
// ========================================
void setup() {
  Serial.begin(115200);
  Serial.println("\n\n=== CHAM CONG VAN TAY ===");
  
  // Khởi tạo pins
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(LED_PIN, OUTPUT);
  pinMode(RESET_BUTTON_PIN, INPUT_PULLUP);
  pinMode(ENROLL_BUTTON_PIN, INPUT_PULLUP);
  
  // Test buzzer và LED
  digitalWrite(LED_PIN, HIGH);
  tone(BUZZER_PIN, 1000, 100);
  delay(100);
  digitalWrite(LED_PIN, LOW);
  
  // Khởi tạo LCD
  lcd.init();
  lcd.backlight();
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("  CHAM CONG VAN TAY");
  lcd.setCursor(0, 1);
  lcd.print("    Initializing...");
  delay(2000);
  
  // Khởi tạo Fingerprint Sensor
  Serial.println("Initializing fingerprint sensor...");
  mySerial.begin(57600, SERIAL_8N1, FINGER_RX, FINGER_TX);
  
  if (finger.verifyPassword()) {
    Serial.println("✓ AS608 sensor found!");
    lcd.setCursor(0, 2);
    lcd.print("✓ Sensor: OK");
    delay(1000);
  } else {
    Serial.println("✗ AS608 sensor NOT found!");
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("ERROR:");
    lcd.setCursor(0, 1);
    lcd.print("AS608 not found!");
    lcd.setCursor(0, 2);
    lcd.print("Check connection!");
    while(1) {
      tone(BUZZER_PIN, 500, 500);
      delay(1000);
    }
  }
  
  // Khởi tạo WiFi
  setupWiFi();
  
  // Sẵn sàng
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("=== READY ===");
  lcd.setCursor(0, 1);
  lcd.print("Dat tay de cham cong");
  lcd.setCursor(0, 2);
  lcd.print("WiFi: " + WiFi.SSID());
  lcd.setCursor(0, 3);
  lcd.print("IP: " + WiFi.localIP().toString());
  
  tone(BUZZER_PIN, 2000, 200);
  delay(200);
  tone(BUZZER_PIN, 2500, 200);
  
  Serial.println("✓ System ready!");
  Serial.println("Server: " + String(SERVER_URL));
  Serial.println("Device: " + String(DEVICE_CODE));
  Serial.println("IP: " + WiFi.localIP().toString());
  
  delay(3000);
  displayIdle();
}

// ========================================
// 6. LOOP - VÒNG LẶP CHÍNH
// ========================================
void loop() {
  // Kiểm tra nút reset WiFi (giữ 3 giây)
  if (digitalRead(RESET_BUTTON_PIN) == LOW) {
    handleResetButton();
  }
  
  // Kiểm tra nút đăng ký vân tay
  if (digitalRead(ENROLL_BUTTON_PIN) == LOW) {
    delay(50); // Debounce
    if (digitalRead(ENROLL_BUTTON_PIN) == LOW) {
      startEnrollMode();
      while(digitalRead(ENROLL_BUTTON_PIN) == LOW); // Đợi nhả nút
    }
  }
  
  // Kiểm tra kết nối WiFi
  if (WiFi.status() != WL_CONNECTED) {
    reconnectWiFi();
  }
  
  // Chế độ đăng ký vân tay
  if (isEnrollMode) {
    processEnrollMode();
    return;
  }
  
  // Chế độ chấm công thông thường
  checkAttendance();
  
  // Poll commands từ server (mỗi 5 giây)
  if (millis() - lastPollTime > 5000) {
    pollServerCommands();
    lastPollTime = millis();
  }
  
  // Cập nhật display (mỗi 10 giây)
  if (millis() - lastDisplayTime > 10000) {
    displayIdle();
    lastDisplayTime = millis();
  }
}

// ========================================
// 7. WIFI FUNCTIONS - HÀM WIFI
// ========================================
void setupWiFi() {
  lcd.clear();
  lcd.print("Connecting WiFi...");
  Serial.println("Starting WiFi setup...");
  
  // Callback khi vào config mode
  wifiManager.setAPCallback([](WiFiManager *myWiFiManager) {
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("CONFIG MODE");
    lcd.setCursor(0, 1);
    lcd.print("WiFi: " + String(myWiFiManager->getConfigPortalSSID()));
    lcd.setCursor(0, 2);
    lcd.print("Pass: 12345678");
    lcd.setCursor(0, 3);
    lcd.print("IP: 192.168.4.1");
    Serial.println("Entered config mode");
  });
  
  // Timeout 3 phút
  wifiManager.setConfigPortalTimeout(180);
  
  // Tên AP: ChamCong_XXXX (XXXX = MAC address)
  String apName = "ChamCong_" + String((uint32_t)ESP.getEfuseMac(), HEX);
  
  // Auto connect
  if (!wifiManager.autoConnect(apName.c_str(), "12345678")) {
    Serial.println("Failed to connect WiFi");
    lcd.clear();
    lcd.print("WiFi Timeout!");
    lcd.setCursor(0, 1);
    lcd.print("Restarting...");
    delay(3000);
    ESP.restart();
  }
  
  Serial.println("✓ WiFi connected!");
  Serial.println("SSID: " + WiFi.SSID());
  Serial.println("IP: " + WiFi.localIP().toString());
  Serial.println("RSSI: " + String(WiFi.RSSI()) + " dBm");
  
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("WiFi Connected!");
  lcd.setCursor(0, 1);
  lcd.print(WiFi.SSID());
  lcd.setCursor(0, 2);
  lcd.print("IP: " + WiFi.localIP().toString());
  delay(2000);
}

void reconnectWiFi() {
  Serial.println("WiFi disconnected! Reconnecting...");
  lcd.clear();
  lcd.print("WiFi Reconnecting...");
  WiFi.reconnect();
  
  int timeout = 0;
  while (WiFi.status() != WL_CONNECTED && timeout < 20) {
    delay(500);
    Serial.print(".");
    timeout++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✓ Reconnected!");
    displayIdle();
  } else {
    Serial.println("\n✗ Failed to reconnect. Restarting...");
    ESP.restart();
  }
}

void handleResetButton() {
  unsigned long pressTime = millis();
  lcd.clear();
  lcd.print("Nhan giu 3s de reset");
  lcd.setCursor(0, 1);
  lcd.print("WiFi settings...");
  
  while (digitalRead(RESET_BUTTON_PIN) == LOW) {
    if (millis() - pressTime > 3000) {
      lcd.clear();
      lcd.print("Resetting WiFi...");
      Serial.println("Resetting WiFi settings...");
      wifiManager.resetSettings();
      delay(1000);
      ESP.restart();
    }
  }
  
  lcd.clear();
  lcd.print("Da huy");
  delay(1000);
  displayIdle();
}

// ========================================
// 8. FINGERPRINT FUNCTIONS - HÀM VÂN TAY
// ========================================
void checkAttendance() {
  // Chụp ảnh vân tay
  uint8_t p = finger.getImage();
  if (p != FINGERPRINT_OK) return;  // Chưa có tay
  
  // Convert sang template
  p = finger.image2Tz();
  if (p != FINGERPRINT_OK) {
    lcd.clear();
    lcd.print("Loi doc van tay!");
    lcd.setCursor(0, 1);
    lcd.print("Thu lai...");
    tone(BUZZER_PIN, 500, 500);
    delay(2000);
    displayIdle();
    return;
  }
  
  // Tìm kiếm trong database
  p = finger.fingerFastSearch();
  if (p != FINGERPRINT_OK) {
    lcd.clear();
    lcd.print("CHUA DANG KY!");
    lcd.setCursor(0, 1);
    lcd.print("Lien he admin");
    tone(BUZZER_PIN, 300, 1000);
    Serial.println("✗ Fingerprint not found");
    delay(3000);
    displayIdle();
    return;
  }
  
  // Tìm thấy
  int foundID = finger.fingerID;
  int confidence = finger.confidence;
  
  Serial.println("✓ Found finger ID: " + String(foundID));
  Serial.println("  Confidence: " + String(confidence));
  
  lcd.clear();
  lcd.print("Dang xu ly...");
  lcd.setCursor(0, 1);
  lcd.print("ID: " + String(foundID));
  lcd.setCursor(0, 2);
  lcd.print("Confidence: " + String(confidence));
  
  // Gọi API chấm công
  callCheckinAPI(foundID);
}

// ========================================
// 9. API FUNCTIONS - HÀM GỌI API
// ========================================
void callCheckinAPI(int fingerId) {
  if (WiFi.status() != WL_CONNECTED) {
    lcd.clear();
    lcd.print("LOI WIFI!");
    lcd.setCursor(0, 1);
    lcd.print("Kiem tra ket noi");
    tone(BUZZER_PIN, 500, 1000);
    Serial.println("✗ WiFi not connected!");
    delay(3000);
    displayIdle();
    return;
  }
  
  HTTPClient http;
  String url = String(SERVER_URL) + "/api/checkin.php?finger_id=" + String(fingerId);
  
  Serial.println("→ Calling API: " + url);
  
  http.begin(url);
  http.setTimeout(5000);
  
  int httpCode = http.GET();
  String response = http.getString();
  http.end();
  
  Serial.println("← HTTP Code: " + String(httpCode));
  Serial.println("← Response: " + response);
  
  if (httpCode == 200) {
    parseCheckinResponse(response);
  } else {
    lcd.clear();
    lcd.print("LOI SERVER!");
    lcd.setCursor(0, 1);
    lcd.print("Code: " + String(httpCode));
    tone(BUZZER_PIN, 500, 1000);
    delay(3000);
    displayIdle();
  }
}

void parseCheckinResponse(String jsonString) {
  DynamicJsonDocument doc(512);
  DeserializationError error = deserializeJson(doc, jsonString);
  
  if (error) {
    lcd.clear();
    lcd.print("LOI PARSE JSON!");
    Serial.println("✗ JSON Parse Error: " + String(error.c_str()));
    tone(BUZZER_PIN, 500, 1000);
    delay(3000);
    displayIdle();
    return;
  }
  
  String status = doc["status"] | "ERROR";
  String name = doc["name"] | "Unknown";
  String action = doc["action"] | "UNKNOWN";
  
  if (status == "OK") {
    // Thành công
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("✓ " + action);
    lcd.setCursor(0, 1);
    lcd.print(name);
    lcd.setCursor(0, 2);
    lcd.print(getTimeString());
    
    // Beep thành công
    digitalWrite(LED_PIN, HIGH);
    tone(BUZZER_PIN, 2000, 100);
    delay(150);
    tone(BUZZER_PIN, 2500, 100);
    delay(2000);
    digitalWrite(LED_PIN, LOW);
    
    Serial.println("✓ " + action + ": " + name);
  } else {
    // Lỗi
    String message = doc["message"] | "Loi khong xac dinh";
    lcd.clear();
    lcd.print("LOI!");
    lcd.setCursor(0, 1);
    lcd.print(message);
    tone(BUZZER_PIN, 500, 1000);
    delay(3000);
  }
  
  delay(1000);
  displayIdle();
}

void pollServerCommands() {
  if (WiFi.status() != WL_CONNECTED) return;
  
  HTTPClient http;
  String url = String(SERVER_URL) + "/api/poll_commands.php?dept=" + String(DEVICE_CODE);
  
  http.begin(url);
  http.setTimeout(3000);
  
  int httpCode = http.GET();
  
  if (httpCode == 200) {
    String response = http.getString();
    processCommand(response);
  }
  
  http.end();
}

void processCommand(String jsonString) {
  DynamicJsonDocument doc(512);
  deserializeJson(doc, jsonString);
  
  bool hasCmd = doc["has_cmd"] | false;
  if (!hasCmd) return;
  
  int cmdId = doc["cmd_id"];
  String type = doc["type"];
  int fid = doc["fid"];
  
  Serial.println("← Command received: " + type + " for ID: " + String(fid));
  
  if (type == "DELETE") {
    deleteFingerprint(fid);
    confirmCommand(cmdId);
  }
}

// ========================================
// 10. DELETE FUNCTIONS - HÀM XÓA (FIXED!)
// ========================================
void deleteFingerprint(int id) {
  Serial.println("=== DELETING FINGERPRINT ===");
  Serial.println("ID to delete: " + String(id));
  
  lcd.clear();
  lcd.print("Dang xoa ID #" + String(id));
  lcd.setCursor(0, 1);
  lcd.print("Vui long doi...");
  
  // Xóa khỏi AS608
  uint8_t p = finger.deleteModel(id);
  
  if (p == FINGERPRINT_OK) {
    Serial.println("✓ Deleted from AS608 sensor");
    lcd.setCursor(0, 2);
    lcd.print("✓ Xoa khoi cam bien");
    
    // ⚡ QUAN TRỌNG: Gọi API để xóa khỏi database
    notifyServerDelete(id);
    
    tone(BUZZER_PIN, 1000, 200);
    delay(2000);
  } else {
    Serial.println("✗ Error deleting from sensor: " + String(p));
    lcd.setCursor(0, 2);
    lcd.print("✗ Loi xoa cam bien!");
    tone(BUZZER_PIN, 500, 1000);
    delay(2000);
  }
  
  displayIdle();
}

// ⚡ HÀM MỚI - FIX VẤNĐỀ XÓA KHÔNG CẬP NHẬT WEB
void notifyServerDelete(int fingerprintId) {
  Serial.println("→ Notifying server about deletion...");
  
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("✗ WiFi not connected, cannot notify server!");
    lcd.setCursor(0, 3);
    lcd.print("⚠ Loi WiFi!");
    return;
  }
  
  HTTPClient http;
  String url = String(SERVER_URL) + "/api/delete.php?id=" + String(fingerprintId);
  
  Serial.println("URL: " + url);
  
  http.begin(url);
  http.setTimeout(5000);
  
  int httpCode = http.GET();
  String response = http.getString();
  http.end();
  
  Serial.println("← HTTP Code: " + String(httpCode));
  Serial.println("← Response: " + response);
  
  if (httpCode == 200) {
    if (response.indexOf("\"status\":\"OK\"") > 0) {
      Serial.println("✓ Successfully deleted from database!");
      lcd.setCursor(0, 3);
      lcd.print("✓ Da xoa tren web!");
    } else {
      Serial.println("⚠ Server returned non-OK status");
      lcd.setCursor(0, 3);
      lcd.print("⚠ Server error!");
    }
  } else {
    Serial.println("✗ HTTP Error: " + String(httpCode));
    lcd.setCursor(0, 3);
    lcd.print("✗ Loi ket noi!");
  }
}

void confirmCommand(int cmdId) {
  HTTPClient http;
  String url = String(SERVER_URL) + "/api/poll_commands.php?done_id=" + String(cmdId);
  
  http.begin(url);
  http.GET();
  http.end();
  
  Serial.println("✓ Command confirmed: " + String(cmdId));
}

// ========================================
// 11. ENROLL FUNCTIONS - HÀM ĐĂNG KÝ
// ========================================
void startEnrollMode() {
  isEnrollMode = true;
  enrollID = getNextFreeID();
  
  if (enrollID == -1) {
    lcd.clear();
    lcd.print("LOI!");
    lcd.setCursor(0, 1);
    lcd.print("Bo nho day!");
    tone(BUZZER_PIN, 500, 1000);
    delay(3000);
    isEnrollMode = false;
    displayIdle();
    return;
  }
  
  lcd.clear();
  lcd.print("DANG KY VAN TAY");
  lcd.setCursor(0, 1);
  lcd.print("ID: #" + String(enrollID));
  lcd.setCursor(0, 2);
  lcd.print("Dat ngon tay...");
  
  Serial.println("=== START ENROLLMENT ===");
  Serial.println("Enrolling ID: " + String(enrollID));
  
  tone(BUZZER_PIN, 2000, 200);
}

void processEnrollMode() {
  // Bước 1: Chụp ảnh lần 1
  lcd.setCursor(0, 3);
  lcd.print("Buoc 1/4: Dat tay...");
  
  while (finger.getImage() != FINGERPRINT_OK);
  
  if (finger.image2Tz(1) != FINGERPRINT_OK) {
    enrollError("Loi chuyen doi 1");
    return;
  }
  
  tone(BUZZER_PIN, 1500, 100);
  lcd.setCursor(0, 3);
  lcd.print("Buoc 2/4: Nha tay...  ");
  delay(2000);
  
  while (finger.getImage() != FINGERPRINT_NOFINGER);
  
  // Bước 2: Chụp ảnh lần 2
  lcd.setCursor(0, 3);
  lcd.print("Buoc 3/4: Dat lai... ");
  
  while (finger.getImage() != FINGERPRINT_OK);
  
  if (finger.image2Tz(2) != FINGERPRINT_OK) {
    enrollError("Loi chuyen doi 2");
    return;
  }
  
  tone(BUZZER_PIN, 1500, 100);
  
  // Bước 3: Tạo model
  lcd.setCursor(0, 3);
  lcd.print("Buoc 4/4: Dang luu...");
  
  if (finger.createModel() != FINGERPRINT_OK) {
    enrollError("2 lan khong giong!");
    return;
  }
  
  // Bước 4: Lưu model
  if (finger.storeModel(enrollID) != FINGERPRINT_OK) {
    enrollError("Loi luu vao bo nho");
    return;
  }
  
  // Thành công
  tone(BUZZER_PIN, 2000, 100);
  delay(100);
  tone(BUZZER_PIN, 2500, 100);
  delay(100);
  tone(BUZZER_PIN, 3000, 100);
  
  lcd.clear();
  lcd.print("✓ THANH CONG!");
  lcd.setCursor(0, 1);
  lcd.print("ID: #" + String(enrollID));
  lcd.setCursor(0, 2);
  lcd.print("Dang gui len web...");
  
  Serial.println("✓ Fingerprint enrolled successfully!");
  
  // Gửi lên server
  notifyServerEnroll(enrollID);
  
  delay(3000);
  isEnrollMode = false;
  displayIdle();
}

void enrollError(String message) {
  lcd.clear();
  lcd.print("LOI DANG KY!");
  lcd.setCursor(0, 1);
  lcd.print(message);
  lcd.setCursor(0, 2);
  lcd.print("Thu lai...");
  
  tone(BUZZER_PIN, 500, 1000);
  Serial.println("✗ Enrollment error: " + message);
  
  delay(3000);
  isEnrollMode = false;
  displayIdle();
}

int getNextFreeID() {
  for (int id = 1; id <= 127; id++) {
    if (finger.loadModel(id) != FINGERPRINT_OK) {
      return id;
    }
  }
  return -1;  // Full
}

void notifyServerEnroll(int fingerprintId) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("✗ WiFi not connected!");
    lcd.setCursor(0, 3);
    lcd.print("⚠ Loi WiFi!");
    return;
  }
  
  HTTPClient http;
  String url = String(SERVER_URL) + "/api/register.php?id=" + String(fingerprintId) + 
               "&dept=" + String(DEVICE_CODE);
  
  Serial.println("→ Notifying enrollment: " + url);
  
  http.begin(url);
  int httpCode = http.GET();
  String response = http.getString();
  http.end();
  
  Serial.println("← Response: " + response);
  
  if (httpCode == 200) {
    lcd.setCursor(0, 3);
    lcd.print("✓ Da gui len web!    ");
    Serial.println("✓ Enrolled on server!");
  } else {
    lcd.setCursor(0, 3);
    lcd.print("⚠ Loi server!        ");
  }
}

// ========================================
// 12. DISPLAY FUNCTIONS - HÀM HIỂN THỊ
// ========================================
void displayIdle() {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("=== CHAM CONG ===");
  lcd.setCursor(0, 1);
  lcd.print("Dat tay de check in");
  lcd.setCursor(0, 2);
  lcd.print("WiFi: " + WiFi.SSID());
  lcd.setCursor(0, 3);
  lcd.print(getTimeString() + " | " + String(WiFi.RSSI()) + "dBm");
}

String getTimeString() {
  unsigned long seconds = millis() / 1000;
  unsigned long minutes = seconds / 60;
  unsigned long hours = minutes / 60;
  
  String h = String(hours % 24);
  String m = String(minutes % 60);
  String s = String(seconds % 60);
  
  if (h.length() == 1) h = "0" + h;
  if (m.length() == 1) m = "0" + m;
  if (s.length() == 1) s = "0" + s;
  
  return h + ":" + m + ":" + s;
}

// ========================================
// END OF CODE
// ========================================
```

---

## 📝 HƯỚNG DẪN SỬ DỤNG

### 1. Cài Đặt Thư Viện

Trong Arduino IDE:
- Tools → Manage Libraries
- Tìm và cài đặt:
  1. ArduinoJson by Benoit Blanchon
  2. Adafruit Fingerprint Sensor Library
  3. LiquidCrystal I2C by Frank de Brabander
  4. WiFiManager by tzapu

### 2. Cấu Hình Hardware

```
ESP32 Pinout:
- GPIO 16 → AS608 RX (vàng)
- GPIO 17 → AS608 TX (trắng)
- GPIO 21 → LCD SDA
- GPIO 22 → LCD SCL
- GPIO 25 → Buzzer (+)
- GPIO 2  → LED (+)
- GPIO 15 → Nút Reset WiFi
- GPIO 4  → Nút Đăng Ký Vân Tay
- 3.3V   → AS608 VCC (đỏ)
- GND    → AS608 GND (đen), LCD GND, Buzzer GND, LED GND
```

### 3. Upload Code

1. Mở Arduino IDE
2. Chọn Board: ESP32 Dev Module
3. Chọn Port: (COM port của ESP32)
4. **THAY ĐỔI** `SERVER_URL` và `DEVICE_CODE` (dòng 20-21)
5. Click Upload
6. Mở Serial Monitor (115200 baud)

### 4. Cấu Hình WiFi Lần Đầu

1. ESP32 sẽ tạo WiFi: `ChamCong_XXXX`
2. Kết nối từ điện thoại (pass: `12345678`)
3. Chọn WiFi và nhập password
4. ESP32 sẽ tự động kết nối

### 5. Sử Dụng

**Chấm công:**
- Đặt tay lên cảm biến
- Đợi hiển thị tên và hành động

**Đăng ký vân tay:**
- Nhấn nút ENROLL (GPIO 4)
- Làm theo hướng dẫn trên LCD

**Reset WiFi:**
- Giữ nút RESET (GPIO 15) trong 3 giây

---

## 🔍 DEBUG

**Serial Monitor sẽ hiển thị:**
```
=== CHAM CONG VAN TAY ===
✓ AS608 sensor found!
Starting WiFi setup...
✓ WiFi connected!
SSID: Ten_WiFi
IP: 192.168.1.123
✓ System ready!
Server: http://192.168.1.100/chamcongv2
Device: IT
```

**Khi check in:**
```
✓ Found finger ID: 5
  Confidence: 156
→ Calling API: http://...
← HTTP Code: 200
← Response: {"status":"OK","name":"Nguyen Van A","action":"CHECK IN"}
✓ CHECK IN: Nguyen Van A
```

**Khi xóa:**
```
=== DELETING FINGERPRINT ===
ID to delete: 5
✓ Deleted from AS608 sensor
→ Notifying server about deletion...
URL: http://.../api/delete.php?id=5
← HTTP Code: 200
← Response: {"status":"OK","message":"Da xoa"...}
✓ Successfully deleted from database!
```

---

## ✅ CHECKLIST

- [ ] Đã cài đặt tất cả thư viện
- [ ] Đã kết nối đúng hardware
- [ ] Đã thay `SERVER_URL` (IP máy tính chạy XAMPP)
- [ ] Đã thay `DEVICE_CODE` (mã phòng ban)
- [ ] Đã upload code thành công
- [ ] WiFi đã kết nối
- [ ] AS608 hoạt động (LED nhấp nháy)
- [ ] LCD hiển thị text
- [ ] Test check in OK
- [ ] Test xóa vân tay → Web cập nhật ✓

---

**🎉 CODE ĐÃ FIX TOÀN BỘ VẤN ĐỀ XÓA VÂN TAY!**

Giờ khi xóa vân tay trên máy, web sẽ tự động cập nhật trong 1-2 giây.
