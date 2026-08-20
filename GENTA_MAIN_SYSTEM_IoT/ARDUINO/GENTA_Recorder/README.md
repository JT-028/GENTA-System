GENTA Arduino sketches

GENTA_chunked_upload.ino
- Purpose: read /recording.wav from SPIFFS and upload to the server in 8KB chunks via HTTPS POST.
- Configure: set WIFI_SSID, WIFI_PASS, and SERVER_URL (use your ngrok HTTPS URL, e.g. https://nonbasic-bob-inimical.ngrok-free.dev/upload)
- Build: use Arduino IDE or PlatformIO targeting an ESP32 board.
- Notes:
  - The sketch uses WiFiClientSecure::setInsecure() to accept ngrok TLS certificates. For production replace this with certificate verification.
  - Ensure recording.wav exists in SPIFFS before running the sketch (upload via Tools->ESP32 Sketch Data Upload in Arduino IDE or use an uploader tool).
  - The sketch doesn't attempt retries; extend it for robust uploads.

Flashing and testing steps (summary):
1. Update the WIFI_SSID, WIFI_PASS, and SERVER_URL constants in the .ino file.
2. Install the ESP32 board package in Arduino IDE (or use PlatformIO).
3. Upload the sketch to the ESP32.
4. Use "ESP32 Sketch Data Upload" to write /recording.wav to SPIFFS, or ensure your device already recorded to SPIFFS.
5. Open Serial Monitor at 115200 baud to see upload progress and response.

