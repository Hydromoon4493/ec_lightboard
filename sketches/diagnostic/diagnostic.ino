#include <FastLED.h>

// ============================================================
// HARDWARE
// ============================================================

#define PANEL_SIZE 16
#define PANELS_X 3
#define PANELS_Y 3

#define WIDTH  48
#define HEIGHT 48

#define LEDS_PER_PANEL 256
#define LEDS_PER_STRIP 768

#define NUM_STRIPS 3

// Your three data lines
#define PIN_ROW1 5
#define PIN_ROW2 18
#define PIN_ROW3 19

#define BRIGHTNESS 40

CRGB leds1[LEDS_PER_STRIP];
CRGB leds2[LEDS_PER_STRIP];
CRGB leds3[LEDS_PER_STRIP];

CRGB* strips[NUM_STRIPS] = {
  leds1,
  leds2,
  leds3
};

// ============================================================
// TEST TIMING
// ============================================================

#define TEST_TIME 5000

unsigned long lastTestChange = 0;
int currentTest = 0;


// ============================================================
// BIG 5x7 DIGITS
//
// Each digit is 5 pixels wide x 7 pixels tall.
// We scale them by 2, making each digit 10x14.
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
// DRAW INTO A LOGICAL 48x48 IMAGE
// ============================================================

CRGB image[HEIGHT][WIDTH];


// ============================================================
// TEST COLORS
// ============================================================

CRGB testColors[] = {
  CRGB::Red,
  CRGB::Green,
  CRGB::Blue,
  CRGB::Yellow,
  CRGB::Cyan,
  CRGB::Magenta,
  CRGB::Orange,
  CRGB::Purple,
  CRGB::White,
  CRGB(80, 255, 80)
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
// DRAW ONE 16x16 PANEL
//
// panelX = 0,1,2
// panelY = 0,1,2
//
// Number:
//
// 1 2 3
// 4 5 6
// 7 8 9
// ============================================================

void drawNumberOnPanel(
  int panelX,
  int panelY,
  int number
) {

  int startX = panelX * PANEL_SIZE;
  int startY = panelY * PANEL_SIZE;

  // ----------------------------------------------------------
  // PANEL BORDER
  // ----------------------------------------------------------

  CRGB borderColor = testColors[currentTest];

  for (int i = 0; i < PANEL_SIZE; i++) {

    image[startY][startX + i] = borderColor;
    image[startY + PANEL_SIZE - 1][startX + i] = borderColor;

    image[startY + i][startX] = borderColor;
    image[startY + i][startX + PANEL_SIZE - 1] = borderColor;

  }


  // ----------------------------------------------------------
  // DRAW BIG NUMBER
  // ----------------------------------------------------------

  const int scale = 2;

  int digitWidth = 5 * scale;
  int digitHeight = 7 * scale;

  int offsetX = (PANEL_SIZE - digitWidth) / 2;
  int offsetY = (PANEL_SIZE - digitHeight) / 2;

  for (int dy = 0; dy < 7; dy++) {

    for (int dx = 0; dx < 5; dx++) {

      if (digits[number][dy] & (1 << (4 - dx))) {

        for (int sy = 0; sy < scale; sy++) {

          for (int sx = 0; sx < scale; sx++) {

            int x = startX + offsetX + dx * scale + sx;
            int y = startY + offsetY + dy * scale + sy;

            if (x >= startX &&
                x < startX + PANEL_SIZE &&
                y >= startY &&
                y < startY + PANEL_SIZE) {

              image[y][x] = CRGB::White;

            }

          }

        }

      }

    }

  }

}


// ============================================================
// BUILD THE 1-9 IMAGE
// ============================================================

void buildTestImage() {

  clearImage();

  int number = 1;

  for (int panelY = 0; panelY < 3; panelY++) {

    for (int panelX = 0; panelX < 3; panelX++) {

      drawNumberOnPanel(
        panelX,
        panelY,
        number
      );

      number++;

    }

  }

}


// ============================================================
// MAP LOGICAL PIXEL TO PHYSICAL LED
//
// This is where the diagnostic tests happen.
//
// logical x/y = where we WANT the pixel
//
// strip = which of the 3 ESP32 data outputs
//
// index = LED position on that output
// ============================================================

void sendPixel(
  int x,
  int y,
  CRGB color,
  bool reversePanels,
  bool reverseX,
  bool reverseY,
  bool serpentine
) {

  // ----------------------------------------------------------
  // GLOBAL X/Y REVERSALS
  // ----------------------------------------------------------

  if (reverseX) {
    x = WIDTH - 1 - x;
  }

  if (reverseY) {
    y = HEIGHT - 1 - y;
  }


  // ----------------------------------------------------------
  // WHICH OF THE THREE DATA LINES?
  //
  // Each data line represents one horizontal group of
  // three 16x16 panels.
  //
  // y 0-15  = strip 0
  // y 16-31 = strip 1
  // y 32-47 = strip 2
  // ----------------------------------------------------------

  int strip = y / PANEL_SIZE;

  int localY = y % PANEL_SIZE;

  // ----------------------------------------------------------
  // WHICH PANEL WITHIN THAT DATA LINE?
  // ----------------------------------------------------------

  int panel = x / PANEL_SIZE;

  int localX = x % PANEL_SIZE;


  // ----------------------------------------------------------
  // REVERSE PANEL ORDER
  //
  // 0 1 2 becomes 2 1 0
  // ----------------------------------------------------------

  if (reversePanels) {

    panel = 2 - panel;

  }


  // ----------------------------------------------------------
  // INTERNAL PANEL X/Y
  // ----------------------------------------------------------

  if (reverseX) {

    localX = PANEL_SIZE - 1 - localX;

  }

  if (reverseY) {

    localY = PANEL_SIZE - 1 - localY;

  }


  // ----------------------------------------------------------
  // PANEL SERPENTINE
  // ----------------------------------------------------------

  if (serpentine && (localY % 2 == 1)) {

    localX = PANEL_SIZE - 1 - localX;

  }


  // ----------------------------------------------------------
  // PHYSICAL LED INDEX
  //
  // Three 16x16 panels are chained together.
  //
  // panel 0 = LEDs 0-255
  // panel 1 = LEDs 256-511
  // panel 2 = LEDs 512-767
  // ----------------------------------------------------------

  int index =
      panel * LEDS_PER_PANEL +
      localY * PANEL_SIZE +
      localX;


  strips[strip][index] = color;

}


// ============================================================
// SEND THE IMAGE USING A PARTICULAR MAPPING
// ============================================================

void displayMapping(
  bool reversePanels,
  bool reverseX,
  bool reverseY,
  bool serpentine
) {

  // Clear physical strips

  fill_solid(leds1, LEDS_PER_STRIP, CRGB::Black);
  fill_solid(leds2, LEDS_PER_STRIP, CRGB::Black);
  fill_solid(leds3, LEDS_PER_STRIP, CRGB::Black);


  // Send every logical pixel

  for (int y = 0; y < HEIGHT; y++) {

    for (int x = 0; x < WIDTH; x++) {

      sendPixel(
        x,
        y,
        image[y][x],
        reversePanels,
        reverseX,
        reverseY,
        serpentine
      );

    }

  }

  FastLED.show();

}


// ============================================================
// TEST DEFINITIONS
// ============================================================
//
// Each test changes ONE OR MORE mapping assumptions.
//
// TEST 0 = normal
// TEST 1 = reverse panel order
// TEST 2 = reverse X
// TEST 3 = reverse Y
// TEST 4 = reverse X + Y
// TEST 5 = serpentine
// TEST 6 = reverse panels + serpentine
// TEST 7 = reverse panels + reverse X
// TEST 8 = reverse panels + reverse Y
// TEST 9 = reverse panels + X + Y + serpentine
//
// ============================================================

void runTest(int test) {

  Serial.println();
  Serial.println("==============================");

  Serial.print("DIAGNOSTIC TEST ");
  Serial.println(test);

  switch (test) {

    case 0:

      Serial.println("NORMAL");
      Serial.println("Panels: 1 2 3");
      Serial.println("X: normal");
      Serial.println("Y: normal");
      Serial.println("Serpentine: OFF");

      displayMapping(
        false,
        false,
        false,
        false
      );

      break;


    case 1:

      Serial.println("REVERSE PANEL ORDER");
      Serial.println("Panels: 3 2 1");

      displayMapping(
        true,
        false,
        false,
        false
      );

      break;


    case 2:

      Serial.println("REVERSE X");

      displayMapping(
        false,
        true,
        false,
        false
      );

      break;


    case 3:

      Serial.println("REVERSE Y");

      displayMapping(
        false,
        false,
        true,
        false
      );

      break;


    case 4:

      Serial.println("ROTATE 180 DEGREES");

      displayMapping(
        false,
        true,
        true,
        false
      );

      break;


    case 5:

      Serial.println("PANEL SERPENTINE");

      displayMapping(
        false,
        false,
        false,
        true
      );

      break;


    case 6:

      Serial.println("REVERSE PANELS + SERPENTINE");

      displayMapping(
        true,
        false,
        false,
        true
      );

      break;


    case 7:

      Serial.println("REVERSE PANELS + REVERSE X");

      displayMapping(
        true,
        true,
        false,
        false
      );

      break;


    case 8:

      Serial.println("REVERSE PANELS + REVERSE Y");

      displayMapping(
        true,
        false,
        true,
        false
      );

      break;


    case 9:

      Serial.println("EVERYTHING REVERSED");

      displayMapping(
        true,
        true,
        true,
        true
      );

      break;

  }

}


// ============================================================
// SETUP
// ============================================================

void setup() {

  Serial.begin(115200);

  delay(1000);

  Serial.println();
  Serial.println("====================================");
  Serial.println("  48x48 LED PANEL MAPPING TEST");
  Serial.println("====================================");
  Serial.println();
  Serial.println("Expected image:");
  Serial.println();
  Serial.println("    1 | 2 | 3");
  Serial.println("    4 | 5 | 6");
  Serial.println("    7 | 8 | 9");
  Serial.println();

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

  buildTestImage();

  currentTest = 0;

  runTest(currentTest);

  lastTestChange = millis();

}


// ============================================================
// LOOP
// ============================================================

void loop() {

  if (millis() - lastTestChange >= TEST_TIME) {

    currentTest++;

    if (currentTest > 9) {

      currentTest = 0;

    }

    runTest(currentTest);

    lastTestChange = millis();

  }

}
