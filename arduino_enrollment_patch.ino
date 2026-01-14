// ============================================
//  PATCH: Fix enrollNewFinger() - Thêm vào code Arduino hiện tại
// ============================================

// HƯỚNG DẪN: 
// 1. Tìm hàm enrollNewFinger() trong code Arduino của bạn
// 2. Tìm đoạn code sau khi lưu vân tay thành công:
//    if(finger.createModel()==FINGERPRINT_OK && finger.storeModel(id)==FINGERPRINT_OK){
// 3. THAY THẾ phần gọi API bằng code bên dưới

// ============================================
//  CODE THAY THẾ (Bắt đầu từ đây)
// ============================================

if(finger.createModel()==FINGERPRINT_OK && finger.storeModel(id)==FINGERPRINT_OK){ 
  Serial.println("✓ Stored in AS608!");
  
  display.clearDisplay();
  display.setCursor(30, 25);
  display.println("DA LUU AS608!");
  display.display();
  delay(500);
  
  // ⚡ IMPROVED: Sync with web with retry logic
  if(WiFi.status() == WL_CONNECTED){
    Serial.println("=== SYNCING WITH WEB ===");
    
    display.setCursor(0, 35);
    display.println("Dang dong bo web...");
    display.display();
    
    String url = String(host) + "/api/register.php?id=" + String(id) + "&dept=" + String(DEVICE_DEPT);
    
    // ⚡ LOGGING: Print full URL for debugging
    Serial.println("Full URL: " + url);
    Serial.print("WiFi IP: ");
    Serial.println(WiFi.localIP());
    
    bool success = false;
    int maxRetries = 3;
    
    // ⚡ RETRY LOGIC: Try 3 times
    for(int attempt = 1; attempt <= maxRetries; attempt++) {
      Serial.printf("→ Attempt %d/%d\n", attempt, maxRetries);
      
      HTTPClient http;
      http.begin(url);
      http.setTimeout(5000); // 5 second timeout
      
      int httpCode = http.GET();
      
      Serial.print("← HTTP Code: ");
      Serial.println(httpCode);
      
      if(httpCode == 200) {
        String response = http.getString();
        Serial.println("← Response: " + response);
        
        // Check if response contains success message
        if(response.indexOf("tạo bản ghi") >= 0 || response.indexOf("tồn tại") >= 0) {
          success = true;
          Serial.println("✓ Web sync successful!");
          
          display.clearDisplay();
          display.setCursor(20, 25);
          display.println("THANH CONG!");
          display.display();
          beepSuccess();
          
          http.end();
          break; // Exit retry loop
        }
      } else if(httpCode > 0) {
        Serial.printf("✗ HTTP Error: %d\n", httpCode);
      } else {
        Serial.printf("✗ Connection Error: %s\n", http.errorToString(httpCode).c_str());
      }
      
      http.end();
      
      // Retry delay
      if(attempt < maxRetries) {
        Serial.println("⟳ Retrying in 1 second...");
        delay(1000);
      }
    }
    
    // ⚡ HANDLE FAILURE
    if(!success) {
      Serial.println("✗ Web sync failed after 3 attempts");
      
      display.clearDisplay();
      display.setCursor(10, 20);
      display.println("Luu AS608 OK!");
      display.setCursor(10, 35);
      display.println("Loi dong bo web");
      display.setCursor(10, 50);
      display.println("Vui long thu lai");
      display.display();
      beepError();
    }
  } else {
    // ⚡ NO WIFI
    Serial.println("✗ WiFi NOT connected!");
    
    display.clearDisplay();
    display.setCursor(10, 20);
    display.println("Luu AS608 OK!");
    display.setCursor(10, 35);
    display.println("Mat WiFi!");
    display.setCursor(10, 50);
    display.println("Dong bo sau");
    display.display();
    beepError();
  }
  
} else { 
  // ⚡ ENROLLMENT FAILED
  Serial.println("✗ Enrollment failed!");
  
  display.clearDisplay();
  display.setCursor(30, 25);
  display.println("THAT BAI!");
  display.display();
  beepError();
} 

delay(1500);

// ============================================
//  CODE THAY THẾ (Kết thúc)
// ============================================

// LƯU Ý:
// - Đảm bảo biến 'host' và 'DEVICE_DEPT' đã được khai báo đúng
// - Kiểm tra IP address trong biến 'host'
// - Mở Serial Monitor (115200 baud) để xem log khi test
