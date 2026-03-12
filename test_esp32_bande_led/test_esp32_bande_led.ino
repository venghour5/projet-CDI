#include <WiFi.h>
#include <WebServer.h>
#include <FastLED.h>

#define NUM_LEDS 60
#define LED_PIN 23

const char* ssid = "TP-Link_AP_1D6A";
const char* password = "31901642";

CRGB leds[NUM_LEDS];
CRGB couleur = CRGB(255, 255, 255);
int vitesse = 1;

bool arcenciel = false;
int hue = 0;

WebServer server(80);

void couleurLED() {
  int rouge = server.arg("r").toInt(); // On l'utilise comme ça : http://IP/couleurLED?r=255&g=0&b=67
  int vert = server.arg("g").toInt();
  int bleu = server.arg("b").toInt();

  couleur = CRGB(rouge, vert, bleu);
  server.send(200, "text/plain", "Couleur changée"); // 200 = OK
}

void vitesseLED() {
  int vArg = server.arg("v").toInt();
  vitesse = vArg;
  server.send(200, "text/plain", "Vitesse changée");
}

void arcencielLED() {
  arcenciel = !arcenciel;
  server.send(200, "text/plain", "Arc en ciel changé");
}

void setup() {
  Serial.begin(115200);

  FastLED.addLeds<NEOPIXEL, LED_PIN>(leds, NUM_LEDS);

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
  server.on("/couleurLED", HTTP_GET, couleurLED);
  server.on("/vitesseLED", HTTP_GET, vitesseLED);
  server.on("/arcencielLED", HTTP_GET, arcencielLED);
  server.begin();

  Serial.println("Serveur démarré.");
}

void loop() {
  if(arcenciel == true){
    for(int i = 0; i < NUM_LEDS; i++){
      leds[i] = CHSV(hue, 255, 255);
    }
    hue = hue + vitesse;
    if(hue > 255) hue = 0;
  }
  else{
    for(int i = 0; i < NUM_LEDS; i++){
      leds[i] = couleur;
    }
  }
  FastLED.show();
  
  server.handleClient();
  delay(20);
}