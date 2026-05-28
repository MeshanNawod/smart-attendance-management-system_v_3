// ============================================================
// DS3231 RTC CLOCK TEST
// Tests: RTC initialization, I2C communication, LCD display
// Displays live date/time on LCD, updates every second
// ============================================================
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <RTClib.h>

LiquidCrystal_I2C lcd(0x27, 20, 4);
RTC_DS3231 rtc;

char daysOfWeek[7][4] = {"Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"};

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("\n=== DS3231 RTC CLOCK TEST ===");

  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();
  lcd.setCursor(0, 0);
  lcd.print(" RTC Clock Test   ");

  if (!rtc.begin()) {
    lcd.setCursor(0, 1);
    lcd.print(" RTC NOT FOUND!   ");
    lcd.setCursor(0, 2);
    lcd.print(" Check I2C wiring  ");
    Serial.println("[FAIL] DS3231 not detected on I2C");
    while (1) { delay(1000); }
  }

  if (rtc.lostPower()) {
    lcd.setCursor(0, 1);
    lcd.print(" RTC lost power   ");
    lcd.setCursor(0, 2);
    lcd.print(" Setting compile  ");
    lcd.setCursor(0, 3);
    lcd.print(" time...          ");
    rtc.adjust(DateTime(F(__DATE__), F(__TIME__)));
    delay(2000);
  }

  Serial.println("[OK] DS3231 RTC initialized");
  lcd.clear();
}

void loop() {
  DateTime now = rtc.now();

  lcd.setCursor(0, 0);
  lcd.print("Date: ");
  lcd.print(now.year(), DEC);
  lcd.print("/");
  lcd.print(now.month(), DEC);
  lcd.print("/");
  lcd.print(now.day(), DEC);

  lcd.setCursor(0, 1);
  lcd.print("Time: ");
  lcd.print(now.hour(), DEC);
  lcd.print(":");
  if (now.minute() < 10) lcd.print("0");
  lcd.print(now.minute(), DEC);
  lcd.print(":");
  if (now.second() < 10) lcd.print("0");
  lcd.print(now.second(), DEC);

  lcd.setCursor(0, 2);
  lcd.print("Day:  ");
  lcd.print(daysOfWeek[now.dayOfTheWeek()]);

  lcd.setCursor(0, 3);
  lcd.print("Temp: ");
  lcd.print(rtc.getTemperature());
  lcd.print(" C");

  Serial.print("[RTC] ");
  Serial.print(now.year()); Serial.print("-");
  Serial.print(now.month()); Serial.print("-");
  Serial.print(now.day()); Serial.print(" ");
  Serial.print(now.hour()); Serial.print(":");
  Serial.print(now.minute()); Serial.print(":");
  Serial.print(now.second());
  Serial.print(" | Temp: ");
  Serial.print(rtc.getTemperature());
  Serial.println(" C");

  delay(1000);
}
