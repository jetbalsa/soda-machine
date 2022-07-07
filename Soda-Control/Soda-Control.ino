#include <SPI.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <Adafruit_NeoPixel.h>

#define SCREEN_WIDTH 128 // OLED display width, in pixels
#define SCREEN_HEIGHT 32 // OLED display height, in pixels
#define OLED_RESET     -1
#define SCREEN_ADDRESS 0x3C
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);



#define LED_PIN    2
#define LED_COUNT 11
Adafruit_NeoPixel strip(LED_COUNT, LED_PIN, NEO_RGB + NEO_KHZ800);

#include <CmdBuffer.hpp>
#include <CmdCallback.hpp>
#include <CmdParser.hpp>
CmdCallback<3> cmdCallback;
CmdBuffer<32> myBuffer;
CmdParser     myParser;

byte sodabuttons[] = {35,37,39,41,43,45,47,49,51,53};
bool sodabuffer[] = {0, 0, 0, 0, 0, 0, 0, 0, 0, 0};

#include <Adafruit_Sensor.h>
#include <DHT.h>
#include <DHT_U.h>
#define IRPIN 4
#define DHT11PIN  5
#define DHTTYPE DHT11
DHT_Unified dht(DHT11PIN, DHTTYPE);

/////// SERIAL COMMANDS LIB //////////////////////

/// READ BUTTONS
char strDICKBUTT[] = "b";
void getButtons(CmdParser *myParser)
{
  Serial.print("^"); 
  for(int x=0; x<10; x++) {
    Serial.print(sodabuffer[x]);
  }
   /// reset buffer
   Serial.println("^");
     for(int x=0; x<10; x++) {
      sodabuffer[x] = 1;
    }
}
/// SET SOMETHING!
char strSet[]   = "s";
void functSet(CmdParser *myParser)
{
    Serial.print(myParser->getCmdParam(1)); Serial.print(" ");
    if (myParser->equalCmdParam(1, "l")) {
        strip.setPixelColor(atoi(myParser->getCmdParam(2)), strip.Color(atoi(myParser->getCmdParam(3)), atoi(myParser->getCmdParam(4)), atoi(myParser->getCmdParam(5))));
        strip.show();
        Serial.print(myParser->getCmdParam(2)); Serial.print(" "); Serial.print(myParser->getCmdParam(3)); Serial.print(" "); Serial.print(myParser->getCmdParam(4)); Serial.print(" "); Serial.print(myParser->getCmdParam(5)); Serial.println(" ");
    }
    if (myParser->equalCmdParam(1, "o")) {
      display.clearDisplay();
      display.setTextSize(atoi(myParser->getCmdParam(2)));
      display.setCursor(atoi(myParser->getCmdParam(3)), atoi(myParser->getCmdParam(4)));
      display.print(myParser->getCmdParam(5));
      display.print(myParser->getCmdParam(6));
      display.display();
    }
    if (myParser->equalCmdParam(1, "a")) {
      if (myParser->equalCmdParam(2, "c")) {
        display.stopscroll();
      }
      if (myParser->equalCmdParam(2, "r")) {
        display.stopscroll();
        display.startscrollright(0x00, 0x0F);
      }
      if (myParser->equalCmdParam(2, "l")) {
         display.stopscroll();
         display.startscrollleft(0x00, 0x0F);
      }
      if (myParser->equalCmdParam(2, "dr")) {
         display.stopscroll();
         display.startscrolldiagright(0x00, 0x07);
      }
      if (myParser->equalCmdParam(2, "dl")) {
         display.stopscroll();
         display.startscrolldiagleft(0x00, 0x07);
      }
    }
    if (myParser->equalCmdParam(1, "temp")) {
        sensors_event_t event;
        dht.temperature().getEvent(&event);
        if (isnan(event.temperature)) {
          Serial.println(F("Error reading temperature!"));
        }
        else {
          Serial.print(F("Temperature: "));
          Serial.print(event.temperature);
          Serial.print(F("C "));
        }
        // Get humidity event and print its value.
        dht.humidity().getEvent(&event);
        if (isnan(event.relative_humidity)) {
          Serial.println(F("Error reading humidity!"));
        }
        else {
          Serial.print(F("Humidity: "));
          Serial.print(event.relative_humidity);
          Serial.println(F("%"));
        }
    }
    if (myParser->equalCmdParam(1, "ir")) {
      analogWrite(IRPIN, atoi(myParser->getCmdParam(2)));
    }
}




void setup() {
 Serial.begin(115200); // start serial! 
 //// DISPLAY STARTUP
 display.begin(SSD1306_SWITCHCAPVCC, SCREEN_ADDRESS);
 display.display();
 delay(2000);
 display.clearDisplay();
 //startup text//
 display.setRotation(2);
 display.setTextSize(2);
 display.setTextColor(SSD1306_WHITE);
 display.setCursor(10, 0);
 display.println(F(" S.O.D.A   MACHINE  "));
 display.display();
 display.startscrollright(0x00, 0x0F);
 /// END OF DISPLAY STARTUP ///

 /// PIN DETECTION STARTUP ///
  for(int x=0; x<10; x++) {
    pinMode(sodabuttons[x], INPUT_PULLUP);
  }
 ////////////////////////////

 // STRIP STARTUP //
 strip.begin();
 strip.show(); 
 strip.setBrightness(50);
 for(int i=0; i<strip.numPixels(); i++) {
  strip.setPixelColor(i, strip.Color(127,   0,   0));
  strip.show();
  delay(100);
 }
 dht.begin();
 pinMode(IRPIN, OUTPUT);
 pinMode(DHT11PIN, INPUT);
 /////////////////

 //////// REMOTE COMMANDS //////////
  myBuffer.setEcho(false);
  cmdCallback.addCmd(strDICKBUTT, &getButtons);
  cmdCallback.addCmd(strSet, &functSet);
}

void loop() {
  // CHECK SODA BUTTONS STATE
  for(int x=0; x<10; x++) {
    if(sodabuffer[x] == 1){
      sodabuffer[x] = digitalRead(sodabuttons[x]);
    }
  }
  cmdCallback.updateCmdProcessing(&myParser, &myBuffer, &Serial);
}
