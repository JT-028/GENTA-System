// ESP32 I2S WAV player for MAX98357A - cleaned, compile-ready
#include <SPIFFS.h>
#include <driver/i2s.h>
#include <math.h>

// Use same pin assignments as GENTA2.ino
// NOTE: many MAX98357A boards expect WS (L/R clock) and BCLK wired in a
// particular orientation. You told me your wiring is BCLK=27 and LRC=26,
// so set them here. If your wiring differs, swap these back.
#define I2S_DOUT      25   // Data out (to DIN of MAX98357A)
#define I2S_BCLK      26   // Bit clock (BCLK)
#define I2S_LRC       27   // Left/right clock (WS)

  // I2S port
  #define I2S_PORT      I2S_NUM_0

  // Default buffer size for streaming
  #define BUFFER_SIZE 1024
  // Output buffer size when converting mono->stereo (bytes)
  #define OUT_BUFFER_SIZE (BUFFER_SIZE * 2)

  // Set to filepath to autoplay at boot. Set to NULL or empty string to disable. 
  const char* AUTO_PLAY_FILE = "/response.wav"; // change to your file in SPIFFS

  // Optional amplifier shutdown (SD) control pin. Set to -1 to leave alone.
  #define AMP_SD_PIN -1

  // Debug pin toggled around I2S writes so you can observe activity with an LED or meter
  #define DEBUG_PIN 2

  // Forward declarations
  void i2sInit(uint32_t sampleRate);
  bool playWavFromSPIFFS(const char* path);
  void listFiles();
  void printHelp();
  void playTone(uint32_t freq, uint32_t ms);
  void playToneFs(uint32_t freq, uint32_t ms, uint32_t sampleRate);
  void playSquareFs(uint32_t freq, uint32_t ms, uint32_t sampleRate);
  void ampEnable();
  void ampDisable();

  // Runtime-configurable amp SD pin (initialized from AMP_SD_PIN)
  int ampSdPin = AMP_SD_PIN;

  // control flag to stop blocking playback loops
  volatile bool stopPlayback = false;

  // Software volume gain (0.0 = silent, 1.0 = unity). Can be set by serial command 'vol <0-100>' percent.
  float volumeGain = 1.0f;

  // Runtime I2S mode controls
  // Default to stereo output (RIGHT_LEFT). Users with a mono MAX98357A can
  // switch to mono at runtime with: `i2s chan left`.
  bool useAPLL = true; // try to use APLL for non-standard sample rates
  i2s_channel_fmt_t runtimeChannelFormat = I2S_CHANNEL_FMT_RIGHT_LEFT; // default stereo output
  int runtimeCommFormat = (int)I2S_COMM_FORMAT_I2S; // use standard I2S comm format by default
  uint32_t lastSampleRate = 16000;

  void setup() {
    Serial.begin(115200);
    delay(100);
    Serial.println("ESP32 I2S WAV player for MAX98357A");

    if (!SPIFFS.begin(true)) {
      Serial.println("SPIFFS Mount Failed");
      while (1) delay(1000);
    }

    // Debug pin to indicate I2S write activity (LED or meter can observe)
    pinMode(DEBUG_PIN, OUTPUT);
    digitalWrite(DEBUG_PIN, LOW);

    // Initialize I2S with a safe default sample rate (will re-init later if WAV sampleRate differs)
    i2sInit(16000);

    // If an autoplay file is configured and present in SPIFFS, play it once at boot
    if (AUTO_PLAY_FILE != nullptr && strlen(AUTO_PLAY_FILE) > 0) {
      if (SPIFFS.exists(AUTO_PLAY_FILE)) {
        Serial.printf("Autoplay file found: %s\n", AUTO_PLAY_FILE);
        Serial.println("Press any key within 2 seconds to cancel autoplay...");
        unsigned long start = millis();
        bool cancel = false;
        while (millis() - start < 2000) {
          if (Serial.available()) { Serial.read(); cancel = true; break; }
          delay(10);
        }
        if (cancel) {
          Serial.println("Autoplay cancelled by user.");
        } else {
          Serial.printf("Autoplaying %s at boot...\n", AUTO_PLAY_FILE);
          playWavFromSPIFFS(AUTO_PLAY_FILE);
        }
      } else {
        Serial.printf("Autoplay file not found: %s\n", AUTO_PLAY_FILE);
      }
    }

    Serial.println("Type 'help' for commands (ls, play <filename>, help)");
  }

  void loop() {
    if (!Serial.available()) { delay(10); return; }
    String line = Serial.readStringUntil('\n');
    line.trim();
    if (line.length() == 0) return;

    if (line.equalsIgnoreCase("help")) { printHelp(); return; }
    if (line.equalsIgnoreCase("ls") || line.equalsIgnoreCase("dir")) { listFiles(); return; }
    if (line.startsWith("play ")) { String fn=line.substring(5); fn.trim(); if (fn.length()==0) Serial.println("Usage: play /filename.wav"); else { stopPlayback=false; playWavFromSPIFFS(fn.c_str()); } return; }
    if (line.equalsIgnoreCase("stop")) { stopPlayback=true; Serial.println("Stop requested"); return; }

    if (line.startsWith("tone ")) {
      String args=line.substring(5); args.trim(); int sp=args.indexOf(' ');
      if (sp==-1) Serial.println("Usage: tone <freq> <duration_ms>");
      else { uint32_t f=args.substring(0,sp).toInt(); uint32_t d=args.substring(sp+1).toInt(); if (f==0||d==0) Serial.println("Invalid args"); else { stopPlayback=false; playTone(f,d); } }
      return;
    }

    if (line.startsWith("tonefs ")) {
      String args=line.substring(7); args.trim(); int sp1=args.indexOf(' '); int sp2=-1; if (sp1!=-1) sp2=args.indexOf(' ',sp1+1);
      if (sp1==-1||sp2==-1) Serial.println("Usage: tonefs <freq> <duration_ms> <samplerate>");
      else { uint32_t f=args.substring(0,sp1).toInt(); uint32_t d=args.substring(sp1+1,sp2).toInt(); uint32_t sr=args.substring(sp2+1).toInt(); if (f==0||d==0||sr==0) Serial.println("Invalid args"); else { stopPlayback=false; playToneFs(f,d,sr); } }
      return;
    }

    if (line.startsWith("sqfs ")) {
      String args=line.substring(5); args.trim(); int sp1=args.indexOf(' '); int sp2=-1; if (sp1!=-1) sp2=args.indexOf(' ',sp1+1);
      if (sp1==-1||sp2==-1) Serial.println("Usage: sqfs <freq> <duration_ms> <samplerate>");
      else { uint32_t f=args.substring(0,sp1).toInt(); uint32_t d=args.substring(sp1+1,sp2).toInt(); uint32_t sr=args.substring(sp2+1).toInt(); if (f==0||d==0||sr==0) Serial.println("Invalid args"); else { stopPlayback=false; playSquareFs(f,d,sr); } }
      return;
    }

      if (line.startsWith("vol ")) {
        String v = line.substring(4); v.trim(); int pct = v.toInt();
        if (pct < 0) pct = 0; if (pct > 200) pct = 200; // allow up to 200% if desired
        volumeGain = ((float)pct) / 100.0f;
        Serial.printf("Volume set to %d%% (gain=%0.2f)\n", pct, volumeGain);
        return;
      }

    if (line.startsWith("sq ")) { String args=line.substring(3); args.trim(); int sp=args.indexOf(' '); if (sp==-1) Serial.println("Usage: sq <freq> <duration_ms>"); else { uint32_t f=args.substring(0,sp).toInt(); uint32_t d=args.substring(sp+1).toInt(); if (f==0||d==0) Serial.println("Invalid args"); else { stopPlayback=false; playSquareFs(f,d,16000); } } return; }

    if (line.startsWith("amp ")) {
      String args=line.substring(4); args.trim();
      if (args.startsWith("pin ")) { String v=args.substring(4); v.trim(); int p=v.toInt(); if (p<0) Serial.println("Invalid pin number"); else { ampSdPin=p; Serial.printf("AMP SD pin set to %d\n", ampSdPin); } }
      else if (args.equalsIgnoreCase("enable")) { ampEnable(); }
      else if (args.equalsIgnoreCase("disable")) { ampDisable(); }
      else Serial.println("Usage: amp pin <n> | amp enable | amp disable");
      return;
    }

    if (line.startsWith("i2s ")) {
      String args=line.substring(4); args.trim();
      if (args.equalsIgnoreCase("info")) { Serial.printf("I2S settings: useAPLL=%s, channelFormat=%d, commFormat=%d\n", useAPLL?"ON":"OFF", (int)runtimeChannelFormat, runtimeCommFormat); }
      else if (args.startsWith("apll ")) { String v=args.substring(5); v.trim(); if (v.equalsIgnoreCase("on")) { useAPLL=true; Serial.println("I2S: APLL enabled"); } else if (v.equalsIgnoreCase("off")) { useAPLL=false; Serial.println("I2S: APLL disabled"); } else Serial.println("Usage: i2s apll on|off"); }
      else if (args.startsWith("chan ")) { String v=args.substring(5); v.trim(); if (v.equalsIgnoreCase("left")) { runtimeChannelFormat=I2S_CHANNEL_FMT_ONLY_LEFT; Serial.println("I2S: channel = ONLY_LEFT"); } else if (v.equalsIgnoreCase("stereo")) { runtimeChannelFormat=I2S_CHANNEL_FMT_RIGHT_LEFT; Serial.println("I2S: channel = RIGHT_LEFT"); } else Serial.println("Usage: i2s chan left|stereo"); }
      else { Serial.println("Unknown i2s command. Usage: i2s info | i2s apll on|off | i2s chan left|stereo"); }
      Serial.println("You may need to re-init I2S for changes to take effect: use 'i2s reinit' or re-run tone/play commands.");
      return;
    }

    if (line.equalsIgnoreCase("i2s reinit")) { i2sInit(lastSampleRate); Serial.printf("I2S reinitialized @ %u Hz\n", lastSampleRate); return; }
    if (line.startsWith("i2s comm ")) { String v=line.substring(9); v.trim(); if (v.equalsIgnoreCase("msb")) { runtimeCommFormat=(int)I2S_COMM_FORMAT_I2S_MSB; Serial.println("I2S: comm = MSB"); } else if (v.equalsIgnoreCase("std")) { runtimeCommFormat=(int)I2S_COMM_FORMAT_I2S; Serial.println("I2S: comm = STD"); } else Serial.println("Usage: i2s comm msb|std"); return; }

    if (line.startsWith("diag ")) {
      String fn = line.substring(5); fn.trim(); if (fn.length()==0) { Serial.println("Usage: diag /file.wav"); } else {
        // diagnostic: print WAV header info and play a short tone at that sample rate
        String path = fn;
        if (!SPIFFS.exists(path)) { Serial.printf("File not found: %s\n", path.c_str()); }
        else {
          File df = SPIFFS.open(path, "r"); if (!df) { Serial.println("Failed to open file"); }
          else {
            // minimal header parse (like playWavFromSPIFFS)
            char riff[4]; df.readBytes(riff,4); df.seek(df.position()+4); char wave[4]; df.readBytes(wave,4);
            uint16_t audioFormat=0, numChannels=0; uint32_t sampleRate=0; uint16_t bitsPerSample=0; uint32_t dataChunkPos=0, dataChunkSize=0;
            while (df.available()) {
              char chunkId[4]; if (df.readBytes(chunkId,4)!=4) break;
              uint32_t chunkSize = (uint32_t)df.read() | ((uint32_t)df.read()<<8) | ((uint32_t)df.read()<<16) | ((uint32_t)df.read()<<24);
              if (strncmp(chunkId,"fmt ",4)==0) {
                audioFormat = (uint16_t)df.read() | ((uint16_t)df.read()<<8);
                numChannels = (uint16_t)df.read() | ((uint16_t)df.read()<<8);
                sampleRate = (uint32_t)df.read() | ((uint32_t)df.read()<<8) | ((uint32_t)df.read()<<16) | ((uint32_t)df.read()<<24);
                df.seek(df.position()+6);
                bitsPerSample = (uint16_t)df.read() | ((uint16_t)df.read()<<8);
                uint32_t fmtRead = 16; if (chunkSize > fmtRead) df.seek(df.position() + (chunkSize - fmtRead));
              } else if (strncmp(chunkId,"data",4)==0) { dataChunkPos = df.position(); dataChunkSize = chunkSize; break; }
              else { df.seek(df.position() + chunkSize); }
            }
            Serial.printf("DIAG: audioFormat=%u, sampleRate=%u, channels=%u, bits=%u, dataBytes=%u\n", audioFormat, sampleRate, numChannels, bitsPerSample, dataChunkSize);
            df.close();
            if (audioFormat==1 && bitsPerSample==16 && sampleRate>0) {
              Serial.println("Playing 1 kHz test tone at the file's sample rate...");
              stopPlayback=false; playToneFs(1000,500,sampleRate);
            } else Serial.println("File not supported for diag tone (only 16-bit PCM) or sampleRate unknown.");
          }
        }
      }
      return;
    }

    Serial.println("Unknown command - type 'help'");
  }

  void printHelp() {
    Serial.println("Commands:");
    Serial.println("  ls               - list files in SPIFFS");
    Serial.println("  play /file.wav   - play a wav file from SPIFFS");
    Serial.println("  tone <freq> <ms> - play a sine tone at 16kHz sample rate");
    Serial.println("  tonefs <f> <ms> <sr> - play sine tone at given sample rate");
    Serial.println("  sq <f> <ms> - play square test at 16kHz (easy to hear)");
    Serial.println("  sqfs <f> <ms> <sr> - square test at sample rate");
    Serial.println("  amp pin <n> - set GPIO to use for AMP SD (runtime)");
    Serial.println("  amp enable|disable - manually enable/disable amp SD");
    Serial.println("  help             - show this message");
  }

  void listFiles() {
    Serial.println("SPIFFS files:");
    File root = SPIFFS.open("/");
    if (!root) { Serial.println("Failed to open SPIFFS root"); return; }
    File file = root.openNextFile();
    while (file) { Serial.printf("%s\t%u\n", file.name(), (unsigned)file.size()); file = root.openNextFile(); }
  }

  void i2sInit(uint32_t sampleRate) {
    i2s_driver_uninstall(I2S_PORT);
    lastSampleRate = sampleRate;

    i2s_config_t i2s_config = {
      .mode = (i2s_mode_t)(I2S_MODE_MASTER | I2S_MODE_TX),
      .sample_rate = sampleRate,
      .bits_per_sample = I2S_BITS_PER_SAMPLE_16BIT,
      .channel_format = runtimeChannelFormat,
      .communication_format = (i2s_comm_format_t)runtimeCommFormat,
      .intr_alloc_flags = ESP_INTR_FLAG_LEVEL1,
      .dma_buf_count = 4,
      .dma_buf_len = 512,
      .use_apll = useAPLL,
      .tx_desc_auto_clear = true,
      .fixed_mclk = 0
    };

    i2s_pin_config_t pin_config = {
      .bck_io_num = I2S_BCLK,
      .ws_io_num = I2S_LRC,
      .data_out_num = I2S_DOUT,
      .data_in_num = I2S_PIN_NO_CHANGE
    };

    esp_err_t err = i2s_driver_install(I2S_PORT, &i2s_config, 0, NULL);
    if (err != ESP_OK) { Serial.printf("i2s_driver_install failed: 0x%08X\n", err); return; }
    i2s_set_pin(I2S_PORT, &pin_config);
    Serial.printf("I2S initialized @ %u Hz\n", sampleRate);
  }

  // --- playWavFromSPIFFS (simplified) ---
  bool playWavFromSPIFFS(const char* path) {
    if (!SPIFFS.exists(path)) { Serial.printf("File not found: %s\n", path); return false; }
    File f = SPIFFS.open(path, "r"); if (!f) { Serial.println("Failed to open file"); return false; }

    // Minimal RIFF/WAVE header parsing (PCM 16-bit)
    char riff[4]; f.readBytes(riff,4); if (strncmp(riff,"RIFF",4)!=0) { Serial.println("Not a RIFF file"); f.close(); return false; }
    // skip file size (4 bytes)
    f.seek(f.position() + 4);

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
    ampEnable();
    i2sInit(sampleRate);

    f.seek(dataChunkPos);
    uint8_t buffer[BUFFER_SIZE]; uint8_t outbuf[OUT_BUFFER_SIZE]; uint32_t remaining = dataChunkSize;
    while (remaining>0) {
      size_t toRead = (remaining>BUFFER_SIZE)?BUFFER_SIZE:remaining;
      size_t actuallyRead = f.readBytes((char*)buffer, toRead);
      if (actuallyRead==0) break;
      if (stopPlayback) { Serial.println("Playback stopped by user."); break; }
      size_t bytes_written = 0;
      if (numChannels==1) {
          // If the I2S driver is configured for ONLY_LEFT/ONLY_RIGHT (mono output),
          // write native mono 16-bit samples. If the driver is configured for stereo
          // (RIGHT_LEFT), duplicate samples into stereo as before.
          if (runtimeChannelFormat == I2S_CHANNEL_FMT_ONLY_LEFT || runtimeChannelFormat == I2S_CHANNEL_FMT_ONLY_RIGHT) {
            // Process mono samples in-place into a temporary mono output buffer
            size_t samples = actuallyRead / 2; if (samples > (BUFFER_SIZE/2)) samples = BUFFER_SIZE/2;
            // reuse outbuf as a temporary buffer for scaled mono samples (OUT_BUFFER_SIZE >= BUFFER_SIZE)
            for (size_t s = 0; s < samples; ++s) {
              int16_t sample = (int16_t)((uint16_t)buffer[s*2] | ((uint16_t)buffer[s*2+1] << 8));
              int32_t scaled = (int32_t)((float)sample * volumeGain);
              if (scaled > 32767) scaled = 32767; else if (scaled < -32768) scaled = -32768;
              int16_t out = (int16_t)scaled;
              outbuf[s*2] = (uint8_t)(out & 0xFF);
              outbuf[s*2+1] = (uint8_t)((out >> 8) & 0xFF);
            }
            size_t outBytes = samples * 2;
            digitalWrite(DEBUG_PIN, HIGH);
            i2s_write(I2S_PORT, outbuf, outBytes, &bytes_written, portMAX_DELAY);
            digitalWrite(DEBUG_PIN, LOW);
            size_t consumed = samples * 2; if (consumed < actuallyRead) { f.seek(f.position() - (actuallyRead - consumed)); remaining -= consumed; } else remaining -= actuallyRead;
          } else {
            // stereo driver: duplicate mono samples into interleaved stereo outbuf
            size_t samples = actuallyRead/2; if (samples*4>OUT_BUFFER_SIZE) samples = OUT_BUFFER_SIZE/4;
            for (size_t s=0;s<samples;++s) {
              int16_t sample = (int16_t)((uint16_t)buffer[s*2] | ((uint16_t)buffer[s*2+1] << 8));
              int32_t scaled = (int32_t)((float)sample * volumeGain);
              if (scaled > 32767) scaled = 32767; else if (scaled < -32768) scaled = -32768;
              int16_t out = (int16_t)scaled;
              outbuf[s*4] = (uint8_t)(out & 0xFF);
              outbuf[s*4+1] = (uint8_t)((out >> 8) & 0xFF);
              outbuf[s*4+2] = outbuf[s*4];
              outbuf[s*4+3] = outbuf[s*4+1];
            }
            size_t outBytes = samples*4;
            digitalWrite(DEBUG_PIN,HIGH);
            i2s_write(I2S_PORT, outbuf, outBytes, &bytes_written, portMAX_DELAY);
            digitalWrite(DEBUG_PIN,LOW);
            size_t consumed = samples*2; if (consumed<actuallyRead) { f.seek(f.position() - (actuallyRead - consumed)); remaining -= consumed; } else remaining -= actuallyRead;
          }
        } else {
          // stereo input handling
          // If the I2S driver is configured for mono output, mix L+R -> mono to avoid
          // phase cancellations and "stuffy" sound when summing stereo into a mono amp.
          if (runtimeChannelFormat == I2S_CHANNEL_FMT_ONLY_LEFT || runtimeChannelFormat == I2S_CHANNEL_FMT_ONLY_RIGHT) {
            // number of 16-bit samples
            size_t sampleCount = actuallyRead / 2; // samples (L and R interleaved)
            size_t frames = sampleCount / 2; // stereo frames
            if (frames > (OUT_BUFFER_SIZE/2)) frames = OUT_BUFFER_SIZE/2;
            for (size_t f = 0; f < frames; ++f) {
              int16_t left = (int16_t)((uint16_t)buffer[f*4] | ((uint16_t)buffer[f*4+1] << 8));
              int16_t right = (int16_t)((uint16_t)buffer[f*4+2] | ((uint16_t)buffer[f*4+3] << 8));
              int32_t mixed = ((int32_t)left + (int32_t)right) / 2; // simple average
              int32_t scaled = (int32_t)((float)mixed * volumeGain);
              if (scaled > 32767) scaled = 32767; else if (scaled < -32768) scaled = -32768;
              int16_t out = (int16_t)scaled;
              outbuf[f*2] = (uint8_t)(out & 0xFF);
              outbuf[f*2+1] = (uint8_t)((out >> 8) & 0xFF);
            }
            size_t outBytes = frames * 2;
            digitalWrite(DEBUG_PIN,HIGH);
            i2s_write(I2S_PORT, outbuf, outBytes, &bytes_written, portMAX_DELAY);
            digitalWrite(DEBUG_PIN,LOW);
            size_t consumed = frames * 4; // consumed bytes from original buffer
            if (consumed < actuallyRead) { f.seek(f.position() - (actuallyRead - consumed)); remaining -= consumed; } else remaining -= actuallyRead;
          } else {
            // stereo driver: scale each 16-bit sample in-place before writing
            size_t sampleCount = actuallyRead / 2;
            for (size_t si = 0; si < sampleCount; ++si) {
              int16_t sample = (int16_t)((uint16_t)buffer[si*2] | ((uint16_t)buffer[si*2+1] << 8));
              int32_t scaled = (int32_t)((float)sample * volumeGain);
              if (scaled > 32767) scaled = 32767; else if (scaled < -32768) scaled = -32768;
              int16_t out = (int16_t)scaled;
              buffer[si*2] = (uint8_t)(out & 0xFF);
              buffer[si*2+1] = (uint8_t)((out >> 8) & 0xFF);
            }
            digitalWrite(DEBUG_PIN,HIGH);
            i2s_write(I2S_PORT, buffer, actuallyRead, &bytes_written, portMAX_DELAY);
            digitalWrite(DEBUG_PIN,LOW);
            remaining -= actuallyRead;
          }
        }
    }
    Serial.println("Playback finished"); f.close(); return true;
  }

  void playTone(uint32_t freq, uint32_t ms) {
    const uint32_t sampleRate = 16000; const uint16_t amplitude = 12000; const uint32_t samples = (sampleRate*ms)/1000;
    ampEnable(); i2sInit(sampleRate);
  const uint16_t chunk=256; int16_t buf[chunk*2];
    i2s_pin_config_t pinA = { .bck_io_num=I2S_BCLK, .ws_io_num=I2S_LRC, .data_out_num=I2S_DOUT, .data_in_num=I2S_PIN_NO_CHANGE };
    i2s_pin_config_t pinB = { .bck_io_num=I2S_LRC, .ws_io_num=I2S_BCLK, .data_out_num=I2S_DOUT, .data_in_num=I2S_PIN_NO_CHANGE };
    uint32_t segment = max((uint32_t)200, ms/2);
    Serial.printf("Testing pin mapping A: BCLK=%d WS=%d\n", pinA.bck_io_num, pinA.ws_io_num);
    i2s_set_pin(I2S_PORT, &pinA);
    uint32_t segSamples = (sampleRate*segment)/1000;
    bool isMonoOut = (runtimeChannelFormat == I2S_CHANNEL_FMT_ONLY_LEFT || runtimeChannelFormat == I2S_CHANNEL_FMT_ONLY_RIGHT);
    for (uint32_t i=0;i<segSamples;i+=chunk) {
      if (stopPlayback) { Serial.println("Tone stopped by user."); return; }
      uint32_t toGen = min((uint32_t)chunk, segSamples - i);
      if (isMonoOut) {
        for (uint32_t j=0;j<toGen;++j) {
          float t=(float)(i+j)/(float)sampleRate;
          float raw = (float)amplitude * sinf(2.0f*3.14159265f*(float)freq*t);
          int32_t scaled = (int32_t)(raw * volumeGain);
          if (scaled > 32767) scaled = 32767; else if (scaled < -32768) scaled = -32768;
          int16_t out = (int16_t)scaled;
          buf[j]=out;
        }
        size_t bytes = toGen * sizeof(int16_t);
        size_t written=0; digitalWrite(DEBUG_PIN,HIGH); i2s_write(I2S_PORT, buf, bytes, &written, portMAX_DELAY); digitalWrite(DEBUG_PIN,LOW);
      } else {
        for (uint32_t j=0;j<toGen;++j) {
          float t=(float)(i+j)/(float)sampleRate;
          float raw = (float)amplitude * sinf(2.0f*3.14159265f*(float)freq*t);
          int32_t scaled = (int32_t)(raw * volumeGain);
          if (scaled > 32767) scaled = 32767; else if (scaled < -32768) scaled = -32768;
          int16_t out = (int16_t)scaled;
          buf[j*2]=out; buf[j*2+1]=out;
        }
        size_t bytes = toGen*2*sizeof(int16_t); size_t written=0; digitalWrite(DEBUG_PIN,HIGH); i2s_write(I2S_PORT, buf, bytes, &written, portMAX_DELAY); digitalWrite(DEBUG_PIN,LOW);
      }
    }
    Serial.printf("Testing pin mapping B: BCLK=%d WS=%d\n", pinB.bck_io_num, pinB.ws_io_num);
    i2s_set_pin(I2S_PORT, &pinB);
    uint32_t segSamplesB = (sampleRate*(ms-segment))/1000;
    for (uint32_t i=0;i<segSamplesB;i+=chunk) {
      if (stopPlayback) { Serial.println("Tone stopped by user."); return; }
      uint32_t toGen = min((uint32_t)chunk, segSamplesB - i);
      if (isMonoOut) {
        for (uint32_t j=0;j<toGen;++j) {
          float t=(float)(i+j)/(float)sampleRate;
          float raw = (float)amplitude * sinf(2.0f*3.14159265f*(float)freq*t);
          int32_t scaled = (int32_t)(raw * volumeGain);
          if (scaled > 32767) scaled = 32767; else if (scaled < -32768) scaled = -32768;
          int16_t out = (int16_t)scaled;
          buf[j]=out;
        }
        size_t bytes = toGen * sizeof(int16_t); size_t written=0; digitalWrite(DEBUG_PIN,HIGH); i2s_write(I2S_PORT, buf, bytes, &written, portMAX_DELAY); digitalWrite(DEBUG_PIN,LOW);
      } else {
        for (uint32_t j=0;j<toGen;++j) {
          float t=(float)(i+j)/(float)sampleRate;
          float raw = (float)amplitude * sinf(2.0f*3.14159265f*(float)freq*t);
          int32_t scaled = (int32_t)(raw * volumeGain);
          if (scaled > 32767) scaled = 32767; else if (scaled < -32768) scaled = -32768;
          int16_t out = (int16_t)scaled;
          buf[j*2]=out; buf[j*2+1]=out;
        }
        size_t bytes = toGen*2*sizeof(int16_t); size_t written=0; digitalWrite(DEBUG_PIN,HIGH); i2s_write(I2S_PORT, buf, bytes, &written, portMAX_DELAY); digitalWrite(DEBUG_PIN,LOW);
      }
    }
    for (uint32_t i=0;i<samples;i+=chunk) {
      if (stopPlayback) { Serial.println("Tone stopped by user."); break; }
      uint32_t toGen = min((uint32_t)chunk, samples - i);
      if (isMonoOut) {
        for (uint32_t j=0;j<toGen;++j) {
          float t=(float)(i+j)/(float)sampleRate;
          float raw = (float)amplitude * sinf(2.0f*3.14159265f*(float)freq*t);
          int32_t scaled = (int32_t)(raw * volumeGain);
          if (scaled > 32767) scaled = 32767; else if (scaled < -32768) scaled = -32768;
          int16_t out = (int16_t)scaled;
          buf[j]=out;
        }
        size_t bytes = toGen * sizeof(int16_t); size_t written=0; digitalWrite(DEBUG_PIN,HIGH); i2s_write(I2S_PORT, buf, bytes, &written, portMAX_DELAY); digitalWrite(DEBUG_PIN,LOW);
      } else {
        for (uint32_t j=0;j<toGen;++j) {
          float t=(float)(i+j)/(float)sampleRate;
          float raw = (float)amplitude * sinf(2.0f*3.14159265f*(float)freq*t);
          int32_t scaled = (int32_t)(raw * volumeGain);
          if (scaled > 32767) scaled = 32767; else if (scaled < -32768) scaled = -32768;
          int16_t out = (int16_t)scaled;
          buf[j*2]=out; buf[j*2+1]=out;
        }
        size_t bytes = toGen*2*sizeof(int16_t); size_t written=0; digitalWrite(DEBUG_PIN,HIGH); i2s_write(I2S_PORT, buf, bytes, &written, portMAX_DELAY); digitalWrite(DEBUG_PIN,LOW);
      }
    }
    Serial.println("Tone finished");
  }

  void playToneFs(uint32_t freq, uint32_t ms, uint32_t sampleRate) {
    const uint16_t amplitude = 12000; const uint32_t samples = (sampleRate*ms)/1000;
    ampEnable(); i2sInit(sampleRate);
    const uint16_t chunk=256; int16_t buf[chunk*2];
    i2s_pin_config_t pinA = { .bck_io_num=I2S_BCLK, .ws_io_num=I2S_LRC, .data_out_num=I2S_DOUT, .data_in_num=I2S_PIN_NO_CHANGE };
    i2s_set_pin(I2S_PORT, &pinA);
    Serial.printf("ToneFs: sampleRate=%u Hz, freq=%u Hz, dur=%u ms\n", sampleRate, freq, ms);
    for (uint32_t i=0;i<samples;i+=chunk) {
      if (stopPlayback) { Serial.println("Tone stopped by user."); return; }
      uint32_t toGen = min((uint32_t)chunk, samples - i);
      for (uint32_t j=0;j<toGen;++j) {
        float t=(float)(i+j)/(float)sampleRate;
        float raw = (float)amplitude * sinf(2.0f*3.14159265f*(float)freq*t);
        int32_t scaled = (int32_t)(raw * volumeGain);
        if (scaled > 32767) scaled = 32767; else if (scaled < -32768) scaled = -32768;
        int16_t out = (int16_t)scaled;
        buf[j*2]=out; buf[j*2+1]=out;
      }
      size_t bytes = toGen*2*sizeof(int16_t); size_t written=0; digitalWrite(DEBUG_PIN,HIGH); i2s_write(I2S_PORT, buf, bytes, &written, portMAX_DELAY); digitalWrite(DEBUG_PIN,LOW);
    }
    Serial.println("ToneFs finished");
  }

  void ampEnable() {
    if (ampSdPin < 0) { Serial.println("AMP SD control not configured (ampSdPin < 0)"); return; }
    pinMode(ampSdPin, OUTPUT);
    digitalWrite(ampSdPin, HIGH);
    Serial.printf("AMP SD pin %d set HIGH (enabled)\n", ampSdPin);
  }

  void ampDisable() {
    if (ampSdPin < 0) { Serial.println("AMP SD control not configured (ampSdPin < 0)"); return; }
    pinMode(ampSdPin, OUTPUT);
    digitalWrite(ampSdPin, LOW);
    Serial.printf("AMP SD pin %d set LOW (disabled)\n", ampSdPin);
  }

  void playSquareFs(uint32_t freq, uint32_t ms, uint32_t sampleRate) {
    const int16_t amplitude = 15000; const uint32_t samples = (sampleRate*ms)/1000;
    ampEnable(); i2sInit(sampleRate);
    const uint16_t chunk=256; int16_t buf[chunk*2];
    uint32_t periodSamples = sampleRate / max((uint32_t)1, freq); if (periodSamples==0) periodSamples=1;
    for (uint32_t i=0;i<samples;i+=chunk) {
      if (stopPlayback) { Serial.println("Square test stopped by user."); return; }
      uint32_t toGen = min((uint32_t)chunk, samples - i);
      for (uint32_t j=0;j<toGen;++j) {
        uint32_t idx=i+j;
        int16_t s = ((idx % periodSamples) < (periodSamples/2)) ? amplitude : -amplitude;
        int32_t scaled = (int32_t)((float)s * volumeGain);
        if (scaled > 32767) scaled = 32767; else if (scaled < -32768) scaled = -32768;
        int16_t out = (int16_t)scaled;
        buf[j*2]=out; buf[j*2+1]=out;
      }
      size_t bytes = toGen*2*sizeof(int16_t); size_t written=0; digitalWrite(DEBUG_PIN,HIGH); i2s_write(I2S_PORT, buf, bytes, &written, portMAX_DELAY); digitalWrite(DEBUG_PIN,LOW);
    }
    Serial.println("Square test finished");
  }
