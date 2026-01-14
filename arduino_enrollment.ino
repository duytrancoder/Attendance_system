// ============================================
//  IMPROVED FINGERPRINT ENROLLMENT
// ============================================
void enrollNewFinger() {
  // Find next available ID
  int id = -1;
  for(int i=1; i<128; i++){
    if(finger.loadModel(i) != FINGERPRINT_OK){
      id=i;
      break;
    }
  }
  
  if(id == -1){
    Serial.println("✗ Memory full!");
    displayMessageWithTimeout("THEM VAN TAY", "Bo nho day!", "", 2000);
    beepError();
    return;
  }
  
  Serial.println("=== ENROLLMENT START ===");
  Serial.println("ID: " + String(id));
  
  // Step 1: First scan
  int p = -1;
  while(p != FINGERPRINT_OK){
    display.clearDisplay();
    display.setCursor(25, 0);
    display.println("THEM VAN TAY");
    display.drawLine(0, 10, 128, 10, WHITE);
    display.setCursor(0, 25);
    display.printf("ID trong: #%d", id);
    display.setCursor(0, 35);
    display.println("Dat tay lan 1...");
    display.setCursor(0, 55);
    display.print("Giu UP/DW: Thoat");
    display.display();
    
    p = finger.getImage();
    
    // Check for cancel
    if(digitalRead(BTN_UP)==LOW || digitalRead(BTN_DOWN)==LOW) {
      unsigned long t=millis();
      while(digitalRead(BTN_UP)==LOW || digitalRead(BTN_DOWN)==LOW) {
        if(millis()-t>1000) {
          beepInfo();
          return;
        }
      }
    }
  }
  
  if(finger.image2Tz(1)!=FINGERPRINT_OK){
    displayMessageWithTimeout("THEM VAN TAY", "Loi doc van tay!", "", 2000);
    beepError();
    return;
  }
  
  // Step 2: Remove finger
  displayMessageWithTimeout("THEM VAN TAY", "Nha tay ra...", "", 1000);
  beepInfo();
  while(finger.getImage() != FINGERPRINT_NOFINGER);
  
  // Step 3: Second scan
  p = -1;
  while(p != FINGERPRINT_OK){
    display.clearDisplay();
    display.setCursor(25, 0);
    display.println("THEM VAN TAY");
    display.drawLine(0, 10, 128, 10, WHITE);
    display.setCursor(0, 25);
    display.printf("ID: #%d", id);
    display.setCursor(0, 35);
    display.println("Xac nhan lan 2...");
    display.setCursor(0, 55);
    display.print("Giu UP/DW: Thoat");
    display.display();
    
    p = finger.getImage();
    
    // Check for cancel
    if(digitalRead(BTN_UP)==LOW || digitalRead(BTN_DOWN)==LOW) {
      unsigned long t=millis();
      while(digitalRead(BTN_UP)==LOW || digitalRead(BTN_DOWN)==LOW) {
        if(millis()-t>1000) {
          beepInfo();
          return;
        }
      }
    }
  }
  
  if(finger.image2Tz(2)!=FINGERPRINT_OK){
    displayMessageWithTimeout("THEM VAN TAY", "Loi doc van tay!", "", 2000);
    beepError();
    return;
  }
  
  // Step 4: Create and store model
  if(finger.createModel()==FINGERPRINT_OK && finger.storeModel(id)==FINGERPRINT_OK){
    Serial.println("✓ Stored in AS608!");
    
    display.clearDisplay();
    display.setCursor(30, 20);
    display.println("DA LUU AS608!");
    display.display();
    delay(500);
    
    // Step 5: Notify server with retry
    if(WiFi.status() == WL_CONNECTED){
      display.setCursor(0, 35);
      display.println("Dang dong bo web...");
      display.display();
      
      String url = String(host) + "/api/register.php?id=" + String(id) + "&dept=" + String(DEVICE_DEPT);
      String response;
      
      if(httpGetWithRetry(url, response, 3, 5000)) {
        Serial.println("✓ Successfully synced with web");
        display.clearDisplay();
        display.setCursor(20, 25);
        display.println("THANH CONG!");
        display.display();
        beepSuccess();
      } else {
        Serial.println("⚠ Web sync failed, but fingerprint is saved in AS608");
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
      Serial.println("⚠ No WiFi, fingerprint saved locally only");
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
    Serial.println("✗ Enrollment failed!");
    displayMessageWithTimeout("THEM VAN TAY", "THAT BAI!", "", 2000);
    beepError();
  }
  
  delay(1500);
}
