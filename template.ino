#include <FastLED.h>

#define WIDTH 48
#define HEIGHT 48

#define DATA1 19
#define DATA2 18
#define DATA3 5

#define LEDS_PER_ROW 768

CRGB leds1[768];
CRGB leds2[768];
CRGB leds3[768];

// Panel order 1,2,3 + FLIP_Y
enum Orientation {
  FLIP_Y
};

// Pixel map of image
// Create via `png_to_fastled.py`
// 48x48
CRGB image[48][48] = {
 //PASTEHERE
};

// ----------------------------------------------------
// Pixel transformation
//
// Reverse the X and Y coordinates before reading
// the pixel from the image array.
//
// x:  0 -> 47, 1 -> 46, ..., 47 -> 0
// y:  0 -> 47, 1 -> 46, ..., 47 -> 0
//
// The image pixel used is therefore:
//
//   image[47 - y][47 - x]
// ----------------------------------------------------

void displayImage()
{
  // Clear all three LED strips
  fill_solid(leds1, 768, CRGB::Black);
  fill_solid(leds2, 768, CRGB::Black);
  fill_solid(leds3, 768, CRGB::Black);

  // Panel order is exactly {1,2,3}
  // 48x48 display = 3 panels across × 3 panels vertically
  for (int y = 0; y < 48; y++)
  {
    for (int x = 0; x < 48; x++)
    {
      // FLIP_Y followed by X reversal
      int sx = 47 - x;
      int sy = 47 - y;

      // Determine which physical 16x16 panel column
      // the pixel belongs to.
      int panelCol = x / 16;

      // Position inside that 16x16 panel
      int localX = x % 16;
      int localY = y % 16;

      // Panel order is {1,2,3}
      // so logical panel 0 -> physical panel 1
      //    logical panel 1 -> physical panel 2
      //    logical panel 2 -> physical panel 3
      int physicalPanel = panelCol + 1;

      // Each physical panel contains 256 LEDs
      int index = (physicalPanel - 1) * 256;

      // Preserve the original serpentine wiring
      if (localY & 1)
      {
        index += localY * 16 + localX;
      }
      else
      {
        index += localY * 16 + (15 - localX);
      }

      // Get pixel from the image
      CRGB color = image[sy][sx];

      // Select the correct LED strip based on vertical row
      int stripRow = y / 16;

      switch (stripRow)
      {
        case 0:
          leds1[index] = color;
          break;

        case 1:
          leds2[index] = color;
          break;

        case 2:
          leds3[index] = color;
          break;
      }
    }
  }

  // Send the finished 48x48 image to the LEDs
  FastLED.show();
}


void setup()
{
  Serial.begin(115200);

  FastLED.addLeds<WS2812B, DATA1, GRB>(leds1, 768);
  FastLED.addLeds<WS2812B, DATA2, GRB>(leds2, 768);
  FastLED.addLeds<WS2812B, DATA3, GRB>(leds3, 768);

  FastLED.setBrightness(35);

  FastLED.clear();
  FastLED.show();

  Serial.println("PANEL ORDER: 123");
  Serial.println("ORIENTATION: FLIP_Y");

  displayImage();
}


void loop()
{
  delay(5000);
}
