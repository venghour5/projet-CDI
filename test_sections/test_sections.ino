#include <FastLED.h>

#define NUM_LEDS 10 // Taille d'une section
#define SECTION 0 // Section qu'on allume (Section 1 = partie 2, section 0 = partie 1)
#define NUM_SECTIONS 6
#define LUMINOSITE 255 // 255 max
#define COULEUR CRGB(255, 255, 255)

#define LED_TOTAL 60
#define LED_PIN 23

CRGB leds[LED_TOTAL];

int section = SECTION;

void setup() {
  FastLED.addLeds<NEOPIXEL, LED_PIN>(leds, LED_TOTAL);
}

void loop() {
  for(int i = 0; i < LED_TOTAL; i++){
    if(NUM_LEDS*section <= i && i <= NUM_LEDS*section+NUM_LEDS-1){
      leds[i] = COULEUR;
    }
    leds[i] = CHSV(hue, 255, 255);
  }
  FastLED.setBrightness(LUMINOSITE);
  FastLED.show();
  delay(20);
}