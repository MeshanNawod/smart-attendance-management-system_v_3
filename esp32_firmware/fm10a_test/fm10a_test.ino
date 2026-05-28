#include <Wire.h> 
#include <LiquidCrystal_I2C.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <Keypad.h>
#include <SPI.h>
#include <MFRC522.h>
#include <RTClib.h>
#include <SD.h>
#include <Adafruit_Fingerprint.h>
#include <WebServer.h>
#include <Preferences.h> 

#if __has_include(<esp_arduino_version.h>)
#include <esp_arduino_version.h>
#endif

// --- 1. PIN DEFINITIONS ---
#define SD_CS_PIN 4      
#define RFID_CS_PIN 15   
#define BLUE_LED 2       
#define FINGER_VOUT 34   
#define FINGER_RX2 16    
#define FINGER_TX2 17    

// --- 2. NETWORK CONFIGURATION ---
const char* serverURL = "http://172.16.62.212/csc2052/attendance.php";
const char* heartbeatURL = "http://172.16.62.212/csc2052/heartbeat.php"; 

// --- 3. OBJECTS ---
LiquidCrystal_I2C lcd(0x27, 20, 4);  
RTC_DS3231 rtc;
MFRC522 mfrc522(RFID_CS_PIN, UINT8_MAX); 
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&Serial2);
WebServer localServer(80); 
Preferences preferences; 

String wifi_ssid;
String wifi_pass;

// Keypad
const byte ROWS = 4; 
const byte COLS = 4;
char keys[ROWS][COLS] = {
  {'D','C','B','A'},
  {'#','9','6','3'},
  {'0','8','5','2'},
  {'*','7','4','1'}
};
byte rowPins[ROWS] = {5, 12, 14, 27}; 
byte colPins[COLS] = {26, 25, 33, 32}; 
Keypad keypad = Keypad(makeKeymap(keys), rowPins, colPins, ROWS, COLS);

// System Variables
enum SystemState { STANDBY, MAIN_MENU, ATTENDANCE_MODE, ENROLL_MODE };
SystemState currentState = STANDBY;
int menuIdx = 0;
bool screenNeedsUpdate = true;
unsigned long lastClockUpdate = 0;
unsigned long lastHeartbeat = 0; 

// Duplicate scan cooldown
String lastScannedID = "";
unsigned long lastScanTime = 0;
const unsigned long COOLDOWN_MS = 5000; 

// Digital Twin Variables (Web Mirroring)
String lcdLine[4] = {"", "", "", ""};
char injectedKey = 0; 

// Forward Declarations to ensure clean compilation
void handleWiFiCommand();
void checkSerialCommands();
void handleStandby(DateTime now);
void handleMainMenu(char key);
void handleAttendance(DateTime now);
void handleEnrollment(char key);
void enrollFingerprint(int id);
void syncOfflineLogs();
void printLeft(String t, int r);
void flashLED(int t, int ms);
void logAndSync(String id, DateTime now);
void sendDataToWeb(String student_no, DateTime now);
int getFingerID();

// --- 4. BUS MANAGER ---
void useSD() { 
  digitalWrite(RFID_CS_PIN, HIGH); 
  digitalWrite(SD_CS_PIN, LOW); 
}

void useRFID() { 
  digitalWrite(SD_CS_PIN, HIGH); 
  digitalWrite(RFID_CS_PIN, LOW); 
}

void silenceBus() { 
  digitalWrite(SD_CS_PIN, HIGH); 
  digitalWrite(RFID_CS_PIN, HIGH); 
}

// --- 5. SYSTEM SETUP ---
void setup() {
  Serial.begin(115200);
  pinMode(BLUE_LED, OUTPUT); 
  pinMode(FINGER_VOUT, INPUT); 
  pinMode(SD_CS_PIN, OUTPUT); 
  pinMode(RFID_CS_PIN, OUTPUT);
  silenceBus();

  Serial.println("\n==================================");
  Serial.println("  UoP AMS: SENTINEL NODE BOOTING  ");
  Serial.println("==================================");

  lcd.init(); 
  lcd.init(); 
  lcd.backlight();
  printLeft("UoP AMS ONLINE", 0);

  // Load Wi-Fi credentials from NVRAM
  preferences.begin("ams_cfg", false);
  wifi_ssid = preferences.getString("ssid", "Card ekak daganin bn"); 
  wifi_pass = preferences.getString("pass", "meshan1234");          
  preferences.end();

  WiFi.begin(wifi_ssid.c_str(), wifi_pass.c_str());
  printLeft("WiFi Syncing...", 1);
  lcd.setCursor(0, 2); 
  lcd.print(wifi_ssid.substring(0, 20));
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) { 
    delay(500); 
    Serial.print("."); 
    attempts++; 
  }
  
  if(WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[NET] WiFi Connected! IP: " + WiFi.localIP().toString());
    localServer.on("/cmd", handleWiFiCommand);
    localServer.begin();
    Serial.println("[NET] Wi-Fi OTA Terminal Started on Port 80");
  } else {
    Serial.println("\n[NET] WiFi Failed. Check credentials.");
    lcd.clear(); 
    printLeft("WIFI FAILED", 0); 
    printLeft("RUNNING LOCALLY", 1); 
    delay(2000);
  }
  
  SPI.begin(18, 19, 23); 
  
  // SD Check
  useSD(); 
  if (SD.begin(SD_CS_PIN)) {
    Serial.println("[HW] SD Card: OK");
  } else {
    Serial.println("[HW] SD Card: FAILED");
  }
  silenceBus();
  
  // RFID Check
  useRFID(); 
  mfrc522.PCD_Init(); 
  byte rfid_v = mfrc522.PCD_ReadRegister(mfrc522.VersionReg);
  if (rfid_v == 0x00 || rfid_v == 0xFF) {
    Serial.println("[HW] RFID MFRC522: FAILED");
  } else {
    Serial.println("[HW] RFID MFRC522: OK");
  }
  silenceBus();
  
  // Fingerprint Check
  delay(500); // Give the biometrics sensor a stable power-up window
  Serial2.begin(57600, SERIAL_8N1, FINGER_RX2, FINGER_TX2); 
  delay(500); // Allow hardware UART interface to settle
  if (finger.verifyPassword()) {
    Serial.println("[HW] FM10A Fingerprint: OK");
  } else {
    Serial.println("[HW] FM10A Fingerprint: FAILED");
  }
  
  // RTC Check
  if (rtc.begin()) {
    Serial.println("[HW] RTC DS3231: OK");
  } else {
    Serial.println("[HW] RTC DS3231: FAILED");
  }

  delay(1500);
  currentState = STANDBY;
  screenNeedsUpdate = true;
}

// --- 6. MAIN LOOP ---
void loop() {
  localServer.handleClient(); 
  checkSerialCommands();      
  
  // 10-Second Heartbeat Ping
  if (millis() - lastHeartbeat > 10000) {
    if (WiFi.status() == WL_CONNECTED) {
      HTTPClient http; 
      http.begin(heartbeatURL); 
      http.GET(); 
      http.end();
    }
    lastHeartbeat = millis();
  }

  // Inject Web Keypad presses into the physical flow
  char key = keypad.getKey();
  if (injectedKey != 0) {
    key = injectedKey;
    injectedKey = 0; // Clear it after reading so it doesn't loop
  }

  DateTime now = rtc.now();

  if (key) { 
    Serial.println("[KEY] Pressed: " + String(key)); 
    flashLED(1, 50); 
    
    // Core state navigation: A or * toggles main menu or exits active modes
    if (key == 'A' || key == '*') { 
      if (currentState == ATTENDANCE_MODE || currentState == ENROLL_MODE) {
        currentState = MAIN_MENU;
      } else {
        currentState = (currentState == MAIN_MENU) ? STANDBY : MAIN_MENU; 
      }
      screenNeedsUpdate = true; 
    }
  }

  switch (currentState) {
    case STANDBY:         handleStandby(now); break;
    case MAIN_MENU:       handleMainMenu(key); break;
    case ATTENDANCE_MODE: handleAttendance(now); break;
    case ENROLL_MODE:     handleEnrollment(key); break;
  }
}

// --- 7. WI-FI COMMAND LOGIC (DIGITAL TWIN) ---
void handleWiFiCommand() {
  localServer.sendHeader("Access-Control-Allow-Origin", "*"); 
  flashLED(1, 100); // Visual hardware confirmation of network traffic
  
  if (localServer.hasArg("command")) {
    String originalCmd = localServer.arg("command");
    String cmd = originalCmd;
    cmd.toUpperCase();
    
    // Silent response for Virtual LCD polling to keep terminal clean
    if (cmd == "GETLCD") {
      String payload = lcdLine[0] + "|" + lcdLine[1] + "|" + lcdLine[2] + "|" + lcdLine[3];
      localServer.send(200, "text/plain", payload);
      return; 
    }

    String response = "[OTA] Command Received: '" + originalCmd + "'\n";

    if (cmd.startsWith("KEYPAD ")) {
      injectedKey = cmd.charAt(7);
      response += "[HW] Keypad Injection Success: Simulated button '" + String(injectedKey) + "'";
    }
    else if (cmd == "SCANWIFI") {
      int n = WiFi.scanNetworks();
      response = "WIFILIST:"; 
      if (n == 0) { 
        response += "NO_NETWORKS_FOUND"; 
      } else { 
        for (int i = 0; i < n; ++i) {
          response += WiFi.SSID(i) + ","; 
        }
      }
      localServer.send(200, "text/plain", response); 
      return; 
    }
    else if (cmd == "PING") {
      response += "[SYS] PONG! Sentinel Swarm Node responding perfectly.";
    }
    else if (cmd == "LCDON") { 
      lcd.backlight(); 
      response += "[HW] LCD Backlight Circuit Enabled."; 
    }
    else if (cmd == "LCDOFF") { 
      lcd.noBacklight(); 
      response += "[HW] LCD Backlight Circuit Disabled."; 
    }
    else if (cmd == "REBOOT") {
      response += "[SYS] Executing hardware reboot in 2 seconds...";
      localServer.send(200, "text/plain", response); 
      delay(2000); 
      ESP.restart();
    }
    else if (cmd.startsWith("SETWIFI ")) {
      String data = originalCmd.substring(8); 
      int splitIdx = data.indexOf('|');
      if (splitIdx > 0) {
        String new_ssid = data.substring(0, splitIdx); 
        String new_pass = data.substring(splitIdx + 1);
        preferences.begin("ams_cfg", false);
        preferences.putString("ssid", new_ssid); 
        preferences.putString("pass", new_pass);
        preferences.end();
        response += "[NET] Wi-Fi NVRAM Updated. Rebooting into: " + new_ssid;
        localServer.send(200, "text/plain", response); 
        delay(2000); 
        ESP.restart();
      } else {
        response += "[ERR] Invalid Wi-Fi parsing format. Expected SSID|PASS.";
      }
    }
    else if (cmd == "DUMP") {
      useSD(); 
      File file = SD.open("/logs.csv");
      if (file) {
        response += "\n--- START OF OFFLINE SD LOGS ---\n";
        while (file.available()) {
          response += (char)file.read();
        }
        file.close(); 
        response += "--- END OF SD LOGS ---";
      } else {
        response += "[ERR] SPI Bus Error: Could not locate /logs.csv on SD module.";
      }
      silenceBus();
    }
    else if (cmd == "CLEARLOGS") {
      useSD();
      if(SD.remove("/logs.csv")) {
        response += "[SD] SUCCESS: Database cache /logs.csv purged.";
      } else {
        response += "[ERR] File handling error: /logs.csv missing or locked.";
      }
      silenceBus();
    }
    else if (cmd == "SYNCLOGS") {
      response += "[SYNC] Offline Log Bulk Transfer Started. Monitoring terminal for PHP codes.";
      localServer.send(200, "text/plain", response); 
      syncOfflineLogs(); 
      return;
    }
    else if (cmd.startsWith("ENROLL ")) {
      int targetID = cmd.substring(7).toInt();
      response += "[ENROLL] Sub-routine triggered for Slot " + String(targetID) + ".\nAwaiting FM10A sensor input...";
      localServer.send(200, "text/plain", response); 
      enrollFingerprint(targetID); 
      return; 
    } 
    else {
      response += "[SYS] Custom String Passed to Terminal.\n[SYS] No built-in action tied to: " + originalCmd;
    }
    
    localServer.send(200, "text/plain", response);
  }
}

// --- 8. OFFLINE SYNC LOGIC ---
void syncOfflineLogs() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[SYNC] Cannot sync, WiFi is down.");
    return;
  }
  
  useSD();
  File file = SD.open("/logs.csv");
  if (!file) {
    silenceBus();
    Serial.println("[SYNC] No offline logs to sync.");
    return;
  }

  Serial.println("\n[SYNC] Bulk transferring offline logs...");
  int syncCount = 0;

  while (file.available()) {
    String line = file.readStringUntil('\n');
    line.trim();
    
    if (line.length() > 10) {
      int firstComma = line.indexOf(',');
      int secondComma = line.indexOf(',', firstComma + 1);
      
      if (firstComma > 0 && secondComma > 0) {
        String dateStr = line.substring(0, firstComma);
        String timeStr = line.substring(firstComma + 1, secondComma);
        String idStr = line.substring(secondComma + 1);
        
        String fullTime = dateStr + "%20" + timeStr;
        
        HTTPClient http; 
        http.begin(serverURL); 
        http.addHeader("Content-Type", "application/x-www-form-urlencoded");
        String postData = "student_no=" + idStr + "&timestamp=" + fullTime + "&device_name=UoP_Offline_Sync";
        int code = http.POST(postData); 
        if (code == 200) syncCount++;
        http.end();
      }
    }
  }
  file.close();
  silenceBus();
  Serial.println("[SYNC] Bulk Transfer Complete. Logs Processed: " + String(syncCount));
}

// --- 9. NORMAL SERIAL COMMANDS ---
void checkSerialCommands() {
  if (Serial.available() > 0) {
    String cmd = Serial.readStringUntil('\n'); 
    cmd.trim(); 
    cmd.toUpperCase();
    
    if (cmd == "PING") {
      Serial.println("[SYS] PONG!");
    }
    else if (cmd == "DUMP") {
      useSD(); 
      File file = SD.open("/logs.csv");
      if (file) { 
        while (file.available()) {
          Serial.write(file.read()); 
        }
        file.close(); 
      }
      silenceBus();
    }
  }
}

// --- 10. ENROLLMENT LOGIC ---
void enrollFingerprint(int id) {
  int p = -1;
  Serial.println("Waiting for valid finger to enroll as ID #" + String(id));
  lcd.clear(); 
  printLeft("ENROLLING ID " + String(id), 0); 
  printLeft("PLACE FINGER NOW", 1);
  
  while (p != FINGERPRINT_OK) {
    p = finger.getImage();
    // Allow Web abort via virtual keypad 'A' or Serial input
    if (injectedKey == 'A' || Serial.available()) {
      injectedKey = 0;
      Serial.println("[ENROLL] Aborted by user command.");
      lcd.clear();
      printLeft("ENROLL ABORTED", 1);
      delay(1500);
      screenNeedsUpdate = true;
      return;
    }
    localServer.handleClient(); // Keep listening to the web server during loop
  }

  p = finger.image2Tz(1);
  if (p != FINGERPRINT_OK) { 
    Serial.println("Error processing image."); 
    lcd.clear();
    printLeft("IMAGE 1 ERROR!", 1);
    delay(2000);
    screenNeedsUpdate = true;
    return; 
  }
  
  Serial.println("Remove finger");
  lcd.clear(); 
  printLeft("REMOVE FINGER", 1);
  delay(1500);
  
  p = 0; 
  while (p != FINGERPRINT_NOFINGER) { 
    p = finger.getImage(); 
  }
  
  Serial.println("Place same finger again");
  lcd.clear(); 
  printLeft("PLACE AGAIN", 1);
  
  p = -1; 
  while (p != FINGERPRINT_OK) { 
    p = finger.getImage(); 
    localServer.handleClient(); 
  }
  
  p = finger.image2Tz(2);
  if (p != FINGERPRINT_OK) { 
    Serial.println("Error processing second image."); 
    lcd.clear();
    printLeft("IMAGE 2 ERROR!", 1);
    delay(2000);
    screenNeedsUpdate = true;
    return; 
  }
  
  Serial.print("Creating model... ");
  p = finger.createModel();
  if (p == FINGERPRINT_OK) {
    p = finger.storeModel(id);
    if (p == FINGERPRINT_OK) {
      Serial.println("[OK] Fingerprint Stored Successfully!");
      lcd.clear(); 
      printLeft("ENROLL SUCCESS!", 0);
      printLeft("Slot: " + String(id), 1);
      flashLED(2, 200);
      delay(2000);
    } else {
      Serial.println("[ERR] Failed to store model.");
      lcd.clear();
      printLeft("STORE ERROR!", 1);
      delay(2000);
    }
  } else {
    Serial.println("[ERR] Fingerprints did not match.");
    lcd.clear(); 
    printLeft("NO MATCH!", 1);
    printLeft("TRY AGAIN", 2);
    delay(2000);
  }
  screenNeedsUpdate = true;
}

// --- 11. ATTENDANCE & WEB SYNC ---
void sendDataToWeb(String student_no, DateTime now) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http; 
    http.begin(serverURL); 
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    
    char tBuf[] = "YYYY-MM-DD hh:mm:ss"; 
    String timeStr = now.toString(tBuf); 
    timeStr.replace(" ", "%20"); 
    
    String postData = "student_no=" + student_no + "&timestamp=" + timeStr + "&device_name=UoP_Scanner_1";
    int code = http.POST(postData); 
    
    if(code > 0) {
      Serial.println("[SYNC] Server Accepted. Response Code: " + String(code));
    } else {
      Serial.println("[SYNC] Server Rejected: " + http.errorToString(code));
    }
    http.end();
  } else {
    Serial.println("[SYNC] WiFi Offline. Saved to SD Card Only.");
  }
}

void handleAttendance(DateTime now) {
  if (screenNeedsUpdate) { 
    lcd.clear(); 
    printLeft(">> ATTENDANCE MODE", 0); 
    printLeft("SCAN SENSOR NOW", 1); 
    printLeft("[A] to Exit", 3);
    screenNeedsUpdate = false; 
  }
  
  if (digitalRead(FINGER_VOUT)) { 
    printLeft("Scanning Finger...", 2);
    int fID = getFingerID(); 
    if (fID != -1) {
      logAndSync("FINGER_" + String(fID), now);
    } else {
      printLeft("Unknown/Bad Scan!", 2);
      flashLED(1, 400); // Visual hardware error confirmation
      delay(1500);
      screenNeedsUpdate = true;
    }
  }

  useRFID();
  if (mfrc522.PICC_IsNewCardPresent() && mfrc522.PICC_ReadCardSerial()) {
    String uid = ""; 
    for (byte i = 0; i < mfrc522.uid.size; i++) {
      uid += String(mfrc522.uid.uidByte[i], HEX);
    }
    silenceBus(); 
    logAndSync("CARD_" + uid, now);
  }
  silenceBus();
}

void logAndSync(String id, DateTime now) {
  if (id == lastScannedID && (millis() - lastScanTime < COOLDOWN_MS)) {
    Serial.println("[SYSTEM] Cooldown active for: " + id);
    printLeft("ALREADY LOGGED!", 2);
    flashLED(1, 100);
    delay(1000);
    screenNeedsUpdate = true;
    return;
  }
  lastScannedID = id;
  lastScanTime = millis();

  Serial.println("\n[SYSTEM] Logging ID: " + id);
  useSD(); 
  File f = SD.open("/logs.csv", FILE_APPEND);
  if (f) { 
    char buf[] = "YYYY-MM-DD,hh:mm:ss,"; 
    f.print(now.toString(buf)); 
    f.println(id); 
    f.close(); 
  }
  silenceBus();
  
  sendDataToWeb(id, now);
  
  printLeft("LOGGED: " + id.substring(0,10), 2); 
  flashLED(2, 200); 
  delay(1500); 
  screenNeedsUpdate = true;
}

// --- 12. UTILITIES & UI (DIGITAL TWIN MIRROR) ---
void printLeft(String t, int r) {
  lcd.setCursor(0, r); 
  String out = t.substring(0, 20); 
  
  lcdLine[r] = out; // Save exactly what is written for the Web Virtual LCD
  lcd.print(out);
  
  for (int i = out.length(); i < 20; i++) {
    lcd.print(" ");
    lcdLine[r] += " "; // Fill the rest with spaces to clear old text on Web LCD
  }
}

int getFingerID() {
  uint8_t p = finger.getImage(); 
  if (p != FINGERPRINT_OK) return -1;
  p = finger.image2Tz(); 
  if (p != FINGERPRINT_OK) return -1;
  p = finger.fingerFastSearch(); 
  if (p != FINGERPRINT_OK) return -1;
  return finger.fingerID;
}

void handleStandby(DateTime now) {
  if (screenNeedsUpdate) { 
    lcd.clear(); 
    printLeft("--- SYSTEM LOCKED ---", 0); 
    printLeft("TAP TO START", 3); 
    screenNeedsUpdate = false; 
  }
  
  if (millis() - lastClockUpdate > 1000) {
    char dBuf[] = "DATE: YYYY-MM-DD"; 
    char tBuf[] = "TIME: hh:mm:ss";
    printLeft(now.toString(dBuf), 1); 
    printLeft(now.toString(tBuf), 2); 
    lastClockUpdate = millis();
  }

  bool unlock = false;
  if (digitalRead(FINGER_VOUT)) { 
    if(getFingerID() != -1) unlock = true; 
  }
  
  useRFID(); 
  if (mfrc522.PICC_IsNewCardPresent() && mfrc522.PICC_ReadCardSerial()) {
    unlock = true;
  }
  silenceBus();

  if (unlock) { 
    Serial.println("[AUTH] System Unlocked.");
    flashLED(1, 300); 
    currentState = MAIN_MENU; 
    menuIdx = 0; 
    screenNeedsUpdate = true; 
  }
}

void handleMainMenu(char key) {
  String items[] = {"Take Attendance", "Enroll Finger", "Exit"};
  if (screenNeedsUpdate) {
    printLeft("- MAIN MENU -", 0);
    for (int i=0; i<3; i++) { 
      lcd.setCursor(0, i+1); 
      lcd.print(i == menuIdx ? "> " : "  "); 
      
      // Update physical and web LCD line manually for this complex row
      String rowText = (i == menuIdx ? "> " : "  ") + items[i];
      lcdLine[i+1] = rowText;
      lcd.print(items[i]);
      for (int j = rowText.length(); j < 20; j++) {
        lcd.print(" ");
        lcdLine[i+1] += " ";
      }
    }
    screenNeedsUpdate = false;
  }
  
  // Navigation mapping: '2' for up, '8' for down, '#' to confirm
  if (key == '2') { menuIdx = (menuIdx - 1 + 3) % 3; screenNeedsUpdate = true; }
  if (key == '8') { menuIdx = (menuIdx + 1) % 3; screenNeedsUpdate = true; }
  if (key == '#') {
    if (menuIdx == 0) currentState = ATTENDANCE_MODE;
    if (menuIdx == 1) currentState = ENROLL_MODE;
    if (menuIdx == 2) currentState = STANDBY;
    screenNeedsUpdate = true;
  }
}

void handleEnrollment(char key) {
  static String enrollBuffer = "";
  
  if (screenNeedsUpdate) {
    lcd.clear(); 
    printLeft(">> LOCAL ENROLL", 0); 
    printLeft("Enter Slot (1-127):", 1); 
    printLeft(enrollBuffer, 2);
    printLeft("[A] Cancel  [#] OK", 3);
    screenNeedsUpdate = false;
  }
  
  if (key) {
    if (key >= '0' && key <= '9') {
      if (enrollBuffer.length() < 3) {
        enrollBuffer += key;
        screenNeedsUpdate = true;
      }
    } else if (key == '*') {
      if (enrollBuffer.length() > 0) {
        enrollBuffer.remove(enrollBuffer.length() - 1);
        screenNeedsUpdate = true;
      } else {
        currentState = MAIN_MENU;
        screenNeedsUpdate = true;
      }
    } else if (key == 'A') {
      currentState = MAIN_MENU;
      enrollBuffer = "";
      screenNeedsUpdate = true;
    } else if (key == '#') {
      int targetID = enrollBuffer.toInt();
      if (targetID >= 1 && targetID <= 127) {
        lcd.clear();
        printLeft("Enrolling Slot #" + String(targetID), 0);
        printLeft("Awaiting Finger...", 1);
        delay(1000);
        enrollFingerprint(targetID);
        enrollBuffer = "";
      } else {
        lcd.clear();
        printLeft("Invalid Slot!", 1);
        printLeft("Use 1 - 127", 2);
        delay(1500);
      }
      screenNeedsUpdate = true;
    }
  }
}

void flashLED(int t, int ms) { 
  for(int i=0; i<t; i++) { 
    digitalWrite(BLUE_LED, HIGH); 
    delay(ms); 
    digitalWrite(BLUE_LED, LOW); 
    if(t > 1) delay(ms); 
  } 
}
