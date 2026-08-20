# GENTA ESP32 Sketch Implementation Guide

This guide explains how to use the ESP32 sketches included in this project. There are two ESP32 devices in the GENTA system:
1. **Recording Device** - Records audio, toggles state, and uploads audio to server
2. **Playback Device** - Downloads and plays TTS responses

## Recording Device Setup (ESP32 #1)

### Files
- **`GENTA_complete.ino`** - This is the main sketch you should use for the recording ESP32
- **`GENTA.ino`** - Original sketch with syntax errors (not recommended)
- **`GENTA_chunked_upload.ino`** - Efficient upload only, without recording (not complete)

### Configuration
1. Open `GENTA_complete.ino` in Arduino IDE
2. Update the following configuration variables:
   ```cpp
   const char* WIFI_SSID = "YOUR_SSID";       // Your WiFi network name
   const char* WIFI_PASS = "YOUR_PASSWORD";   // Your WiFi password
   const char* SERVER_URL = "https://nonbasic-bob-inimical.ngrok-free.dev/upload"; // Your ngrok URL
   ```

3. **Hardware Connections:**
   - I2S Microphone:
     - WS/LRCK → Pin 25
     - SD/DATA → Pin 33
     - SCK/BCLK → Pin 32
   - Status LED → Pin 26
   - Record Button → Pin 23 (with internal pullup)
   - Toggle Button → Pin 22 (with internal pullup)

4. **Functions:**
   - Long press record button (pin 23) to start recording
   - Release the record button to stop recording and upload the audio
   - Press toggle button (pin 22) to toggle state (written to state.txt)
   - Web server running at http://genta.local/

## Playback Device Setup (ESP32 #2)

### Files
- **`GENTA2_improved.ino`** - This is the main sketch you should use for playback ESP32
- **`GENTA2.ino`** - Original sketch with limited features (not recommended)

### Configuration
1. Open `GENTA2_improved.ino` in Arduino IDE
2. Update the following configuration variables:
   ```cpp
   const char* WIFI_SSID = "YOUR_SSID";       // Your WiFi network name
   const char* WIFI_PASS = "YOUR_PASSWORD";   // Your WiFi password
   const char* SERVER_URL = "https://nonbasic-bob-inimical.ngrok-free.dev/response.wav"; // Your ngrok response audio URL
   ```

3. **Hardware Connections:**
   - I2S DAC/Amplifier:
     - LRC/WS → Pin 27
     - BCK/BCLK → Pin 26
     - DIN/DATA → Pin 25
   - Optional Status LED → Pin 2

4. **Functions:**
   - Device polls for audio at the SERVER_URL every 3 seconds
   - LED turns on during audio playback
   - Better error handling with retries
   - Power saving between requests

## Building and Flashing

### Arduino IDE
1. Install ESP32 board support in Arduino IDE
   - Tools → Board → Boards Manager → Search for "esp32"
   - Install "ESP32 by Espressif Systems"
2. Select your ESP32 board (usually ESP32 Dev Module)
   - Tools → Board → ESP32 Arduino → ESP32 Dev Module
3. Select the correct port
   - Tools → Port → (your ESP32 COM port)
4. Install required libraries:
   - Sketch → Include Library → Manage Libraries
   - Install: WiFi, WiFiClientSecure (built-in)
5. Upload the sketch

### SPIFFS Data Upload (for web interface files)
1. Install the ESP32 Sketch Data Upload tool:
   - Follow [these instructions](https://github.com/me-no-dev/arduino-esp32fs-plugin)
2. Create a "data" folder in your sketch folder and add any web interface files
3. Upload the SPIFFS data:
   - Tools → ESP32 Sketch Data Upload

## Troubleshooting

### Common Issues:
1. **WiFi Connection Problems:**
   - Double-check SSID and password
   - Ensure the network is 2.4GHz (ESP32 doesn't support 5GHz)

2. **Upload Fails:**
   - Make sure SERVER_URL is correct and accessible
   - Check if ngrok tunnel is running
   - Verify the /upload endpoint exists on the server

3. **Audio Not Playing:**
   - Verify I2S connections
   - Check if audio file exists at the specified URL
   - Try different amplification settings

4. **SPIFFS Issues:**
   - Format SPIFFS if necessary (set FORMAT_FILESYSTEM to true temporarily)
   - Check available space with listSPIFFS()

## Updates and Improvements

The new sketches provide several advantages over the originals:
- Memory-efficient chunked uploads (no more out-of-memory errors)
- Better error handling and reporting
- Proper syntax and formatting
- Clear configuration sections
- Improved comments and organization
- WiFiClientSecure for HTTPS support
- More robust button handling

## Security Notes

These sketches use `setInsecure()` for HTTPS connections, which accepts any server certificate. This works with ngrok's changing certificates but isn't recommended for production. For a production deployment, implement proper certificate validation or fingerprint checking.
