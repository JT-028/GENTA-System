// Clean, minimal INMP441 recorder baseline.
//
// OLED integration (I2C 128x64):
//   VDD -> 3V3, GND -> GND, SDA -> GPIO21, SCL -> GPIO22
//   Address: 0x3C (from I2C scan)
//   Control via HTTP: /oled?value=idle|listening|thinking|correct|incorrect|error
#include <driver/i2s.h>
#include <LittleFS.h>
#define SPIFFS LittleFS  // Compatibility layer - use LittleFS with SPIFFS name
#include <WiFi.h>
#include <WebServer.h>
#include <ESPmDNS.h>
#include <Preferences.h>  // For WiFi credential storage
#include <WiFiUdp.h>
// OLED libs (U8g2 for SH1106)
#include <Wire.h>
#include <U8g2lib.h>
#include <math.h>

// Forward declarations to satisfy Arduino's auto-generated prototypes
struct Eye;         // legacy forward-declare used elsewhere
struct EyeState;    // forward-declare for functions that take EyeState& params
enum OledMode : uint8_t; // forward-declare enum used in function prototypes
// Forward-declare custom text helper so it can be used in lambdas before its definition
static void show_custom_text(const char* line1, const char* line2, int hold_ms=1200);

// (display wrapper moved below OLED init so it can access u8g2 and oledReady)

// Pins (adjust to your wiring)
#define I2S_WS 32
#define I2S_SD 25
#define I2S_SCK 33
#define I2S_PORT I2S_NUM_0

#define I2S_SAMPLE_RATE 16000
#define I2S_SAMPLE_BITS 16
#define I2S_CHANNELS 1

// Duration used only to reserve header size; real recordings are bounded by storage
// Increase this to allow much longer continuous recordings so the ESP won't
// cut off while the user is still holding the record button. Default increased
// from 20s to 120s (adjust if you have very limited SPIFFS space).
#define RECORD_TIME 120
#define FLASH_RECORD_SIZE (I2S_CHANNELS * I2S_SAMPLE_RATE * (I2S_SAMPLE_BITS/8) * RECORD_TIME)

// Network and filesystem
// WiFi credentials now stored in NVS via Preferences
const char* host = "esp32fs";
WebServer server(80);

// WiFi management globals
Preferences preferences;
const char* PREF_NAMESPACE = "wifi_config";
const char* PREF_SSID = "ssid";
const char* PREF_PASSWORD = "password";

const char recordingFilename[] = "/recording.wav";
const int WAV_HEADER_SIZE = 44;

const int buttonPin = 23;
const int greenLed = 26;

// State management moved to GENTA2 (Player). No state changes here.

// Ring buffer parameters to decouple I2S reads from SPIFFS writes
// Smaller buffer for real-time write mode with fast LittleFS
#define RING_BUFFER_SIZE (96 * 1024) // 96KB = ~3 seconds buffer (safety margin)
#define WRITE_CHUNK_SIZE 4096 // Balance between speed and responsiveness
#define MIN_RING_BUFFER_SIZE (32 * 1024) // Minimum acceptable ring buffer size

volatile bool recordingStarted = false;
volatile bool recorderTaskRunning = false;
volatile bool writerTaskRunning = false;
TaskHandle_t recorderTaskHandle = NULL;
TaskHandle_t writerTaskHandle = NULL;

// Software gain multiplier applied in writer (avoid doing large multiplies in capture)
#define SAMPLE_GAIN_MULT 3 // increase to raise loudness; clip if necessary
// If set to 1, no normalization; if 1, only software gain is applied. If 1 enables
// normalization after recording, set NORMALIZE_ON_STOP to 1 to normalize to full scale.
#define NORMALIZE_ON_STOP 0 // Disabled - normalization is slow; 3x gain is enough

// Ring buffer state
static uint8_t* ringBuf = NULL;
static size_t ringBufSize = RING_BUFFER_SIZE;
static size_t ringWritePos = 0;
static size_t ringReadPos = 0;
static SemaphoreHandle_t ringMutex = NULL;
static volatile size_t droppedBytes = 0;
// If ring allocation fails, fall back to direct writes from recorderTask
static bool useDirectWrites = false;

// ---------- OLED DISPLAY ----------
#define I2C_SDA 21
#define I2C_SCL 19   // Match wiring: SCL on GPIO19 (as per top comment)
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
// U8g2 SH1106 128x64 over hardware I2C
U8G2_SH1106_128X64_NONAME_F_HW_I2C u8g2(U8G2_R0, /* reset=*/ U8X8_PIN_NONE);
static bool oledReady = false;

// ---- Minimal display wrapper to run control_display.ino code on U8g2 ----
// Colors
enum GColor { G_COLOR_BLACK = 0, G_COLOR_WHITE = 1 };

static inline void g_set_color(int color) {
  u8g2.setDrawColor(color ? 1 : 0);
}

static inline void g_init_display() {
  // U8g2 is initialized in oledBegin(); nothing else required here.
}

static inline void g_clear_display() {
  if (!oledReady) return;
  u8g2.clearBuffer();
}

static inline void g_update_display() {
  if (!oledReady) return;
  u8g2.sendBuffer();
}

static inline void g_draw_filled_round_rect(int x, int y, int w, int h, int r, int color) {
  if (!oledReady) return;
  g_set_color(color);
  if (r < 0) r = 0;
  u8g2.drawRBox(x, y, w, h, r);
  g_set_color(G_COLOR_WHITE); // default back to white drawing
}

// Filled triangle (scanline fill)
static inline void g_draw_filled_triangle(int x0, int y0, int x1, int y1, int x2, int y2, int color) {
  if (!oledReady) return;
  auto swap_int = [](int &a, int &b){ int t = a; a = b; b = t; };
  // Sort by y
  if (y0 > y1) { swap_int(y0, y1); swap_int(x0, x1); }
  if (y1 > y2) { swap_int(y1, y2); swap_int(x1, x2); }
  if (y0 > y1) { swap_int(y0, y1); swap_int(x0, x1); }

  int total_height = y2 - y0;
  if (total_height == 0) return;
  g_set_color(color);
  for (int i = 0; i < total_height; i++) {
    bool second_half = i > (y1 - y0) || (y1 == y0);
    int segment_height = second_half ? (y2 - y1) : (y1 - y0);
    if (segment_height == 0) continue;
    float alpha = (float)i / (float)total_height;
    float beta  = (float)(i - (second_half ? (y1 - y0) : 0)) / (float)segment_height;
    int ax = x0 + (int)((x2 - x0) * alpha);
    int bx = second_half ? (x1 + (int)((x2 - x1) * beta)) : (x0 + (int)((x1 - x0) * beta));
    if (ax > bx) swap_int(ax, bx);
    int y = y0 + i;
    u8g2.drawHLine(ax, y, (uint8_t)(bx - ax + 1));
  }
  g_set_color(G_COLOR_WHITE);
}

enum OledMode : uint8_t { OLED_BOOTING, OLED_IDLE, OLED_LISTENING, OLED_ASSIST, OLED_THINKING, OLED_REPORT, OLED_QUIZ, OLED_PROCESSING, OLED_CORRECT, OLED_INCORRECT, OLED_ERROR };

// Persistent report progress value updated by /oled handler.
static volatile int report_progress_global = -1;
// Track when assist animation last ran to avoid replaying repeatedly
static unsigned long lastAssistMs = 0;
static OledMode oledMode = OLED_IDLE;
// Track whether we've shown the report-complete frame (host can poll this)
static volatile bool report_completion_played = false;
static volatile unsigned long report_completion_shown_at = 0;

static void oledClear() {
  if (!oledReady) return;
  u8g2.clearBuffer();
}

static void oledShowText(const char* l1, const char* l2 = nullptr) {
  if (!oledReady) return;
  u8g2.clearBuffer();
  // First line (medium font)
  u8g2.setFont(u8g2_font_6x12_tf);
  u8g2.drawStr(0, 14, l1);
  // Second line (small font)
  if (l2) {
    u8g2.setFont(u8g2_font_5x8_tf);
    u8g2.drawStr(0, 30, l2);
  }
  u8g2.sendBuffer();
}

static void oledShowMode(OledMode m) {
  oledMode = m;
  if (!oledReady) return;
}

static void oledTestPattern() {
  if (!oledReady) return;
  u8g2.clearBuffer();
  // Checkerboard 8x8 tiles
  for (int y = 0; y < SCREEN_HEIGHT; y += 8) {
    for (int x = 0; x < SCREEN_WIDTH; x += 8) {
      if (((x >> 3) + (y >> 3)) & 1) {
        u8g2.drawBox(x, y, 8, 8);
      }
    }
  }
  // Diagonals
  for (int x = 0; x < SCREEN_WIDTH; ++x) {
    int y = (x * SCREEN_HEIGHT) / SCREEN_WIDTH;
    u8g2.drawPixel(x, y);
    int y2 = SCREEN_HEIGHT - 1 - y;
    u8g2.drawPixel(x, y2);
  }
  // Border and label
  u8g2.drawFrame(0, 0, SCREEN_WIDTH, SCREEN_HEIGHT);
  u8g2.setFont(u8g2_font_5x8_tf);
  u8g2.drawStr(2, 8, "TEST");
  u8g2.sendBuffer();
}

// -------------------- Eye look from control_display.ino --------------------
// Constants and state
const int REF_EYE_HEIGHT = 40;
const int REF_EYE_WIDTH = 40;
const int REF_SPACE_BETWEEN_EYE = 10;
const int REF_CORNER_RADIUS = 10;
const int MIN_INNER_GAP = 6; // minimum pixels between inner edges so eyes never touch

struct EyeState {
  int height;
  int width;
  int x;
  int y;
};

static EyeState left_eye, right_eye;
static int corner_radius = REF_CORNER_RADIUS;

static int calculate_safe_radius(int r, int w, int h) {
  if (w < 2 * (r + 1)) r = (w / 2) - 1;
  if (h < 2 * (r + 1)) r = (h / 2) - 1;
  return (r < 0) ? 0 : r;
}

// Ensure the inner edges of left and right eyes keep at least a minimum gap
static void enforce_min_gap(EyeState &l, EyeState &r, int minGap) {
  int innerRightOfLeft = l.x + l.width / 2;
  int innerLeftOfRight = r.x - r.width / 2;
  int gap = innerLeftOfRight - innerRightOfLeft;
  if (gap < minGap) {
    int push = (minGap - gap + 1) / 2; // split outward
    l.x -= push;
    r.x += push;
    // Clamp to screen bounds to avoid going off-screen
    int lMinX = l.width / 2;
    int rMaxX = SCREEN_WIDTH - r.width / 2;
    if (l.x < lMinX) l.x = lMinX;
    if (r.x > rMaxX) r.x = rMaxX;
  }
}

static void draw_eyes() {
  int r_left = calculate_safe_radius(corner_radius, left_eye.width, left_eye.height);
  int x_left = (int)(left_eye.x - left_eye.width / 2);
  int y_left = (int)(left_eye.y - left_eye.height / 2);
  g_draw_filled_round_rect(x_left, y_left, left_eye.width, left_eye.height, r_left, G_COLOR_WHITE);

  int r_right = calculate_safe_radius(corner_radius, right_eye.width, right_eye.height);
  int x_right = (int)(right_eye.x - right_eye.width / 2);
  int y_right = (int)(right_eye.y - right_eye.height / 2);
  g_draw_filled_round_rect(x_right, y_right, right_eye.width, right_eye.height, r_right, G_COLOR_WHITE);
}

static void draw_frame() {
  g_clear_display();
  draw_eyes();
  g_update_display();
}

static void reset_eyes(bool update=true) {
  left_eye.height = REF_EYE_HEIGHT;
  left_eye.width = REF_EYE_WIDTH;
  right_eye.height = REF_EYE_HEIGHT;
  right_eye.width = REF_EYE_WIDTH;

  left_eye.x = SCREEN_WIDTH / 2 - REF_EYE_WIDTH / 2 - REF_SPACE_BETWEEN_EYE / 2;
  left_eye.y = SCREEN_HEIGHT / 2;
  right_eye.x = SCREEN_WIDTH / 2 + REF_EYE_WIDTH / 2 + REF_SPACE_BETWEEN_EYE / 2;
  right_eye.y = SCREEN_HEIGHT / 2;

  corner_radius = REF_CORNER_RADIUS;
  if (update) draw_frame();
}

static void blink(int speed=12) {
  reset_eyes(false);
  for (int i=0; i<3; i++) {
    left_eye.height -= speed;
    right_eye.height -= speed;
    int current_h = left_eye.height;
    int mapped_radius = map(current_h, 4, REF_EYE_HEIGHT, 1, REF_CORNER_RADIUS);
    corner_radius = min(mapped_radius, current_h / 2);
    left_eye.width += 3;
    right_eye.width += 3;
    draw_frame();
    delay(1);
  }
  for (int i=0; i<3; i++) {
    left_eye.height += speed;
    right_eye.height += speed;
    int current_h = left_eye.height;
    int mapped_radius = map(current_h, 4, REF_EYE_HEIGHT, 1, REF_CORNER_RADIUS);
    corner_radius = min(mapped_radius, current_h / 2);
    left_eye.width -= 3;
    right_eye.width -= 3;
    draw_frame();
    delay(1);
  }
  reset_eyes(true);
}

// ============ COZMO-STYLE EXPRESSIONS (Based on Reference Image) ============

// SAD: Looking down with droopy eyes (Cozmo reference)
static void sad_eyes() {
  reset_eyes(true);
  
  // Animate eyes looking down sadly
  for (int i = 0; i < 8; i++) {
    left_eye.y = SCREEN_HEIGHT / 2 + i;
    right_eye.y = SCREEN_HEIGHT / 2 + i;
    left_eye.height = REF_EYE_HEIGHT - i;
    right_eye.height = REF_EYE_HEIGHT - i;
    draw_frame();
    delay(30);
  }
  
  delay(1500);
  reset_eyes(true);
}

// WORRIED: Raised inner corners, slight squint
static void worried_eyes() {
  reset_eyes(true);
  
  // Eyes move slightly together and up
  for (int i = 0; i < 6; i++) {
    left_eye.x += 1;
    right_eye.x -= 1;
    left_eye.y -= 1;
    right_eye.y -= 1;
    left_eye.height = REF_EYE_HEIGHT - i / 2;
    right_eye.height = REF_EYE_HEIGHT - i / 2;
    draw_frame();
    delay(40);
  }
  
  delay(1200);
  reset_eyes(true);
}

// FOCUSED/DETERMINED: Narrow, intense eyes
static void focused_eyes() {
  reset_eyes(true);
  
  // Narrow eyes intensely
  for (int i = 0; i < 10; i++) {
    left_eye.height = REF_EYE_HEIGHT - i;
    right_eye.height = REF_EYE_HEIGHT - i;
    left_eye.width = REF_EYE_WIDTH + i / 2;
    right_eye.width = REF_EYE_WIDTH + i / 2;
    draw_frame();
    delay(25);
  }
  
  delay(1500);
  reset_eyes(true);
}

// ANNOYED: Asymmetric squint, one eye more closed
static void annoyed_eyes() {
  reset_eyes(true);
  
  // Asymmetric squint animation
  for (int i = 0; i < 8; i++) {
    left_eye.height = REF_EYE_HEIGHT - i * 2;
    right_eye.height = REF_EYE_HEIGHT - i;
    left_eye.width = REF_EYE_WIDTH + i;
    right_eye.width = REF_EYE_WIDTH + i / 2;
    draw_frame();
    delay(35);
  }
  
  delay(1300);
  reset_eyes(true);
}

// SKEPTICAL: One eye smaller, slight tilt
static void skeptical_eyes() {
  reset_eyes(true);
  
  // One eye narrows skeptically
  for (int i = 0; i < 8; i++) {
    left_eye.height = REF_EYE_HEIGHT - i;
    left_eye.y = SCREEN_HEIGHT / 2 + i / 2;
    right_eye.y = SCREEN_HEIGHT / 2 - i / 2;
    draw_frame();
    delay(35);
  }
  
  delay(1400);
  reset_eyes(true);
}

// FRUSTRATED/BORED: Eyes look to the side, droopy
static void frustrated_eyes() {
  reset_eyes(true);
  
  // Eyes drift to the side lazily
  for (int i = 0; i < 10; i++) {
    left_eye.x -= 1;
    right_eye.x -= 1;
    left_eye.height = REF_EYE_HEIGHT - i / 2;
    right_eye.height = REF_EYE_HEIGHT - i / 2;
    draw_frame();
    delay(40);
  }
  
  delay(1500);
  reset_eyes(true);
}

// UNIMPRESSED: Flat, half-closed eyes
static void unimpressed_eyes() {
  reset_eyes(true);
  
  // Eyes slowly droop to half-mast
  for (int i = 0; i < 12; i++) {
    left_eye.height = REF_EYE_HEIGHT - i;
    right_eye.height = REF_EYE_HEIGHT - i;
    draw_frame();
    delay(30);
  }
  
  delay(1600);
  reset_eyes(true);
}

// SLEEPY: Very narrow eyes, slow blink
static void sleepy_eyes() {
  reset_eyes(true);
  
  // Slow droop
  for (int i = 0; i < 15; i++) {
    left_eye.height = REF_EYE_HEIGHT - i;
    right_eye.height = REF_EYE_HEIGHT - i;
    draw_frame();
    delay(50);
  }
  
  // Almost closed
  delay(800);
  
  // Slow open
  for (int i = 15; i >= 0; i--) {
    left_eye.height = REF_EYE_HEIGHT - i;
    right_eye.height = REF_EYE_HEIGHT - i;
    draw_frame();
    delay(50);
  }
  
  reset_eyes(true);
}

// SUSPICIOUS: Eyes look sideways with slight squint
static void suspicious_eyes() {
  reset_eyes(true);
  
  // Slow side-eye
  for (int i = 0; i < 10; i++) {
    left_eye.x += 1;
    right_eye.x += 1;
    left_eye.height = REF_EYE_HEIGHT - i / 3;
    right_eye.height = REF_EYE_HEIGHT - i / 3;
    draw_frame();
    delay(40);
  }
  
  delay(1400);
  reset_eyes(true);
}

// SQUINT: Both eyes narrow significantly
static void squint_eyes() {
  reset_eyes(true);
  
  for (int i = 0; i < 14; i++) {
    left_eye.height = REF_EYE_HEIGHT - i;
    right_eye.height = REF_EYE_HEIGHT - i;
    left_eye.width = REF_EYE_WIDTH + i / 2;
    right_eye.width = REF_EYE_WIDTH + i / 2;
    draw_frame();
    delay(20);
  }
  
  delay(1200);
  reset_eyes(true);
}

// ANGRY: Sharp inward tilt, narrowed eyes
static void angry_eyes() {
  reset_eyes(true);
  
  // Eyes narrow and move inward angrily
  for (int i = 0; i < 8; i++) {
    left_eye.x += 2;
    right_eye.x -= 2;
    left_eye.height = REF_EYE_HEIGHT - i;
    right_eye.height = REF_EYE_HEIGHT - i;
    // Angry tilt
    left_eye.y = SCREEN_HEIGHT / 2 + i / 2;
    right_eye.y = SCREEN_HEIGHT / 2 - i / 2;
    draw_frame();
    delay(25);
  }
  
  delay(1500);
  reset_eyes(true);
}

// FURIOUS: More intense anger, rapid animation
static void furious_eyes() {
  reset_eyes(true);
  
  // Rapid angry animation
  for (int i = 0; i < 10; i++) {
    left_eye.x += 2;
    right_eye.x -= 2;
    left_eye.height = REF_EYE_HEIGHT - i * 1.2;
    right_eye.height = REF_EYE_HEIGHT - i * 1.2;
    left_eye.y = SCREEN_HEIGHT / 2 + i;
    right_eye.y = SCREEN_HEIGHT / 2 - i;
    draw_frame();
    delay(15);
  }
  
  // Quick shake
  for (int shake = 0; shake < 3; shake++) {
    left_eye.x += 2; right_eye.x += 2;
    draw_frame(); delay(30);
    left_eye.x -= 4; right_eye.x -= 4;
    draw_frame(); delay(30);
    left_eye.x += 2; right_eye.x += 2;
    draw_frame(); delay(30);
  }
  
  delay(800);
  reset_eyes(true);
}

// SCARED: Wide eyes, looking up
static void scared_eyes() {
  reset_eyes(true);
  
  // Quick expansion (startled)
  for (int i = 0; i < 8; i++) {
    left_eye.width = REF_EYE_WIDTH + i * 2;
    left_eye.height = REF_EYE_HEIGHT + i * 2;
    right_eye.width = REF_EYE_WIDTH + i * 2;
    right_eye.height = REF_EYE_HEIGHT + i * 2;
    left_eye.y = SCREEN_HEIGHT / 2 - i;
    right_eye.y = SCREEN_HEIGHT / 2 - i;
    draw_frame();
    delay(20);
  }
  
  // Hold scared look
  delay(1200);
  
  // Quick nervous blinks
  for (int b = 0; b < 2; b++) {
    left_eye.height = 4; right_eye.height = 4;
    draw_frame(); delay(80);
    left_eye.height = REF_EYE_HEIGHT + 16;
    right_eye.height = REF_EYE_HEIGHT + 16;
    draw_frame(); delay(120);
  }
  
  reset_eyes(true);
}

// AWE: Very wide, amazed eyes
static void awe_eyes() {
  reset_eyes(true);
  
  // Slow expansion with wonder
  for (int i = 0; i < 12; i++) {
    left_eye.width = REF_EYE_WIDTH + i * 2;
    left_eye.height = REF_EYE_HEIGHT + i * 2;
    right_eye.width = REF_EYE_WIDTH + i * 2;
    right_eye.height = REF_EYE_HEIGHT + i * 2;
    corner_radius = REF_CORNER_RADIUS + i / 2;
    draw_frame();
    delay(40);
  }
  
  delay(1800);
  reset_eyes(true);
}

// GLEE: Happy with slight bounce (like happy but more playful)
static void glee_eyes() {
  reset_eyes(true);
  
  // Quick bounce up
  for (int i = 0; i < 6; i++) {
    left_eye.y = SCREEN_HEIGHT / 2 - i;
    right_eye.y = SCREEN_HEIGHT / 2 - i;
    left_eye.width = REF_EYE_WIDTH + i;
    right_eye.width = REF_EYE_WIDTH + i;
    draw_frame();
    delay(30);
  }
  
  // Settle with happy curves
  int offset = REF_EYE_HEIGHT / 2;
  for (int i = 0; i < 8; i++) {
    g_draw_filled_triangle(left_eye.x - left_eye.width / 2 - 1, left_eye.y + offset,
                           left_eye.x + left_eye.width / 2 + 1, left_eye.y + 5 + offset,
                           left_eye.x - left_eye.width / 2 - 1, left_eye.y + left_eye.height + offset,
                           G_COLOR_BLACK);
    g_draw_filled_triangle(right_eye.x + right_eye.width / 2 + 1, right_eye.y + offset,
                           right_eye.x - right_eye.width / 2 - 2, right_eye.y + 5 + offset,
                           right_eye.x + right_eye.width / 2 + 1, right_eye.y + right_eye.height + offset,
                           G_COLOR_BLACK);
    offset -= 2;
    g_update_display();
    delay(20);
  }
  
  delay(1500);
  reset_eyes(true);
}

static void sleep_anim() { // renamed to avoid clash with ::sleep
  reset_eyes(false);
  left_eye.height = 2;
  left_eye.width = REF_EYE_WIDTH;
  right_eye.height = 2;
  right_eye.width = REF_EYE_WIDTH;
  corner_radius = 0;
  draw_frame();
}

static void wakeup() {
  reset_eyes(false);
  for (int h = 2; h <= REF_EYE_HEIGHT; h += 2) {
    left_eye.height = h;
    right_eye.height = h;
    int mapped_radius = map(h, 2, REF_EYE_HEIGHT, 1, REF_CORNER_RADIUS);
    corner_radius = min(mapped_radius, h / 2);
    draw_frame();
  }
}

// Booting animation - rotating dots around a center point
static void booting_animation() {
  if (!oledReady) return;
  
  g_clear_display();
  
  // Center of screen
  int cx = SCREEN_WIDTH / 2;
  int cy = SCREEN_HEIGHT / 2;
  
  // Draw "GENTA" text at top
  u8g2.setFont(u8g2_font_7x13_tf);
  u8g2.drawStr(cx - 21, 12, "GENTA");
  
  // Draw "Booting..." text
  u8g2.setFont(u8g2_font_6x10_tf);
  u8g2.drawStr(cx - 30, cy + 20, "Booting...");
  
  // Animated rotating dots (like a loading spinner)
  static int angle = 0;
  const int radius = 12;
  const int numDots = 8;
  
  for (int i = 0; i < numDots; i++) {
    float currentAngle = (angle + (i * 360.0 / numDots)) * PI / 180.0;
    int x = cx + (int)(radius * cos(currentAngle));
    int y = cy + (int)(radius * sin(currentAngle));
    
    // Fade dots based on position (trail effect)
    if (i < 2) {
      u8g2.drawDisc(x, y, 2);  // Full brightness
    } else if (i < 4) {
      u8g2.drawDisc(x, y, 1);  // Medium
    } else {
      u8g2.drawPixel(x, y);    // Dim
    }
  }
  
  g_update_display();
  angle = (angle + 45) % 360;  // Rotate 45 degrees each frame
}

// System ready animation - quick pulse effect
static void system_ready_animation() {
  if (!oledReady) return;
  
  int cx = SCREEN_WIDTH / 2;
  int cy = SCREEN_HEIGHT / 2;
  
  // Show "READY!" message with expanding circle
  for (int r = 0; r < 30; r += 3) {
  g_clear_display();
  u8g2.drawCircle(cx, cy, r);
  u8g2.setFont(u8g2_font_7x13B_tf);
  // Center the READY! text horizontally using the font width
  int tw = u8g2.getStrWidth("READY!");
  u8g2.drawStr(cx - (tw / 2), cy + 5, "READY!");
    g_update_display();
    delay(30);
  }
  
  delay(500);
}

static void happy_eye() {
  reset_eyes(true);
  int offset = REF_EYE_HEIGHT / 2;
  for (int i=0; i<10; i++) {
    g_draw_filled_triangle(left_eye.x - left_eye.width / 2 - 1, left_eye.y + offset,
                           left_eye.x + left_eye.width / 2 + 1, left_eye.y + 5 + offset,
                           left_eye.x - left_eye.width / 2 - 1, left_eye.y + left_eye.height + offset,
                           G_COLOR_BLACK);
    g_draw_filled_triangle(right_eye.x + right_eye.width / 2 + 1, right_eye.y + offset,
                           right_eye.x - right_eye.width / 2 - 2, right_eye.y + 5 + offset,
                           right_eye.x + right_eye.width / 2 + 1, right_eye.y + right_eye.height + offset,
                           G_COLOR_BLACK);
    offset -= 2;
    g_update_display();
    delay(1);
  }
  // Keep happy eyes visible for longer (5 seconds instead of 1 second)
  // This allows students to appreciate the happy expression
  delay(5000);
  reset_eyes(true);
}

// Cozmo-style excited shimmy - rapid side-to-side eye wobble
static void excited_shimmy() {
  reset_eyes(true);
  
  // Make eyes slightly bigger for excitement
  left_eye.width = REF_EYE_WIDTH + 8;
  left_eye.height = REF_EYE_HEIGHT + 6;
  right_eye.width = REF_EYE_WIDTH + 8;
  right_eye.height = REF_EYE_HEIGHT + 6;
  
  // Rapid shimmy left-right
  for (int cycle = 0; cycle < 5; cycle++) {
    // Shimmy right
    for (int i = 0; i < 3; i++) {
      left_eye.x += 2;
      right_eye.x += 2;
      draw_frame();
      delay(15);
    }
    // Shimmy left
    for (int i = 0; i < 3; i++) {
      left_eye.x -= 2;
      right_eye.x -= 2;
      draw_frame();
      delay(15);
    }
  }
  
  // Quick bounce at the end
  for (int i = 0; i < 2; i++) {
    left_eye.y -= 3;
    right_eye.y -= 3;
    draw_frame();
    delay(30);
    left_eye.y += 3;
    right_eye.y += 3;
    draw_frame();
    delay(30);
  }
  
  delay(500);
  reset_eyes(true);
}

// Cozmo-style curious look - one eye bigger than the other (asymmetric)
static void curious_look() {
  reset_eyes(true);
  
  // Phase 1: Initial curious tilt - eyes look to the side
  // Both eyes move left and widen slightly (looking at something interesting)
  for (int i = 0; i < 6; i++) {
    left_eye.x = SCREEN_WIDTH / 2 - REF_EYE_WIDTH / 2 - REF_SPACE_BETWEEN_EYE / 2 - i * 2;
    right_eye.x = SCREEN_WIDTH / 2 + REF_EYE_WIDTH / 2 + REF_SPACE_BETWEEN_EYE / 2 - i * 2;
    
    // Widen eyes slightly (curious/attentive)
    left_eye.width = REF_EYE_WIDTH + i;
    right_eye.width = REF_EYE_WIDTH + i;
    
    draw_frame();
    delay(40);
  }
  
  // Phase 2: Asymmetric curiosity - right eye bigger (Cozmo's signature curious look)
  for (int i = 0; i < 8; i++) {
    // Right eye grows bigger (curious/interested)
    right_eye.width = REF_EYE_WIDTH + 6 + i * 2;
    right_eye.height = REF_EYE_HEIGHT + i * 2;
    
    // Left eye stays normal or slightly smaller
    left_eye.width = REF_EYE_WIDTH + 6 - i / 2;
    left_eye.height = REF_EYE_HEIGHT - i / 2;
    
    // Slight head tilt effect (shift eyes vertically)
    left_eye.y = SCREEN_HEIGHT / 2 + i / 2;
    right_eye.y = SCREEN_HEIGHT / 2 - i / 2;
    
    draw_frame();
    delay(35);
  }
  
  // Hold asymmetric curious look briefly
  delay(300);
  
  // Phase 3: Look to the other side (scanning curiously)
  // Move eyes to the right smoothly
  for (int i = 0; i < 12; i++) {
    left_eye.x = SCREEN_WIDTH / 2 - REF_EYE_WIDTH / 2 - REF_SPACE_BETWEEN_EYE / 2 - 12 + i * 2;
    right_eye.x = SCREEN_WIDTH / 2 + REF_EYE_WIDTH / 2 + REF_SPACE_BETWEEN_EYE / 2 - 12 + i * 2;
    
    draw_frame();
    delay(35);
  }
  
  // Hold for a moment
  delay(400);
  
  // Phase 4: Quick look back center with a slight bounce
  for (int i = 0; i < 6; i++) {
    left_eye.x = SCREEN_WIDTH / 2 - REF_EYE_WIDTH / 2 - REF_SPACE_BETWEEN_EYE / 2 + 12 - i * 2;
    right_eye.x = SCREEN_WIDTH / 2 + REF_EYE_WIDTH / 2 + REF_SPACE_BETWEEN_EYE / 2 + 12 - i * 2;
    
    // Slight vertical bounce (curiosity satisfied)
    int bounce = (i < 3) ? -i : (6 - i);
    left_eye.y = SCREEN_HEIGHT / 2 + left_eye.y - SCREEN_HEIGHT / 2 + bounce;
    right_eye.y = SCREEN_HEIGHT / 2 + right_eye.y - SCREEN_HEIGHT / 2 + bounce;
    
    draw_frame();
    delay(40);
  }
  
  // Return to normal
  reset_eyes(true);
}

// Cozmo-style surprised - eyes suddenly wide open
static void surprised_look() {
  reset_eyes(true);
  
  // Sudden expansion (surprise!)
  for (int i = 0; i < 6; i++) {
    left_eye.width = REF_EYE_WIDTH + i * 4;
    left_eye.height = REF_EYE_HEIGHT + i * 3;
    right_eye.width = REF_EYE_WIDTH + i * 4;
    right_eye.height = REF_EYE_HEIGHT + i * 3;
    
    // Eyes move slightly up (startled)
    left_eye.y = SCREEN_HEIGHT / 2 - i;
    right_eye.y = SCREEN_HEIGHT / 2 - i;
    
    corner_radius = REF_CORNER_RADIUS + i;
    
    draw_frame();
    delay(20);
  }
  
  // Hold surprise
  delay(800);
  
  // Quick blink (processing surprise)
  left_eye.height = 4;
  right_eye.height = 4;
  draw_frame();
  delay(100);
  
  // Return to normal
  reset_eyes(true);
}

// --------- Smooth pose transition helpers for seamless look changes ---------
static void compute_idle_pose(EyeState &l, EyeState &r, int &r_corner) {
  l.height = REF_EYE_HEIGHT; l.width = REF_EYE_WIDTH;
  r.height = REF_EYE_HEIGHT; r.width = REF_EYE_WIDTH;
  l.x = SCREEN_WIDTH / 2 - REF_EYE_WIDTH / 2 - REF_SPACE_BETWEEN_EYE / 2;
  l.y = SCREEN_HEIGHT / 2;
  r.x = SCREEN_WIDTH / 2 + REF_EYE_WIDTH / 2 + REF_SPACE_BETWEEN_EYE / 2;
  r.y = SCREEN_HEIGHT / 2;
  r_corner = REF_CORNER_RADIUS;
}

static void compute_listening_pose(EyeState &l, EyeState &r, int &r_corner) {
  // Base from idle center, then apply subtle inward/up shifts and squint/widen
  compute_idle_pose(l, r, r_corner);
  l.x  += 4;  r.x -= 4;
  l.y  -= 3;  r.y -= 3;
  l.height  = REF_EYE_HEIGHT - 6; r.height = REF_EYE_HEIGHT - 6;
  l.width   = REF_EYE_WIDTH + 6;  r.width  = REF_EYE_WIDTH + 6;
  r_corner  = min(REF_CORNER_RADIUS, l.height / 2);
  enforce_min_gap(l, r, MIN_INNER_GAP);
}

static void compute_thinking_pose(EyeState &l, EyeState &r, int &r_corner) {
  // Not used for the new thinking animation - we'll draw custom inverted arcs
  // Keep standard pose as fallback
  compute_idle_pose(l, r, r_corner);
}

static void animate_to_pose(const EyeState &targetL, const EyeState &targetR, int targetCorner, int frames = 8, int frameDelay = 6) {
  // Start from current state
  EyeState startL = left_eye;
  EyeState startR = right_eye;
  int startCorner = corner_radius;
  if (frames < 1) frames = 1;
  for (int i = 1; i <= frames; ++i) {
    // linear interpolation
    left_eye.x = startL.x + (targetL.x - startL.x) * i / frames;
    left_eye.y = startL.y + (targetL.y - startL.y) * i / frames;
    left_eye.width  = startL.width  + (targetL.width  - startL.width)  * i / frames;
    left_eye.height = startL.height + (targetL.height - startL.height) * i / frames;
    right_eye.x = startR.x + (targetR.x - startR.x) * i / frames;
    right_eye.y = startR.y + (targetR.y - startR.y) * i / frames;
    right_eye.width  = startR.width  + (targetR.width  - startR.width)  * i / frames;
    right_eye.height = startR.height + (targetR.height - startR.height) * i / frames;
    corner_radius = startCorner + (targetCorner - startCorner) * i / frames;
    draw_frame();
    delay(frameDelay);
  }
  // Snap to exact target to avoid rounding drift
  left_eye = targetL; right_eye = targetR; corner_radius = targetCorner;
  draw_frame();
}

static void go_idle() {
  EyeState tL, tR; int tC;
  compute_idle_pose(tL, tR, tC);
  animate_to_pose(tL, tR, tC, 8, 6);
}

// Focused, attentive pose used when listening
static void listening_look() {
  EyeState tL, tR; int tC;
  compute_listening_pose(tL, tR, tC);
  animate_to_pose(tL, tR, tC, 8, 6);
}

static void thinking_look() {
  // Cozmo-style thinking expression: inverted arcs (upside-down curves)
  // Like the second reference image - opposite of happy eyes
  reset_eyes(true);
  
  // Draw inverted curves that point downward (thinking/pondering look)
  int offset = -REF_EYE_HEIGHT / 2;  // Start from top, negative offset for inverted
  
  for (int i = 0; i < 10; i++) {
    // Left eye: inverted triangle pointing down from top
    g_draw_filled_triangle(left_eye.x - left_eye.width / 2 - 1, left_eye.y - offset,
                           left_eye.x + left_eye.width / 2 + 1, left_eye.y - 5 - offset,
                           left_eye.x - left_eye.width / 2 - 1, left_eye.y - left_eye.height - offset,
                           G_COLOR_BLACK);
    
    // Right eye: inverted triangle pointing down from top
    g_draw_filled_triangle(right_eye.x + right_eye.width / 2 + 1, right_eye.y - offset,
                           right_eye.x - right_eye.width / 2 - 2, right_eye.y - 5 - offset,
                           right_eye.x + right_eye.width / 2 + 1, right_eye.y - right_eye.height - offset,
                           G_COLOR_BLACK);
    
    offset += 2;  // Move curves downward
    g_update_display();
    delay(1);
  }
  
  // Hold the thinking expression
  delay(100);
}

// Processing animation with animated dots (. .. ...)
// Shows ONLY animated dots in center - no eyes (clean processing indicator)
static void processing_anim() {
  if (!oledReady) return;
  static uint8_t dotCount = 1;
  static unsigned long lastUpdate = 0;
  unsigned long now = millis();
  
  // Update dots every 500ms
  if (now - lastUpdate > 500) {
    lastUpdate = now;
    dotCount = (dotCount % 3) + 1;  // Cycle 1->2->3->1
  }
  
  // Clear display - show ONLY dots (no eyes)
  u8g2.clearBuffer();
  
  // Draw animated dots centered on screen (vertically and horizontally)
  u8g2.setFont(u8g2_font_9x15_tf);  // Larger font for dots
  const char* dots[3] = {".", "..", "..."};
  int textWidth = u8g2.getStrWidth(dots[dotCount-1]);
  int textHeight = u8g2.getMaxCharHeight();
  
  // Center both horizontally and vertically
  int x = (SCREEN_WIDTH - textWidth) / 2;
  int y = (SCREEN_HEIGHT + textHeight) / 2;  // Center vertically
  
  u8g2.drawStr(x, y, dots[dotCount-1]);
  u8g2.sendBuffer();
}

// Report creation animation: draws a progress bar and percentage on the OLED.
// If progress < 0 the function shows an indeterminate state (animated dots).
static void report_creation_draw(int progress) {
  // Animated, smoothing progress drawer. Called frequently from loop()
  if (!oledReady) {
    Serial.println("report_creation_draw called but OLED not ready");
    return;
  }

  // Keep a smoothly-changing displayedProgress so the bar animates between
  // values rather than jumping; this is non-blocking and safe to call often.
  static int displayedProgress = 0;
  // Limit rate of change per frame to make the fill visibly animate
  const int MAX_STEP = 3; // percent per frame

  // Normalize target progress
  int target = progress;
  if (target < 0) target = 0; // treat negative as 0 for now (no indeterminate dots)
  if (target > 100) target = 100;

  if (displayedProgress < target) {
    displayedProgress += min(MAX_STEP, target - displayedProgress);
  } else if (displayedProgress > target) {
    displayedProgress -= min(MAX_STEP, displayedProgress - target);
  }

  // Completion detection: when the displayed progress reaches 100% for the
  // first time, show a short one-shot completion frame and hold it for a
  // short period. We expose a global flag so the host can poll /oled_status
  // and wait until the ESP confirms the completion animation was shown.
  const unsigned long COMPLETE_HOLD_MS = 1200UL;

  Serial.print("report_creation_draw: target="); Serial.print(target);
  Serial.print(" displayed="); Serial.println(displayedProgress);

  // If we recently showed the completion frame, keep holding it for the
  // configured hold duration to ensure the user sees it.
  if (report_completion_shown_at != 0UL) {
    if ((millis() - report_completion_shown_at) < COMPLETE_HOLD_MS) {
      // Still within hold window — do not overwrite completion frame
      return;
    } else {
      // Hold time elapsed — clear marker and allow normal drawing to resume
      report_completion_shown_at = 0UL;
      // Keep report_completion_played true so we don't replay until progress drops
    }
  }

  if (target >= 100 && displayedProgress >= 100) {
    if (!report_completion_played) {
      report_completion_played = true;
      report_completion_shown_at = millis();

      // Draw a brief completion frame with message
      u8g2.clearBuffer();
      u8g2.setFont(u8g2_font_7x13B_tf);
      const char* doneTxt = "Done!";
      int tw = u8g2.getStrWidth(doneTxt);
      u8g2.drawStr((SCREEN_WIDTH - tw) / 2, SCREEN_HEIGHT / 2 - 6, doneTxt);

      u8g2.setFont(u8g2_font_6x10_tf);
      const char* msg = "Creation of Reports Complete";
      int mw = u8g2.getStrWidth(msg);
      u8g2.drawStr((SCREEN_WIDTH - mw) / 2, SCREEN_HEIGHT / 2 + 12, msg);
      u8g2.sendBuffer();

      // Return so the message remains visible; loop() will call this again and
      // hold until COMPLETE_HOLD_MS has elapsed, at which point normal drawing resumes.
      return;
    }
    // If report_completion_played is true we've already shown completion; fall
    // through to normal drawing (or return earlier if within hold window).
  } else {
    // If target drops below 100 again, allow completion animation to play later
    report_completion_played = false;
    report_completion_shown_at = 0UL;
  }

  u8g2.clearBuffer();

  // Title centered
  const char* title = "Creating reports";
  u8g2.setFont(u8g2_font_6x10_tf);
  int titleW = u8g2.getStrWidth(title);
  u8g2.drawStr((SCREEN_WIDTH - titleW) / 2, 12, title);

  // Progress bar frame
  const int barW = SCREEN_WIDTH - 28; // margin both sides
  const int barH = 10;
  const int barX = (SCREEN_WIDTH - barW) / 2;
  const int barY = SCREEN_HEIGHT / 2 - (barH/2);
  u8g2.drawFrame(barX, barY, barW, barH);

  // Draw filled portion based on displayedProgress
  int fillW = (displayedProgress * (barW - 2)) / 100; // leave 1px padding inside frame
  if (fillW > 0) {
    // Draw a filled rounded rectangle to match the frame
    // Use drawBox for speed; ensure we don't overrun the frame
    if (fillW > (barW - 2)) fillW = barW - 2;
    u8g2.drawBox(barX + 1, barY + 1, fillW, barH - 2);
  }

  // Percentage text below bar (always show, even for 0)
  char pctBuf[8];
  snprintf(pctBuf, sizeof(pctBuf), "%d%%", displayedProgress);
  u8g2.setFont(u8g2_font_5x8_tf);
  int pctW = u8g2.getStrWidth(pctBuf);
  u8g2.drawStr((SCREEN_WIDTH - pctW) / 2, barY + barH + 12, pctBuf);

  // NOTE: indeterminate dots removed - we always show a bar and percent.

  u8g2.sendBuffer();
}

// Intro poses for power-on sequence
static void compute_right_peek_pose(EyeState &l, EyeState &r, int &r_corner) {
  // Look to the right; right eye appears bigger
  compute_idle_pose(l, r, r_corner);
  l.x += 8;  r.x += 8;          // shift gaze to the right
  l.y -= 1;  r.y -= 1;          // slight lift
  // emphasize larger right eye, smaller left
  r.width  = REF_EYE_WIDTH + 10; r.height = REF_EYE_HEIGHT + 4;
  l.width  = REF_EYE_WIDTH - 6;  l.height = REF_EYE_HEIGHT - 4;
  r_corner = min(REF_CORNER_RADIUS, min(l.height, r.height) / 2);
  enforce_min_gap(l, r, MIN_INNER_GAP);
}

static void compute_left_peek_pose(EyeState &l, EyeState &r, int &r_corner) {
  // Look to the left; left eye appears bigger
  compute_idle_pose(l, r, r_corner);
  l.x -= 8;  r.x -= 8;          // shift gaze to the left
  l.y -= 1;  r.y -= 1;          // slight lift
  // emphasize larger left eye, smaller right
  l.width  = REF_EYE_WIDTH + 10; l.height = REF_EYE_HEIGHT + 4;
  r.width  = REF_EYE_WIDTH - 6;  r.height = REF_EYE_HEIGHT - 4;
  r_corner = min(REF_CORNER_RADIUS, min(l.height, r.height) / 2);
  enforce_min_gap(l, r, MIN_INNER_GAP);
}

static void look_right_intro() {
  EyeState tL, tR; int tC; compute_right_peek_pose(tL, tR, tC);
  animate_to_pose(tL, tR, tC, 10, 8);
}

static void look_left_intro() {
  EyeState tL, tR; int tC; compute_left_peek_pose(tL, tR, tC);
  animate_to_pose(tL, tR, tC, 10, 8);
}

static void saccade(int direction_x, int direction_y) {
  const int MOVEMENT_AMPLITUDE_X = 8;
  const int MOVEMENT_AMPLITUDE_Y = 6;
  const int BLINK_AMPLITUDE = 8;
  for (int i = 1; i <= 2; i++) {
    left_eye.x += MOVEMENT_AMPLITUDE_X * direction_x;
    right_eye.x += MOVEMENT_AMPLITUDE_X * direction_x;
    left_eye.y += MOVEMENT_AMPLITUDE_Y * direction_y;
    right_eye.y += MOVEMENT_AMPLITUDE_Y * direction_y;
    int height_change = (i == 1) ? -BLINK_AMPLITUDE : BLINK_AMPLITUDE;
    right_eye.height += height_change;
    left_eye.height += height_change;
    draw_frame();
    delay(1);
  }
}

// Idle behavior: glance to each corner and return
// Idle behavior: pick a random glance direction, move there smoothly, hold, return to center
static void idle_random_glance() {
  // Build target from idle pose with random offsets
  EyeState idleL, idleR; int idleC;
  compute_idle_pose(idleL, idleR, idleC);

  // Directions: emphasize left/right, but include diagonals and up/down
  const int dirs[8][2] = {
    {-1,  0}, { 1,  0}, // left, right
    {-1, -1}, { 1, -1}, // up-left, up-right
    {-1,  1}, { 1,  1}, // down-left, down-right
    { 0, -1}, { 0,  1}  // up, down
  };
  int choice = (int)random(0, 8);
  int sx = dirs[choice][0];
  int sy = dirs[choice][1];

  // Random amplitudes for natural motion
  int ax = 8 + (int)random(0, 5); // 8..12 px
  int ay = 5 + (int)random(0, 4); // 5..8 px

  EyeState targetL = idleL;
  EyeState targetR = idleR;
  targetL.x += ax * sx; targetR.x += ax * sx;
  targetL.y += ay * sy; targetR.y += ay * sy;
  // Slight squint and widen for a glance
  int targetC = min(REF_CORNER_RADIUS, (REF_EYE_HEIGHT - 4) / 2);
  targetL.height = REF_EYE_HEIGHT - 4; targetR.height = REF_EYE_HEIGHT - 4;
  targetL.width  = REF_EYE_WIDTH  + 2; targetR.width  = REF_EYE_WIDTH  + 2;

  // Ease to glance, brief hold, then ease back to idle
  animate_to_pose(targetL, targetR, targetC, 12, 12);
  delay(80 + (int)random(0, 120));
  animate_to_pose(idleL, idleR, idleC, 12, 12);
}

static void move_big_eye(int direction) {
  reset_eyes(false);
  const int OVERSIZE_AMOUNT = 1;
  const int MOVEMENT_AMPLITUDE = 2;
  const int BLINK_AMPLITUDE = 5;
  for (int i=0; i<3; i++) {
    left_eye.x += MOVEMENT_AMPLITUDE * direction;
    right_eye.x += MOVEMENT_AMPLITUDE * direction;
    right_eye.height -= BLINK_AMPLITUDE;
    left_eye.height -= BLINK_AMPLITUDE;
    EyeState* target_eye = (direction > 0) ? &right_eye : &left_eye;
    target_eye->height += OVERSIZE_AMOUNT;
    target_eye->width += OVERSIZE_AMOUNT;
    draw_frame();
    delay(1);
  }
  for (int i=0; i<3; i++) {
    left_eye.x += MOVEMENT_AMPLITUDE * direction;
    right_eye.x += MOVEMENT_AMPLITUDE * direction;
    right_eye.height += BLINK_AMPLITUDE;
    left_eye.height += BLINK_AMPLITUDE;
    EyeState* target_eye = (direction > 0) ? &right_eye : &left_eye;
    target_eye->height += OVERSIZE_AMOUNT;
    target_eye->width += OVERSIZE_AMOUNT;
    draw_frame();
    delay(1);
  }
  delay(1000);
  for (int i=0; i<3; i++) {
    left_eye.x -= MOVEMENT_AMPLITUDE * direction;
    right_eye.x -= MOVEMENT_AMPLITUDE * direction;
    right_eye.height -= BLINK_AMPLITUDE;
    left_eye.height -= BLINK_AMPLITUDE;
    EyeState* target_eye = (direction > 0) ? &right_eye : &left_eye;
    target_eye->height -= OVERSIZE_AMOUNT;
    target_eye->width -= OVERSIZE_AMOUNT;
    draw_frame();
    delay(1);
  }
  for (int i=0; i<3; i++) {
    left_eye.x -= MOVEMENT_AMPLITUDE * direction;
    right_eye.x -= MOVEMENT_AMPLITUDE * direction;
    right_eye.height += BLINK_AMPLITUDE;
    left_eye.height += BLINK_AMPLITUDE;
    EyeState* target_eye = (direction > 0) ? &right_eye : &left_eye;
    target_eye->height -= OVERSIZE_AMOUNT;
    target_eye->width -= OVERSIZE_AMOUNT;
    draw_frame();
    delay(1);
  }
  reset_eyes(true);
}

static inline void move_right_big_eye() { move_big_eye(1); }
static inline void move_left_big_eye()  { move_big_eye(-1); }
// -------------------------------------------------------------------------

// ============================================================================
// WiFi Configuration Functions
// ============================================================================

/**
 * Load WiFi credentials from NVS storage
 */
void loadWiFiCredentials(String &ssid, String &password) {
  preferences.begin(PREF_NAMESPACE, true); // Read-only
  ssid = preferences.getString(PREF_SSID, "");
  password = preferences.getString(PREF_PASSWORD, "");
  preferences.end();
}

/**
 * Save WiFi credentials to NVS storage
 */
bool saveWiFiCredentials(const String &ssid, const String &password) {
  preferences.begin(PREF_NAMESPACE, false); // Read-write
  preferences.putString(PREF_SSID, ssid);
  preferences.putString(PREF_PASSWORD, password);
  preferences.end();
  return true;
}

/**
 * Connect to WiFi using stored or provided credentials
 */
bool connectToWiFi(const String &ssid, const String &password, int timeout_seconds = 30) {
  Serial.printf("Connecting to WiFi: %s\n", ssid.c_str());
  
  // Disconnect from any previous WiFi connection first
  WiFi.disconnect(true);
  delay(100);
  
  // Set WiFi mode to station
  WiFi.mode(WIFI_STA);
  delay(100);
  
  WiFi.begin(ssid.c_str(), password.c_str());
  
  unsigned long startTime = millis();
  while (WiFi.status() != WL_CONNECTED && 
         (millis() - startTime) < (timeout_seconds * 1000)) {
    delay(500);
    Serial.print(".");
  }
  Serial.println();
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("Connected! IP: %s\n", WiFi.localIP().toString().c_str());
    return true;
  } else {
    Serial.printf("Connection failed! WiFi status: %d\n", WiFi.status());
    return false;
  }
}

// UDP announcement helper for recorder
const int DISCOVERY_UDP_PORT = 5005;

void announceDeviceUDPRecorder() {
  // Give network stack a moment to stabilize after connection
  delay(500);
  
  WiFiUDP udp;
  String payload = "{";
  payload += "\"mac\":\"" + WiFi.macAddress() + "\",";
  payload += "\"role\":\"recorder\",";
  payload += "\"ssid\":\"" + WiFi.SSID() + "\"";
  payload += "}";

  // Send multiple announcements with longer delays to improve robustness
  const int ANNOUNCE_COUNT = 6;  // Increased from 4
  for (int i = 0; i < ANNOUNCE_COUNT; ++i) {
    udp.beginPacket("255.255.255.255", DISCOVERY_UDP_PORT);
    udp.write((const uint8_t*)payload.c_str(), payload.length());
    udp.endPacket();
    delay(500);  // Increased from 200ms
  }
  Serial.println("UDP discovery announcements sent: " + payload);
}

/**
 * Scan for available WiFi networks
 */
String scanWiFiNetworks() {
  Serial.println("Scanning WiFi networks...");
  int n = WiFi.scanNetworks();
  
  String json = "{\"networks\":[";
  
  for (int i = 0; i < n; i++) {
    if (i > 0) json += ",";
    json += "{";
    json += "\"ssid\":\"" + WiFi.SSID(i) + "\",";
    json += "\"rssi\":" + String(WiFi.RSSI(i)) + ",";
    json += "\"encryption\":\"" + String(WiFi.encryptionType(i)) + "\"";
    json += "}";
  }
  
  json += "]}";
  WiFi.scanDelete();
  
  return json;
}

// ============================================================================
// HTTP Endpoint Handlers for WiFi Management
// ============================================================================

/**
 * GET /wifi/status
 * Returns current WiFi connection status
 */
void handleWiFiStatus() {
  String json = "{";
  json += "\"connected\":" + String(WiFi.status() == WL_CONNECTED ? "true" : "false") + ",";
  json += "\"ssid\":\"" + WiFi.SSID() + "\",";
  json += "\"ip\":\"" + WiFi.localIP().toString() + "\",";
  json += "\"rssi\":" + String(WiFi.RSSI()) + ",";
  json += "\"mac\":\"" + WiFi.macAddress() + "\"";
  json += "}";
  
  server.send(200, "application/json", json);
}

/**
 * POST /wifi/configure
 * Configure WiFi credentials
 * Body: {"ssid": "NetworkName", "password": "Password123"}
 */
void handleWiFiConfigure() {
  if (server.method() != HTTP_POST) {
    server.send(405, "text/plain", "Method Not Allowed");
    return;
  }
  
  // Try to read JSON body first
  String body = server.arg("plain");
  String ssid;
  String password;
  if (body.length() > 0) {
    Serial.println("Received WiFi config (body): " + body);
    // Simple JSON parsing (you can use ArduinoJson library for better parsing)
    int ssidStart = body.indexOf("\"ssid\":\"");
    if (ssidStart >= 0) {
      ssidStart += 8;
      int ssidEnd = body.indexOf('"', ssidStart);
      if (ssidEnd > ssidStart) ssid = body.substring(ssidStart, ssidEnd);
    }
    int passStart = body.indexOf("\"password\":\"");
    if (passStart >= 0) {
      passStart += 12;
      int passEnd = body.indexOf('"', passStart);
      if (passEnd > passStart) password = body.substring(passStart, passEnd);
    }
  } else {
    // Fallback: check form-encoded fields (in case client sent urlencoded form)
    if (server.hasArg("ssid") || server.hasArg("password")) {
      ssid = server.arg("ssid");
      password = server.arg("password");
      Serial.println("Received WiFi config (form): ssid=" + ssid + " password_len=" + String(password.length()));
    } else {
      // Last-resort: try to inspect first arg (some clients put body in unnamed arg)
      if (server.args() > 0) {
        String maybe = server.arg(0);
        Serial.println("Received WiFi config (arg0): " + maybe);
        int ssidStart = maybe.indexOf("\"ssid\":\"");
        if (ssidStart >= 0) {
          ssidStart += 8;
          int ssidEnd = maybe.indexOf('"', ssidStart);
          if (ssidEnd > ssidStart) ssid = maybe.substring(ssidStart, ssidEnd);
        }
        int passStart = maybe.indexOf("\"password\":\"");
        if (passStart >= 0) {
          passStart += 12;
          int passEnd = maybe.indexOf('"', passStart);
          if (passEnd > passStart) password = maybe.substring(passStart, passEnd);
        }
      } else {
        Serial.println("Received WiFi config: <empty request body>");
      }
    }
  }
  
  if (ssid.length() == 0) {
    server.send(400, "text/plain", "SSID is required");
    Serial.println("ERROR: No SSID received in request");
    return;
  }
  
  Serial.printf("Saving WiFi credentials: SSID='%s', password_len=%d\n", ssid.c_str(), password.length());
  
  // Save credentials
  if (saveWiFiCredentials(ssid, password)) {
    server.send(200, "application/json", 
                "{\"success\":true,\"message\":\"WiFi credentials saved. Restart to apply.\"}");
    Serial.println("✓ WiFi credentials saved successfully");
  } else {
    server.send(500, "text/plain", "Failed to save credentials");
    Serial.println("✗ Failed to save WiFi credentials");
  }
}

/**
 * GET /wifi/scan
 * Scan and return available WiFi networks
 */
void handleWiFiScan() {
  String json = scanWiFiNetworks();
  server.send(200, "application/json", json);
}

/**
 * GET /restart
 * Restart ESP32 to apply new WiFi settings
 */
void handleRestart() {
  server.send(200, "text/plain", "Restarting ESP32...");
  delay(1000);
  ESP.restart();
}

// -------------------------------------------------------------------------

// -------------------- Auto-behavior (blink and idle) --------------------
static bool autoBlink = true;
static bool autoIdle = true;
static uint32_t nextBlinkMs = 0;
static uint32_t nextIdleMs = 0;

static void scheduleNextBlink() {
  nextBlinkMs = millis() + 2000 + (uint32_t)random(0, 4000); // 2-6s
}

static void scheduleNextIdle() {
  nextIdleMs = millis() + 3000 + (uint32_t)random(0, 5000); // 3-8s
}

// (removed) oledTryInitAt - not needed with U8g2 driver

static void oledBegin() {
  Wire.begin(I2C_SDA, I2C_SCL);
  Wire.setClock(400000);
  // Probe for common I2C addresses
  uint8_t addr = 0x3C;
  Wire.beginTransmission(0x3C);
  if (Wire.endTransmission() != 0) {
    Wire.beginTransmission(0x3D);
    if (Wire.endTransmission() == 0) {
      addr = 0x3D;
    } else {
      // Do a quick scan so the user sees devices in Serial
      byte found = 0;
      for (byte a = 1; a < 127; a++) {
        Wire.beginTransmission(a);
        if (Wire.endTransmission() == 0) {
          Serial.print("I2C device found at 0x"); if (a < 16) Serial.print('0'); Serial.println(a, HEX);
          found++;
        }
      }
      if (found == 0) Serial.println("I2C scan: no devices found on SDA=21 SCL=22");
      oledReady = false;
      return;
    }
  }
  // Set address (U8g2 expects 8-bit address = 7-bit << 1)
  u8g2.setI2CAddress((uint8_t)(addr << 1));
  u8g2.begin();
  oledReady = true;
  // No text splash; the boot animation (sleep -> wakeup) will run in setup()
  oledShowMode(OLED_IDLE);
}
// ----------------------------------

String getContentType(String filename){
  if (filename.endsWith(".wav")) return "audio/wav";
  if (filename.endsWith(".txt")) return "text/plain";
  if (filename.endsWith(".htm") || filename.endsWith(".html")) return "text/html";
  if (filename.endsWith(".js")) return "application/javascript";
  if (filename.endsWith(".css")) return "text/css";
  if (filename.endsWith(".png")) return "image/png";
  return "text/plain";
}

bool fs_exists(const char* path){
  File f = LittleFS.open(path, "r");
  if (!f) return false; f.close(); return true;
}

void listSPIFFS(){
  Serial.println("\nListing LittleFS files:");
  File root = LittleFS.open("/");
  if (!root) { Serial.println("Failed to open LittleFS root"); return; }
  File file = root.openNextFile();
  while(file){
    Serial.print(" "); Serial.print(file.name()); Serial.print(" "); Serial.println(file.size());
    file = root.openNextFile();
  }
}

void wavHeader(uint8_t* header, uint32_t dataBytes, uint32_t sampleRate){
  uint32_t byteRate = sampleRate * (I2S_SAMPLE_BITS/8) * I2S_CHANNELS;
  uint16_t blockAlign = I2S_CHANNELS * (I2S_SAMPLE_BITS/8);
  uint32_t chunkSize = 36 + dataBytes;
  header[0]='R'; header[1]='I'; header[2]='F'; header[3]='F';
  header[4]=chunkSize & 0xFF; header[5]=(chunkSize>>8)&0xFF; header[6]=(chunkSize>>16)&0xFF; header[7]=(chunkSize>>24)&0xFF;
  header[8]='W'; header[9]='A'; header[10]='V'; header[11]='E';
  header[12]='f'; header[13]='m'; header[14]='t'; header[15]=' ';
  header[16]=16; header[17]=0; header[18]=0; header[19]=0; // subchunk1 size
  header[20]=1; header[21]=0; // PCM
  header[22]=I2S_CHANNELS & 0xFF; header[23]=(I2S_CHANNELS>>8)&0xFF;
  header[24]=sampleRate & 0xFF; header[25]=(sampleRate>>8)&0xFF; header[26]=(sampleRate>>16)&0xFF; header[27]=(sampleRate>>24)&0xFF;
  header[28]=byteRate & 0xFF; header[29]=(byteRate>>8)&0xFF; header[30]=(byteRate>>16)&0xFF; header[31]=(byteRate>>24)&0xFF;
  header[32]=blockAlign & 0xFF; header[33]=(blockAlign>>8)&0xFF;
  header[34]=I2S_SAMPLE_BITS & 0xFF; header[35]=0;
  header[36]='d'; header[37]='a'; header[38]='t'; header[39]='a';
  header[40]=dataBytes & 0xFF; header[41]=(dataBytes>>8)&0xFF; header[42]=(dataBytes>>16)&0xFF; header[43]=(dataBytes>>24)&0xFF;
}

void SPIFFSInit(){
  if (fs_exists(recordingFilename)) LittleFS.remove(recordingFilename);
  File f = LittleFS.open(recordingFilename, FILE_WRITE);
  if (!f){ Serial.println("Failed to open recording file for header"); return; }
  uint8_t header[WAV_HEADER_SIZE];
  wavHeader(header, FLASH_RECORD_SIZE, I2S_SAMPLE_RATE);
  f.write(header, WAV_HEADER_SIZE);
  f.close();
}

void handleList(){
  String out = "[";
  File root = LittleFS.open("/");
  if (root){
    File file = root.openNextFile();
    while(file){
      if (out != "[") out += ',';
      out += "{\"name\":\"" + String(file.name()) + "\",\"size\":" + String(file.size()) + "}";
      file = root.openNextFile();
    }
  }
  out += "]";
  server.send(200, "application/json", out);
}

void handleDownload(){
  if (!fs_exists(recordingFilename)) { server.send(404, "text/plain", "FileNotFound"); return; }
  File f = LittleFS.open(recordingFilename, "r");
  server.streamFile(f, "audio/wav");
  f.close();
}

// Helper: compute available bytes in ring (protected by caller with ringMutex)
static size_t ring_available_no_lock(){
  if (ringWritePos >= ringReadPos) return ringWritePos - ringReadPos;
  return ringBufSize - (ringReadPos - ringWritePos);
}

// Helper: compute free space in ring (protected by caller)
static size_t ring_free_no_lock(){
  return ringBufSize - ring_available_no_lock();
}

// Copy into ring buffer (assumes taken mutex)
static size_t ring_write_no_lock(const uint8_t* src, size_t len){
  size_t free = ring_free_no_lock();
  size_t toWrite = (len <= free) ? len : free;
  if (toWrite == 0) return 0;
  size_t first = min(toWrite, ringBufSize - ringWritePos);
  memcpy(ringBuf + ringWritePos, src, first);
  ringWritePos += first;
  if (ringWritePos >= ringBufSize) ringWritePos = 0;
  size_t rem = toWrite - first;
  if (rem > 0){
    memcpy(ringBuf + ringWritePos, src + first, rem);
    ringWritePos += rem;
    if (ringWritePos >= ringBufSize) ringWritePos = 0;
  }
  return toWrite;
}

// Read from ring into dest (assumes taken mutex)
static size_t ring_read_no_lock(uint8_t* dest, size_t len){
  size_t avail = ring_available_no_lock();
  size_t toRead = (len <= avail) ? len : avail;
  if (toRead == 0) return 0;
  size_t first = min(toRead, ringBufSize - ringReadPos);
  memcpy(dest, ringBuf + ringReadPos, first);
  ringReadPos += first;
  if (ringReadPos >= ringBufSize) ringReadPos = 0;
  size_t rem = toRead - first;
  if (rem > 0){
    memcpy(dest + first, ringBuf + ringReadPos, rem);
    ringReadPos += rem;
    if (ringReadPos >= ringBufSize) ringReadPos = 0;
  }
  return toRead;
}

void writerTask(void* arg){
  (void)arg;
  writerTaskRunning = true;
  
  // REAL-TIME MODE: Write during recording (LittleFS is fast enough!)
  File f = LittleFS.open(recordingFilename, FILE_APPEND);
  if (!f) { 
    Serial.println("Writer: failed to open recording file"); 
    writerTaskRunning = false; 
    vTaskDelete(NULL); 
    return; 
  }
  
  uint8_t* localBuf = (uint8_t*) heap_caps_malloc(WRITE_CHUNK_SIZE, MALLOC_CAP_8BIT);
  if (!localBuf) { 
    Serial.println("Writer: failed to allocate buffer"); 
    f.close(); 
    writerTaskRunning = false; 
    vTaskDelete(NULL); 
    return; 
  }
  
  size_t totalWritten = 0;
  size_t writeCount = 0;
  size_t maxBufferUsed = 0;
  
  // Continuous write loop during and after recording
  for (;;) {
    bool rec = recordingStarted;
    size_t available = 0;
    size_t got = 0;
    
    if (ringMutex) {
      // Short timeout - check buffer frequently
      if (xSemaphoreTake(ringMutex, pdMS_TO_TICKS(2)) == pdTRUE) {
        available = ring_available_no_lock();
        if (available > maxBufferUsed) maxBufferUsed = available;
        
        // Drain aggressively when buffer is getting full (>50%)
        size_t targetRead = (available > ringBufSize / 2) ? WRITE_CHUNK_SIZE : min(available, (size_t)(WRITE_CHUNK_SIZE / 2));
        if (targetRead > 0) got = ring_read_no_lock(localBuf, targetRead);
        xSemaphoreGive(ringMutex);
      }
    }
    
    // Exit when recording stopped AND buffer empty
    if (!rec && available == 0) break;
    
    if (got == 0) {
      // No data - yield to recorder
      taskYIELD();
      continue;
    }
    
    // Apply software gain
    for (size_t i = 0; i + 1 < got; i += 2){
      int16_t s = (int16_t)((uint16_t)localBuf[i] | ((uint16_t)localBuf[i+1] << 8));
      int32_t ns = (int32_t)s * SAMPLE_GAIN_MULT;
      if (ns > 32767) ns = 32767;
      else if (ns < -32768) ns = -32768;
      int16_t out = (int16_t)ns;
      localBuf[i] = out & 0xFF;
      localBuf[i+1] = (out >> 8) & 0xFF;
    }
    
    // Write to LittleFS (much faster than SPIFFS!)
    size_t written = f.write(localBuf, got);
    if (written != got) {
      Serial.printf("\n[Writer: short write %u/%u]\n", (unsigned)written, (unsigned)got);
    }
    totalWritten += written;
    writeCount++;
    if (writeCount % 10 == 0) Serial.print('#');
    
    // Don't yield after write - keep draining continuously
  }
  
  f.flush();
  f.close();
  heap_caps_free(localBuf);
  Serial.printf("\nWriter stats: writes=%u, bytes=%u, maxBufUsed=%u\n", 
                (unsigned)writeCount, (unsigned)totalWritten, (unsigned)maxBufferUsed);
  writerTaskRunning = false;
  writerTaskHandle = NULL;
  vTaskDelay(10);
  vTaskDelete(NULL);
}

void recorderTask(void* arg){
  (void)arg;
  recorderTaskRunning = true;
  const size_t readSize = 256; // Smaller reads = more opportunities for writer to drain
  uint8_t* readBuf = (uint8_t*) heap_caps_malloc(readSize, MALLOC_CAP_DMA);
  if (!readBuf){ Serial.println("Failed to allocate DMA read buffer"); recorderTaskRunning = false; vTaskDelete(NULL); return; }
  size_t totalCaptured = 0;
  size_t readCount = 0;
  size_t timeoutCount = 0;
  size_t errorCount = 0;
  
  if (useDirectWrites) {
    // fall back: write directly to LittleFS from capture loop
    File f = LittleFS.open(recordingFilename, FILE_APPEND);
    if (!f) Serial.println("Recorder (direct): failed to open recording file for append");
    while (recordingStarted && totalCaptured < FLASH_RECORD_SIZE) {
      size_t bytesRead = 0;
      esp_err_t r = i2s_read(I2S_PORT, readBuf, readSize, &bytesRead, pdMS_TO_TICKS(1000));
      if (r == ESP_OK && bytesRead > 0) {
        if (f) f.write(readBuf, bytesRead);
        totalCaptured += bytesRead;
        readCount++;
        if (readCount % 10 == 0) Serial.print('.');
      } else if (r == ESP_ERR_TIMEOUT) {
        timeoutCount++;
        continue;
      } else {
        Serial.printf("i2s_read error: 0x%08X\n", r);
        errorCount++;
        break;
      }
    }
    if (f) f.close();
    Serial.printf("\nRecorder stats: reads=%u, timeouts=%u, errors=%u, bytes=%u\n", 
                  (unsigned)readCount, (unsigned)timeoutCount, (unsigned)errorCount, (unsigned)totalCaptured);
  } else {
    while (recordingStarted && totalCaptured < FLASH_RECORD_SIZE){
      size_t bytesRead = 0;
      esp_err_t r = i2s_read(I2S_PORT, readBuf, readSize, &bytesRead, pdMS_TO_TICKS(1000));
      if (r == ESP_OK && bytesRead > 0){
        readCount++;
        // Write to ring buffer with reasonable timeout
        size_t totalWritten = 0;
        
        for (int attempt = 0; attempt < 50 && totalWritten < bytesRead; attempt++) {
          if (xSemaphoreTake(ringMutex, pdMS_TO_TICKS(10)) == pdTRUE) {
            size_t remaining = bytesRead - totalWritten;
            size_t copied = ring_write_no_lock(readBuf + totalWritten, remaining);
            totalWritten += copied;
            xSemaphoreGive(ringMutex);
            
            if (totalWritten >= bytesRead) break; // Success!
            if (copied == 0) vTaskDelay(10); // Buffer full, wait for writer
          }
        }
        
        // Track dropped data
        if (totalWritten < bytesRead) {
          droppedBytes += (bytesRead - totalWritten);
          Serial.printf("\n!DROP:%u (buffer full)\n", (unsigned)(bytesRead - totalWritten));
        }
        
        totalCaptured += totalWritten;
        if (readCount % 20 == 0) Serial.print('.');
      } else if (r == ESP_ERR_TIMEOUT){
        timeoutCount++;
        if (timeoutCount % 50 == 0) {
          Serial.printf("\n[WARN: %u timeouts, only %u bytes captured]\n", (unsigned)timeoutCount, (unsigned)totalCaptured);
        }
        continue;
      } else {
        Serial.printf("\ni2s_read error: 0x%08X after %u reads\n", r, (unsigned)readCount);
        errorCount++;
        break;
      }
    }
    Serial.printf("\nRecorder stats: reads=%u, timeouts=%u, errors=%u, bytes=%u\n", 
                  (unsigned)readCount, (unsigned)timeoutCount, (unsigned)errorCount, (unsigned)totalCaptured);
  }
  heap_caps_free(readBuf);
  recorderTaskRunning = false;
  vTaskDelete(NULL);
}

void startRecording(){
  if (recordingStarted) return;
  recordingStarted = true;
  digitalWrite(greenLed, HIGH);
  Serial.println("*** Recording Start ***");
  oledShowMode(OLED_LISTENING);
  listening_look();
  
  // Free up RAM before allocating ring buffer
  Serial.printf("Free heap before: %u bytes\n", ESP.getFreeHeap());
  
  SPIFFSInit();
  i2s_config_t i2s_config = {
    .mode = (i2s_mode_t)(I2S_MODE_MASTER | I2S_MODE_RX),
    .sample_rate = I2S_SAMPLE_RATE,
    .bits_per_sample = (i2s_bits_per_sample_t)I2S_BITS_PER_SAMPLE_16BIT,
    .channel_format = I2S_CHANNEL_FMT_ONLY_LEFT,
    .communication_format = (i2s_comm_format_t)(I2S_COMM_FORMAT_I2S | I2S_COMM_FORMAT_I2S_MSB),
    .intr_alloc_flags = ESP_INTR_FLAG_LEVEL1,
    .dma_buf_count = 8, // Reduced from 32 - too many buffers can cause issues
    .dma_buf_len = 1024,  // Increased from 512 - larger buffers = less overhead
    .use_apll = false,
    .tx_desc_auto_clear = false,
    .fixed_mclk = 0
  };
  i2s_pin_config_t pin_config = {
    .bck_io_num = I2S_SCK, 
    .ws_io_num = I2S_WS, 
    .data_out_num = I2S_PIN_NO_CHANGE, 
    .data_in_num = I2S_SD 
  };
  esp_err_t er = i2s_driver_install(I2S_PORT, &i2s_config, 0, NULL);
  if (er != ESP_OK){ 
    Serial.printf("i2s_driver_install failed: 0x%08X\n", er); 
    recordingStarted = false; 
    digitalWrite(greenLed, LOW); 
    oledShowMode(OLED_ERROR);
    return; 
  }
  i2s_set_pin(I2S_PORT, &pin_config);
  
  // CRITICAL: Clear I2S DMA buffer before starting to avoid stale data
  i2s_zero_dma_buffer(I2S_PORT);
  // Allocate ring buffer and mutex
  if (!ringBuf){
    // Free up as much RAM as possible before allocation
    Serial.printf("Free heap: %u bytes, largest block: %u bytes\n", 
                  ESP.getFreeHeap(), ESP.getMaxAllocHeap());
    
    // Try allocating ring buffer with progressive fallback
    size_t trySize = ringBufSize;
    // Try sizes: 256KB, 192KB, 160KB, 128KB, 96KB, 64KB, 32KB
    size_t trySizes[] = {256*1024, 192*1024, 160*1024, 128*1024, 96*1024, 64*1024, 32*1024, 16*1024};
    
    for (int i = 0; i < 8; i++) {
      trySize = trySizes[i];
      ringBuf = (uint8_t*) heap_caps_malloc(trySize, MALLOC_CAP_8BIT);
      if (ringBuf) {
        ringBufSize = trySize;
        Serial.printf("Allocated ring buffer: %u bytes\n", (unsigned)ringBufSize);
        ringWritePos = 0;
        ringReadPos = 0;
        useDirectWrites = false;
        break;
      }
    }
    
    if (!ringBuf) {
      Serial.println("Failed to allocate ring buffer; falling back to direct writes");
      useDirectWrites = true;
    } else if (ringBufSize < MIN_RING_BUFFER_SIZE) {
      float bufferSeconds = (float)ringBufSize / (I2S_SAMPLE_RATE * (I2S_SAMPLE_BITS/8) * I2S_CHANNELS);
      Serial.printf("WARNING: Small buffer (%u bytes = %.1fs). Real-time write mode active.\n", 
                    (unsigned)ringBufSize, bufferSeconds);
    } else {
      // Calculate buffer capacity
      float bufferSeconds = (float)ringBufSize / (I2S_SAMPLE_RATE * (I2S_SAMPLE_BITS/8) * I2S_CHANNELS);
      Serial.printf("Buffer: %.1f seconds capacity. Real-time write mode (unlimited recording).\n", bufferSeconds);
    }
  }
  // Always reset ring positions for a new recording run
  ringWritePos = 0;
  ringReadPos = 0;
  // reset diagnostics for this run
  droppedBytes = 0;
  if (!useDirectWrites) {
    if (!ringMutex) ringMutex = xSemaphoreCreateMutex();
    // Start writer on Core 0, priority 10 - MUST drain faster than recorder fills!
    if (!writerTaskRunning) xTaskCreatePinnedToCore(writerTask, "writer", 8192, NULL, 10, &writerTaskHandle, 0);
  } else {
    // ensure no mutex when using direct writes
    if (ringMutex) { vSemaphoreDelete(ringMutex); ringMutex = NULL; }
  }

  // Start recorder (producer) on Core 1, priority 5 - lower than writer to prioritize draining
  xTaskCreatePinnedToCore(recorderTask, "recorder", 8192, NULL, 5, &recorderTaskHandle, 1);
}

bool normalizeRecording() {
  Serial.println("Normalizing recorded WAV to full scale...");
  const char* srcPath = recordingFilename;
  const char* tmpPath = "/recording_norm.wav";
  File src = LittleFS.open(srcPath, "r");
  if (!src) { Serial.println("Normalization: failed to open recorded file for reading"); return false; }
  if (src.size() <= WAV_HEADER_SIZE) {
    Serial.println("Recorded file too small to normalize");
    src.close();
    return false;
  }
  src.seek(WAV_HEADER_SIZE);
  const size_t chunk = 4096;
  uint8_t* buf = (uint8_t*) heap_caps_malloc(chunk, MALLOC_CAP_SPIRAM | MALLOC_CAP_8BIT);
  if (!buf) buf = (uint8_t*) heap_caps_malloc(chunk, MALLOC_CAP_8BIT);
  if (!buf) { Serial.println("Normalization: failed to allocate buffer"); src.close(); return false; }
  int32_t globalPeak = 0;
  size_t readBytes = 0;
  while ((readBytes = src.read(buf, chunk)) > 0) {
    for (size_t i = 0; i + 1 < readBytes; i += 2) {
      int16_t s = (int16_t)((uint16_t)buf[i] | ((uint16_t)buf[i+1] << 8));
      int32_t a = abs((int)s);
      if (a > globalPeak) globalPeak = a;
    }
  }
  if (globalPeak <= 0) {
    Serial.println("Normalization: silent file or zero peak; skipping normalization");
    heap_caps_free(buf);
    src.close();
    return false;
  }
  float scale = 32767.0f / (float)globalPeak;
  Serial.printf("Normalization: peak=%d scale=%.3f\n", (int)globalPeak, scale);
  src.seek(WAV_HEADER_SIZE);
  File tmp = LittleFS.open(tmpPath, FILE_WRITE);
  if (!tmp) { Serial.println("Normalization: failed to open temp file"); heap_caps_free(buf); src.close(); return false; }
  uint32_t dataBytes = (uint32_t)(src.size() - WAV_HEADER_SIZE);
  uint8_t hdr[WAV_HEADER_SIZE];
  wavHeader(hdr, dataBytes, I2S_SAMPLE_RATE);
  tmp.write(hdr, WAV_HEADER_SIZE);
  while ((readBytes = src.read(buf, chunk)) > 0) {
    for (size_t i = 0; i + 1 < readBytes; i += 2) {
      int16_t s = (int16_t)((uint16_t)buf[i] | ((uint16_t)buf[i+1] << 8));
      int32_t ns = (int32_t)roundf((float)s * scale);
      if (ns > 32767) ns = 32767;
      else if (ns < -32768) ns = -32768;
      int16_t out = (int16_t)ns;
      buf[i] = out & 0xFF;
      buf[i+1] = (out >> 8) & 0xFF;
    }
    tmp.write(buf, readBytes);
  }
  tmp.flush(); tmp.close(); src.close();
  LittleFS.remove(srcPath);
  LittleFS.rename(tmpPath, srcPath);
  heap_caps_free(buf);
  Serial.println("Normalization complete: replaced recorded file with normalized version.");
  return true;
}

void stopRecording(){
  if (!recordingStarted) return;
  recordingStarted = false;
  Serial.println("*** Recording Stop ***");
  oledShowMode(OLED_THINKING);
  blink(12);
  unsigned long start = millis();
  // wait for recorder to stop
  while (recorderTaskRunning && (millis() - start) < 5000) { delay(50); }
  // wait for writer to flush ring buffer
  unsigned long writerStart = millis();
  while (writerTaskRunning && (millis() - writerStart) < 10000) { delay(50); }
  i2s_driver_uninstall(I2S_PORT);
  digitalWrite(greenLed, LOW);
  File f = LittleFS.open(recordingFilename, "r+");
  if (f){ size_t totalSize = f.size(); if (totalSize > WAV_HEADER_SIZE){ uint32_t dataBytes = totalSize - WAV_HEADER_SIZE; uint8_t header[WAV_HEADER_SIZE]; wavHeader(header, dataBytes, I2S_SAMPLE_RATE); f.seek(0); f.write(header, WAV_HEADER_SIZE); Serial.printf("WAV header rewritten: data=%u bytes\n", (unsigned)dataBytes); } f.close(); }

  if (droppedBytes > 0) Serial.printf("Warning: dropped %u bytes during capture (ring full or mutex contention)\n", (unsigned)droppedBytes);

#if NORMALIZE_ON_STOP
  // Normalize recorded WAV to full scale to improve playback volume
  Serial.println("Normalizing recorded WAV to full scale...");
  // We'll do a 2-pass normalization without loading entire file into RAM.
  const char* srcPath = recordingFilename;
  const char* tmpPath = "/recording_norm.wav";
  File src = SPIFFS.open(srcPath, "r");
  if (src) {
    if (src.size() <= WAV_HEADER_SIZE) {
      Serial.println("Recorded file too small to normalize");
      src.close();
    } else {
      src.seek(WAV_HEADER_SIZE);
      const size_t chunk = 4096;
      uint8_t* buf = (uint8_t*) heap_caps_malloc(chunk, MALLOC_CAP_8BIT);
      if (!buf) { Serial.println("Normalization: failed to allocate buffer"); src.close(); }
      else {
        int32_t globalPeak = 0;
        size_t readBytes = 0;
        // Pass 1: find peak
        while ((readBytes = src.read(buf, chunk)) > 0) {
          for (size_t i = 0; i + 1 < readBytes; i += 2) {
            int16_t s = (int16_t)((uint16_t)buf[i] | ((uint16_t)buf[i+1] << 8));
            int32_t a = abs((int)s);
            if (a > globalPeak) globalPeak = a;
          }
        }
        if (globalPeak <= 0) {
          Serial.println("Normalization: silent file or zero peak; skipping normalization");
        } else {
          float scale = 32767.0f / (float)globalPeak;
          Serial.printf("Normalization: peak=%d scale=%.3f\n", (int)globalPeak, scale);
          // Pass 2: create temp file and write scaled samples
          src.seek(WAV_HEADER_SIZE);
          File tmp = SPIFFS.open(tmpPath, FILE_WRITE);
          if (!tmp) { Serial.println("Normalization: failed to open temp file"); }
          else {
            // write WAV header placeholder; data length = original data length
            uint32_t dataBytes = (uint32_t)(src.size() - WAV_HEADER_SIZE);
            uint8_t hdr[WAV_HEADER_SIZE];
            wavHeader(hdr, dataBytes, I2S_SAMPLE_RATE);
            tmp.write(hdr, WAV_HEADER_SIZE);
            // Scale and write
            while ((readBytes = src.read(buf, chunk)) > 0) {
              for (size_t i = 0; i + 1 < readBytes; i += 2) {
                int16_t s = (int16_t)((uint16_t)buf[i] | ((uint16_t)buf[i+1] << 8));
                int32_t ns = (int32_t)roundf((float)s * scale);
                if (ns > 32767) ns = 32767;
                else if (ns < -32768) ns = -32768;
                int16_t out = (int16_t)ns;
                buf[i] = out & 0xFF;
                buf[i+1] = (out >> 8) & 0xFF;
              }
              tmp.write(buf, readBytes);
            }
            tmp.flush(); tmp.close();
            // Replace original with normalized
            src.close(); src = File();
            SPIFFS.remove(srcPath);
            SPIFFS.rename(tmpPath, srcPath);
            Serial.println("Normalization complete: replaced recorded file with normalized version.");
          }
        }
        heap_caps_free(buf);
      }
    }
  } else {
    Serial.println("Normalization: failed to open recorded file for reading");
  }
#endif
  // Return to idle visual
  oledShowMode(OLED_IDLE);
  go_idle();
}

// Forward declarations for WiFi management functions
void loadWiFiCredentials(String &ssid, String &password);
bool saveWiFiCredentials(const String &ssid, const String &password);
bool connectToWiFi(const String &ssid, const String &password, int timeout_seconds);
String scanWiFiNetworks();
void handleWiFiStatus();
void handleWiFiConfigure();
void handleWiFiScan();
void handleRestart();

void setup(){
  Serial.begin(115200);
  pinMode(greenLed, OUTPUT);
  pinMode(buttonPin, INPUT_PULLUP);
  
  // Initialize OLED (SH1106 via U8g2) to show boot progress
  oledBegin();
  if (!oledReady) {
    // Quick I2C scan for troubleshooting
    Wire.begin(I2C_SDA, I2C_SCL);
    byte found = 0;
    for (byte a = 1; a < 127; a++) {
      Wire.beginTransmission(a);
      if (Wire.endTransmission() == 0) {
        Serial.print("I2C device found at 0x"); if (a < 16) Serial.print('0'); Serial.println(a, HEX);
        found++;
      }
    }
    if (found == 0) Serial.println("I2C scan: no devices found on SDA=21 SCL=22");
  }
  
  // Mount LittleFS (faster and more reliable than SPIFFS)
  if (!LittleFS.begin(true)){ 
    Serial.println("LittleFS mount failed - trying to format..."); 
    if (!LittleFS.begin(true)) {
      Serial.println("LittleFS format failed!"); 
      while(1) delay(1000);
    } else {
      Serial.println("LittleFS formatted successfully!");
    }
  } else {
    Serial.println("LittleFS mounted successfully!");
  }
  
  // Initialize NVS for WiFi credentials
  preferences.begin(PREF_NAMESPACE, true);
  preferences.end();
  
  // Load saved WiFi credentials
  String savedSSID, savedPassword;
  loadWiFiCredentials(savedSSID, savedPassword);
  
  // Try to connect with saved credentials
  Serial.println("=== WiFi Connection Process ===");
  WiFi.mode(WIFI_STA);
  bool wifiConnected = false;
  if (savedSSID.length() > 0) {
    Serial.printf("Found saved WiFi credentials for SSID: %s\n", savedSSID.c_str());
    if (connectToWiFi(savedSSID, savedPassword, 30)) {
      Serial.println("✓ Connected with saved credentials");
      wifiConnected = true;
      // Announce presence to Flask listener
      announceDeviceUDPRecorder();
    } else {
      Serial.println("✗ Failed to connect with saved credentials");
      Serial.println("Please check:");
      Serial.println("  1. SSID is correct");
      Serial.println("  2. Password is correct");
      Serial.println("  3. WiFi router is powered on");
      Serial.println("  4. ESP32 is in range of router");
      Serial.println("\nYou can update WiFi via web interface");
    }
  } else {
    Serial.println("No saved credentials found - using default WiFi");
    // Use hardcoded credentials as fallback (first boot only)
    
    //String ssid = "Nasjoo";
    //String password = "nasjow028";

    String ssid = "BAHAY KUBO";
    String password = "TiglaoFam28210928";

    if (connectToWiFi(ssid, password, 30)) {
      // Save these as default for next boot
      saveWiFiCredentials(ssid, password);
      Serial.println("Default credentials saved to NVS");
      wifiConnected = true;
      announceDeviceUDPRecorder();
    }
  }
  
  // Print connection status
  if (wifiConnected) {
    Serial.println("\n========================================");
    Serial.println("✓ WiFi Connected Successfully!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
    Serial.print("Gateway: ");
    Serial.println(WiFi.gatewayIP());
    Serial.print("Subnet Mask: ");
    Serial.println(WiFi.subnetMask());
    Serial.println("========================================");
    // Final announcement with confirmed IP
    announceDeviceUDPRecorder();
  } else {
    Serial.println("\n========================================");
    Serial.println("✗ WiFi Connection Failed");
    Serial.println("Device will continue in offline mode");
    Serial.println("Use serial command 'clearwifi' to reset credentials");
    Serial.println("========================================");
  }
  
  if (MDNS.begin(host)) Serial.printf("MDNS started: http://%s.local\n", host);
  
  if (oledReady) {
    // Start with booting animation - will stay here until GENTA7 sends wakeup signal
    oledShowMode(OLED_BOOTING);
    Serial.println("OLED: Showing boot animation. Waiting for system ready signal...");
  }
  
  server.on("/list", HTTP_GET, handleList);
  server.on("/recording.wav", HTTP_GET, handleDownload);
  
  // WiFi management endpoints
  server.on("/wifi/status", HTTP_GET, handleWiFiStatus);
  server.on("/wifi/configure", HTTP_POST, handleWiFiConfigure);
  server.on("/wifi/scan", HTTP_GET, handleWiFiScan);
  server.on("/restart", HTTP_GET, handleRestart);
  
  // Control OLED state from host
  server.on("/oled", HTTP_GET, [](){
    if (recordingStarted) { server.send(423, "text/plain", "Busy: recording in progress"); return; }
    if (!server.hasArg("value")) { server.send(400, "text/plain", "Missing 'value' param"); return; }
    String v = server.arg("value"); v.toLowerCase();
    // Debug: log incoming OLED control requests
    Serial.print("/oled received -> value="); Serial.println(v);
    
  // Core expressions
    if      (v == "idle")       { oledShowMode(OLED_IDLE); go_idle(); }
    else if (v == "listening")  { oledShowMode(OLED_LISTENING); listening_look(); }
    else if (v == "thinking")   { oledShowMode(OLED_THINKING); thinking_look(); }
    else if (v == "processing") { oledShowMode(OLED_PROCESSING); processing_anim(); }
  // Quiz transition
  else if (v == "quiz" || v == "quiz_mode") { oledShowMode(OLED_QUIZ); quiz_mode_animation(); }
  // Assisting transition (show short typewriter 'Assisting' + listening text)
  else if (v == "assist" || v == "assisting") {
    // Only play the assist-mode transition animation once per short interval
    unsigned long now = millis();
    if (now - lastAssistMs > 5000) {
      lastAssistMs = now;
      oledShowMode(OLED_ASSIST);
      assist_mode_animation();
    } else {
      // Already played recently; just show listening pose without replaying animation
      oledShowMode(OLED_LISTENING);
      listening_look();
    }
  }
    // Report creation with optional numeric progress: /oled?value=report&progress=42
    else if (v == "report" || v == "report_creation") {
      int p = -1;
      if (server.hasArg("progress")) {
        p = server.arg("progress").toInt();
        if (p < 0) p = 0;
        if (p > 100) p = 100;
      }
      Serial.print("/oled report progress param -> "); Serial.println(p);
      // Update the persistent progress value and switch to REPORT mode.
      report_progress_global = p;
      oledShowMode(OLED_REPORT);
      // Draw immediately for fastest feedback, loop() will continue redrawing.
      report_creation_draw(report_progress_global);
    }
    // Custom text/message display: /oled?value=text&line1=...&line2=...&hold=ms
    else if (v == "text" || v == "message") {
      String l1 = server.hasArg("line1") ? server.arg("line1") : "";
      String l2 = server.hasArg("line2") ? server.arg("line2") : "";
      int hold = server.hasArg("hold") ? server.arg("hold").toInt() : 1200;
      // sanitize lengths
      if (l1.length() > 64) l1 = l1.substring(0, 64);
      if (l2.length() > 64) l2 = l2.substring(0, 64);
      Serial.print("/oled text -> "); Serial.print(l1); Serial.print(" | "); Serial.println(l2);
      // Show custom two-line typewriter-style message
      show_custom_text(l1.c_str(), l2.c_str(), hold);
    }
    
    // Cozmo-style expressions (positive emotions)
    else if (v == "correct" || v == "happy")    { oledShowMode(OLED_CORRECT); happy_eye(); }
    else if (v == "excited")    { excited_shimmy(); }
    else if (v == "glee")       { glee_eyes(); }
    else if (v == "awe")        { awe_eyes(); }
    else if (v == "surprised")  { surprised_look(); }
    
    // Cozmo-style expressions (negative emotions)
    else if (v == "incorrect" || v == "sad")  { oledShowMode(OLED_INCORRECT); sad_eyes(); }
    else if (v == "worried")    { worried_eyes(); }
    else if (v == "frustrated") { frustrated_eyes(); }
    else if (v == "annoyed")    { annoyed_eyes(); }
    else if (v == "angry")      { angry_eyes(); }
    else if (v == "furious")    { furious_eyes(); }
    else if (v == "scared")     { scared_eyes(); }
    
    // Cozmo-style expressions (neutral/curious)
    else if (v == "curious")    { curious_look(); }
    else if (v == "skeptical")  { skeptical_eyes(); }
    else if (v == "suspicious") { suspicious_eyes(); }
    else if (v == "focused")    { focused_eyes(); }
    else if (v == "squint")     { squint_eyes(); }
    else if (v == "unimpressed") { unimpressed_eyes(); }
    else if (v == "sleepy")     { sleepy_eyes(); }
    
    // Error and utility
    else if (v == "error")      { oledShowMode(OLED_ERROR); sleep_anim(); }
    else if (v == "test")       oledTestPattern();
    else if (v == "clear")      { if (oledReady) { u8g2.clearBuffer(); u8g2.sendBuffer(); } }
    else { server.send(400, "text/plain", "Invalid value"); return; }
    
    server.send(200, "text/plain", "OK");
  });
  
  // Wakeup endpoint - triggers system ready sequence (called by GENTA7 after discovery)
  server.on("/wakeup", HTTP_GET, [](){
    if (!oledReady) {
      server.send(503, "text/plain", "OLED not available");
      return;
    }
    
    Serial.println("🎉 SYSTEM READY signal received from GENTA7!");
    
    // Show "READY!" animation
    system_ready_animation();
    
    // Full wakeup sequence
    oledShowMode(OLED_IDLE);
    sleep_anim();
    delay(250);
    wakeup();
    reset_eyes(true);
    
    // Power-on eye sequence
    look_right_intro();
    delay(2000);
    go_idle();
    delay(2000);
    look_left_intro();
    delay(2000);
    go_idle();
    
    // Initialize automatic behaviors
    randomSeed(esp_random());
    autoIdle = false;
    scheduleNextBlink();
    if (autoIdle) scheduleNextIdle();
    
    Serial.println("✓ OLED wakeup sequence complete - GENTA is ready!");
    server.send(200, "text/plain", "Wakeup sequence complete");
  });

  // Status endpoint so the host can poll OLED/report state
  server.on("/oled_status", HTTP_GET, [](){
    // Return current report progress and whether completion animation was played
    String json = "{";
    json += "\"report_progress\":" + String(report_progress_global) + ",";
    json += "\"completion_played\":" + String(report_completion_played ? "true" : "false") + ",";
    json += "\"completion_shown_at\":" + String(report_completion_shown_at) + ",";
    json += "\"oled_mode\":" + String((int)oledMode);
    json += "}";
    server.send(200, "application/json", json);
  });
  
  // Add /clear endpoint to delete recording file
  server.on("/clear", HTTP_GET, [](){
    if (recordingStarted) {
      server.send(400, "text/plain", "Cannot clear while recording");
      Serial.println("Clear requested but recording in progress");
      return;
    }
    
    if (fs_exists(recordingFilename)) {
      LittleFS.remove(recordingFilename);
      Serial.println("Recording file cleared via /clear endpoint");
      server.send(200, "text/plain", "Recording cleared");
    } else {
      Serial.println("No recording file to clear");
      server.send(200, "text/plain", "No recording file");
    }
  });
  
  // Add /stop endpoint to stop recording
  server.on("/stop", HTTP_GET, [](){
    if (recordingStarted) {
      stopRecording();
      server.send(200, "text/plain", "Recording stopped");
      Serial.println("Recording stopped via /stop endpoint");
    } else {
      server.send(200, "text/plain", "Not recording");
    }
  });
  
  // Add /size endpoint to check recording file size (for polling)
  server.on("/size", HTTP_GET, [](){
    if (fs_exists(recordingFilename)) {
      File f = LittleFS.open(recordingFilename, "r");
      if (f) {
        size_t fileSize = f.size();
        f.close();
        
        // Return 0 if file only contains WAV header (no actual audio data)
        // This prevents detecting empty/cleared files as valid recordings
        if (fileSize <= WAV_HEADER_SIZE) {
          server.send(200, "text/plain", "0");
          return;
        }
        
        server.send(200, "text/plain", String(fileSize));
      } else {
        server.send(500, "text/plain", "0");
      }
    } else {
      server.send(404, "text/plain", "0");
    }
  });
  
  // Diagnostics: ring buffer metrics
  server.on("/metrics", HTTP_GET, [](){
    String out;
    if (ringMutex) xSemaphoreTake(ringMutex, pdMS_TO_TICKS(50));
    size_t avail = ring_available_no_lock();
    size_t freeb = ring_free_no_lock();
    if (ringMutex) xSemaphoreGive(ringMutex);
    out += "ring_size=" + String(ringBufSize);
    out += "\nring_available=" + String(avail);
    out += "\nring_free=" + String(freeb);
    out += "\ndropped_bytes=" + String((unsigned)droppedBytes);
    out += "\nrecording=" + String(recordingStarted ? 1 : 0);
    server.send(200, "text/plain", out);
  });
  
  // On-demand normalization
  server.on("/normalize_now", HTTP_GET, [](){
    if (recordingStarted) { server.send(400, "text/plain", "Cannot normalize while recording"); return; }
    if (!fs_exists(recordingFilename)) { server.send(404, "text/plain", "No recording"); return; }
    bool ok = normalizeRecording();
    server.send(200, "text/plain", ok ? "Normalized" : "Skipped");
  });
  
  server.begin(); Serial.println("HTTP server started");
  listSPIFFS();
}

void loop(){
  // Handle recording button (GPIO 23)
  static bool prevBtn = HIGH;
  bool curBtn = digitalRead(buttonPin);
  if (prevBtn == HIGH && curBtn == LOW){ delay(10); if (!recordingStarted) startRecording(); }
  if (prevBtn == LOW && curBtn == HIGH){ if (recordingStarted) stopRecording(); }
  prevBtn = curBtn;
  
  if (Serial.available()){ 
    String cmd = Serial.readStringUntil('\n');
    cmd.trim();
    if (cmd.length() > 0) {
      char c = cmd[0];
      if (c=='i' || c=='I'){ 
        if (!recordingStarted) startRecording(); 
        else stopRecording(); 
      } else if (cmd.equalsIgnoreCase("clearwifi") || cmd.equalsIgnoreCase("resetwifi")) {
        Serial.println("Clearing saved WiFi credentials...");
        preferences.begin(PREF_NAMESPACE, false);
        preferences.clear();
        preferences.end();
        Serial.println("✓ WiFi credentials cleared!");
        Serial.println("Restarting ESP32...");
        delay(1000);
        ESP.restart();
      } else if (cmd.equalsIgnoreCase("help")) {
        Serial.println("Serial commands:");
        Serial.println("  i          - toggle recording");
        Serial.println("  clearwifi  - clear saved WiFi credentials and restart");
        Serial.println("  help       - show this message");
      }
    }
  }
  
  // Show booting animation while waiting for system ready signal
  if (oledReady && oledMode == OLED_BOOTING && !recordingStarted) {
    booting_animation();
    delay(100);  // Update animation every 100ms
  }

  // Show persistent report progress similar to boot animation cadence
  if (oledReady && oledMode == OLED_REPORT && !recordingStarted) {
    // report_progress_global is updated by the HTTP handler
    report_creation_draw(report_progress_global);
    delay(120); // refresh at ~8Hz for smooth progress updates
  }
  
  // Auto behaviors when idle (disabled during recording)
  // Skip animations if OLED init failed to prevent I2C NACK spam
  if (oledReady && oledMode == OLED_IDLE && !recordingStarted) {
    uint32_t now = millis();
    if (autoBlink && now >= nextBlinkMs) { blink(12); scheduleNextBlink(); }
    if (autoIdle && now >= nextIdleMs) {
      idle_random_glance();
      scheduleNextIdle();
    }
  }
  server.handleClient(); delay(1);
}

// Quiz-mode transition animation: short typewriter + quick progress fill
static void quiz_mode_animation() {
  if (!oledReady) return;

  const char* line1 = "Proceeding to";
  const char* line2 = "QUIZ MODE";

  // Typewriter for line1 (small font)
  u8g2.clearBuffer();
  u8g2.setFont(u8g2_font_6x10_tf);
  int y1 = 18;
  int len1 = strlen(line1);
  char buf[32];
  for (int i = 1; i <= len1; ++i) {
    strncpy(buf, line1, i);
    buf[i] = '\0';
    int w = u8g2.getStrWidth(buf);
    u8g2.clearBuffer();
    u8g2.drawStr((SCREEN_WIDTH - w) / 2, y1, buf);
    u8g2.sendBuffer();
    delay(50);
  }

  // Typewriter for line2 (larger font)
  u8g2.setFont(u8g2_font_7x13_tf);
  int y2 = y1 + 22;
  int len2 = strlen(line2);
  for (int i = 1; i <= len2; ++i) {
    strncpy(buf, line2, i);
    buf[i] = '\0';
    int w = u8g2.getStrWidth(buf);
    u8g2.clearBuffer();
    // Keep the first line fully displayed while typing the second
    u8g2.setFont(u8g2_font_6x10_tf);
    u8g2.drawStr((SCREEN_WIDTH - u8g2.getStrWidth(line1)) / 2, y1, line1);
    u8g2.setFont(u8g2_font_7x13_tf);
    u8g2.drawStr((SCREEN_WIDTH - w) / 2, y2, buf);
    u8g2.sendBuffer();
    delay(60);
  }

  // Quick progress bar fill to give a sense of transition
  const int barW = SCREEN_WIDTH - 40;
  const int barH = 8;
  const int barX = (SCREEN_WIDTH - barW) / 2;
  const int barY = y2 + 14;
  u8g2.clearBuffer();
  // Draw static text lines
  u8g2.setFont(u8g2_font_6x10_tf);
  u8g2.drawStr((SCREEN_WIDTH - u8g2.getStrWidth(line1)) / 2, y1, line1);
  u8g2.setFont(u8g2_font_7x13_tf);
  u8g2.drawStr((SCREEN_WIDTH - u8g2.getStrWidth(line2)) / 2, y2, line2);
  u8g2.drawFrame(barX, barY, barW, barH);
  u8g2.sendBuffer();

  for (int p = 0; p <= barW - 2; p += 4) {
    u8g2.drawBox(barX + 1, barY + 1, p, barH - 2);
    u8g2.sendBuffer();
    delay(30);
  }

  delay(250);

  // End: set to idle pose ready for quiz
  oledShowMode(OLED_IDLE);
  go_idle();
}

// Assisting transition animation: short typewriter + centered READY! then quick listening cue
static void assist_mode_animation() {
  // Debug: always log when assist animation is invoked so we can trace calls
  Serial.println("assist_mode_animation: invoked");
  if (!oledReady) {
    Serial.println("assist_mode_animation: skipped because OLED not ready");
    return;
  }

  const char* line1 = "Assisting";
  const char* line2 = "LISTENING";

  // Typewriter for line1 (small font)
  u8g2.clearBuffer();
  u8g2.setFont(u8g2_font_6x10_tf);
  int y1 = 18;
  int len1 = strlen(line1);
  char buf[32];
  for (int i = 1; i <= len1; ++i) {
    strncpy(buf, line1, i);
    buf[i] = '\0';
    int w = u8g2.getStrWidth(buf);
    u8g2.clearBuffer();
    u8g2.drawStr((SCREEN_WIDTH - w) / 2, y1, buf);
    u8g2.sendBuffer();
    delay(45);
  }

  // Typewriter for line2 (slightly larger)
  u8g2.setFont(u8g2_font_7x13_tf);
  int y2 = y1 + 22;
  int len2 = strlen(line2);
  for (int i = 1; i <= len2; ++i) {
    strncpy(buf, line2, i);
    buf[i] = '\0';
    int w = u8g2.getStrWidth(buf);
    u8g2.clearBuffer();
    // Keep the first line fully displayed while typing the second
    u8g2.setFont(u8g2_font_6x10_tf);
    u8g2.drawStr((SCREEN_WIDTH - u8g2.getStrWidth(line1)) / 2, y1, line1);
    u8g2.setFont(u8g2_font_7x13_tf);
    u8g2.drawStr((SCREEN_WIDTH - w) / 2, y2, buf);
    u8g2.sendBuffer();
    delay(55);
  }

  // Small centered READY! to emphasize listening readiness
  const char* ready = "READY!";
  u8g2.clearBuffer();
  u8g2.setFont(u8g2_font_7x13_tf);
  int rw = u8g2.getStrWidth(ready);
  u8g2.drawStr((SCREEN_WIDTH - u8g2.getStrWidth(line1)) / 2, y1, line1);
  u8g2.drawStr((SCREEN_WIDTH - rw) / 2, y2, ready);
  u8g2.sendBuffer();
  delay(300);

  // Brief pulsing dot to indicate active listening
  for (int k = 0; k < 3; ++k) {
    u8g2.clearBuffer();
    u8g2.setFont(u8g2_font_6x10_tf);
    u8g2.drawStr((SCREEN_WIDTH - u8g2.getStrWidth(line1)) / 2, y1, line1);
    u8g2.drawStr((SCREEN_WIDTH - rw) / 2, y2, ready);
    int cx = SCREEN_WIDTH / 2;
    int cy = y2 + 18;
    int r = 2 + k; // simple pulsing radius
    u8g2.drawDisc(cx, cy, r, U8G2_DRAW_ALL);
    u8g2.sendBuffer();
    delay(180);
  }

  // End: return to idle/listening-ready pose
  oledShowMode(OLED_LISTENING);
}

// Custom two-line text display (typewriter effect). Used by host to show short messages.
static void show_custom_text(const char* line1, const char* line2, int hold_ms) {
  if (!oledReady) return;
  // Typewriter line1
  u8g2.clearBuffer();
  u8g2.setFont(u8g2_font_6x10_tf);
  int y1 = 18;
  int len1 = strlen(line1);
  char buf[128];
  for (int i = 1; i <= len1; ++i) {
    strncpy(buf, line1, i);
    buf[i] = '\0';
    int w = u8g2.getStrWidth(buf);
    u8g2.clearBuffer();
    u8g2.drawStr((SCREEN_WIDTH - w) / 2, y1, buf);
    u8g2.sendBuffer();
    delay(35);
  }

  // Typewriter line2
  u8g2.setFont(u8g2_font_6x10_tf);
  int y2 = y1 + 22;
  int len2 = strlen(line2);
  for (int i = 1; i <= len2; ++i) {
    strncpy(buf, line2, i);
    buf[i] = '\0';
    int w = u8g2.getStrWidth(buf);
    u8g2.clearBuffer();
    u8g2.drawStr((SCREEN_WIDTH - u8g2.getStrWidth(line1)) / 2, y1, line1);
    u8g2.drawStr((SCREEN_WIDTH - w) / 2, y2, buf);
    u8g2.sendBuffer();
    delay(35);
  }

  // Hold message visible for hold_ms
  unsigned long started = millis();
  while (millis() - started < (unsigned long)hold_ms) {
    delay(50);
  }

  // Return to idle pose
  oledShowMode(OLED_IDLE);
  go_idle();
}