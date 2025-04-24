#include <WiFi.h>
#include <WiFiMulti.h>
#include <WebServer.h>
#include <ESP32Servo.h>

const char* ssid = "";
const char* pass = "";

WiFiMulti wifiMulti;
WebServer server(80);

Servo myServo;
const int servoPin = 4;    // サーボ制御ピン
const int steps = 5;
int lastPulse = -1;

bool tryNormalConnect(unsigned long t) {
  WiFi.begin(ssid, pass);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < t) delay(500);
  return WiFi.status() == WL_CONNECTED;
}

bool tryMultiConnect(unsigned long t) {
  wifiMulti.addAP(ssid, pass);
  unsigned long start = millis();
  while (millis() - start < t) {
    if (wifiMulti.run() == WL_CONNECTED) return true;
    delay(500);
  }
  return false;
}

void trySmartConfig() {
  WiFi.beginSmartConfig();
  while (!WiFi.smartConfigDone()) delay(500);
}

void connectWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.disconnect(true);
  delay(500);
  if (!tryNormalConnect(5000) && !tryMultiConnect(5000)) {
    trySmartConfig();
  }
}

void setup() {
  Serial.begin(115200);
  connectWiFi();
  Serial.print("IP: "); Serial.println(WiFi.localIP());

  // サーボ初期化 (1000us〜2000us)
  myServo.setPeriodHertz(50);
  myServo.attach(servoPin, 1000, 2000);

  server.on("/", []() {
    server.send(200, "text/plain", "GET /0 〜 /4 で5段階制御。ステップ0→0°(1000us),4→180°(2000us)");
  });

  server.onNotFound([]() {
    String uri = server.uri();
    if (uri.length() > 1) {
      int step = uri.substring(1).toInt();
      if (step >= 0 && step < steps) {
        int pulse = map(step, 0, steps - 1, 1000, 2000);
        if (pulse != lastPulse) {
          myServo.writeMicroseconds(pulse);
          lastPulse = pulse;
        }
        int angle = step * (180 / (steps - 1));
        server.send(200, "text/plain", "ステップ " + String(step) + " → 角度 " + String(angle) + "° (" + String(pulse) + "us)");
      } else {
        server.send(400, "text/plain", "ステップは0〜4で指定してください");
      }
    } else {
      server.send(404, "text/plain", "無効なリクエスト");
    }
  });

  server.begin();
}

void loop() {
  server.handleClient();
}
