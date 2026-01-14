// ============================================
//  HTTP HELPER FUNCTIONS WITH RETRY LOGIC
// ============================================

// HTTP GET with retry logic
bool httpGetWithRetry(String url, String& response, int maxRetries = 3, int timeoutMs = 5000) {
  for(int attempt = 1; attempt <= maxRetries; attempt++) {
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("✗ WiFi not connected!");
      return false;
    }
    
    HTTPClient http;
    http.begin(url);
    http.setTimeout(timeoutMs);
    
    Serial.printf("→ HTTP GET (attempt %d/%d): %s\n", attempt, maxRetries, url.c_str());
    
    int httpCode = http.GET();
    
    if (httpCode == 200) {
      response = http.getString();
      http.end();
      Serial.printf("← HTTP 200 OK (length: %d)\n", response.length());
      return true;
    } else if (httpCode > 0) {
      Serial.printf("← HTTP Error: %d\n", httpCode);
    } else {
      Serial.printf("← Connection Error: %s\n", http.errorToString(httpCode).c_str());
    }
    
    http.end();
    
    if (attempt < maxRetries) {
      Serial.printf("⟳ Retrying in 1 second...\n");
      delay(1000);
    }
  }
  
  Serial.printf("✗ Failed after %d attempts\n", maxRetries);
  return false;
}

// HTTP GET with JSON parsing
bool httpGetJSON(String url, DynamicJsonDocument& doc, int maxRetries = 3) {
  String response;
  if (!httpGetWithRetry(url, response, maxRetries)) {
    return false;
  }
  
  DeserializationError error = deserializeJson(doc, response);
  if (error) {
    Serial.print("✗ JSON parse error: ");
    Serial.println(error.c_str());
    return false;
  }
  
  return true;
}

// Non-blocking delay with button check
bool delayWithButtonCheck(unsigned long ms) {
  unsigned long startTime = millis();
  while(millis() - startTime < ms) {
    if(digitalRead(BTN_SELECT)==LOW || 
       digitalRead(BTN_UP)==LOW || 
       digitalRead(BTN_DOWN)==LOW) {
      beepInfo();
      return true; // Button pressed
    }
    delay(10);
  }
  return false; // Timeout
}

// Display message with timeout
void displayMessageWithTimeout(String line1, String line2, String line3, unsigned long timeoutMs) {
  display.clearDisplay();
  display.setTextSize(1);
  display.setCursor(0, 10);
  display.println(line1);
  if(line2.length() > 0) {
    display.setCursor(0, 30);
    display.println(line2);
  }
  if(line3.length() > 0) {
    display.setCursor(0, 50);
    display.println(line3);
  }
  display.display();
  delayWithButtonCheck(timeoutMs);
}
