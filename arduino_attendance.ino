// ============================================
//  IMPROVED ATTENDANCE CHECK FUNCTION
// ============================================
void checkAttendance() {
  if (finger.getImage() == FINGERPRINT_OK) {
    if (finger.image2Tz() == FINGERPRINT_OK) {
      if (finger.fingerFastSearch() == FINGERPRINT_OK) {
        int id = finger.fingerID;
        String name = "Unknown";
        
        Serial.println("✓ Found ID: " + String(id));
        
        // Check WiFi before making request
        if (WiFi.status() != WL_CONNECTED) {
          name = "Mat WiFi";
          beepError();
          displayMessageWithTimeout("Xin chao", name, "", 2000);
          return;
        }
        
        // Call checkin API with retry
        String url = String(host) + "/api/checkin.php?finger_id=" + String(id);
        DynamicJsonDocument doc(1024);
        
        if (httpGetJSON(url, doc, 2)) { // 2 retries
          if (doc.containsKey("name")) {
            name = doc["name"].as<String>();
            name = removeVietnamese(name);
            
            String action = doc.containsKey("action") ? doc["action"].as<String>() : "";
            Serial.println("Action: " + action);
            
            beepSuccess();
          } else if (doc.containsKey("error") || doc.containsKey("message")) {
            name = doc.containsKey("error") ? doc["error"].as<String>() : doc["message"].as<String>();
            if(name.indexOf("Chua dang ky") >= 0) {
              name = "Chua dang ky";
            }
            beepError();
          }
        } else {
          name = "Loi Server";
          beepError();
        }
        
        // Display with non-blocking delay
        display.clearDisplay();
        centerPrint("Xin chao", 10, 1);
        if(name.length() > 10) {
          centerPrint(name, 30, 1);
        } else {
          centerPrint(name, 28, 2);
        }
        display.display();
        
        // Non-blocking delay - allow early exit
        delayWithButtonCheck(2500);
      } else {
        display.clearDisplay();
        centerPrint("Khong khop!", 30, 1);
        display.display();
        beepError();
        delayWithButtonCheck(1000);
      }
    }
  }
}
