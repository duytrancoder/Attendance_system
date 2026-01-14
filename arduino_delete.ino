// ============================================
//  IMPROVED DELETE FUNCTIONS
// ============================================

// Delete single fingerprint by ID
void deleteFingerByID() {
  int targetID=1;
  bool selecting=true;
  delay(300);
  
  // ID selection loop
  while(selecting){
    drawIDSelection(targetID);
    
    if(digitalRead(BTN_UP)==LOW){
      beepInfo();
      targetID++;
      if(targetID>127) targetID=127;
      drawIDSelection(targetID);
      unsigned long t=millis();
      while(digitalRead(BTN_UP)==LOW){
        if(millis()-t>1500){
          targetID+=10;
          if(targetID>127)targetID=127;
          beepInfo();
          drawIDSelection(targetID);
          t=millis();
        }
        delay(10);
      }
    }
    
    if(digitalRead(BTN_DOWN)==LOW){
      beepInfo();
      targetID--;
      if(targetID<1) targetID=1;
      drawIDSelection(targetID);
      unsigned long t=millis();
      while(digitalRead(BTN_DOWN)==LOW){
        if(millis()-t>1500){
          targetID-=10;
          if(targetID<1)targetID=1;
          beepInfo();
          drawIDSelection(targetID);
          t=millis();
        }
        delay(10);
      }
    }
    
    if(digitalRead(BTN_SELECT)==LOW){
      beepInfo();
      selecting=false;
      while(digitalRead(BTN_SELECT)==LOW);
    }
  }
  
  // Confirmation screen
  display.clearDisplay();
  display.setTextSize(1);
  display.setCursor(0,0);
  display.println("XAC NHAN XOA?");
  display.setTextSize(2);
  display.setCursor(30,20);
  display.printf("ID:%d",targetID);
  display.setTextSize(1);
  display.setCursor(0,50);
  display.println("OK: Xac nhan");
  display.setCursor(0,58);
  display.println("UP/DW: Huy");
  display.display();
  
  while(1){
    if(digitalRead(BTN_SELECT)==LOW){
      Serial.println("=== DELETING FINGERPRINT ===");
      Serial.println("ID: " + String(targetID));
      
      display.clearDisplay();
      display.setTextSize(1);
      display.setCursor(0, 0);
      display.println("BUOC 1/2:");
      display.println("Dang xoa web...");
      display.display();
      
      // STEP 1: Delete from web FIRST (with retry)
      bool webDeleted = false;
      if (WiFi.status() == WL_CONNECTED) {
        String url = String(host) + "/api/delete.php?id=" + String(targetID);
        String response;
        
        if(httpGetWithRetry(url, response, 3, 5000)) {
          // Check if response indicates success
          if(response.indexOf("\"status\":\"OK\"") >= 0 || 
             response.indexOf("Da xoa") >= 0) {
            webDeleted = true;
            Serial.println("✓ Web deleted successfully");
            
            display.setCursor(0, 20);
            display.println("✓ Xoa web OK!");
          } else {
            Serial.println("⚠ Web returned unexpected response");
            Serial.println(response);
          }
        }
        
        if(!webDeleted) {
          display.setCursor(0, 20);
          display.println("✗ Loi xoa web!");
          display.setCursor(0, 35);
          display.println("Tiep tuc xoa AS608?");
          display.setCursor(0, 50);
          display.println("OK: Co | UP/DW: Khong");
          display.display();
          
          // Let user decide
          while(1) {
            if(digitalRead(BTN_SELECT)==LOW) {
              webDeleted = true;
              beepInfo();
              while(digitalRead(BTN_SELECT)==LOW);
              break;
            }
            if(digitalRead(BTN_UP)==LOW || digitalRead(BTN_DOWN)==LOW) {
              beepInfo();
              return; // Cancel
            }
          }
        }
      } else {
        display.setCursor(0, 20);
        display.println("✗ Mat WiFi!");
        display.setCursor(0, 35);
        display.println("Xoa AS608 offline?");
        display.setCursor(0, 50);
        display.println("OK: Co | UP/DW: Khong");
        display.display();
        
        // Let user decide
        while(1) {
          if(digitalRead(BTN_SELECT)==LOW) {
            webDeleted = true;
            beepInfo();
            while(digitalRead(BTN_SELECT)==LOW);
            break;
          }
          if(digitalRead(BTN_UP)==LOW || digitalRead(BTN_DOWN)==LOW) {
            beepInfo();
            return; // Cancel
          }
        }
      }
      
      // STEP 2: Delete from AS608
      if(webDeleted) {
        display.clearDisplay();
        display.setCursor(0, 0);
        display.println("BUOC 2/2:");
        display.println("Dang xoa AS608...");
        display.display();
        
        if(finger.deleteModel(targetID)==FINGERPRINT_OK){
          Serial.println("✓ Deleted from AS608");
          display.setCursor(0, 30);
          display.println("✓ Xoa AS608 OK!");
          display.display();
          beepSuccess();
        } else {
          Serial.println("✗ AS608 delete failed");
          display.setCursor(0, 30);
          display.println("✗ Loi xoa AS608!");
          display.display();
          beepError();
        }
      }
      
      delay(2000);
      return;
    }
    
    if(digitalRead(BTN_UP)==LOW||digitalRead(BTN_DOWN)==LOW){
      beepInfo();
      return; // Cancel
    }
  }
}

// Delete all fingerprints
void deleteAllFingers() {
  delay(300);
  
  // First confirmation
  display.clearDisplay();
  display.setTextSize(1);
  display.setCursor(0,0);
  display.println("XOA TAT CA?");
  display.drawLine(0, 10, 128, 10, WHITE);
  display.setCursor(0, 25);
  display.println("OK: Tiep tuc");
  display.setCursor(0, 40);
  display.println("UP/DW: Huy");
  display.display();
  
  while(1){
    if(digitalRead(BTN_SELECT)==LOW){
      beepInfo();
      while(digitalRead(BTN_SELECT)==LOW);
      break;
    }
    if(digitalRead(BTN_UP)==LOW||digitalRead(BTN_DOWN)==LOW){
      beepInfo();
      return;
    }
  }
  
  // Second confirmation (hold for 3 seconds)
  display.clearDisplay();
  display.setCursor(0,0);
  display.println("XAC NHAN XOA TAT CA");
  display.drawLine(0, 10, 128, 10, WHITE);
  display.setCursor(0, 25);
  display.println("GIU OK 3 GIAY");
  display.setCursor(0, 40);
  display.println("DE XAC NHAN");
  display.display();
  
  unsigned long pressStart = 0;
  bool pressing = false;
  
  while(1) {
    if(digitalRead(BTN_SELECT)==LOW) {
      if(!pressing) {
        pressing = true;
        pressStart = millis();
      }
      
      unsigned long elapsed = millis() - pressStart;
      if(elapsed > 3000) {
        // Confirmed!
        break;
      }
      
      // Show progress
      display.fillRect(0, 55, map(elapsed, 0, 3000, 0, 128), 8, WHITE);
      display.display();
    } else {
      if(pressing) {
        // Released too early
        beepInfo();
        return;
      }
    }
    
    if(digitalRead(BTN_UP)==LOW||digitalRead(BTN_DOWN)==LOW){
      beepInfo();
      return;
    }
  }
  
  beepInfo();
  Serial.println("=== DELETING ALL FINGERPRINTS ===");
  
  // STEP 1: Delete from web FIRST
  display.clearDisplay();
  display.setCursor(0, 0);
  display.println("BUOC 1/2:");
  display.println("Dang xoa web...");
  display.display();
  
  bool webDeleted = false;
  if (WiFi.status() == WL_CONNECTED) {
    String url = String(host) + "/api/delete.php?all=true&dept=" + String(DEVICE_DEPT);
    String response;
    
    if(httpGetWithRetry(url, response, 3, 10000)) { // 10s timeout for delete all
      if(response.indexOf("\"status\":\"OK\"") >= 0) {
        webDeleted = true;
        Serial.println("✓ Web deleted all successfully");
        
        // Parse employee count
        int empDeleted = 0;
        int idx = response.indexOf("\"employees_deleted\":");
        if(idx >= 0) {
          empDeleted = response.substring(idx + 20, idx + 25).toInt();
        }
        
        display.setCursor(0, 20);
        display.print("✓ Xoa web OK!");
        display.setCursor(0, 35);
        display.printf("Da xoa %d NV", empDeleted);
      } else {
        Serial.println("⚠ Web returned error");
        Serial.println(response);
      }
    }
    
    if(!webDeleted) {
      display.setCursor(0, 20);
      display.println("✗ Loi xoa web!");
      display.setCursor(0, 35);
      display.println("HUY XOA!");
      display.display();
      beepError();
      delay(2000);
      return; // ABORT - don't delete AS608 if web failed
    }
  } else {
    display.setCursor(0, 20);
    display.println("✗ Mat WiFi!");
    display.setCursor(0, 35);
    display.println("HUY XOA!");
    display.display();
    beepError();
    delay(2000);
    return; // ABORT - don't delete AS608 without web sync
  }
  
  // STEP 2: Delete from AS608 (only if web succeeded)
  display.setCursor(0, 50);
  display.println("Dang xoa AS608...");
  display.display();
  delay(500);
  
  if(finger.emptyDatabase()==FINGERPRINT_OK){
    Serial.println("✓ Deleted all from AS608");
    
    display.clearDisplay();
    display.setCursor(20, 25);
    display.println("THANH CONG!");
    display.display();
    beepSuccess();
  } else {
    Serial.println("✗ AS608 delete all failed");
    
    display.clearDisplay();
    display.setCursor(10, 20);
    display.println("Web OK!");
    display.setCursor(10, 35);
    display.println("Loi xoa AS608!");
    display.display();
    beepError();
  }
  
  delay(3000);
}
