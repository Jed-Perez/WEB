// ESP32 + DHT22 example. Requires Adafruit DHT sensor library and Unified Sensor.
// Configure config.h from config.example.h. Physical hardware has not been validated.
#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>
#include "config.h"

DHT sensor(DHT_PIN, DHT22);
unsigned long lastAttempt = 0;
String bootId;
uint32_t sequenceNumber = 0;
String pendingPayload;

void setup() {
  Serial.begin(115200);
  sensor.begin();
  bootId = String((uint32_t)(ESP.getEfuseMac() >> 32), HEX) + String((uint32_t)ESP.getEfuseMac(), HEX) + "-" + String(esp_random(), HEX);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  // Never print the Wi-Fi password or device key.
}

void loop() {
  if (millis() - lastAttempt < 30000) return;
  lastAttempt = millis();
  if (WiFi.status() != WL_CONNECTED) {
    WiFi.reconnect();
    Serial.println("Wi-Fi unavailable; waiting for connection.");
    return;
  }
  if (pendingPayload.isEmpty()) {
    float temperature = sensor.readTemperature();
    if (isnan(temperature) || temperature < -55 || temperature > 125) {
      Serial.println("Sensor read failed; no sample sent.");
      return;
    }
    String sampleId = bootId + "-" + String(++sequenceNumber);
    pendingPayload = "{\"sample_id\":\"" + sampleId + "\",\"temperature\":" + String(temperature, 2) + "}";
  }
  HTTPClient http;
  http.setTimeout(10000);
  if (!http.begin(RESORT_ENDPOINT)) return;
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Authorization", String("Bearer ") + RESORT_DEVICE_KEY);
  int code = http.POST(pendingPayload);
  Serial.printf("Resort360 HTTP status: %d\n", code);
  // Retain identical sample ID/value on transient failures for safe retry.
  if (code == 200 || code == 201) pendingPayload = "";
  else if (code == 409 || code == 422) {
    Serial.println("Sample rejected; inspect sensor or configuration.");
    pendingPayload = "";
  } else if (code == 401) Serial.println("Device key disabled or incorrect; ask administrator.");
  http.end();
}
