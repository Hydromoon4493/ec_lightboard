#include <FastLED.h>

// -----------------------------
// USER SETTINGS
// -----------------------------
#define DATA_PIN_1  5     // Chain 1 data pin
#define DATA_PIN_2  18    // Chain 2 data pin
#define DATA_PIN_3  19    // Chain 3 data pin

#define LED_TYPE    WS2812B
#define COLOR_ORDER GRB

#define PANEL_WIDTH   16
#define PANEL_HEIGHT  16
#define PANELS_PER_CHAIN  3

#define LEDS_PER_PANEL (PANEL_WIDTH * PANEL_HEIGHT)
#define LEDS_PER_CHAIN (LEDS_PER_PANEL * PANELS_PER_CHAIN)

// LED arrays for each chain
CRGB chain1[LEDS_PER_CHAIN];
CRGB chain2[LEDS_PER_CHAIN];
CRGB chain3[LEDS_PER_CHAIN];

void setup() {
  FastLED.addLeds<LED_TYPE, DATA_PIN_1, COLOR_ORDER>(chain1, LEDS_PER_CHAIN);
  FastLED.addLeds<LED_TYPE, DATA_PIN_2, COLOR_ORDER>(chain2, LEDS_PER_CHAIN);
  FastLED.addLeds<LED_TYPE, DATA_PIN_3, COLOR_ORDER>(chain3, LEDS_PER_CHAIN);

  FastLED.setBrightness(50);   // Safe brightness for testing
}

// Fill an entire chain with a color
void fillChain(CRGB* chain, CRGB color) {
  for (int i = 0; i < LEDS_PER_CHAIN; i++) {
    chain[i] = color;
  }
}

// Test each panel separately (panel 1 red, panel 2 green, panel 3 blue)
void panelTest(CRGB* chain) {
  // Panel 1
  for (int i = 0; i < LEDS_PER_PANEL; i++) chain[i] = CRGB::Red;

  // Panel 2
  for (int i = LEDS_PER_PANEL; i < LEDS_PER_PANEL * 2; i++) chain[i] = CRGB::Green;

  // Panel 3
  for (int i = LEDS_PER_PANEL * 2; i < LEDS_PER_CHAIN; i++) chain[i] = CRGB::Blue;
}

// Rainbow animation
void rainbowChain(CRGB* chain, uint8_t offset) {
  for (int i = 0; i < LEDS_PER_CHAIN; i++) {
    chain[i] = CHSV(offset + (i / 4), 255, 255);
  }
}

void loop() {

  // -----------------------------
  // TEST 1: Solid color test
  // -----------------------------
  fillChain(chain1, CRGB::Red);
  fillChain(chain2, CRGB::Red);
  fillChain(chain3, CRGB::Red);
  FastLED.show();
  delay(800);

  fillChain(chain1, CRGB::Green);
  fillChain(chain2, CRGB::Green);
  fillChain(chain3, CRGB::Green);
  FastLED.show();
  delay(800);

  fillChain(chain1, CRGB::Blue);
  fillChain(chain2, CRGB::Blue);
  fillChain(chain3, CRGB::Blue);
  FastLED.show();
  delay(800);

  // -----------------------------
  // TEST 2: Per-panel test
  // -----------------------------
  panelTest(chain1);
  panelTest(chain2);
  panelTest(chain3);
  FastLED.show();
  delay(1500);

  // -----------------------------
  // TEST 3: Rainbow animation
  // -----------------------------
  for (int t = 0; t < 300; t++) {
    rainbowChain(chain1, t);
    rainbowChain(chain2, t + 40);
    rainbowChain(chain3, t + 80);
    FastLED.show();
    delay(20);
  }
}