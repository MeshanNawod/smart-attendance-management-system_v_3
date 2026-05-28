// MFRC522 — Full library init with diagnostics
#include <SPI.h>
#include <MFRC522.h>

#define SS_PIN  15
#define RST_PIN 22

MFRC522 mfrc522(SS_PIN, RST_PIN);

void setup() {
  Serial.begin(115200);
  delay(2000);
  Serial.println("\n=== MFRC522 FULL LIBRARY TEST ===");

  // Full hardware reset sequence
  pinMode(RST_PIN, OUTPUT);
  digitalWrite(RST_PIN, LOW);
  delay(100);
  digitalWrite(RST_PIN, HIGH);
  delay(100);

  // Explicit SPI pins
  SPI.begin(18, 19, 23, -1);

  // Full library init (does software reset + config)
  mfrc522.PCD_Init();
  mfrc522.PCD_AntennaOn();

  // Read version via library
  byte ver = mfrc522.PCD_ReadRegister(MFRC522::VersionReg);
  Serial.print("VersionReg = 0x");
  Serial.println(ver, HEX);

  if (ver == 0x12 || ver == 0x92 || ver == 0x90 || ver == 0x88) {
    Serial.println("MFRC522 ALIVE! (genuine NXP or FM17550 clone)");
    Serial.println("\nNow tap a card to read UID. Loop running...\n");
  } else if (ver == 0xB7) {
    Serial.println("Returned command byte — SPI echo. MOSI possibly shorted to MISO?");
    Serial.println("Check: D23 (MOSI) and D19 (MISO) are not bridged on breadboard.");
  } else if (ver != 0x00 && ver != 0xFF) {
    Serial.println("MFRC522 DETECTED — likely a Chinese clone chip (non-standard version).");
    Serial.print("VersionReg = 0x");
    Serial.println(ver, HEX);
    Serial.println("SPI bus OK. Proceeding to card scan...\n");
  } else {
    Serial.println("MFRC522 NOT RESPONDING.");
    Serial.print("VersionReg = 0x");
    Serial.println(ver, HEX);
    while (1);
  }
}

void loop() {
  if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) {
    delay(200);
    return;
  }

  String uid = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    if (mfrc522.uid.uidByte[i] < 0x10) uid += "0";
    uid += String(mfrc522.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();

  Serial.print("CARD DETECTED | UID: ");
  Serial.println(uid);

  mfrc522.PICC_HaltA();
  delay(3000);
}
