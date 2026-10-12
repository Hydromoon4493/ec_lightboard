#include <FastLED.h>

// ============================================================
// 48x48 LED MATRIX
// 9 panels = 3 x 3
// Each panel = 16 x 16 = 256 LEDs
// Each data line = 3 panels = 768 LEDs
// ============================================================

#define WIDTH 48
#define HEIGHT 48

#define PANEL_SIZE 16
#define NUM_PANELS 9
#define LEDS_PER_PANEL 256
#define LEDS_PER_STRIP 768

// ------------------------------------------------------------
// DATA PINS
// ------------------------------------------------------------

#define PIN_ROW1 19
#define PIN_ROW2 18
#define PIN_ROW3 5

// ------------------------------------------------------------
// LED ARRAYS
// ------------------------------------------------------------

CRGB row1[LEDS_PER_STRIP];
CRGB row2[LEDS_PER_STRIP];
CRGB row3[LEDS_PER_STRIP];

CRGB* leds[3] = {
  row1,
  row2,
  row3
};

// ------------------------------------------------------------
// TEST SETTINGS
// ------------------------------------------------------------

#define TEST_TIME 5000   // 5 seconds per test

// ------------------------------------------------------------
// PANEL ORDERS
//
// These describe the order of the THREE physical panels
// along each data line.
//
// Example:
//
// 123 = logical 1 -> physical 1
//       logical 2 -> physical 2
//       logical 3 -> physical 3
//
// ------------------------------------------------------------

const uint8_t panelOrders[6][3] = {

  {0, 1, 2},   // 1 2 3
  {0, 2, 1},   // 1 3 2
  {1, 0, 2},   // 2 1 3
  {1, 2, 0},   // 2 3 1
  {2, 0, 1},   // 3 1 2
  {2, 1, 0}    // 3 2 1

};

// ------------------------------------------------------------
// ORIENTATIONS
// ------------------------------------------------------------

enum Orientation {

  NORMAL,

  FLIP_X,

  FLIP_Y,

  ROTATE_180,

  ROTATE_90_CW,

  ROTATE_90_CCW,

  TRANSPOSE,

  ANTI_TRANSPOSE

};

const char* orientationNames[8] = {

  "NORMAL",
  "FLIP X",
  "FLIP Y",
  "ROTATE 180",
  "ROTATE 90 CW",
  "ROTATE 90 CCW",
  "TRANSPOSE",
  "ANTI-TRANSPOSE"

};

// ------------------------------------------------------------
// TEST IMAGE
// ------------------------------------------------------------

CRGB image[HEIGHT][WIDTH];

// ------------------------------------------------------------
// NUMBER FONT
//
// 5 x 7 font
// ------------------------------------------------------------

const byte font5x7[10][7] = {

  {
    B01110,
    B10001,
    B10011,
    B10101,
    B11001,
    B10001,
    B01110
  },

  {
    B00100,
    B01100,
    B00100,
    B00100,
    B00100,
    B00100,
    B01110
  },

  {
    B01110,
    B10001,
    B00001,
    B00010,
    B00100,
    B01000,
    B11111
  },

  {
    B11110,
    B00001,
    B00001,
    B01110,
    B00001,
    B00001,
    B11110
  },

  {
    B00010,
    B00110,
    B01010,
    B10010,
    B11111,
    B00010,
    B00010
  },

  {
    B11111,
    B10000,
    B10000,
    B11110,
    B00001,
    B00001,
    B11110
  },

  {
    B01110,
    B10000,
    B10000,
    B11110,
    B10001,
    B10001,
    B01110
  },

  {
    B11111,
    B00001,
    B00010,
    B00100,
    B01000,
    B01000,
    B01000
  },

  {
    B01110,
    B10001,
    B10001,
    B01110,
    B10001,
    B10001,
    B01110
  },

  {
    B01110,
    B10001,
    B10001,
    B01111,
    B00001,
    B00001,
    B01110
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
// DRAW A LARGE NUMBER
// ============================================================

void drawNumber(
  int number,
  int startX,
  int startY,
  CRGB color
) {

  if (number < 0 || number > 9)
    return;

  // 5x7 font, enlarged 2x
  const int scale = 2;

  for (int row = 0; row < 7; row++) {

    for (int col = 0; col < 5; col++) {

      if (font5x7[number][row] & (1 << (4 - col))) {

        for (int dy = 0; dy < scale; dy++) {

          for (int dx = 0; dx < scale; dx++) {

            int x = startX + col * scale + dx;
            int y = startY + row * scale + dy;

            if (
              x >= 0 &&
              x < WIDTH &&
              y >= 0 &&
              y < HEIGHT
            ) {

              image[y][x] = color;

            }

          }

        }

      }

    }

  }

}

// ============================================================
// DRAW ONE PANEL
//
// Each panel gets:
//
//   ----------------
//        NUMBER
//
// The TOP LINE is deliberately very obvious.
//
// This lets us determine whether a panel is:
//
//   NORMAL
//   FLIPPED
//   ROTATED
//   etc.
// ============================================================

void drawPanel(
  int panelX,
  int panelY,
  int number
) {

  int x0 = panelX * PANEL_SIZE;
  int y0 = panelY * PANEL_SIZE;

  // ----------------------------------------------------------
  // TOP ORIENTATION MARKER
  // ----------------------------------------------------------

  for (int x = 0; x < PANEL_SIZE; x++) {

    image[y0][x0 + x] = CRGB::White;

  }

  // ----------------------------------------------------------
  // LEFT EDGE
  // Small marker to make rotation easier to recognize.
  // ----------------------------------------------------------

  for (int y = 1; y < PANEL_SIZE; y++) {

    image[y0 + y][x0] = CRGB::Blue;

  }

  // ----------------------------------------------------------
  // NUMBER
  // ----------------------------------------------------------

  // Center approximately in 16x16 panel

  int numberWidth = 10;
  int numberHeight = 14;

  int startX =
    x0 + (PANEL_SIZE - numberWidth) / 2;

  int startY =
    y0 + 2;

  drawNumber(
    number,
    startX,
    startY,
    CRGB::Red
  );

}

// ============================================================
// BUILD 1-9 IMAGE
// ============================================================

void build123456789() {

  clearImage();

  // Top row
  drawPanel(0, 0, 1);
  drawPanel(1, 0, 2);
  drawPanel(2, 0, 3);

  // Middle row
  drawPanel(0, 1, 4);
  drawPanel(1, 1, 5);
  drawPanel(2, 1, 6);

  // Bottom row
  drawPanel(0, 2, 7);
  drawPanel(1, 2, 8);
  drawPanel(2, 2, 9);

}

// ============================================================
// TRANSFORM PIXEL
// ============================================================

void transformPixel(
  int x,
  int y,
  Orientation orientation,
  int &newX,
  int &newY
) {

  switch (orientation) {

    case NORMAL:

      newX = x;
      newY = y;

      break;


    case FLIP_X:

      newX = PANEL_SIZE - 1 - x;
      newY = y;

      break;


    case FLIP_Y:

      newX = x;
      newY = PANEL_SIZE - 1 - y;

      break;


    case ROTATE_180:

      newX = PANEL_SIZE - 1 - x;
      newY = PANEL_SIZE - 1 - y;

      break;


    case ROTATE_90_CW:

      newX = PANEL_SIZE - 1 - y;
      newY = x;

      break;


    case ROTATE_90_CCW:

      newX = y;
      newY = PANEL_SIZE - 1 - x;

      break;


    case TRANSPOSE:

      newX = y;
      newY = x;

      break;


    case ANTI_TRANSPOSE:

      newX = PANEL_SIZE - 1 - y;
      newY = PANEL_SIZE - 1 - x;

      break;

  }

}

// ============================================================
// SEND PIXEL
// ============================================================

void sendPixel(
  int logicalX,
  int logicalY,
  Orientation orientation,
  const uint8_t order[3]
) {

  // Determine logical panel

  int logicalPanelX =
    logicalX / PANEL_SIZE;

  int logicalPanelY =
    logicalY / PANEL_SIZE;

  // Pixel inside panel

  int localX =
    logicalX % PANEL_SIZE;

  int localY =
    logicalY % PANEL_SIZE;

  // Transform panel orientation

  int physicalX;
  int physicalY;

  transformPixel(
    localX,
    localY,
    orientation,
    physicalX,
    physicalY
  );

  // Determine physical panel along the strip

  int physicalPanelX =
    order[logicalPanelX];

  // Physical LED position

  int stripIndex =
    physicalPanelX * LEDS_PER_PANEL
    + physicalY * PANEL_SIZE
    + physicalX;

  // Determine which data line

  int strip = logicalPanelY;

  if (strip < 0 || strip > 2)
    return;

  if (stripIndex < 0 || stripIndex >= LEDS_PER_STRIP)
    return;

  leds[strip][stripIndex] =
    image[logicalY][logicalX];

}

// ============================================================
// DISPLAY CURRENT TEST
// ============================================================

void displayTest(
  int orderIndex,
  Orientation orientation
) {

  // Clear physical LED buffers

  fill_solid(
    row1,
    LEDS_PER_STRIP,
    CRGB::Black
  );

  fill_solid(
    row2,
    LEDS_PER_STRIP,
    CRGB::Black
  );

  fill_solid(
    row3,
    LEDS_PER_STRIP,
    CRGB::Black
  );

  // Map entire 48x48 image

  for (int y = 0; y < HEIGHT; y++) {

    for (int x = 0; x < WIDTH; x++) {

      sendPixel(
        x,
        y,
        orientation,
        panelOrders[orderIndex]
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

  // ----------------------------------------------------------
  // FastLED
  // ----------------------------------------------------------

  FastLED.addLeds<WS2812B, PIN_ROW1, GRB>(
    row1,
    LEDS_PER_STRIP
  );

  FastLED.addLeds<WS2812B, PIN_ROW2, GRB>(
    row2,
    LEDS_PER_STRIP
  );

  FastLED.addLeds<WS2812B, PIN_ROW3, GRB>(
    row3,
    LEDS_PER_STRIP
  );

  FastLED.setBrightness(80);

  // ----------------------------------------------------------
  // Build diagnostic image
  // ----------------------------------------------------------

  build123456789();

  Serial.println();
  Serial.println("======================================");
  Serial.println("48-PANEL-MAPPING DIAGNOSTIC");
  Serial.println("======================================");
  Serial.println();
  Serial.println("Each panel has:");
  Serial.println("  WHITE = TOP");
  Serial.println("  BLUE  = LEFT");
  Serial.println("  RED   = NUMBER");
  Serial.println();
  Serial.println("Correct result should be:");
  Serial.println();
  Serial.println("       1  2  3");
  Serial.println("       4  5  6");
  Serial.println("       7  8  9");
  Serial.println();
  Serial.println("with WHITE above every number");
  Serial.println("and BLUE along the left side.");
  Serial.println();

}

// ============================================================
// LOOP
// ============================================================

void loop() {

  static int testNumber = 0;

  // ----------------------------------------------------------
  // There are 6 panel orders x 8 orientations = 48 tests
  // ----------------------------------------------------------

  int orderIndex =
    testNumber / 8;

  int orientationIndex =
    testNumber % 8;

  Orientation orientation =
    (Orientation)orientationIndex;

  // ----------------------------------------------------------
  // Serial information
  // ----------------------------------------------------------

  Serial.println();
  Serial.println("--------------------------------------");

  Serial.print("TEST ");
  Serial.print(testNumber + 1);
  Serial.println(" / 48");

  Serial.print("PANEL ORDER: ");

  Serial.print(
    panelOrders[orderIndex][0] + 1
  );

  Serial.print(
    panelOrders[orderIndex][1] + 1
  );

  Serial.print(
    panelOrders[orderIndex][2] + 1
  );

  Serial.println();

  Serial.print("ORIENTATION: ");
  Serial.println(
    orientationNames[orientationIndex]
  );

  Serial.println("--------------------------------------");

  // ----------------------------------------------------------
  // Display test
  // ----------------------------------------------------------

  displayTest(
    orderIndex,
    orientation
  );

  // ----------------------------------------------------------
  // Wait
  // ----------------------------------------------------------

  delay(TEST_TIME);

  // ----------------------------------------------------------
  // Next test
  // ----------------------------------------------------------

  testNumber++;

  if (testNumber >= 48) {

    testNumber = 0;

    Serial.println();
    Serial.println("======================================");
    Serial.println("48 TESTS COMPLETE - RESTARTING");
    Serial.println("======================================");

  }

}
