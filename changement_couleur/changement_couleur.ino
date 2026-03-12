#include <FastLED.h>

#define NUM_LEDS 60
#define LED_PIN 23

CRGB leds[NUM_LEDS];
int hue = 0; // Teinte initiale
  
void setup() {
  FastLED.addLeds<NEOPIXEL, LED_PIN>(leds, NUM_LEDS);
}

void loop() {
  for(int i = 0; i < NUM_LEDS; i++){
    leds[i] = CHSV(hue, 255, 255); // Utilisation de la teinte pour changer la couleur
  }
  FastLED.show();
  
  hue = hue + 2;
  if(hue > 255) hue = 0;
  delay(5);
}