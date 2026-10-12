#include <FastLED.h>

// ============================================================
// 48x48 / 9 x 16x16 PANEL DIAGNOSTIC
// ============================================================
//
// Tests:
//   6 possible panel orders
//   x
//   8 possible panel orientations
//
// TOTAL = 48 TESTS
//
// The physical test-ID dots are always placed in the same
// physical location so you can identify the test even if
// the 1-9 image is completely scrambled.
//
// ============================================================


// ============================================================
// DISPLAY CONSTANTS
// ============================================================

#define WIDTH 48
#define HEIGHT 48

#define PANEL_SIZE 16

#define PANELS_X 3
#define PANELS_Y 3

#define LEDS_PER_PANEL 256
#define LEDS_PER_STRIP 768

#define BRIGHTNESS 40

// How long each test stays on
#define TEST_TIME 4000


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
// LOGICAL IMAGE
// ============================================================

CRGB image[HEIGHT][WIDTH];


// ============================================================
// TEST DEFINITIONS
// ============================================================

// Panel orders.
//
// The numbers mean which logical panel goes into physical
// position 0, 1, 2 along a data line.
//
// 123 = 1,2,3
// 321 = 3,2,1
//
// etc.

const uint8_t panelOrders[6][3] = {

  {0, 1, 2},   // 123
  {0, 2, 1},   // 132
  {1, 0, 2},   // 213
  {1, 2, 0},   // 231
  {2, 0, 1},   // 312
  {2, 1, 0}    // 321

};


// Orientation names

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


// ============================================================
// 5x7 FONT
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
// CLEAR LOGICAL IMAGE
// ============================================================

void clearImage() {

  for (int y = 0; y < HEIGHT; y++) {

    for (int x = 0; x < WIDTH; x++) {

      image[y][x] = CRGB::Black;

    }

  }

}


// ============================================================
// DRAW NUMBER
// ============================================================

void drawNumber(
  int panelX,
  int panelY,
  int number
) {

  int startX = panelX * PANEL_SIZE;
  int startY = panelY * PANEL_SIZE;

  // ----------------------------------------------------------
  // PANEL BORDER
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
// BUILD:
//
// 1 2 3
// 4 5 6
// 7 8 9
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
// TRANSFORM A PIXEL INSIDE A 16x16 PANEL
// ============================================================

void transformPixel(
  int inX,
  int inY,
  int orientation,
  int &outX,
  int &outY
) {

  switch (orientation) {

    // --------------------------------------------------------
    // NORMAL
    // --------------------------------------------------------

    case 0:

      outX = inX;
      outY = inY;

      break;


    // --------------------------------------------------------
    // FLIP X
    // --------------------------------------------------------

    case 1:

      outX = 15 - inX;
      outY = inY;

      break;


    // --------------------------------------------------------
    // FLIP Y
    // --------------------------------------------------------

    case 2:

      outX = inX;
      outY = 15 - inY;

      break;


    // --------------------------------------------------------
    // ROTATE 180
    // --------------------------------------------------------

    case 3:

      outX = 15 - inX;
      outY = 15 - inY;

      break;


    // --------------------------------------------------------
    // ROTATE 90 CW
    // --------------------------------------------------------

    case 4:

      outX = 15 - inY;
      outY = inX;

      break;


    // --------------------------------------------------------
    // ROTATE 90 CCW
    // --------------------------------------------------------

    case 5:

      outX = inY;
      outY = 15 - inX;

      break;


    // --------------------------------------------------------
    // TRANSPOSE
    // --------------------------------------------------------

    case 6:

      outX = inY;
      outY = inX;

      break;


    // --------------------------------------------------------
    // ANTI-TRANSPOSE
    // --------------------------------------------------------

    case 7:

      outX = 15 - inY;
      outY = 15 - inX;

      break;

  }

}


// ============================================================
// SEND ONE LOGICAL PIXEL
// ============================================================

void sendPixel(
  int x,
  int y,
  CRGB color,
  int orderNumber,
  int orientation
) {

  // ----------------------------------------------------------
  // WHICH LOGICAL PANEL?
  // ----------------------------------------------------------

  int logicalPanelX = x / PANEL_SIZE;
  int logicalPanelY = y / PANEL_SIZE;


  // ----------------------------------------------------------
  // WHICH PANEL NUMBER?
  //
  // logical panel:
  //
  // 0 1 2
  // 3 4 5
  // 6 7 8
  // ----------------------------------------------------------

  int logicalPanel =
    logicalPanelY * 3 +
    logicalPanelX;


  // ----------------------------------------------------------
  // PHYSICAL PANEL POSITION
  //
  // Each data line has its own three-panel order.
  // ----------------------------------------------------------

  int physicalPanelX = -1;

  for (int p = 0; p < 3; p++) {

    if (panelOrders[orderNumber][p] ==
        (logicalPanel % 3)) {

      physicalPanelX = p;

      break;

    }

  }


  // ----------------------------------------------------------
  // DATA LINE
  //
  // Logical rows:
  //
  // 1 2 3 -> strip 0
  // 4 5 6 -> strip 1
  // 7 8 9 -> strip 2
  // ----------------------------------------------------------

  int strip = logicalPanelY;


  // ----------------------------------------------------------
  // LOCAL POSITION INSIDE 16x16 PANEL
  // ----------------------------------------------------------

  int localX = x % PANEL_SIZE;
  int localY = y % PANEL_SIZE;


  // ----------------------------------------------------------
  // APPLY PANEL ORIENTATION
  // ----------------------------------------------------------

  int physicalX;
  int physicalY;

  transformPixel(
    localX,
    localY,
    orientation,
    physicalX,
    physicalY
  );


  // ----------------------------------------------------------
  // FINAL PHYSICAL LED INDEX
  // ----------------------------------------------------------

  int index =
    physicalPanelX * LEDS_PER_PANEL +
    physicalY * PANEL_SIZE +
    physicalX;


  strips[strip][index] = color;

}


// ============================================================
// PUT TEST NUMBER INTO PHYSICAL LEDs
//
// 6 DOTS = BINARY TEST NUMBER
//
// Example:
//
// Test 5 = 000101
//
// ● ● ● ● ● ●
// 0 0 0 1 0 1
//
// These are written DIRECTLY to physical LEDs after all
// mapping has happened, so they remain in the same physical
// location for every test.
//
// ============================================================

void drawTestDots(int testNumber) {

  // Physical location:
  //
  // top-left corner
  //
  // 6 dots horizontally
  // each dot is 2x2 pixels
  //
  // This is deliberately very obvious.

  const int startX = 2;
  const int startY = 2;

  for (int bit = 0; bit < 6; bit++) {

    bool on =
      (testNumber >> bit) & 1;

    CRGB dotColor;

    if (on) {

      dotColor = CRGB::Yellow;

    } else {

      dotColor = CRGB(20, 20, 20);

    }


    int x0 =
      startX +
      bit * 3;

    int y0 = startY;


    // 2x2 dot

    for (int dy = 0; dy < 2; dy++) {

      for (int dx = 0; dx < 2; dx++) {

        int x = x0 + dx;
        int y = y0 + dy;


        // ----------------------------------------------------
        // DIRECT PHYSICAL ADDRESS
        //
        // These LEDs bypass the image mapping.
        // ----------------------------------------------------

        int physicalStrip =
          y / PANEL_SIZE;

        int localY =
          y % PANEL_SIZE;

        int physicalPanel =
          x / PANEL_SIZE;

        int localX =
          x % PANEL_SIZE;

        int index =
          physicalPanel * LEDS_PER_PANEL +
          localY * PANEL_SIZE +
          localX;


        strips[physicalStrip][index] =
          dotColor;

      }

    }

  }

}


// ============================================================
// DISPLAY CURRENT TEST
// ============================================================

void displayTest(
  int testNumber,
  int orderNumber,
  int orientation
) {

  // ----------------------------------------------------------
  // CLEAR PHYSICAL LEDs
  // ----------------------------------------------------------

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


  // ----------------------------------------------------------
  // SEND IMAGE
  // ----------------------------------------------------------

  for (int y = 0; y < HEIGHT; y++) {

    for (int x = 0; x < WIDTH; x++) {

      sendPixel(
        x,
        y,
        image[y][x],
        orderNumber,
        orientation
      );

    }

  }


  // ----------------------------------------------------------
  // ADD PHYSICAL TEST-ID DOTS
  // ----------------------------------------------------------

  drawTestDots(testNumber);


  // ----------------------------------------------------------
  // DISPLAY
  // ----------------------------------------------------------

  FastLED.show();

}


// ============================================================
// PRINT TEST INFORMATION
// ============================================================

void printTestInfo(
  int testNumber,
  int orderNumber,
  int orientation
) {

  Serial.println();
  Serial.println("========================================");

  Serial.print("TEST ");
  Serial.print(testNumber + 1);
  Serial.println(" / 48");

  Serial.print("PANEL ORDER: ");

  for (int i = 0; i < 3; i++) {

    Serial.print(
      panelOrders[orderNumber][i] + 1
    );

  }

  Serial.println();

  Serial.print("ORIENTATION: ");
  Serial.println(
    orientationNames[orientation]
  );

  Serial.println("----------------------------------------");

  Serial.println("Expected logical image:");

  Serial.println();
  Serial.println("       1   2   3");
  Serial.println("       4   5   6");
  Serial.println("       7   8   9");

  Serial.println();
  Serial.println("TEST ID = yellow dots in physical");
  Serial.println("top-left corner.");

  Serial.println("========================================");

}


// ============================================================
// RUN TEST
// ============================================================

void runTest(int testNumber) {

  int orderNumber =
    testNumber / 8;

  int orientation =
    testNumber % 8;


  printTestInfo(
    testNumber,
    orderNumber,
    orientation
  );


  displayTest(
    testNumber,
    orderNumber,
    orientation
  );

}


// ============================================================
// SETUP
// ============================================================

void setup() {

  Serial.begin(115200);

  delay(1000);

  Serial.println();
  Serial.println("########################################");
  Serial.println("# 48x48 LED PANEL MAPPING DIAGNOSTIC #");
  Serial.println("########################################");

  Serial.println();

  Serial.println("Testing 6 panel orders x 8 orientations");

  Serial.println("Total tests: 48");

  Serial.println();

  // ----------------------------------------------------------
  // FASTLED
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
  // BUILD 1-9 IMAGE
  // ----------------------------------------------------------

  build123();


  // ----------------------------------------------------------
  // START TEST 0
  // ----------------------------------------------------------

  runTest(0);

}


// ============================================================
// LOOP
// ============================================================

void loop() {

  static unsigned long lastChange = 0;

  static int testNumber = 0;


  if (millis() - lastChange >= TEST_TIME) {

    testNumber++;

    if (testNumber >= 48) {

      testNumber = 0;

      Serial.println();
      Serial.println("########################################");
      Serial.println("# RESTARTING 48-TEST CYCLE");
      Serial.println("########################################");

    }


    runTest(testNumber);

    lastChange = millis();

  }

}
