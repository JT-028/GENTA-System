#include <WiFi.h>
#include <HTTPClient.h>
#include <LittleFS.h>
#define SPIFFS LittleFS  // Compatibility layer - use LittleFS with SPIFFS name
#include <WiFiClientSecure.h>
#include <driver/i2s.h>
#include <WebServer.h>
#include <Preferences.h>  // For WiFi credential storage
#include <WiFiUdp.h>

// WiFi credentials now stored in NVS via Preferences
// (removed hardcoded credentials)

// Server (where TTS audio is hosted)
const char* audioURL = "https://nonbasic-bob-inimical.ngrok-free.dev/response.wav";

// State management (GPIO 22 button)
const int stateButtonPin = 22; // GPIO 22 for state toggle
const char* stateFilename = "/state.txt";
volatile int currentState = 0; // 0 = Assisting Mode, 1 = Quiz Mode
volatile bool stateButtonEnabled = false; // Disabled until LRN is entered

// WiFi management globals
Preferences preferences;
const char* PREF_NAMESPACE = "wifi_config";
const char* PREF_SSID = "ssid";
const char* PREF_PASSWORD = "password";

// LED for visual feedback (you can use any available GPIO)
const int feedbackLed = 2; // Built-in LED on ESP32 (GPIO 2)  

// I2S pins for speaker
#define I2S_DOUT      25   // Data out
#define I2S_BCLK      26   // Bit clock
#define I2S_LRC       27   // Left/right clock

#define I2S_PORT      I2S_NUM_0
#define SAMPLE_RATE   16000
#define BITS_PER_SAMPLE I2S_BITS_PER_SAMPLE_16BIT

// Buffer size for audio chunks
#define BUFFER_SIZE 1024

// Reinitialize I2S for a given sample rate and standard settings
void i2sInit(uint32_t sampleRate) {
  // uninstall any existing driver first
  i2s_driver_uninstall(I2S_PORT);

  const i2s_config_t i2s_config = {
    .mode = (i2s_mode_t)(I2S_MODE_MASTER | I2S_MODE_TX),
    .sample_rate = sampleRate,
    .bits_per_sample = I2S_BITS_PER_SAMPLE_16BIT,
    // default to stereo output like the tested player; server audio may be mono but duplication will be handled
    .channel_format = I2S_CHANNEL_FMT_RIGHT_LEFT,
    // use standard I2S communication format (worked best during tests)
    .communication_format = (i2s_comm_format_t)I2S_COMM_FORMAT_I2S,
    .intr_alloc_flags = ESP_INTR_FLAG_LEVEL1,
    .dma_buf_count = 4,
    .dma_buf_len = 512,
    .use_apll = true,
    .tx_desc_auto_clear = true,
    .fixed_mclk = 0
  };

  const i2s_pin_config_t pin_config = {
    .bck_io_num = I2S_BCLK,
    .ws_io_num = I2S_LRC,
    .data_out_num = I2S_DOUT,
    .data_in_num = I2S_PIN_NO_CHANGE
  };

  esp_err_t err = i2s_driver_install(I2S_PORT, &i2s_config, 0, NULL);
  if (err != ESP_OK) {
    Serial.printf("i2s_driver_install failed: 0x%08X\n", err);
    return;
  }
  i2s_set_pin(I2S_PORT, &pin_config);
  Serial.printf("I2S initialized @ %u Hz\n", sampleRate);
}

// Helper: minimal WAV streamer from LittleFS (used after HTTP download saved tmp file)
bool playWavFromSPIFFS(const char* path) {
  if (!SPIFFS.exists(path)) { Serial.printf("File not found: %s\n", path); return false; }
  File f = SPIFFS.open(path, "r"); if (!f) { Serial.println("Failed to open file"); return false; }

  // Minimal RIFF header parse
  char riff[4]; f.readBytes(riff,4); if (strncmp(riff,"RIFF",4)!=0) { Serial.println("Not a RIFF file"); f.close(); return false; }
  f.seek(f.position() + 4); // skip size
  char wave[4]; f.readBytes(wave,4); if (strncmp(wave,"WAVE",4)!=0) { Serial.println("Not a WAVE file"); f.close(); return false; }

  uint16_t audioFormat=0, numChannels=0; uint32_t sampleRate=0; uint16_t bitsPerSample=0; uint32_t dataChunkPos=0, dataChunkSize=0;
  while (f.available()) {
    char chunkId[4]; if (f.readBytes(chunkId,4)!=4) break;
    uint32_t chunkSize = (uint32_t)f.read() | ((uint32_t)f.read()<<8) | ((uint32_t)f.read()<<16) | ((uint32_t)f.read()<<24);
    if (strncmp(chunkId,"fmt ",4)==0) {
      audioFormat = (uint16_t)f.read() | ((uint16_t)f.read()<<8);
      numChannels = (uint16_t)f.read() | ((uint16_t)f.read()<<8);
      sampleRate = (uint32_t)f.read() | ((uint32_t)f.read()<<8) | ((uint32_t)f.read()<<16) | ((uint32_t)f.read()<<24);
      f.seek(f.position()+6);
      bitsPerSample = (uint16_t)f.read() | ((uint16_t)f.read()<<8);
      uint32_t fmtRead = 16; if (chunkSize > fmtRead) f.seek(f.position() + (chunkSize - fmtRead));
    } else if (strncmp(chunkId,"data",4)==0) { dataChunkPos = f.position(); dataChunkSize = chunkSize; break; }
    else { f.seek(f.position() + chunkSize); }
  }
  if (audioFormat!=1) { Serial.printf("Unsupported audio format: %u\n", audioFormat); f.close(); return false; }
  if (bitsPerSample!=16) { Serial.printf("Only 16-bit WAV supported (found %u)\n", bitsPerSample); f.close(); return false; }
  if (dataChunkPos==0) { Serial.println("No data chunk found"); f.close(); return false; }

  Serial.printf("WAV: %u Hz, %u channels, %u bits\n", sampleRate, numChannels, bitsPerSample);
  i2sInit(sampleRate);

  f.seek(dataChunkPos);
  const size_t BUFFER_SIZE_LOCAL = 1024;
  uint8_t buffer[BUFFER_SIZE_LOCAL];
  uint8_t outbuf[BUFFER_SIZE_LOCAL * 2];
  uint32_t remaining = dataChunkSize;
  while (remaining > 0) {
    size_t toRead = (remaining > BUFFER_SIZE_LOCAL) ? BUFFER_SIZE_LOCAL : remaining;
    size_t actuallyRead = f.readBytes((char*)buffer, toRead);
    if (actuallyRead == 0) break;
    size_t bytes_written = 0;
    if (numChannels == 1) {
      // duplicate mono -> stereo because I2S configured stereo by default
      size_t samples = actuallyRead / 2;
      if (samples * 4 > sizeof(outbuf)) samples = sizeof(outbuf) / 4;
      for (size_t s = 0; s < samples; ++s) {
        uint8_t lo = buffer[s*2]; uint8_t hi = buffer[s*2+1];
        // left
        outbuf[s*4]   = lo;
        outbuf[s*4+1] = hi;
        // right (duplicate)
        outbuf[s*4+2] = lo;
        outbuf[s*4+3] = hi;
      }
      i2s_write(I2S_PORT, outbuf, samples*4, &bytes_written, portMAX_DELAY);
    } else {
      // stereo file; write as-is
      i2s_write(I2S_PORT, buffer, actuallyRead, &bytes_written, portMAX_DELAY);
    }
    if (bytes_written == 0) break;
    if (actuallyRead > bytes_written) {
      // nothing special; continue
    }
    remaining -= actuallyRead;
  }
  f.close();
  Serial.println("Playback finished.");
  return true;
}

// --- LittleFS helper utilities (Serial commands) ---------------------------
void listSPIFFS() {
  Serial.println("Listing LittleFS files:");
  File root = SPIFFS.open("/");
  if (!root) {
    Serial.println("Failed to open LittleFS root");
    return;
  }
  if (!root.isDirectory()) {
    Serial.println("LittleFS root is not a directory");
    root.close();
    return;
  }
  File file = root.openNextFile();
  while (file) {
    String name = file.name();
    size_t size = file.size();
    Serial.printf("%s\t%u\n", name.c_str(), (unsigned)size);
    file = root.openNextFile();
  }
  root.close();
}

void printCAContents() {
  const char *path = "/ca.pem";
  if (!SPIFFS.exists(path)) {
    Serial.println("/ca.pem not found in LittleFS");
    return;
  }
  File f = SPIFFS.open(path, "r");
  if (!f) {
    Serial.println("Failed to open /ca.pem");
    return;
  }
  Serial.println("---- /ca.pem (first 2048 bytes) ----");
  size_t toRead = min((size_t)2048, (size_t)f.size());
  const size_t chunk = 256;
  char buf[chunk];
  size_t remaining = toRead;
  while (remaining > 0) {
    size_t r = f.readBytes(buf, min(chunk, remaining));
    if (r == 0) break;
    Serial.write((uint8_t*)buf, r);
    remaining -= r;
  }
  Serial.println("\n---- end ----");
  f.close();
}

void printHelper() {
  Serial.println("Serial helper commands:");
  Serial.println("  help       - show this message");
  Serial.println("  ls         - list files in LittleFS");
  Serial.println("  showca     - print first part of /ca.pem (if present)");
  Serial.println("  clearwifi  - clear saved WiFi credentials and restart");
}


// CA certificate loaded from LittleFS (PEM format)
String caCert;
bool haveCACert = false;

// HTTP server to accept uploads / playback commands
WebServer server(80);

// Helper: attempt to download audioURL using the provided secure client and play it.
// Returns true on success, false otherwise. Adds debug serial prints.
bool fetchAndPlayWithClient(WiFiClientSecure &clientRef) {
  HTTPClient http2;
  Serial.println("Beginning HTTP GET for TTS audio...");
  http2.begin(clientRef, audioURL);
  int httpCode2 = http2.GET();
  Serial.printf("HTTP GET returned %d\n", httpCode2);
  if (httpCode2 == HTTP_CODE_OK) {
    String contentType = http2.header("Content-Type");
    Serial.printf("Content-Type: %s\n", contentType.c_str());
    if (contentType.length() == 0 || !contentType.startsWith("audio")) {
      Serial.printf("Unexpected Content-Type: %s\n", contentType.c_str());
      http2.end();
      return false;
    }
    WiFiClient* stream2 = http2.getStreamPtr();
    uint8_t buffer2[BUFFER_SIZE];
    const char* tmpPath2 = "/tmp_response.wav";
    if (SPIFFS.exists(tmpPath2)) SPIFFS.remove(tmpPath2);
    File tmp2 = SPIFFS.open(tmpPath2, "w");
    if (!tmp2) {
      Serial.println("Failed to open temp file on LittleFS");
      http2.end();
      return false;
    }
    Serial.println("Saving HTTP audio to LittleFS...");
    unsigned long start2 = millis();
    while (http2.connected() || stream2->available()) {
      if (stream2->available()) {
        size_t len = stream2->readBytes(buffer2, BUFFER_SIZE);
        if (len > 0) tmp2.write(buffer2, len);
        start2 = millis();
      } else {
        if (millis() - start2 > 5000) break;
        delay(10);
      }
    }
    tmp2.close();
    Serial.println("Saved to LittleFS, playing file...");
    playWavFromSPIFFS(tmpPath2);
    SPIFFS.remove(tmpPath2);
    http2.end();
    return true;
  } else {
    Serial.printf("HTTP GET failed with code %d\n", httpCode2);
    http2.end();
    return false;
  }
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
void announceDeviceUDP(const char* role);

void setup() {
  Serial.begin(115200);

  // Init LittleFS (for temporary file storage)
  if (!SPIFFS.begin(true)) {
    Serial.println("LittleFS Mount Failed");
    return;
  }

  // Initialize pins
  pinMode(stateButtonPin, INPUT_PULLUP); // State button with pull-up
  pinMode(feedbackLed, OUTPUT);
  digitalWrite(feedbackLed, LOW);
  
  // Initialize state file if doesn't exist
  if (!SPIFFS.exists(stateFilename)) {
    File f = SPIFFS.open(stateFilename, FILE_WRITE);
    if (f) {
      f.print("0"); // Default to Assisting Mode
      f.close();
      currentState = 0;
      Serial.println("Created state.txt with default value: 0 (Assisting Mode)");
    }
  } else {
    // Read existing state
    File f = SPIFFS.open(stateFilename, "r");
    if (f) {
      String stateStr = f.readString();
      currentState = stateStr.toInt();
      f.close();
      Serial.printf("Read existing state: %d (%s)\n", currentState, 
                   (currentState == 0) ? "Assisting Mode" : "Quiz Mode");
    }
  }

  // Initialize I2S early so we can play local audio immediately (no need to wait for WiFi)
  i2sInit(SAMPLE_RATE);
  String savedSSID, savedPassword;
  loadWiFiCredentials(savedSSID, savedPassword);
  
  // Connect to WiFi
  Serial.println("=== WiFi Connection Process ===");
  bool wifiConnected = false;
  if (savedSSID.length() > 0) {
    Serial.printf("Found saved WiFi credentials for SSID: %s\n", savedSSID.c_str());
    if (connectToWiFi(savedSSID, savedPassword, 30)) {
      Serial.println("✓ Connected with saved credentials");
      wifiConnected = true;
      // Announce presence to local network (Flask listener)
      announceDeviceUDP("player");
    } else {
      Serial.println("✗ Failed to connect with saved credentials");
      Serial.println("Please check:");
      Serial.println("  1. SSID is correct");
      Serial.println("  2. Password is correct");
      Serial.println("  3. WiFi router is powered on");
      Serial.println("  4. ESP32 is in range of router");
      Serial.println("\nYou can update WiFi via web interface at:");
      Serial.println("http://192.168.4.1 (if in AP mode)");
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
      announceDeviceUDP("player");
    }
  }
  
  // Only print success details if actually connected
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
    announceDeviceUDP("player");
  } else {
    Serial.println("\n========================================");
    Serial.println("✗ WiFi Connection Failed");
    Serial.println("Device will continue in offline mode");
    Serial.println("Use serial command 'clearwifi' to reset credentials");
    Serial.println("========================================");
  }

  // Start HTTP server for uploading and controlling playback
  server.on("/upload", HTTP_POST, [](){
    server.send(200, "text/plain", "OK");
  }, [&](){
    HTTPUpload& upload = server.upload();
    static File upFile;
    const char* tmpPath = "/response.wav";
    if (upload.status == UPLOAD_FILE_START) {
      if (SPIFFS.exists(tmpPath)) SPIFFS.remove(tmpPath);
      upFile = SPIFFS.open(tmpPath, FILE_WRITE);
      Serial.println("Upload: start");
    } else if (upload.status == UPLOAD_FILE_WRITE) {
      if (upFile) upFile.write(upload.buf, upload.currentSize);
    } else if (upload.status == UPLOAD_FILE_END) {
      if (upFile) {
        upFile.close();
        unsigned int sz = 0;
        File info = SPIFFS.open(tmpPath, "r");
        if (info) { sz = (unsigned)info.size(); info.close(); }
        Serial.printf("Upload: saved %s (%u bytes)\n", tmpPath, sz);
        // Autoplay the uploaded response.wav immediately and then remove it to wait for next upload
        Serial.println("Autoplaying uploaded response.wav...");
        playWavFromSPIFFS(tmpPath);
        // remove after playback to clear for next response
        if (SPIFFS.exists(tmpPath)) SPIFFS.remove(tmpPath);
        Serial.println("Cleared uploaded response.wav after playback.");
      }
    }
  });

  // Upload welcome audio into /WelcomeAudio/<filename>
  server.on("/upload_welcome", HTTP_POST, [](){
    server.send(200, "text/plain", "OK");
  }, [&](){
    HTTPUpload& upload = server.upload();
    static File upFile;
    if (upload.status == UPLOAD_FILE_START) {
      String fname = String("/WelcomeAudio/") + String(upload.filename);
      if (SPIFFS.exists(fname)) SPIFFS.remove(fname);
      upFile = SPIFFS.open(fname, FILE_WRITE);
      Serial.printf("Welcome upload start: %s\n", fname.c_str());
    } else if (upload.status == UPLOAD_FILE_WRITE) {
      if (upFile) upFile.write(upload.buf, upload.currentSize);
    } else if (upload.status == UPLOAD_FILE_END) {
      if (upFile) {
        upFile.close();
        String saved = String("/WelcomeAudio/") + String(upload.filename);
        File info = SPIFFS.open(saved, "r");
        unsigned int sz = info ? (unsigned)info.size() : 0;
        if (info) info.close();
        Serial.printf("Saved welcome %s (%u bytes)\n", upload.filename, sz);
      }
    }
  });

  server.on("/play", HTTP_GET, [](){
    String path = server.arg("file");
    if (path.length() == 0) path = "/response.wav";
    if (!SPIFFS.exists(path.c_str())) {
      server.send(404, "text/plain", "FileNotFound");
      return;
    }
    server.send(200, "text/plain", "OK");
    // Trigger playback (blocking)
    playWavFromSPIFFS(path.c_str());
  });

  // Play an already-uploaded welcome file by name (no upload)
  server.on("/play_welcome", HTTP_GET, [](){
    String name = server.arg("name");
    if (name.length() == 0) {
      server.send(400, "text/plain", "MissingName");
      return;
    }
    // sanitize: disallow path traversal and only allow simple basenames
    if (name.indexOf("../") != -1 || name.indexOf('/') != -1 || name.indexOf('\\') != -1) {
      server.send(400, "text/plain", "InvalidName");
      return;
    }
    String path = "/WelcomeAudio/" + name;
    if (!SPIFFS.exists(path.c_str())) {
      server.send(404, "text/plain", "FileNotFound");
      return;
    }
    server.send(200, "text/plain", "OK");
    playWavFromSPIFFS(path.c_str());
  });

  server.on("/list", HTTP_GET, [](){
    String out = "[";
    File root = SPIFFS.open("/");
    if (root) {
      File file = root.openNextFile();
      while(file){
        if (out != "[") out += ',';
        out += "{\"name\":\"" + String(file.name()) + "\",\"size\":" + String(file.size()) + "}";
        file = root.openNextFile();
      }
    }
    out += "]";
    server.send(200, "application/json", out);
  });

  // Generic file upload endpoint (for ca.pem and other files)
  server.on("/upload_file", HTTP_POST, [](){
    server.send(200, "text/plain", "OK");
  }, [&](){
    HTTPUpload& upload = server.upload();
    static File upFile;
    if (upload.status == UPLOAD_FILE_START) {
      String filename = "/" + String(upload.filename);
      Serial.printf("File upload start: %s\n", filename.c_str());
      if (SPIFFS.exists(filename)) SPIFFS.remove(filename);
      upFile = SPIFFS.open(filename, FILE_WRITE);
    } else if (upload.status == UPLOAD_FILE_WRITE) {
      if (upFile) upFile.write(upload.buf, upload.currentSize);
    } else if (upload.status == UPLOAD_FILE_END) {
      if (upFile) {
        upFile.close();
        String filename = "/" + String(upload.filename);
        File info = SPIFFS.open(filename, "r");
        unsigned int sz = info ? (unsigned)info.size() : 0;
        if (info) info.close();
        Serial.printf("File uploaded: %s (%u bytes)\n", upload.filename, sz);
      }
    }
  });
  
  // State management endpoints
  server.on("/state.txt", HTTP_GET, [](){
    if (!SPIFFS.exists(stateFilename)) {
      server.send(404, "text/plain", "State file not found");
      return;
    }
    File f = SPIFFS.open(stateFilename, "r");
    if (f) {
      String stateContent = f.readString();
      f.close();
      server.send(200, "text/plain", stateContent);
      Serial.printf("State requested: %s\n", stateContent.c_str());
    } else {
      server.send(500, "text/plain", "Failed to read state");
    }
  });
  
  server.on("/set_state", HTTP_GET, [](){
    if (server.hasArg("value")) {
      String newState = server.arg("value");
      if (newState == "0" || newState == "1") {
        File f = SPIFFS.open(stateFilename, FILE_WRITE);
        if (f) {
          f.print(newState);
          f.close();
          currentState = newState.toInt();
          Serial.printf("State changed via HTTP: %s\n", newState.c_str());
          server.send(200, "text/plain", "State updated: " + newState);
        } else {
          server.send(500, "text/plain", "Failed to write state");
        }
      } else {
        server.send(400, "text/plain", "Invalid state value (use 0 or 1)");
      }
    } else {
      server.send(400, "text/plain", "Missing 'value' parameter");
    }
  });
  
  server.on("/enable_state_button", HTTP_GET, [](){
    stateButtonEnabled = true;
    Serial.println("State button ENABLED - User can now change modes");
    server.send(200, "text/plain", "State button enabled");
  });
  
  server.on("/disable_state_button", HTTP_GET, [](){
    stateButtonEnabled = false;
    Serial.println("State button DISABLED - User must enter LRN first");
    server.send(200, "text/plain", "State button disabled");
  });
  
  // WiFi management endpoints
  server.on("/wifi/status", HTTP_GET, handleWiFiStatus);
  server.on("/wifi/configure", HTTP_POST, handleWiFiConfigure);
  server.on("/wifi/scan", HTTP_GET, handleWiFiScan);
  server.on("/restart", HTTP_GET, handleRestart);

  server.begin();
  Serial.println("Playback HTTP server started on port 80");
  Serial.println("========================================");
  Serial.print("GENTA2 READY! Access at: http://");
  Serial.println(WiFi.localIP());
  Serial.println("========================================");

  // Auto-play a random welcome audio (if any) from /WelcomeAudio at boot
  {
    // Count welcome files
    File root = SPIFFS.open("/");
    int welcomeCount = 0;
    if (root) {
      File f = root.openNextFile();
      while (f) {
        String name = String(f.name());
        if (name.startsWith("/WelcomeAudio/")) welcomeCount++;
        f = root.openNextFile();
      }
      root.close();
    }
    if (welcomeCount > 0) {
      // pick an index and replay the file
      randomSeed((uint32_t)esp_random());
      int pick = random(0, welcomeCount);
      int idx = 0;
      File root2 = SPIFFS.open("/");
      if (root2) {
        File f2 = root2.openNextFile();
        while (f2) {
          String name = String(f2.name());
          if (name.startsWith("/WelcomeAudio/")) {
            if (idx == pick) {
              Serial.printf("Auto-playing welcome file: %s\n", name.c_str());
              playWavFromSPIFFS(name.c_str());
              break;
            }
            idx++;
          }
          f2 = root2.openNextFile();
        }
        root2.close();
      }
    } else {
      Serial.println("No welcome audio files found in /WelcomeAudio/");
    }
  }

  // Load CA cert from LittleFS
  if (SPIFFS.exists("/ca.pem")) {
    File caf = SPIFFS.open("/ca.pem", "r");
    if (caf) {
      caCert = caf.readString();
      caf.close();
      caCert.trim();
      if (caCert.length() > 0) {
        haveCACert = true;
        Serial.printf("Loaded CA cert (%u bytes) from /ca.pem\n", (unsigned)caCert.length());
      } else {
        Serial.println("/ca.pem found but empty");
      }
    } else {
      Serial.println("Failed to open /ca.pem for reading");
    }
  } else {
    Serial.println("/ca.pem not found in LittleFS; HTTPS downloads require /ca.pem for security");
  }
  Serial.println("Type 'help' on the serial console for LittleFS helper commands.");
}

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

// UDP announcement helper
const int DISCOVERY_UDP_PORT = 5005;

void announceDeviceUDP(const char* role) {
  // Give network stack a moment to stabilize after connection
  delay(500);
  
  WiFiUDP udp;
  String payload = "{";
  payload += "\"mac\":\"" + WiFi.macAddress() + "\",";
  payload += "\"role\":\"" + String(role) + "\",";
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
  
  String ssid;
  String password;
  
  // Try to read JSON body first
  String body = server.arg("plain");
  if (body.length() > 0) {
    Serial.println("Received WiFi config (body): " + body);
    // Simple JSON parsing (you can use ArduinoJson library for better parsing)
    int ssidStart = body.indexOf("\"ssid\":\"");
    if (ssidStart >= 0) {
      ssidStart += 8;
      int ssidEnd = body.indexOf('"', ssidStart);
      if (ssidEnd > ssidStart) {
        ssid = body.substring(ssidStart, ssidEnd);
      }
    }
    int passStart = body.indexOf("\"password\":\"");
    if (passStart >= 0) {
      passStart += 12;
      int passEnd = body.indexOf('"', passStart);
      if (passEnd > passStart) {
        password = body.substring(passStart, passEnd);
      }
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
          if (ssidEnd > ssidStart) {
            ssid = maybe.substring(ssidStart, ssidEnd);
          }
        }
        int passStart = maybe.indexOf("\"password\":\"");
        if (passStart >= 0) {
          passStart += 12;
          int passEnd = maybe.indexOf('"', passStart);
          if (passEnd > passStart) {
            password = maybe.substring(passStart, passEnd);
          }
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

void loop() {
  // Handle state toggle button (GPIO 22)
  static bool prevStateBtn = HIGH;
  bool curStateBtn = digitalRead(stateButtonPin);
  if (prevStateBtn == HIGH && curStateBtn == LOW) {
    delay(50); // Debounce
    if (digitalRead(stateButtonPin) == LOW) {
      Serial.println("*** STATE BUTTON PRESSED (GPIO 22) ***");
      // Check if state button is enabled (only after LRN entry)
      if (!stateButtonEnabled) {
        Serial.println("State button pressed but DISABLED - User must enter LRN first");
        // Quick visual feedback: single short blink to indicate disabled (non-blocking)
        digitalWrite(feedbackLed, HIGH);
        delay(100);
        digitalWrite(feedbackLed, LOW);
      } else {
        // Toggle state: 0 -> 1 or 1 -> 0
        currentState = (currentState == 0) ? 1 : 0;
        
        // Write new state to file
        File f = SPIFFS.open(stateFilename, FILE_WRITE);
        if (f) {
          f.print(currentState);
          f.close();
          Serial.printf("*** STATE CHANGED: %d (%s) ***\n", 
                       currentState, 
                       (currentState == 0) ? "Assisting Mode" : "Quiz Mode");
          
          // Quick visual feedback: single short blink (non-blocking)
          digitalWrite(feedbackLed, HIGH);
          delay(150);
          digitalWrite(feedbackLed, LOW);
        } else {
          Serial.println("Failed to update state file");
        }
      }
    }
  }
  prevStateBtn = curStateBtn;
  
  // Handle HTTP server requests FIRST (high priority!)
  server.handleClient();
  
  // Check for serial helper commands (non-blocking)
  if (Serial.available()) {
    String cmd = Serial.readStringUntil('\n');
    cmd.trim();
    if (cmd.length() > 0) {
      if (cmd.equalsIgnoreCase("help")) {
        printHelper();
      } else if (cmd.equalsIgnoreCase("ls")) {
        listSPIFFS();
      } else if (cmd.equalsIgnoreCase("showca") || cmd.equalsIgnoreCase("ca")) {
        printCAContents();
      } else if (cmd.equalsIgnoreCase("clearwifi") || cmd.equalsIgnoreCase("resetwifi")) {
        Serial.println("Clearing saved WiFi credentials...");
        preferences.begin(PREF_NAMESPACE, false);
        preferences.clear();
        preferences.end();
        Serial.println("✓ WiFi credentials cleared!");
        Serial.println("Restarting ESP32...");
        delay(1000);
        ESP.restart();
      } else {
        Serial.printf("Unknown command: %s\n", cmd.c_str());
        printHelper();
      }
    }
    // allow user to interact without immediately attempting downloads
    delay(200);
    return;
  }
  
  // DISABLED: Don't auto-fetch TTS in loop (it blocks HTTP server)
  // This should only be triggered by /upload endpoint or manual command
  // The constant HTTPS fetch attempts block server.handleClient()
  /*
  if (WiFi.status() == WL_CONNECTED) {
    if (!haveCACert) {
      Serial.println("Skipping HTTPS download: no CA cert loaded (SPIFFS:/ca.pem). Place a PEM file and reboot.");
      delay(5000);
      return;
    }

    // Try secure fetch (using CA cert). If it fails (HTTP_CODE -1), fall back to an insecure fetch
    HTTPClient http; // kept only for legacy scaffolding; actual work in helper
    WiFiClientSecure client;
    client.setCACert(caCert.c_str());
    Serial.println("Attempting secure HTTPS fetch for TTS audio (using /ca.pem)...");
    if (!fetchAndPlayWithClient(client)) {
      Serial.println("Secure fetch failed; attempting insecure TLS fallback (for testing only)...");
      WiFiClientSecure client_insecure;
      client_insecure.setInsecure();
      if (!fetchAndPlayWithClient(client_insecure)) {
        Serial.println("Insecure fetch also failed. Will retry later.");
      } else {
        Serial.println("Insecure fetch succeeded (note: certificate validation was skipped). Consider updating /ca.pem with the correct CA for production.");
      }
    } else {
      Serial.println("Secure fetch and playback completed successfully.");
    }
  }

  delay(5000); // Check every 5 seconds for new audio
  */
  
  // Small delay to prevent tight loop (allow other tasks to run)
  delay(10);
}
