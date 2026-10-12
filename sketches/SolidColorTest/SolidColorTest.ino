#include <FastLED.h>

#define NUM_LEDS_PER_STRIP 768 // 16x16 * 3 panels = 768 LEDs per data line

// Data Pins
#define PIN_ROW1 19 // Top Row (Panels 3, 2, 1)
#define PIN_ROW2 18 // Middle Row (Panels 3, 2, 1)
#define PIN_ROW3 5  // Bottom Row (Panels 3, 2, 1)

CRGB leds1[NUM_LEDS_PER_STRIP];
CRGB leds2[NUM_LEDS_PER_STRIP];
CRGB leds3[NUM_LEDS_PER_STRIP];

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("--- Starting Basic 3-Row Solid Color Test ---");

  // Initialize WS2812B channels with GRB order
  FastLED.addLeds<WS2812B, PIN_ROW1, GRB>(leds1, NUM_LEDS_PER_STRIP);
  FastLED.addLeds<WS2812B, PIN_ROW2, GRB>(leds2, NUM_LEDS_PER_STRIP);
  FastLED.addLeds<WS2812B, PIN_ROW3, GRB>(leds3, NUM_LEDS_PER_STRIP);

  // Set low brightness to prevent sudden power supply trips
  FastLED.setBrightness(30);

  // Fill Row 1 (D19) with SOLID RED
  fill_solid(leds1, NUM_LEDS_PER_STRIP, CRGB::Red);

  // Fill Row 2 (D18) with SOLID GREEN
  fill_solid(leds2, NUM_LEDS_PER_STRIP, CRGB::Green);

  // Fill Row 3 (D5) with SOLID BLUE
  fill_solid(leds3, NUM_LEDS_PER_STRIP, CRGB::Blue);

  FastLED.show();
  Serial.println("FastLED.show() executed. Check LEDs!");
}

void loop() {
  // Heartbeat pulse on Serial Monitor every 2 seconds to prove board hasn't crashed
  Serial.println("ESP32 active and running...");
  delay(2000);
}
