#include <FastLED.h>
#define NUM_LEDS 60
#define LED_PIN 23

CRGB leds[NUM_LEDS];
CRGB couleur = CRGB(255, 255, 255);

void setup() {
  FastLED.addLeds<NEOPIXEL, LED_PIN>(leds, NUM_LEDS);
}

void loop() {
  for(int i = 0; i < NUM_LEDS; i++){
    leds[i] = couleur;
  }
  FastLED.show();
  delay(100);
}