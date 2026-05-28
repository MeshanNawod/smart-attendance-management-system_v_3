/*
 * I2C LCD 16x4 Display Test — standalone sketch
 * Mirrors the lcdWrite()/lcdClear() functions from esp32_firmware.ino
 * so you can validate display behaviour before integrating back.
 *
 * Wiring (ESP32 DevKit):
 *   LCD SDA  →  GPIO 21
 *   LCD SCL  →  GPIO 22
 *   LCD VCC  →  5V
 *   LCD GND  →  GND
 *
 * Common I2C addresses: 0x27, 0x3F, 0x3E, 0x23, 0x21, 0x25, 0x26, 0x20
 */

#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// I2C pins (ESP32 standard)
const int PIN_SDA = 21;
const int PIN_SCL = 22;

// Display geometry — 16 columns × 4 rows
const int LCD_COLS = 16;
const int LCD_ROWS = 4;

// Addresses to probe (most common first)
const byte I2C_ADDRS[] = {0x27, 0x3F, 0x3E, 0x23, 0x21, 0x25, 0x26, 0x20};
const int NUM_ADDRS = sizeof(I2C_ADDRS) / sizeof(I2C_ADDRS[0]);

LiquidCrystal_I2C* lcd = nullptr;
byte foundAddr = 0;

// ─── Mirrored from esp32_firmware.ino ─────────────────────────────────
String lcdLine[4] = {"", "", "", ""};
bool lcdOK = false;

void lcdWrite(int row, String text) {
  if (row < 0 || row > 3) return;
  // Pad or truncate to exactly LCD_COLS (16)
  while (text.length() < LCD_COLS) text += ' ';
  if (text.length() > LCD_COLS) text = text.substring(0, LCD_COLS);
  lcdLine[row] = text;

  if (lcdOK) {
    lcd->setCursor(0, row);
    lcd->print(text);
  }

  Serial.println("[LCD] |" + text + "|");
}

void lcdClear() {
  String blank = "";
  while (blank.length() < LCD_COLS) blank += ' ';
  for (int i = 0; i < 4; i++) {
    lcdLine[i] = blank;
    if (lcdOK) {
      lcd->setCursor(0, i);
      lcd->print(blank);
    }
  }
  Serial.println("[LCD] |------ CLEARED ------|");
}
// ─── End firmware-mirrored section ────────────────────────────────────

// ─── I2C Scanner ──────────────────────────────────────────────────────
byte scanForLCD() {
  Serial.println("");
  Serial.println("--- I2C LCD Scanner ---");
  Serial.println("Scanning addresses on SDA=" + String(PIN_SDA) + " SCL=" + String(PIN_SCL));
  Serial.println("");

  byte devices[16];
  int found = 0;

  for (byte addr = 1; addr < 127; addr++) {
    Wire.beginTransmission(addr);
    if (Wire.endTransmission() == 0) {
      if (found < 16) devices[found++] = addr;
      Serial.print("  [I2C] Found device at 0x");
      Serial.println(addr, HEX);
    }
    delay(5);
  }

  Serial.println("");
  Serial.print("Total I2C devices found: ");
  Serial.println(found);

  for (int i = 0; i < NUM_ADDRS; i++) {
    for (int j = 0; j < found; j++) {
      if (devices[j] == I2C_ADDRS[i]) {
        Serial.println("  *** LCD candidate at 0x" + String(I2C_ADDRS[i], HEX) + " ***");
        return I2C_ADDRS[i];
      }
    }
  }

  Serial.println("");
  Serial.println("No LCD at known addresses via scan. Trying direct probe...");
  for (int i = 0; i < NUM_ADDRS; i++) {
    Wire.beginTransmission(I2C_ADDRS[i]);
    if (Wire.endTransmission() == 0) {
      Serial.println("  Direct probe: 0x" + String(I2C_ADDRS[i], HEX) + " ACK!");
      return I2C_ADDRS[i];
    }
  }

  return 0;
}

// ─── Main test sequence using firmware-style lcdWrite/lcdClear ────────
unsigned long testStart = 0;
int testStep = 0;

void runTestSequence() {
  switch (testStep) {
    case 0:
      Serial.println("");
      Serial.println("--- Test 1: lcdWrite each row ---");
      lcdClear();
      lcdWrite(0, "Row0:HelloWorld!");
      lcdWrite(1, "1234567890abcdef");
      lcdWrite(2, "ABCDEFGHIJklmnop");
      lcdWrite(3, " -=!@#$%^&*()_+ ");
      Serial.println("  Pattern 1 done");
      testStart = millis();
      testStep++;
      break;

    case 1:
      if (millis() - testStart < 2000) break;
      Serial.println("");
      Serial.println("--- Test 2: 16-col ruler + buffer check ---");
      lcdClear();
      lcdWrite(0, "1234567890123456");
      lcdWrite(1, "16-col ruler");
      lcdWrite(3, "SDA21 SCL22");
      Serial.print("  Buffer[0]=\""); Serial.print(lcdLine[0]); Serial.println("\"");
      Serial.print("  Buffer[1]=\""); Serial.print(lcdLine[1]); Serial.println("\"");
      Serial.println("  Pattern 2 done");
      testStart = millis();
      testStep++;
      break;

    case 2:
      if (millis() - testStart < 2000) break;
      Serial.println("");
      Serial.println("--- Test 3: lcdWrite short text (auto-pad) ---");
      lcdClear();
      lcdWrite(0, "Short");
      lcdWrite(1, "Pad to 16");
      Serial.print("  Buffer[0] len="); Serial.println(lcdLine[0].length());
      Serial.print("  Buffer[0]=\""); Serial.print(lcdLine[0]); Serial.println("\"");
      Serial.println("  Pattern 3 done");
      testStart = millis();
      testStep++;
      break;

    case 3:
      if (millis() - testStart < 2000) break;
      Serial.println("");
      Serial.println("--- Test 4: lcdWrite long text (truncate) ---");
      lcdClear();
      lcdWrite(0, "This string is way too long for 16 cols");
      Serial.print("  Buffer[0]=\""); Serial.print(lcdLine[0]); Serial.println("\"");
      Serial.println("  Pattern 4 done");
      testStart = millis();
      testStep++;
      break;

    case 4:
      if (millis() - testStart < 2000) break;
      Serial.println("");
      Serial.println("--- Test 5: lcdClear + rewrite ---");
      lcdWrite(0, "Before clear");
      delay(500);
      lcdClear();
      lcdWrite(2, "After clear");
      Serial.println("  Pattern 5 done");
      testStart = millis();
      testStep++;
      break;

    case 5:
      if (millis() - testStart < 2000) break;
      Serial.println("");
      Serial.println("--- Test 6: out-of-range (ignore) ---");
      lcdWrite(4, "NO");
      lcdWrite(-1, "NO");
      Serial.println("  Pattern 6 done (no crash)");
      testStart = millis();
      testStep++;
      break;

    case 6:
      if (millis() - testStart < 1500) break;
      Serial.println("");
      Serial.println("--- Test 7: scrolling counter ---");
      testStart = millis();
      testStep++;
      break;

    case 7: {
      int n = (millis() - testStart) / 400;
      if (n <= 16) {
        char buf[17];
        snprintf(buf, sizeof(buf), "Cnt: %02d / 16", n);
        lcdWrite(1, String(buf));
        break;
      }
      Serial.println("  Pattern 7 done");
      testStart = millis();
      testStep++;
      break;
    }

    case 8:
      if (millis() - testStart < 1000) break;
      Serial.println("");
      Serial.println("--- Test 8: Backlight toggle ---");
      lcdClear();
      lcdWrite(0, "Backlight test");
      testStart = millis();
      testStep++;
      break;

    case 9: {
      unsigned long t = millis() - testStart;
      int cycle = t / 1200;
      if (cycle < 3) {
        bool on = (t % 1200) < 600;
        if (on) { lcd->backlight(); Serial.println("  ON"); }
        else    { lcd->noBacklight(); Serial.println("  OFF"); }
        delay(10);
        break;
      }
      lcd->backlight();
      Serial.println("  Pattern 8 done");
      testStart = millis();
      testStep++;
      break;
    }

    case 10:
      if (millis() - testStart < 1000) break;
      Serial.println("");
      Serial.println("--- ALL TESTS COMPLETE ---");
      lcdClear();
      lcdWrite(0, "=== PASS ===");
      lcdWrite(1, "LCD 16x4 OK!");
      lcdWrite(2, "Addr: 0x" + String(foundAddr, HEX));
      Serial.println("LCD found at 0x" + String(foundAddr, HEX) + " working.");
      Serial.println("Clock demo loop (updates line 0 every 5s)...");
      testStep++;
      break;

    default:
      break;
  }
}

// ─── Clock display (mirrors firmware loop's 5s clock update) ──────────
void updateClockDisplay() {
  static unsigned long lastClock = 0;
  if (millis() - lastClock < 5000) return;
  lastClock = millis();

  unsigned long sec = millis() / 1000;
  unsigned long hh = sec / 3600;
  unsigned long mm = (sec % 3600) / 60;
  unsigned long ss = sec % 60;
  char buf[17];
  snprintf(buf, sizeof(buf), "Up %02lu:%02lu:%02lu", hh, mm, ss);
  lcdWrite(0, String(buf));
}

// ─── Setup ────────────────────────────────────────────────────────────
void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("");
  Serial.println("=========================================");
  Serial.println("    I2C LCD 16x4 DISPLAY TEST            ");
  Serial.println("  (mirrors esp32_firmware.ino display    ");
  Serial.println("   functions: lcdWrite, lcdClear, etc.)  ");
  Serial.println("=========================================");
  Serial.println("Pin Mapping (ESP32 DevKit):");
  Serial.println("  PIN_SDA = 21  (LCD SDA)");
  Serial.println("  PIN_SCL = 22  (LCD SCL)");
  Serial.println("  VCC     ->  5V");
  Serial.println("  GND     ->  GND");
  Serial.println("");

  Wire.begin(PIN_SDA, PIN_SCL);
  delay(100);

  foundAddr = scanForLCD();

  if (foundAddr == 0) {
    Serial.println("");
    Serial.println("!!! NO LCD DETECTED !!!");
    Serial.println("Check wiring:");
    Serial.println("  - 5V power to LCD");
    Serial.println("  - SDA -> GPIO 21");
    Serial.println("  - SCL -> GPIO 22");
    Serial.println("  - GND common");
    Serial.println("  - I2C backpack seated");
    return;
  }

  Serial.print("Init LCD at 0x");
  Serial.print(foundAddr, HEX);
  Serial.println("...");

  lcd = new LiquidCrystal_I2C(foundAddr, LCD_COLS, LCD_ROWS);
  lcd->init();
  lcd->backlight();
  lcdOK = true;

  Serial.println("LCD ready, starting tests...");
  testStart = millis();
}

// ─── Loop ─────────────────────────────────────────────────────────────
void loop() {
  if (lcdOK && foundAddr) {
    if (testStep <= 10) {
      runTestSequence();
    } else {
      updateClockDisplay();
    }
  }
  delay(20);
}
