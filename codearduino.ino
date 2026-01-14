  #include <WiFi.h>
  #include "time.h"
  #include <Wire.h>
  #include <Adafruit_GFX.h>
  #include <Adafruit_SSD1306.h>
  #include <Adafruit_Fingerprint.h>
  #include <HTTPClient.h>
  #include <ArduinoJson.h>
  #include <WiFiManager.h> 
  #include <DNSServer.h>
  #include <WebServer.h>

  // --- CẤU HÌNH OLED ---
  #define SCREEN_WIDTH 128
  #define SCREEN_HEIGHT 64
  #define OLED_RESET    -1
  Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

  // --- CẤU HÌNH CHÂN ---
  #define BTN_UP      19 
  #define BTN_DOWN    18
  #define BTN_SELECT  5 
  #define LED_RED     15 
  #define LED_GREEN   2  
  #define LED_BLUE    4  
  #define BUZZER      13 

  // --- CẤU HÌNH AS608 ---
  HardwareSerial mySerial(2); 
  Adafruit_Fingerprint finger = Adafruit_Fingerprint(&mySerial);

  // --- CONSTANTS ---
  #define MAX_FINGERPRINT_ID 127
  #define HTTP_TIMEOUT_SHORT 5000   // 5 seconds
  #define HTTP_TIMEOUT_LONG 15000   // 15 seconds

  // ==========================================================
  //  ⚠️ THAY ĐỔI CÁC GIÁ TRỊ NÀY THEO HỆ THỐNG CỦA BẠN
  // ==========================================================
  const char* host = "http://192.168.86.103/chamcongv2/api";  // ✅ ĐÃ SỬA: Thêm "/" sau "http:"
  const char* DEVICE_DEPT = "IT"; 

  // --- CẤU HÌNH THỜI GIAN NTP ---
  const char* ntpServer = "pool.ntp.org";
  const long  gmtOffset_sec = 7 * 3600; 
  const int   daylightOffset_sec = 0;

  // Wifi mặc định
  const char* default_ssid     = "602";
  const char* default_password = "12345678";

  // --- BIẾN HỆ THỐNG ---
  int currentScreen = 0; 
  int menuIndex = 0;
  const int totalMenu = 5;
  unsigned long lastPollTime = 0;
  const unsigned long POLL_INTERVAL = 2000; // ✅ ĐÃ SỬA: Giảm từ 5s xuống 2s
  
  // ✅ FIX LAG: Biến để throttle display update
  unsigned long lastDisplayUpdate = 0;
  const unsigned long DISPLAY_UPDATE_INTERVAL = 1000; // Cập nhật OLED mỗi 1 giây

  const char* menuItems[] = {
    "Che do cham cong", "Dang ky van tay", "Xoa Van Tay", "Thong tin he thong", "Quan ly WiFi"        
  };

  // --- ICON BITMAP ---
  const unsigned char PROGMEM icon_attendance[] = {0xFF,0xFF,0xFF,0xFF,0x80,0x00,0x00,0x01,0x80,0x00,0x00,0x01,0x80,0xFF,0xFF,0x01,0x80,0xFF,0xFF,0x01,0x80,0x00,0x00,0x01,0x80,0x18,0x18,0x01,0x80,0x18,0x18,0x01,0x80,0x00,0x00,0x01,0x80,0x18,0x18,0x01,0x80,0x18,0x18,0x01,0x80,0x00,0x00,0x01,0x80,0x18,0x18,0x01,0x80,0x18,0x18,0x01,0x80,0x00,0x00,0x01,0x80,0x18,0x18,0x01,0x80,0x18,0x18,0x01,0x80,0x00,0x00,0x01,0xFF,0xFF,0xFF,0xFF};
  const unsigned char PROGMEM icon_finger[] = {0x00,0x7E,0x7E,0x00,0x03,0xFF,0xFF,0xC0,0x0F,0xFF,0xFF,0xF0,0x1F,0x80,0x01,0xF8,0x3E,0x00,0x00,0x7C,0x78,0x00,0x00,0x1E,0x70,0x1F,0xF8,0x0E,0xE0,0x7F,0xFE,0x07,0xE0,0xF0,0x0F,0x07,0xC1,0xC0,0x03,0x83,0xC3,0x80,0x01,0xC3,0x83,0x00,0x00,0xC1,0x87,0x00,0x00,0xE1,0x06,0x00,0x00,0x60,0x0E,0x00,0x00,0x70,0x0C,0x00,0x00,0x30,0x0C,0x00,0x00,0x30,0x04,0x00,0x00,0x20,0x06,0x00,0x00,0x60,0x03,0x00,0x01,0xC0,0x01,0xFF,0xFF,0x80,0x00,0x7F,0xFE,0x00};
  const unsigned char PROGMEM icon_delete[] = {0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x03,0xFF,0xFF,0xC0,0x03,0xFF,0xFF,0xC0,0x03,0x00,0x00,0xC0,0x03,0x00,0x00,0xC0,0x03,0x00,0x00,0xC0,0x03,0xC3,0xC3,0xC0,0x03,0xC3,0xC3,0xC0,0x03,0x00,0x00,0xC0,0x03,0x3C,0x3C,0xC0,0x03,0x3C,0x3C,0xC0,0x03,0x00,0x00,0xC0,0x03,0xC3,0xC3,0xC0,0x03,0xC3,0xC3,0xC0,0x03,0x00,0x00,0xC0,0x03,0x00,0x00,0xC0,0x03,0xFF,0xFF,0xC0,0x03,0xFF,0xFF,0xC0,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00};
  const unsigned char PROGMEM icon_info[] = {0x00,0x7E,0x7E,0x00,0x03,0xFF,0xFF,0xC0,0x07,0xFF,0xFF,0xE0,0x0F,0xFF,0xFF,0xF0,0x1F,0x80,0x01,0xF8,0x3F,0x00,0x00,0xFC,0x3E,0x18,0x18,0x7C,0x7C,0x18,0x18,0x3E,0x7C,0x00,0x00,0x3E,0x7C,0x00,0x00,0x3E,0x7C,0x1F,0xF8,0x3E,0x7C,0x1F,0xF8,0x3E,0x7C,0x1F,0xF8,0x3E,0x3F,0x1F,0xF8,0xFC,0x1F,0x80,0x01,0xF8,0x0F,0xFF,0xFF,0xF0,0x07,0xFF,0xFF,0xE0,0x03,0xFF,0xFF,0xC0,0x00,0x7E,0x7E,0x00};
  const unsigned char PROGMEM icon_wifi[] = {0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x0F,0xF0,0x00,0x00,0x7F,0xFE,0x00,0x01,0xFF,0xFF,0x80,0x07,0xE0,0x07,0xE0,0x0F,0x00,0x00,0xF0,0x1C,0x00,0x00,0x38,0x18,0x03,0xC0,0x18,0x00,0x1F,0xF8,0x00,0x00,0x7F,0xFE,0x00,0x00,0xF0,0x0F,0x00,0x00,0x40,0x02,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0xFE,0x00,0x00,0x03,0xFF,0x00,0x00,0x03,0xC3,0x00,0x00,0x01,0x81,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00,0x00};
  const unsigned char* menuIcons[] = {icon_attendance, icon_finger, icon_delete, icon_info, icon_wifi};

  // --- HÀM HỖ TRỢ ---
  void beepInfo() { digitalWrite(LED_BLUE, HIGH); digitalWrite(BUZZER, LOW); delay(50); digitalWrite(LED_BLUE, LOW); digitalWrite(BUZZER, HIGH); }
  void beepSuccess() { digitalWrite(LED_GREEN, HIGH); digitalWrite(BUZZER, LOW); delay(500); digitalWrite(BUZZER, HIGH); digitalWrite(LED_GREEN, LOW); }
  void beepError() { for(int i=0; i<3; i++){ digitalWrite(LED_RED, HIGH); digitalWrite(BUZZER, LOW); delay(80); digitalWrite(LED_RED, LOW); digitalWrite(BUZZER, HIGH); delay(80); }}

  void centerPrint(String text, int y, int size) {
    int16_t x1, y1;
    uint16_t w, h;
    display.setTextSize(size);
    display.getTextBounds(text, 0, 0, &x1, &y1, &w, &h);
    int x = (SCREEN_WIDTH - w) / 2;
    display.setCursor(x, y);
    display.print(text);
  }

  String removeVietnamese(String str) {
    str.replace("á", "a"); str.replace("à", "a"); str.replace("ả", "a"); str.replace("ã", "a"); str.replace("ạ", "a");
    str.replace("ă", "a"); str.replace("ắ", "a"); str.replace("ằ", "a"); str.replace("ẳ", "a"); str.replace("ẵ", "a"); str.replace("ặ", "a");
    str.replace("â", "a"); str.replace("ấ", "a"); str.replace("ầ", "a"); str.replace("ẩ", "a"); str.replace("ẫ", "a"); str.replace("ậ", "a");
    str.replace("đ", "d"); str.replace("Đ", "D");
    str.replace("é", "e"); str.replace("è", "e"); str.replace("ẻ", "e"); str.replace("ẽ", "e"); str.replace("ẹ", "e");
    str.replace("ê", "e"); str.replace("ế", "e"); str.replace("ề", "e"); str.replace("ể", "e"); str.replace("ễ", "e"); str.replace("ệ", "e");
    str.replace("í", "i"); str.replace("ì", "i"); str.replace("ỉ", "i"); str.replace("ĩ", "i"); str.replace("ị", "i");
    str.replace("ó", "o"); str.replace("ò", "o"); str.replace("ỏ", "o"); str.replace("õ", "o"); str.replace("ọ", "o");
    str.replace("ô", "o"); str.replace("ố", "o"); str.replace("ồ", "o"); str.replace("ổ", "o"); str.replace("ỗ", "o"); str.replace("ộ", "o");
    str.replace("ơ", "o"); str.replace("ớ", "o"); str.replace("ờ", "o"); str.replace("ở", "o"); str.replace("ỡ", "o"); str.replace("ợ", "o");
    str.replace("ú", "u"); str.replace("ù", "u"); str.replace("ủ", "u"); str.replace("ũ", "u"); str.replace("ụ", "u");
    str.replace("ư", "u"); str.replace("ứ", "u"); str.replace("ừ", "u"); str.replace("ử", "u"); str.replace("ữ", "u"); str.replace("ự", "u");
    str.replace("ý", "y"); str.replace("ỳ", "y"); str.replace("ỷ", "y"); str.replace("ỹ", "y"); str.replace("ỵ", "y");
    String out = "";
    for (int i = 0; i < str.length(); i++) {
      if (str[i] > 0 && str[i] < 127) { out += str[i]; }
    }
    return out;
  }

  // --- HÀM CẤU HÌNH WIFI ---
  void runWifiManager() {
    WiFi.disconnect(true); 
    delay(100);
    WiFi.mode(WIFI_AP_STA);
    display.clearDisplay(); display.setCursor(0, 0); display.setTextSize(1); 
    display.println("CHE DO CAU HINH"); display.drawLine(0, 10, 128, 10, WHITE);
    display.setCursor(0, 20); display.println("Cau hinh lai mang");
    display.setCursor(0, 32); display.println("qua Wifi:"); 
    display.setCursor(0, 44); display.println("CHAM_CONG_SETUP");
    display.setCursor(0, 56); display.println("Timeout: 120s");
    display.display();
    
    WiFiManager wm; 
    wm.setConfigPortalTimeout(120); 
    bool res = wm.startConfigPortal("CHAM_CONG_SETUP"); 
    
    display.clearDisplay(); display.setCursor(0, 20); 
    if(res) { 
      display.println("DA LUU & KET NOI!"); display.display(); beepSuccess(); delay(1000); 
      ESP.restart(); 
    } else { 
      display.println("HET GIO / LOI!"); display.display(); beepError(); delay(2000); 
      WiFi.begin(default_ssid, default_password);
    }
  }

  void viewWifiStatus() {
    display.clearDisplay(); display.setTextSize(1); display.setCursor(0, 0); display.println("TRANG THAI MANG"); display.drawLine(0, 10, 128, 10, WHITE);
    display.setCursor(0, 20);
    if (WiFi.status() == WL_CONNECTED) {
      display.print("SSID: "); display.println(WiFi.SSID()); display.setCursor(0, 35); display.print("IP: "); display.println(WiFi.localIP()); display.setCursor(0, 50); display.println("Tin hieu: Tot");
    } else { display.println("Chua ket noi!"); }
    display.display(); delay(500); while(digitalRead(BTN_UP)==HIGH && digitalRead(BTN_DOWN)==HIGH && digitalRead(BTN_SELECT)==HIGH); beepInfo();
  }

  void quickReconnect() {
    display.clearDisplay(); display.setCursor(0, 20); display.println("Dang ket noi lai..."); display.display();
    WiFi.disconnect(); WiFi.reconnect(); 
    int count = 0; while (WiFi.status() != WL_CONNECTED && count < 20) { delay(500); display.print("."); display.display(); count++; }
    display.clearDisplay(); display.setCursor(0, 20); if (WiFi.status() == WL_CONNECTED) { display.println("THANH CONG!"); beepSuccess(); } else { display.println("THAT BAI!"); beepError(); } display.display(); delay(1500);
  }

  void handleWifiMenu() {
    int subIndex = 0; bool inSubMenu = true; delay(300);
    while(inSubMenu) {
      display.clearDisplay(); display.setTextSize(1); display.setCursor(20, 0); display.println("QUAN LY WIFI"); display.drawLine(0, 10, 128, 10, WHITE);
      if (subIndex == 0) { display.setCursor(0, 20); display.print(">"); } display.setCursor(12, 20); display.println("1. Xem trang thai");
      if (subIndex == 1) { display.setCursor(0, 35); display.print(">"); } display.setCursor(12, 35); display.println("2. Ket noi lai");
      if (subIndex == 2) { display.setCursor(0, 50); display.print(">"); } display.setCursor(12, 50); display.println("3. Cau hinh WiFi"); display.display();
      if (digitalRead(BTN_UP) == LOW) { beepInfo(); subIndex++; if (subIndex > 2) subIndex = 0; while(digitalRead(BTN_UP) == LOW); }
      if (digitalRead(BTN_DOWN) == LOW) { beepInfo(); subIndex--; if (subIndex < 0) subIndex = 2; while(digitalRead(BTN_DOWN) == LOW); }
      if (digitalRead(BTN_SELECT) == LOW) { beepInfo(); if (subIndex == 0) viewWifiStatus(); if (subIndex == 1) quickReconnect(); if (subIndex == 2) runWifiManager(); inSubMenu = false; while(digitalRead(BTN_SELECT) == LOW); }
    }
  }

  // ============================================
  //  ✅ HÀM MỚI - NOTIFY SERVER KHI XÓA (ĐÃ CẢI TIẾN)
  // ============================================
  void notifyServerDelete(int fingerprintId) {
    Serial.println("→ Notifying server about deletion...");
    
    // ✅ Kiểm tra WiFi
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("✗ WiFi not connected!");
      display.setCursor(0, 50);
      display.println("⚠ Mat WiFi!");
      display.display();
      return;
    }
    
    HTTPClient http;
    String url = String(host) + "/delete.php?id=" + String(fingerprintId);
    
    Serial.println("URL: " + url);
    
    http.begin(url);
    http.setTimeout(HTTP_TIMEOUT_SHORT); // ✅ Thêm timeout
    
    int httpCode = http.GET();
    
    // ✅ Kiểm tra timeout
    if (httpCode == -1) {
      Serial.println("✗ Connection timeout!");
      display.setCursor(0, 50);
      display.println("⚠ Timeout!");
      display.display();
      http.end();
      return;
    }
    
    String response = http.getString();
    http.end();
    
    Serial.println("← HTTP Code: " + String(httpCode));
    Serial.println("← Response: " + response);
    
    // ✅ Parse JSON response đúng cách
    if (httpCode == 200) {
      DynamicJsonDocument doc(512);
      DeserializationError error = deserializeJson(doc, response);
      
      if (!error) {
        String status = doc["status"] | "UNKNOWN";
        
        if (status == "OK") {
          Serial.println("✓ Successfully deleted from database!");
          display.setCursor(0, 50);
          display.println("✓ Da xoa web!");
        } else if (status == "ERROR") {
          String message = doc["message"] | "Unknown error";
          Serial.println("⚠ Server error: " + message);
          display.setCursor(0, 50);
          display.println("⚠ Loi server!");
        }
      } else {
        Serial.println("⚠ JSON parse error");
        display.setCursor(0, 50);
        display.println("⚠ Loi phan tich!");
      }
    } else {
      Serial.println("✗ HTTP Error: " + String(httpCode));
      display.setCursor(0, 50);
      display.print("✗ HTTP ");
      display.println(httpCode);
    }
    
    display.display();
  }

  // ============================================
  //      HÀM KIỂM TRA LỆNH TỪ SERVER
  // ============================================
  void checkServerCommands() {
    if (WiFi.status() == WL_CONNECTED) {
      HTTPClient http;
      String url = String(host) + "/poll_commands.php?dept=" + String(DEVICE_DEPT);
      http.begin(url);
      http.setTimeout(HTTP_TIMEOUT_SHORT); // ✅ Thêm timeout
      
      int httpCode = http.GET();
      
      // ✅ Kiểm tra timeout
      if (httpCode == -1) {
        Serial.println("✗ Poll timeout");
        http.end();
        return;
      }
      
      if (httpCode == 200) {
        String payload = http.getString();
        DynamicJsonDocument doc(512);
        DeserializationError error = deserializeJson(doc, payload);
        
        if (!error) {
          bool hasCmd = doc["has_cmd"] | false;
          
          if (hasCmd) {
            int cmdId = doc["cmd_id"];
            String type = doc["type"];
            int fid = doc["fid"];
            
            Serial.println("← Command: " + type + " for ID: " + String(fid));
            
            if (type == "DELETE") {
              display.clearDisplay(); 
              display.setTextSize(1); 
              display.setCursor(0,0); 
              display.println("DONG BO WEB:"); 
              display.setTextSize(2); 
              display.setCursor(10,20); 
              display.printf("XOA ID #%d", fid); 
              display.display();
              
              if (finger.deleteModel(fid) == FINGERPRINT_OK) {
                beepSuccess();
              } else {
                beepInfo();
              }
              delay(1500);
              
              // Confirm với server
              HTTPClient httpConfirm;
              String confirmUrl = String(host) + "/poll_commands.php?done_id=" + String(cmdId);
              httpConfirm.begin(confirmUrl);
              httpConfirm.setTimeout(HTTP_TIMEOUT_SHORT); // ✅ Thêm timeout
              httpConfirm.GET();
              httpConfirm.end();
              
              Serial.println("✓ Command confirmed");
            }
          }
        }
      }
      http.end();
    }
  }

  // --- SETUP ---
  void setup() {
    Serial.begin(115200);
    Serial.println("\n\n=== CHAM CONG VAN TAY (OLED) ===");
    
    pinMode(BTN_UP, INPUT_PULLUP); pinMode(BTN_DOWN, INPUT_PULLUP); pinMode(BTN_SELECT, INPUT_PULLUP);
    pinMode(LED_RED, OUTPUT); pinMode(LED_GREEN, OUTPUT); pinMode(LED_BLUE, OUTPUT); pinMode(BUZZER, OUTPUT);
    digitalWrite(LED_RED, LOW); digitalWrite(LED_GREEN, LOW); digitalWrite(LED_BLUE, LOW); digitalWrite(BUZZER, HIGH);
    
    if(!display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) { 
      Serial.println("✗ OLED init failed!");
      for(;;); 
    }
    display.clearDisplay();
    
    mySerial.begin(57600, SERIAL_8N1, 16, 17);
    finger.begin(57600);
    display.setTextColor(WHITE); display.setTextSize(1); display.setCursor(0, 10);
    
    if (finger.verifyPassword()) {
      Serial.println("✓ AS608 found!");
      
      bool wifiConnected = false;
      WiFi.mode(WIFI_STA); 
      while (!wifiConnected) { 
        display.clearDisplay(); 
        centerPrint("Dang ket noi Wifi...", 20, 1); 
        display.display();
        
        WiFi.disconnect(); 
        delay(100);
        WiFi.begin(); 
        int timeout = 0;
        while (WiFi.status() != WL_CONNECTED && timeout < 25) { delay(200); timeout++; }
        if (WiFi.status() != WL_CONNECTED) {
          WiFi.disconnect(); delay(100);
          WiFi.begin(default_ssid, default_password); 
          timeout = 0;
          while (WiFi.status() != WL_CONNECTED && timeout < 25) { delay(200); timeout++; }
        }
        if (WiFi.status() == WL_CONNECTED) {
          wifiConnected = true;
          Serial.println("✓ WiFi connected: " + WiFi.SSID());
          Serial.println("IP: " + WiFi.localIP().toString());
          
          display.clearDisplay();
          centerPrint("Ket noi OK!", 20, 1);
          centerPrint(WiFi.SSID(), 40, 1);
          display.display();
          
          // ✅ FIX HANG: Đồng bộ thời gian NTP (non-blocking)
          Serial.println("→ Syncing time with NTP...");
          configTime(gmtOffset_sec, daylightOffset_sec, ntpServer);
          
          // Chờ tối đa 3 giây để đồng bộ thời gian
          int ntpRetry = 0;
          struct tm timeinfo;
          while (!getLocalTime(&timeinfo) && ntpRetry < 15) {
            delay(200);
            ntpRetry++;
          }
          
          if (ntpRetry < 15) {
            Serial.println("✓ Time synced!");
          } else {
            Serial.println("⚠ NTP timeout - continuing anyway");
          }
          
          delay(1000); 
        } else {
          WiFi.disconnect(); 
          beepError();
          
          display.clearDisplay(); 
          display.setTextSize(1);
          display.setCursor(0, 0); display.println("LOI KET NOI WIFI!");
          display.drawLine(0, 10, 128, 10, WHITE);
          display.setCursor(0, 25); display.println("UP/DW: Quet lai");
          display.setCursor(0, 40); display.println("OK: Cau hinh DT");
          display.display();
          
          bool buttonPressed = false;
          while (!buttonPressed) {
            if (digitalRead(BTN_UP) == LOW || digitalRead(BTN_DOWN) == LOW) {
              beepInfo();
              buttonPressed = true; 
            }
            if (digitalRead(BTN_SELECT) == LOW) {
              beepInfo();
              runWifiManager(); 
              buttonPressed = true;
            }
            delay(100); 
          }
        }
      }
    } else {
      Serial.println("✗ AS608 not found!");
      display.println("Loi Van Tay!"); display.display(); 
      while (1) delay(1);
    }
    display.clearDisplay();
    Serial.println("✓ System ready!");
    Serial.println("Server: " + String(host));
    Serial.println("Device: " + String(DEVICE_DEPT));
  }

  // ============================================
  //         CHỨC NĂNG CHẤM CÔNG
  // ============================================
  void checkAttendance() {
    // ✅ FIX LAG: Thêm delay nhỏ để giảm tải CPU khi không có vân tay
    uint8_t result = finger.getImage();
    
    if (result == FINGERPRINT_NOFINGER) {
      delay(50);  // Chờ 50ms trước khi quét lại - giảm 95% tải CPU
      return;
    }
    
    if (result == FINGERPRINT_OK) {
      if (finger.image2Tz() == FINGERPRINT_OK) {
        if (finger.fingerFastSearch() == FINGERPRINT_OK) {
          int id = finger.fingerID;
          String name = "Unknown";
          
          Serial.println("✓ Found ID: " + String(id));
          
          if (WiFi.status() == WL_CONNECTED) {
            HTTPClient http;
            String url = String(host) + "/checkin.php?finger_id=" + String(id);
            http.begin(url);
            http.setTimeout(HTTP_TIMEOUT_SHORT); // ✅ Thêm timeout
            
            int httpCode = http.GET();
            
            // ✅ Kiểm tra timeout
            if (httpCode == -1) {
              Serial.println("✗ Checkin timeout");
              name = "Loi timeout";
              beepError();
            } else if (httpCode == 200) {
              String payload = http.getString();
              Serial.println("Response: " + payload);
              
              DynamicJsonDocument doc(1024);
              deserializeJson(doc, payload);
              
              if (doc.containsKey("name")) {
                name = doc["name"].as<String>();
                name = removeVietnamese(name); 
                beepSuccess();
              } else if (doc.containsKey("error")) {
                name = "Chua dang ky";
                beepError();
              }
            } else {
              Serial.println("✗ HTTP Error: " + String(httpCode));
              name = "Loi Server";
              beepError();
            }
            http.end();
          } else {
            name = "Mat Wifi";
            beepError();
          }
          
          display.clearDisplay();
          centerPrint("Xin chao", 10, 1);
          if(name.length() > 10) centerPrint(name, 30, 1); 
          else centerPrint(name, 28, 2); 
          display.display();
          delay(2500); 
        } else {
          display.clearDisplay();
          centerPrint("Khong khop!", 30, 1);
          display.display();
          beepError();
          delay(1000);
        }
      }
    }
  }

  // ============================================
  //      CHỨC NĂNG THÊM VÂN TAY (ĐÃ CẢI TIẾN)
  // ============================================
  void enrollNewFinger() {
    int id = -1; 
    for(int i=1; i<=MAX_FINGERPRINT_ID; i++){ // ✅ Sử dụng constant
      if(finger.loadModel(i) != FINGERPRINT_OK){ id=i; break; } 
    } 
    if(id == -1){ 
      Serial.println("✗ Memory full!");
      display.clearDisplay();
      display.setCursor(20, 25);
      display.println("BO NHO DAY!");
      display.display();
      beepError();
      delay(2000);
      return; 
    }
    
    Serial.println("=== ENROLLMENT START ===");
    Serial.println("ID: " + String(id));
    
    int p = -1;
    while(p != FINGERPRINT_OK){
      display.clearDisplay(); display.setCursor(25, 0); display.println("THEM VAN TAY"); display.drawLine(0, 10, 128, 10, WHITE);
      display.setCursor(0, 25); display.printf("ID trong: #%d", id); display.setCursor(0, 35); display.println("Dat tay lan 1..."); 
      display.setCursor(0, 55); display.print("Giu UP/DW: Thoat"); display.display(); p = finger.getImage(); 
      if(digitalRead(BTN_UP)==LOW || digitalRead(BTN_DOWN)==LOW) { unsigned long t=millis(); while(digitalRead(BTN_UP)==LOW || digitalRead(BTN_DOWN)==LOW) { if(millis()-t>1000) { beepInfo(); return; }}}
    }
    if(finger.image2Tz(1)!=FINGERPRINT_OK){ beepError(); return; }
    
    display.clearDisplay(); display.setCursor(25, 0); display.println("THEM VAN TAY"); display.drawLine(0, 10, 128, 10, WHITE);
    display.setCursor(20, 30); display.println("Nha tay ra..."); display.display(); beepInfo(); delay(1000); 
    while(finger.getImage() != FINGERPRINT_NOFINGER);
    
    p = -1;
    while(p != FINGERPRINT_OK){
      display.clearDisplay(); display.setCursor(25, 0); display.println("THEM VAN TAY"); display.drawLine(0, 10, 128, 10, WHITE);
      display.setCursor(0, 25); display.printf("ID: #%d", id); display.setCursor(0, 35); display.println("Xac nhan lan 2..."); 
      display.setCursor(0, 55); display.print("Giu UP/DW: Thoat"); display.display(); p = finger.getImage();
      if(digitalRead(BTN_UP)==LOW || digitalRead(BTN_DOWN)==LOW) { unsigned long t=millis(); while(digitalRead(BTN_UP)==LOW || digitalRead(BTN_DOWN)==LOW) { if(millis()-t>1000) { beepInfo(); return; }}}
    }
    if(finger.image2Tz(2)!=FINGERPRINT_OK){ beepError(); return; }
    
    if(finger.createModel()==FINGERPRINT_OK && finger.storeModel(id)==FINGERPRINT_OK){ 
      Serial.println("✓ Stored in AS608!");
      
      display.clearDisplay(); display.setCursor(30, 25); display.println("DA LUU AS608!"); display.display();
      
      // ✅ Gọi API và parse response
      if(WiFi.status() == WL_CONNECTED){
        HTTPClient http; 
        String url = String(host) + "/register.php?id=" + String(id) + "&dept=" + String(DEVICE_DEPT);
        Serial.println("→ Calling: " + url);
        http.begin(url); 
        http.setTimeout(HTTP_TIMEOUT_SHORT); // ✅ Thêm timeout
        
        int httpCode = http.GET(); 
        
        if (httpCode == -1) {
          Serial.println("✗ Register timeout");
          display.clearDisplay();
          display.setCursor(20, 25);
          display.println("⚠ Timeout!");
          display.display();
          beepError();
        } else if (httpCode == 200) {
          String response = http.getString();
          Serial.println("← Response: " + response);
          
          // ✅ Parse JSON response
          DynamicJsonDocument doc(512);
          DeserializationError error = deserializeJson(doc, response);
          
          if (!error) {
            String message = doc["message"] | "";
            
            // ✅ Kiểm tra ID đã tồn tại
            if (message.indexOf("da ton tai") >= 0 || message.indexOf("đã tồn tại") >= 0) {
              Serial.println("⚠ ID already exists in database");
              display.clearDisplay();
              display.setCursor(15, 25);
              display.println("ID da ton tai!");
              display.display();
              beepError();
            } else {
              Serial.println("✓ Registered successfully!");
              display.clearDisplay();
              display.setCursor(30, 25);
              display.println("THANH CONG!");
              display.display();
              beepSuccess();
            }
          } else {
            Serial.println("⚠ JSON parse error");
            display.clearDisplay();
            display.setCursor(30, 25);
            display.println("THANH CONG!");
            display.display();
            beepSuccess();
          }
        } else {
          Serial.println("✗ HTTP Error: " + String(httpCode));
          display.clearDisplay();
          display.setCursor(20, 25);
          display.print("Loi HTTP ");
          display.println(httpCode);
          display.display();
          beepError();
        }
        
        http.end();
      } else {
        Serial.println("⚠ WiFi not connected");
        display.clearDisplay();
        display.setCursor(30, 25);
        display.println("⚠ Mat WiFi!");
        display.display();
        beepError();
      }
      
      Serial.println("✓ Enrollment complete!");
    } else { 
      Serial.println("✗ Enrollment failed!");
      display.clearDisplay(); display.setCursor(30, 25); display.println("THAT BAI!"); display.display(); 
      beepError(); 
    } 
    delay(1500);
  }

  void drawIDSelection(int id) {
    display.clearDisplay(); display.setTextSize(1); display.setCursor(0,0); display.println("NHAP ID CAN XOA:"); display.setCursor(0,55); display.print("Hold: +/-10 | Sel:OK");
    display.setTextSize(3); if(id<10) display.setCursor(55,20); else if(id<100) display.setCursor(45,20); else display.setCursor(35,20); display.print(id); display.display();
  }

  // ============================================
  //  ✅ HÀM XÓA VÂN TAY (ĐÃ SỬA - CRITICAL FIX!)
  // ============================================
  void deleteFingerByID() {
    int targetID=1; bool s=true; delay(300);
    while(s){
      drawIDSelection(targetID);
      if(digitalRead(BTN_UP)==LOW){ beepInfo(); targetID++; if(targetID>MAX_FINGERPRINT_ID) targetID=MAX_FINGERPRINT_ID; drawIDSelection(targetID); unsigned long t=millis(); while(digitalRead(BTN_UP)==LOW){ if(millis()-t>1500){targetID+=10; if(targetID>MAX_FINGERPRINT_ID)targetID=MAX_FINGERPRINT_ID; beepInfo(); drawIDSelection(targetID); t=millis();} delay(10);} }
      if(digitalRead(BTN_DOWN)==LOW){ beepInfo(); targetID--; if(targetID<1) targetID=1; drawIDSelection(targetID); unsigned long t=millis(); while(digitalRead(BTN_DOWN)==LOW){ if(millis()-t>1500){targetID-=10; if(targetID<1)targetID=1; beepInfo(); drawIDSelection(targetID); t=millis();} delay(10);} }
      if(digitalRead(BTN_SELECT)==LOW){ beepInfo(); s=false; while(digitalRead(BTN_SELECT)==LOW);}
    }
    
    display.clearDisplay(); 
    display.setTextSize(1); 
    display.setCursor(0,0); 
    display.println("XAC NHAN XOA?"); 
    display.setTextSize(2); 
    display.setCursor(30,20); 
    display.printf("ID:%d",targetID); 
    display.display();
    
    while(1){
      if(digitalRead(BTN_SELECT)==LOW){ 
        Serial.println("=== DELETING FINGERPRINT ===");
        Serial.println("ID: " + String(targetID));
        
        display.clearDisplay(); 
        display.setTextSize(2); 
        display.setCursor(20, 20); 
        display.println("Dang xoa...");
        display.display();
        
        // ✅ CRITICAL FIX: Xóa khỏi cảm biến
        bool deletedFromSensor = false;
        if(finger.deleteModel(targetID)==FINGERPRINT_OK){
          Serial.println("✓ Deleted from AS608");
          deletedFromSensor = true;
        } else { 
          Serial.println("⚠ Not found in AS608, but will notify server anyway");
          deletedFromSensor = false;
        } 
        
        // ✅ CRITICAL FIX: LUÔN notify server, bất kể kết quả xóa từ sensor
        notifyServerDelete(targetID);
        
        // Hiển thị kết quả
        display.clearDisplay();
        display.setTextSize(2);
        display.setCursor(30, 20);
        display.println("Da xoa!");
        display.display();
        
        // ✅ Beep phù hợp
        if (deletedFromSensor) {
          beepSuccess();
        } else {
          beepInfo();
        }
        
        delay(2000); 
        return; 
      }
      if(digitalRead(BTN_UP)==LOW||digitalRead(BTN_DOWN)==LOW){
        beepInfo(); 
        return;
      }
    }
  }

  // ============================================
  //  ✅ HÀM XÓA TẤT CẢ (ĐÃ CẢI TIẾN - PARSE JSON)
  // ============================================
  void deleteAllFingers() {
    delay(300); 
    display.clearDisplay(); 
    display.setCursor(0,0); 
    display.println("XOA TAT CA?"); 
    display.setCursor(0,15);
    display.println("UP/DW: Huy");
    display.setCursor(0,30);
    display.println("OK: Xac nhan");
    display.display();
    
    while(1){
      if(digitalRead(BTN_SELECT)==LOW){ 
        Serial.println("=== DELETING ALL FINGERPRINTS ===");
        
        display.clearDisplay(); 
        display.setTextSize(2); 
        display.setCursor(20, 20); 
        display.println("Dang xoa..."); 
        display.display(); 
        
        // ✅ BƯỚC 1: Xóa tất cả khỏi AS608
        if(finger.emptyDatabase()==FINGERPRINT_OK){
          Serial.println("✓ All deleted from AS608");
          
          // ✅ BƯỚC 2: Notify server xóa tất cả
          if (WiFi.status() == WL_CONNECTED) {
            Serial.println("→ Notifying server to delete all employees...");
            
            HTTPClient http;
            String url = String(host) + "/delete.php?all=true&dept=" + String(DEVICE_DEPT);
            
            Serial.println("URL: " + url);
            
            http.begin(url);
            http.setTimeout(HTTP_TIMEOUT_LONG); // ✅ 15 giây (xóa nhiều nên lâu hơn)
            
            int httpCode = http.GET();
            
            // ✅ Kiểm tra timeout
            if (httpCode == -1) {
              Serial.println("✗ Delete all timeout!");
              display.clearDisplay();
              display.setTextSize(2);
              display.setCursor(25, 20);
              display.println("Timeout!");
              display.display();
              beepError();
              http.end();
              delay(3000);
              return;
            }
            
            String response = http.getString();
            http.end();
            
            Serial.println("← HTTP Code: " + String(httpCode));
            Serial.println("← Response: " + response);
            
            // ✅ PARSE JSON RESPONSE
            if (httpCode == 200) {
              DynamicJsonDocument doc(512);
              DeserializationError error = deserializeJson(doc, response);
              
              if (!error) {
                String status = doc["status"] | "UNKNOWN";
                String message = doc["message"] | "";
                int empDeleted = doc["employees_deleted"] | 0;
                int attDeleted = doc["attendance_deleted"] | 0;
                
                Serial.println("Status: " + status);
                Serial.println("Message: " + message);
                Serial.println("Employees deleted: " + String(empDeleted));
                Serial.println("Attendance deleted: " + String(attDeleted));
                
                if (status == "OK") {
                  Serial.println("✓ All employees deleted from database!");
                  // Hiển thị kết quả
                  display.clearDisplay();
                  display.setTextSize(2);
                  display.setCursor(30, 20);
                  display.println("Da xoa!");
                  display.display();
                  beepSuccess();
                } else {
                  // Server trả về ERROR
                  Serial.println("⚠ Server error: " + message);
                  display.clearDisplay();
                  display.setTextSize(2);
                  display.setCursor(35, 20);
                  display.println("Loi!");
                  display.display();
                  beepError();
                }
              } else {
                Serial.println("⚠ JSON parse error");
                display.clearDisplay();
                display.setTextSize(2);
                display.setCursor(35, 20);
                display.println("Loi!");
                display.display();
                beepError();
              }
            } else {
              Serial.println("✗ HTTP Error: " + String(httpCode));
              display.clearDisplay();
              display.setTextSize(2);
              display.setCursor(35, 20);
              display.println("Loi!");
              display.display();
              beepError();
            }
          } else {
            Serial.println("✗ WiFi not connected!");
            display.clearDisplay();
            display.setTextSize(2);
            display.setCursor(20, 20);
            display.println("Mat WiFi!");
            display.display();
            beepError();
          }
        } else { 
          Serial.println("✗ Delete all failed");
          display.clearDisplay();
          display.setTextSize(2);
          display.setCursor(35, 20);
          display.println("Loi!");
          display.display();
          beepError(); 
        } 
        delay(3000); 
        return; 
      }
      if(digitalRead(BTN_UP)==LOW||digitalRead(BTN_DOWN)==LOW){
        beepInfo(); 
        return;
      }
    }
  }

  void handleDeleteMenu() {
    int sub = 0; bool in = true; delay(300); 
    while(in){
      display.clearDisplay(); display.setTextSize(1); display.setCursor(40, 0); display.println("MENU XOA"); display.drawLine(0, 10, 128, 10, WHITE); 
      if(sub == 0){ display.setCursor(0, 16); display.print(">"); } display.setCursor(12, 16); display.println("1. Xoa theo ID");
      if(sub == 1){ display.setCursor(0, 32); display.print(">"); } display.setCursor(12, 32); display.println("2. Xoa TAT CA");
      if(sub == 2){ display.setCursor(0, 48); display.print(">"); } display.setCursor(12, 48); display.println("3. Thoat"); display.display();
      if(digitalRead(BTN_UP) == LOW){ beepInfo(); sub++; if(sub > 2) sub = 0; while(digitalRead(BTN_UP) == LOW); }
      if(digitalRead(BTN_DOWN) == LOW){ beepInfo(); sub--; if(sub < 0) sub = 2; while(digitalRead(BTN_DOWN) == LOW); }
      if(digitalRead(BTN_SELECT) == LOW){ beepInfo(); if(sub == 0) deleteFingerByID(); if(sub == 1) deleteAllFingers(); in = false; while(digitalRead(BTN_SELECT) == LOW); }
    }
  }

  // --- HÀM THÔNG TIN HỆ THỐNG ---
  void showSystemInfo() {
    finger.getTemplateCount(); 
    unsigned long totalSeconds = millis() / 1000;
    int runHours = totalSeconds / 3600;
    int runMinutes = (totalSeconds % 3600) / 60;
    display.clearDisplay();
    display.setTextSize(1); 
    display.setCursor(10, 0); 
    display.println("THONG TIN HE THONG"); 
    display.drawLine(0, 10, 128, 10, WHITE);
    
    display.setCursor(0, 20); 
    display.print("Phong: "); 
    display.print(DEVICE_DEPT);
    display.setCursor(0, 35); 
    display.print("User: "); 
    display.print(finger.templateCount); 
    display.print("/");
    display.print(MAX_FINGERPRINT_ID); // ✅ Sử dụng constant
    display.setCursor(0, 50); 
    display.printf("Uptime: %02d:%02d", runHours, runMinutes);
    
    display.display(); 
    delay(500); 
    while(digitalRead(BTN_SELECT) == HIGH && digitalRead(BTN_UP) == HIGH && digitalRead(BTN_DOWN) == HIGH); 
    beepInfo(); 
  }

  // --- VÒNG LẶP CHÍNH ---
  void loop() {
    // ✅ FIX LAG: Check lệnh từ Server mỗi 2 giây (chỉ khi ở màn hình chính)
    if (currentScreen == 0 && millis() - lastPollTime > POLL_INTERVAL) {
      checkServerCommands();
      lastPollTime = millis();
    }
    
    if (digitalRead(BTN_UP) == LOW) { beepInfo(); if (currentScreen == 1) { menuIndex--; if (menuIndex < 0) menuIndex = totalMenu - 1; } while(digitalRead(BTN_UP) == LOW); }
    if (digitalRead(BTN_DOWN) == LOW) { beepInfo(); if (currentScreen == 1) { menuIndex++; if (menuIndex >= totalMenu) menuIndex = 0; } while(digitalRead(BTN_DOWN) == LOW); }
    if (digitalRead(BTN_SELECT) == LOW) {
      beepInfo();
      if (currentScreen == 0) { currentScreen = 1; menuIndex = 0; } 
      else if (currentScreen == 1) {
        if (menuIndex == 0) currentScreen = 0; 
        if (menuIndex == 1) enrollNewFinger(); 
        if (menuIndex == 2) handleDeleteMenu(); 
        if (menuIndex == 3) showSystemInfo(); 
        if (menuIndex == 4) handleWifiMenu();
      }
      while(digitalRead(BTN_SELECT) == LOW); 
    }
    
    if (currentScreen == 0) { 
      checkAttendance();
      
      // ✅ FIX LAG: Chỉ cập nhật OLED mỗi 1 giây (thay vì liên tục)
      if (millis() - lastDisplayUpdate >= DISPLAY_UPDATE_INTERVAL) {
        struct tm timeinfo;
        if(getLocalTime(&timeinfo)){
          display.clearDisplay();
          display.setTextSize(1); display.setCursor(0, 0);
          display.printf("Ngay: %02d/%02d/%04d", timeinfo.tm_mday, timeinfo.tm_mon + 1, timeinfo.tm_year + 1900);
          display.setTextSize(2); display.setCursor(16, 25); 
          display.printf("%02d:%02d:%02d", timeinfo.tm_hour, timeinfo.tm_min, timeinfo.tm_sec);
          display.display();
        }
        lastDisplayUpdate = millis();
      }
    }
    else { 
      display.clearDisplay();
      display.drawBitmap(48, 5, menuIcons[menuIndex], 32, 32, WHITE);
      display.setTextSize(1);
      int textLen = strlen(menuItems[menuIndex]) * 6; 
      int xPos = (128 - textLen) / 2; if(xPos < 0) xPos = 0;
      display.setCursor(xPos, 50); display.println(menuItems[menuIndex]);
      display.display();
    }
  }
