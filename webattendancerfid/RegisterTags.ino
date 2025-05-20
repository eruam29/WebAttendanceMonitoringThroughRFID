#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecure.h>
#include <SPI.h>
    #include <MFRC522.h>

#define SS_PIN D2  // RFID SS pin
#define RST_PIN D1 // RFID RST pin
#define LED_PIN D4 // LED pin
#define BUZZER_PIN D0 // Buzzer pin
#define WIFI_SSID "yaj"
#define WIFI_PASS "passwords"
#define SERVER_URL "https://techtracknet.site/register_tag.php"

WiFiClientSecure client;
MFRC522 mfrc522(SS_PIN, RST_PIN);

void setup() {
    Serial.begin(115200);
    SPI.begin();
    mfrc522.PCD_Init();

    pinMode(LED_PIN, OUTPUT); // Set LED pin as output
    pinMode(BUZZER_PIN, OUTPUT); // Set Buzzer pin as output
    digitalWrite(LED_PIN, LOW); // Ensure LED is off initially
    digitalWrite(BUZZER_PIN, LOW); // Ensure Buzzer is off initially

    WiFi.begin(WIFI_SSID, WIFI_PASS);
    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
        Serial.print(".");
    }
    Serial.println("\nWiFi Connected!");
    client.setInsecure();
}

void loop() {
    if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) {
        return;
    }

    String rfidTag = "";
    for (byte i = 0; i < mfrc522.uid.size; i++) {
        rfidTag += String(mfrc522.uid.uidByte[i], DEC);
    }

    Serial.print("Scanned RFID: ");
    Serial.println(rfidTag);

    if (WiFi.status() == WL_CONNECTED) {
        HTTPClient https;
        https.begin(client, SERVER_URL);
        https.addHeader("Content-Type", "application/x-www-form-urlencoded");

        String postData = "rfid_tag=" + rfidTag;
        int httpResponseCode = https.POST(postData);

        if (httpResponseCode > 0) {
            Serial.print("Server Response: ");
            Serial.println(https.getString());

         // Turn on the LED and beep the buzzer when a response is received
            digitalWrite(LED_PIN, HIGH);
            digitalWrite(BUZZER_PIN, HIGH); // Turn on the buzzer
            delay(500); // Beep for 500ms
            digitalWrite(BUZZER_PIN, LOW); // Turn off the buzzer
            delay(500); // Keep the LED on for an additional 500ms
            digitalWrite(LED_PIN, LOW); // Turn off the LED
        } else {
            Serial.print("Error sending data. Code: ");
            Serial.println(httpResponseCode);
        }

        https.end();
    }

    delay(2000);
}