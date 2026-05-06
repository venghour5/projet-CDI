#include <WiFi.h>
#include <WebServer.h>
#include <FastLED.h>

// Variables à modifier manuellement
#define LED_PIN 23
#define LEDS_TOTAL 156 // Nombre total de leds
const char* ssid = "TP-Link_AP_1D6A";
const char* password = "31901642";

// IP statique
IPAddress local_IP(172, 30, 167, 2);
IPAddress gateway(172, 30, 255, 251);
IPAddress subnet(255, 255, 0, 0);

// Par défaut, changera plus tard
int num_leds = LEDS_TOTAL; // Valeur pour chaque section, entièreté de la bande par défaut
int num_sections = 1; // Une par défaut (toute la bande)
int vitesse = 1; // Pour le mode arc en ciel
int luminosite = 255;
CRGB couleur = CRGB(255, 255, 255);

// Options
bool enleverAnciennesLEDs = true; // Retire les LEDs allumées après changement de section, par exemple

// A ne pas toucher
CRGB leds[LEDS_TOTAL];

bool arcenciel = false;
int section_actuelle = 0; // 0 = la première section, 1 = la deuxième section. S'il y en a qu'une au total, garder sur 0.
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

void sectionLED() {
  int nouvelle_num_sections = server.arg("n").toInt();
  int nouvelle_section_actuelle = server.arg("s").toInt();

  num_leds = LEDS_TOTAL / nouvelle_num_sections;
  num_sections = nouvelle_num_sections;
  section_actuelle = nouvelle_section_actuelle;

  server.send(200, "text/plain", "Sections changées");
}

void activerEnleverAnciennesLEDs() {
  enleverAnciennesLEDs = !enleverAnciennesLEDs;
  server.send(200, "text/plain", "enleverAnciennesLEDs changé");
}

void luminositeLED() {
  int nouvelle_luminosite = server.arg("l").toInt();
  luminosite = nouvelle_luminosite;
  FastLED.setBrightness(luminosite);
}

void setup() {
  Serial.begin(115200);

  FastLED.addLeds<NEOPIXEL, LED_PIN>(leds, LEDS_TOTAL);

  WiFi.config(local_IP, gateway, subnet);
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
  server.on("/sectionLED", HTTP_GET, sectionLED);
  server.on("/enleverAnciennesLEDs", HTTP_GET, activerEnleverAnciennesLEDs);
  server.on("/luminositeLED", HTTP_GET, luminositeLED);
  
  server.begin();

  Serial.println("Serveur démarré.");

  FastLED.setBrightness(luminosite);
  Serial.print("Luminosité à ");
  Serial.print(luminosite);
}

void loop() {
  // Mode arc-en-ciel (Utile pour le test des couleurs de la bande)
  if(arcenciel == true){
    for(int i = 0; i < LEDS_TOTAL; i++){
      leds[i] = CHSV(hue, 255, 255);
    }
    hue = hue + vitesse;
    if(hue > 255) hue = 0;
  }
  // Mode classique (par sections)
  else{
    for(int i = 0; i < LEDS_TOTAL; i++){
      if(num_leds*section_actuelle <= i && i <= num_leds*section_actuelle+num_leds-1){
        leds[i] = couleur;
      }
      else if(enleverAnciennesLEDs == true){
        leds[i] = (0, 0, 0);
      }
    }
  }
  FastLED.show();
  
  server.handleClient();
  delay(20);
}
