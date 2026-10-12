#include <FastLED.h>

#define PANEL_WIDTH 16
#define PANEL_HEIGHT 16
#define PANELS_X 3
#define MATRIX_WIDTH 48
#define MATRIX_HEIGHT 48

#define NUM_LEDS_PER_STRIP (16 * 16 * 3) // 768 LEDs per data line

// Data Pins
#define PIN_ROW1 19 // Top Row
#define PIN_ROW2 18 // Middle Row
#define PIN_ROW3 5  // Bottom Row

CRGB leds1[NUM_LEDS_PER_STRIP];
CRGB leds2[NUM_LEDS_PER_STRIP];
CRGB leds3[NUM_LEDS_PER_STRIP];

// Adjusted indexing for 90-degree rotated panel orientation
uint16_t xyToIndexRotated(uint8_t x, uint8_t local_y) {
  // Map physical display coordinates to rotated 16x16 matrix flow
  uint8_t panelX = (47 - x) / 16; // Right-to-left panel flow from front view
  uint8_t localX = (47 - x) % 16;

  uint16_t panelOffset = panelX * 256;

  // Rotated column-major mapping
  uint16_t localIndex;
  if (localX % 2 == 1) {
    localIndex = (localX * 16) + (15 - local_y);
  } else {
    localIndex = (localX * 16) + local_y;
  }

  return panelOffset + localIndex;
}

void setPixel(uint8_t x, uint8_t y, CRGB color) {
  if (x >= MATRIX_WIDTH || y >= MATRIX_HEIGHT) return;

  if (y < 16) {
    leds1[xyToIndexRotated(x, y)] = color;
  } else if (y < 32) {
    leds2[xyToIndexRotated(x, y - 16)] = color;
  } else {
    leds3[xyToIndexRotated(x, y - 32)] = color;
  }
}

void setup() {
  FastLED.addLeds<WS2812B, PIN_ROW1, GRB>(leds1, NUM_LEDS_PER_STRIP);
  FastLED.addLeds<WS2812B, PIN_ROW2, GRB>(leds2, NUM_LEDS_PER_STRIP);
  FastLED.addLeds<WS2812B, PIN_ROW3, GRB>(leds3, NUM_LEDS_PER_STRIP);

  FastLED.setBrightness(40);
  FastLED.clear();

  // Draw Blue border across all coordinates to verify orientation
  for (int x = 0; x < MATRIX_WIDTH; x++) {
    setPixel(x, 0, CRGB::Blue);
    setPixel(x, MATRIX_HEIGHT - 1, CRGB::Blue);
  }
  for (int y = 0; y < MATRIX_HEIGHT; y++) {
    setPixel(0, y, CRGB::Blue);
    setPixel(MATRIX_WIDTH - 1, y, CRGB::Blue);
  }

  // True Top-Left Pixel
  setPixel(0, 0, CRGB::Green);

  FastLED.show();
}

void loop() {
  // Static check
}