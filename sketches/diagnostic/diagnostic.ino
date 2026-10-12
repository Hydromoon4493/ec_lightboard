#include <FastLED.h>

// ============================================================
// DISPLAY
// ============================================================

#define WIDTH 48
#define HEIGHT 48

#define PANEL_SIZE 16

#define LEDS_PER_PANEL 256
#define LEDS_PER_STRIP 768

#define BRIGHTNESS 40

// ============================================================
// DATA PINS
// ============================================================

#define PIN_ROW1 5
#define PIN_ROW2 18
#define PIN_ROW3 19

// ============================================================
// LED ARRAYS
// ============================================================

CRGB leds1[LEDS_PER_STRIP];
CRGB leds2[LEDS_PER_STRIP];
CRGB leds3[LEDS_PER_STRIP];

CRGB* strips[3] = {
  leds1,
  leds2,
  leds3
};

// ============================================================
// TEST IMAGE
//
// 1 2 3
// 4 5 6
// 7 8 9
// ============================================================

CRGB image[HEIGHT][WIDTH];

// ============================================================
// BIG DIGITS
// ============================================================

const uint8_t digits[10][7] = {

  // 0
  {
    0b01110,
    0b10001,
    0b10011,
    0b10101,
    0b11001,
    0b10001,
    0b01110
  },

  // 1
  {
    0b00100,
    0b01100,
    0b00100,
    0b00100,
    0b00100,
    0b00100,
    0b01110
  },

  // 2
  {
    0b01110,
    0b10001,
    0b00001,
    0b00010,
    0b00100,
    0b01000,
    0b11111
  },

  // 3
  {
    0b11110,
    0b00001,
    0b00001,
    0b01110,
    0b00001,
    0b00001,
    0b11110
  },

  // 4
  {
    0b00010,
    0b00110,
    0b01010,
    0b10010,
    0b11111,
    0b00010,
    0b00010
  },

  // 5
  {
    0b11111,
    0b10000,
    0b10000,
    0b11110,
    0b00001,
    0b00001,
    0b11110
  },

  // 6
  {
    0b01110,
    0b10000,
    0b10000,
    0b11110,
    0b10001,
    0b10001,
    0b01110
  },

  // 7
  {
    0b11111,
    0b00001,
    0b00010,
    0b00100,
    0b01000,
    0b01000,
    0b01000
  },

  // 8
  {
    0b01110,
    0b10001,
    0b10001,
    0b01110,
    0b10001,
    0b10001,
    0b01110
  },

  // 9
  {
    0b01110,
    0b10001,
    0b10001,
    0b01111,
    0b00001,
    0b00001,
    0b01110
  }
};


// ============================================================
// CLEAR IMAGE
// ============================================================

void clearImage() {

  for (int y = 0; y < HEIGHT; y++) {

    for (int x = 0; x < WIDTH; x++) {

      image[y][x] = CRGB::Black;

    }

  }

}


// ============================================================
// DRAW NUMBER ON 16x16 PANEL
// ============================================================

void drawNumber(
  int panelX,
  int panelY,
  int number
) {

  int startX = panelX * PANEL_SIZE;
  int startY = panelY * PANEL_SIZE;

  // ----------------------------------------------------------
  // BORDER
  // ----------------------------------------------------------

  for (int i = 0; i < PANEL_SIZE; i++) {

    image[startY][startX + i] = CRGB::Red;
    image[startY + 15][startX + i] = CRGB::Red;

    image[startY + i][startX] = CRGB::Red;
    image[startY + i][startX + 15] = CRGB::Red;

  }

  // ----------------------------------------------------------
  // BIG NUMBER
  // ----------------------------------------------------------

  const int scale = 2;

  int digitWidth = 5 * scale;
  int digitHeight = 7 * scale;

  int offsetX = (16 - digitWidth) / 2;
  int offsetY = (16 - digitHeight) / 2;

  for (int dy = 0; dy < 7; dy++) {

    for (int dx = 0; dx < 5; dx++) {

      if (digits[number][dy] & (1 << (4 - dx))) {

        for (int sy = 0; sy < scale; sy++) {

          for (int sx = 0; sx < scale; sx++) {

            int x =
              startX +
              offsetX +
              dx * scale +
              sx;

            int y =
              startY +
              offsetY +
              dy * scale +
              sy;

            if (x >= 0 &&
                x < WIDTH &&
                y >= 0 &&
                y < HEIGHT) {

              image[y][x] = CRGB::White;

            }

          }

        }

      }

    }

  }

}


// ============================================================
// BUILD 1-9
// ============================================================

void build123() {

  clearImage();

  int number = 1;

  for (int panelY = 0; panelY < 3; panelY++) {

    for (int panelX = 0; panelX < 3; panelX++) {

      drawNumber(
        panelX,
        panelY,
        number
      );

      number++;

    }

  }

}


// ============================================================
// SEND ONE PIXEL
//
// corrected = true means compensate for the physical
// 180-degree rotation.
//
// x -> 47-x
// y -> 47-y
// ============================================================

void sendPixel(
  int x,
  int y,
  CRGB color,
  bool corrected
) {

  // ----------------------------------------------------------
  // 180 DEGREE CORRECTION
  // ----------------------------------------------------------

  if (corrected) {

    x = WIDTH - 1 - x;
    y = HEIGHT - 1 - y;

  }


  // ----------------------------------------------------------
  // WHICH 16x16 PANEL?
  // ----------------------------------------------------------

  int panelX = x / PANEL_SIZE;
  int panelY = y / PANEL_SIZE;


  // ----------------------------------------------------------
  // POSITION INSIDE PANEL
  // ----------------------------------------------------------

  int localX = x % PANEL_SIZE;
  int localY = y % PANEL_SIZE;


  // ----------------------------------------------------------
  // WHICH DATA LINE?
  //
  // Top 3 panels    -> PIN_ROW1
  // Middle 3 panels -> PIN_ROW2
  // Bottom 3 panels -> PIN_ROW3
  // ----------------------------------------------------------

  int strip = panelY;


  // ----------------------------------------------------------
  // POSITION IN 768-LED CHAIN
  // ----------------------------------------------------------

  int index =
    panelX * LEDS_PER_PANEL +
    localY * PANEL_SIZE +
    localX;


  // ----------------------------------------------------------
  // WRITE PIXEL
  // ----------------------------------------------------------

  strips[strip][index] = color;

}


// ============================================================
// DISPLAY IMAGE
// ============================================================

void displayImage(bool corrected) {

  // Clear physical LEDs

  fill_solid(
    leds1,
    LEDS_PER_STRIP,
    CRGB::Black
  );

  fill_solid(
    leds2,
    LEDS_PER_STRIP,
    CRGB::Black
  );

  fill_solid(
    leds3,
    LEDS_PER_STRIP,
    CRGB::Black
  );


  // Send logical image to physical display

  for (int y = 0; y < HEIGHT; y++) {

    for (int x = 0; x < WIDTH; x++) {

      sendPixel(
        x,
        y,
        image[y][x],
        corrected
      );

    }

  }

  FastLED.show();

}


// ============================================================
// SETUP
// ============================================================

void setup() {

  Serial.begin(115200);

  delay(1000);

  Serial.println();
  Serial.println("======================================");
  Serial.println("     48x48 PANEL ROTATION TEST");
  Serial.println("======================================");
  Serial.println();
  Serial.println("Expected:");
  Serial.println();
  Serial.println("     1 | 2 | 3");
  Serial.println("     4 | 5 | 6");
  Serial.println("     7 | 8 | 9");
  Serial.println();

  // ----------------------------------------------------------
  // LED SETUP
  // ----------------------------------------------------------

  FastLED.addLeds<WS2812B, PIN_ROW1, GRB>(
    leds1,
    LEDS_PER_STRIP
  );

  FastLED.addLeds<WS2812B, PIN_ROW2, GRB>(
    leds2,
    LEDS_PER_STRIP
  );

  FastLED.addLeds<WS2812B, PIN_ROW3, GRB>(
    leds3,
    LEDS_PER_STRIP
  );

  FastLED.setBrightness(BRIGHTNESS);


  // ----------------------------------------------------------
  // BUILD TEST IMAGE
  // ----------------------------------------------------------

  build123();


  // ----------------------------------------------------------
  // START WITH CORRECTED VERSION
  // ----------------------------------------------------------

  Serial.println("--------------------------------------");
  Serial.println("TEST A");
  Serial.println("180 DEGREE CORRECTION: ON");
  Serial.println("--------------------------------------");

  displayImage(true);

}


// ============================================================
// LOOP
// ============================================================

void loop() {

  static unsigned long lastChange = 0;

  static bool corrected = true;

  if (millis() - lastChange >= 5000) {

    corrected = !corrected;

    if (corrected) {

      Serial.println();
      Serial.println("--------------------------------------");
      Serial.println("TEST A");
      Serial.println("180 DEGREE CORRECTION: ON");
      Serial.println("--------------------------------------");

    } else {

      Serial.println();
      Serial.println("--------------------------------------");
      Serial.println("TEST B");
      Serial.println("180 DEGREE CORRECTION: OFF");
      Serial.println("--------------------------------------");

    }

    displayImage(corrected);

    lastChange = millis();

  }

}
