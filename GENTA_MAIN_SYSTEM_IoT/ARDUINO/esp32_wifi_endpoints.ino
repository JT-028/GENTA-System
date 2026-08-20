/*
 * WiFi Configuration Endpoints for ESP32
 * Add these endpoints to your existing GENTA.ino and GENTA2.ino files
 * 
 * This allows configuring WiFi through HTTP requests from GENTA_Flask
 */

#include <Preferences.h>  // For storing WiFi credentials

// Global variables for WiFi management
Preferences preferences;
const char* PREF_NAMESPACE = "wifi_config";
const char* PREF_SSID = "ssid";
const char* PREF_PASSWORD = "password";

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
    Serial.println("Connection failed!");
    return false;
  }
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
// HTTP Endpoint Handlers (Add to your WebServer setup)
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
  
  // Parse JSON body
  String body = server.arg("plain");
  Serial.println("Received WiFi config: " + body);
  
  // Simple JSON parsing (you can use ArduinoJson library for better parsing)
  int ssidStart = body.indexOf("\"ssid\":\"") + 8;
  int ssidEnd = body.indexOf("\"", ssidStart);
  String ssid = body.substring(ssidStart, ssidEnd);
  
  int passStart = body.indexOf("\"password\":\"") + 12;
  int passEnd = body.indexOf("\"", passStart);
  String password = body.substring(passStart, passEnd);
  
  if (ssid.length() == 0) {
    server.send(400, "text/plain", "SSID is required");
    return;
  }
  
  // Save credentials
  if (saveWiFiCredentials(ssid, password)) {
    server.send(200, "application/json", 
                "{\"success\":true,\"message\":\"WiFi credentials saved. Restart to apply.\"}");
    Serial.println("WiFi credentials saved successfully");
  } else {
    server.send(500, "text/plain", "Failed to save credentials");
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

// ============================================================================
// Modified Setup Function
// ============================================================================

void setup() {
  Serial.begin(115200);
  delay(500);
  Serial.println("\n\n=== GENTA with WiFi Management ===");
  
  // Initialize NVS
  preferences.begin(PREF_NAMESPACE, true);
  preferences.end();
  
  // Load saved WiFi credentials
  String savedSSID, savedPassword;
  loadWiFiCredentials(savedSSID, savedPassword);
  
  // Try to connect with saved credentials
  if (savedSSID.length() > 0) {
    Serial.println("Found saved WiFi credentials");
    if (connectToWiFi(savedSSID, savedPassword)) {
      Serial.println("✓ Connected with saved credentials");
    } else {
      Serial.println("✗ Failed to connect with saved credentials");
      Serial.println("Trying fallback...");
      // Fallback to hardcoded WiFi (optional)
      connectToWiFi("YOUR_SSID", "YOUR_PASSWORD");
    }
  } else {
    Serial.println("No saved credentials, using hardcoded WiFi");
    // Use hardcoded credentials as fallback
    String ssid = "YOUR_SSID";
    String password = "YOUR_PASSWORD";
    if (connectToWiFi(ssid, password)) {
      // Save these as default
      saveWiFiCredentials(ssid, password);
    }
  }
  
  // Setup mDNS
  if (MDNS.begin(host)) {
    Serial.printf("mDNS started: %s.local\n", host);
    MDNS.addService("http", "tcp", 80);
  }
  
  // Register WiFi management endpoints
  server.on("/wifi/status", HTTP_GET, handleWiFiStatus);
  server.on("/wifi/configure", HTTP_POST, handleWiFiConfigure);
  server.on("/wifi/scan", HTTP_GET, handleWiFiScan);
  server.on("/restart", HTTP_GET, handleRestart);
  
  // ... rest of your existing setup code (I2S, OLED, etc.) ...
  
  server.begin();
  Serial.println("=== System Ready ===");
  Serial.printf("WiFi: %s\n", WiFi.SSID().c_str());
  Serial.printf("IP: %s\n", WiFi.localIP().toString().c_str());
}

// ============================================================================
// INTEGRATION INSTRUCTIONS
// ============================================================================

/*
 * HOW TO ADD TO YOUR EXISTING CODE:
 * 
 * 1. Add at top of file (with other includes):
 *    #include <Preferences.h>
 * 
 * 2. Add global variables (after other globals):
 *    Preferences preferences;
 *    const char* PREF_NAMESPACE = "wifi_config";
 *    const char* PREF_SSID = "ssid";
 *    const char* PREF_PASSWORD = "password";
 * 
 * 3. Copy all functions above into your code
 * 
 * 4. In your existing setup(), REPLACE the WiFi connection code with:
 *    
 *    // OLD CODE (remove this):
 *    WiFi.begin(ssid, password);
 *    while (WiFi.status() != WL_CONNECTED) {
 *      delay(500);
 *      Serial.print(".");
 *    }
 *    
 *    // NEW CODE (use this):
 *    String savedSSID, savedPassword;
 *    loadWiFiCredentials(savedSSID, savedPassword);
 *    if (savedSSID.length() > 0) {
 *      if (!connectToWiFi(savedSSID, savedPassword)) {
 *        connectToWiFi("YOUR_SSID", "YOUR_PASSWORD");  // Fallback
 *      }
 *    } else {
 *      connectToWiFi("YOUR_SSID", "YOUR_PASSWORD");
 *      saveWiFiCredentials("YOUR_SSID", "YOUR_PASSWORD");
 *    }
 * 
 * 5. Register endpoints (add to setup() after server.on() calls):
 *    server.on("/wifi/status", HTTP_GET, handleWiFiStatus);
 *    server.on("/wifi/configure", HTTP_POST, handleWiFiConfigure);
 *    server.on("/wifi/scan", HTTP_GET, handleWiFiScan);
 *    server.on("/restart", HTTP_GET, handleRestart);
 * 
 * 6. That's it! Upload and test
 */

// ============================================================================
// TESTING THE ENDPOINTS
// ============================================================================

/*
 * Test with curl or browser:
 * 
 * 1. Get current status:
 *    curl http://192.168.50.62/wifi/status
 * 
 * 2. Configure new WiFi:
 *    curl -X POST http://192.168.50.62/wifi/configure \
 *         -H "Content-Type: application/json" \
 *         -d '{"ssid":"NewNetwork","password":"NewPass123"}'
 * 
 * 3. Scan networks:
 *    curl http://192.168.50.62/wifi/scan
 * 
 * 4. Restart ESP32:
 *    curl http://192.168.50.62/restart
 */
