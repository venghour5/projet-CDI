#include <WiFi.h>
#include <WebServer.h>

#define LED_PIN 2

const char* ssid = "WIFI_FOR_ESP32";
const char* password = "WIFI_FOR_ESP32";

WebServer server(80);

void allumeLED() {
  digitalWrite(LED_PIN, HIGH);
  server.send(200, "text/plain", "LED allumée."); // 200 = OK
}

void eteintLED() {
  digitalWrite(LED_PIN, LOW);
    server.send(200, "text/plain", "LED éteinte."); // 200 = OK
}

void setup() {
  Serial.begin(115200);

  pinMode(LED_PIN, OUTPUT);

  WiFi.begin(ssid, password);
  Serial.println("Connecting");

  // Pendant la connexion au WiFi
  while(WiFi.status() != WL_CONNECTED){
    delay(500);
    Serial.print(".");
  }

  Serial.print("Connecté.");
  Serial.println(WiFi.localIP());

  // Mise en place du serveur HTTP
  server.on("/on", HTTP_GET, allumeLED); // On associe certaines fonctions à certains appels
  server.on("/off", HTTP_GET, eteintLED);
  server.begin();

  Serial.println("Serveur démarré.");
}

void loop() {
  server.handleClient();
}