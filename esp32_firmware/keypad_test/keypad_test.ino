/*
 * Keypad 4x4 Matrix Test — standalone sketch
 * Manual column-scan (no Keypad library required).
 * Prints pressed key to Serial Monitor.
 *
 * Wiring (ESP32 DevKit):
 *   Row pins: D13, D12, D14, D5   (OUTPUT, normally HIGH)
 *   Col pins: D26, D25, D33, D32   (INPUT_PULLUP)
 *   Matrix layout:
 *         Col26 Col25 Col33 Col32
 *   Row13   1     2     3     A
 *   Row12   4     5     6     B
 *   Row14   7     8     9     C
 *   Row27   *     0     #     D
 */

const byte KEYPAD_ROWS = 4;
const byte KEYPAD_COLS = 4;
const byte KP_ROW[KEYPAD_ROWS] = {13, 12, 14, 5};
const byte KP_COL[KEYPAD_COLS] = {26, 25, 33, 32};
const char KP_MAP[KEYPAD_ROWS][KEYPAD_COLS] = {
  {'1','2','3','A'},
  {'4','5','6','B'},
  {'7','8','9','C'},
  {'*','0','#','D'}
};

char lastKey = 0;
unsigned long lastKeyTime = 0;

void setup() {
  Serial.begin(115200);
  delay(500);
  Serial.println();
  Serial.println("==================================");
  Serial.println("    4x4 MATRIX KEYPAD TEST        ");
  Serial.println("==================================");
  Serial.println("Wiring:");
  Serial.println("  Rows (OUTPUT): 13, 12, 14, 27");
  Serial.println("  Cols (INPUT_PULLUP): 26, 25, 33, 32");
  Serial.println();

  // Init rows as OUTPUT, normally HIGH
  for (int r = 0; r < KEYPAD_ROWS; r++) {
    pinMode(KP_ROW[r], OUTPUT);
    digitalWrite(KP_ROW[r], HIGH);
  }

  // Init cols as INPUT_PULLUP
  for (int c = 0; c < KEYPAD_COLS; c++) {
    pinMode(KP_COL[c], INPUT_PULLUP);
  }

  // Verify pin states
  Serial.println("--- Pin State Check ---");
  for (int r = 0; r < KEYPAD_ROWS; r++) {
    Serial.print("  Row D");
    Serial.print(KP_ROW[r]);
    Serial.print(" = ");
    Serial.println(digitalRead(KP_ROW[r]));
  }
  for (int c = 0; c < KEYPAD_COLS; c++) {
    Serial.print("  Col D");
    Serial.print(KP_COL[c]);
    Serial.print(" = ");
    Serial.println(digitalRead(KP_COL[c]));
  }
  Serial.println("------------------------");
  Serial.println("Ready! Press any key on the 4x4 matrix.");
  Serial.println();
}

void loop() {
  char k = scanKeypad();

  // Debounce / repeat filter
  if (k && k != lastKey) {
    lastKey = k;
    lastKeyTime = millis();
    Serial.print("[KEY] Pressed: ");
    Serial.print(k);
    Serial.print("  (char: '");
    Serial.print(k);
    Serial.println("')");
  }

  // Reset lastKey when released (200ms debounce)
  if (!k && millis() - lastKeyTime > 200) {
    lastKey = 0;
  }

  delay(10);  // avoid busy-wait
}

char scanKeypad() {
  // Drive one row LOW at a time, read columns
  for (int r = 0; r < KEYPAD_ROWS; r++) {
    digitalWrite(KP_ROW[r], LOW);
    delay(1);  // allow pin to settle
    for (int c = 0; c < KEYPAD_COLS; c++) {
      if (digitalRead(KP_COL[c]) == LOW) {
        delay(1);  // debounce
        if (digitalRead(KP_COL[c]) == LOW) {
          digitalWrite(KP_ROW[r], HIGH);  // restore row
          return KP_MAP[r][c];
        }
      }
    }
    digitalWrite(KP_ROW[r], HIGH);  // restore row
  }
  return 0;  // no key pressed
}
