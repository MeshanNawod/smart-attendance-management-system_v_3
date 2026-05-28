// MICROSD CARD TEST — Serial Only
#include <SPI.h>
#include <SD.h>

#define SD_CS_PIN 4

void setup() {
  Serial.begin(115200);
  delay(2000);
  Serial.println("\n=== MICROSD CARD TEST ===");
  Serial.print("SD_CS_PIN = D");
  Serial.println(SD_CS_PIN);

  SPI.begin(18, 19, 23, -1);
  SPI.setFrequency(1000000); // 1 MHz

  // Test basic SPI bus
  pinMode(SD_CS_PIN, OUTPUT);
  digitalWrite(SD_CS_PIN, HIGH);
  Serial.println("SPI initialized. Attempting SD.begin()...\n");

  if (!SD.begin(SD_CS_PIN)) {
    Serial.println("[FAIL] SD.begin(SD_CS_PIN) returned false");
    Serial.println("\nPossible causes:");
    Serial.println("  1. No SD card inserted in the slot");
    Serial.println("  2. SD card not formatted as FAT32");
    Serial.println("  3. MicroSD card adapter VCC needs 5V (not 3.3V)");
    Serial.println("     → Common yellow adapters need VCC=5V, not 3.3V!");
    Serial.println("  4. Wiring: CS=D4, SCK=D18, MOSI=D23, MISO=D19");
    Serial.println("  5. ESP32 3.3V rail overloaded — disconnect other modules");
    while (1) { delay(1000); }
  }

  Serial.println("[OK] SD.begin() SUCCESS");

  uint64_t cardSize = SD.cardSize() / (1024 * 1024);
  Serial.print("Card size: ");
  Serial.print(cardSize);
  Serial.println(" MB");

  uint64_t totalBytes = SD.totalBytes();
  uint64_t usedBytes = SD.usedBytes();
  Serial.print("Total space: ");
  Serial.print(totalBytes / (1024 * 1024));
  Serial.println(" MB");
  Serial.print("Used space: ");
  Serial.print(usedBytes / (1024 * 1024));
  Serial.println(" MB");

  // Write test file
  File f = SD.open("/test_sd.txt", FILE_WRITE);
  if (f) {
    f.println("Sentinel Swarm SD Test - OK");
    f.println("Timestamp: ");
    f.println(millis());
    f.close();
    Serial.println("[OK] Created /test_sd.txt");
  } else {
    Serial.println("[FAIL] Could not create /test_sd.txt");
  }

  // Read back
  f = SD.open("/test_sd.txt", FILE_READ);
  if (f) {
    Serial.println("\n--- File contents ---");
    while (f.available()) {
      Serial.write(f.read());
    }
    Serial.println("--- End ---\n");
    f.close();
    Serial.println("[OK] Read /test_sd.txt successfully");
  } else {
    Serial.println("[FAIL] Could not read /test_sd.txt");
  }

  Serial.println("\n=== SD TEST COMPLETE ===");
}

void loop() {
  delay(5000);
  Serial.println("SD Test Complete — Reset to re-run");
}
