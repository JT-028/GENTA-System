# ESP32 I2S WAV Player (MAX98357A)

Short guide for the `ESP32_I2S_Player.ino` sketch included in this folder.

Overview
- Streams 16-bit PCM WAV files from SPIFFS to a MAX98357A I2S amplifier.
- Uses the same I2S pins as `GENTA2.ino`: DATA(GPIO25), BCLK(GPIO26), LRC/WS(GPIO27).
- Supports mono or stereo 16-bit PCM WAV files. The sketch reads the WAV sample rate and reconfigures I2S accordingly (no resampling).
- Serial commands available to list and play files. Optional autoplay at boot is provided.

Wiring (MAX98357A)
- ESP32 GPIO25 -> DIN (DATA) on MAX98357A
- ESP32 GPIO26 -> BCLK on MAX98357A
- ESP32 GPIO27 -> LRC/WS on MAX98357A
- ESP32 GND -> GND on MAX98357A (common ground)
- MAX98357A VCC -> 3.3V or 5V as allowed by your module (check module specs)
- Speaker -> MAX98357A speaker outputs

Supported WAV format
- PCM (uncompressed) 16-bit per sample only.
- Channels: mono or stereo.
- The sketch will reject compressed formats (MP3, AAC) and other bit depths.

Uploading WAV to SPIFFS
1) Arduino IDE (ESP32 core + Filesystem Uploader plugin)
  - Install the ESP32 board support package and the "ESP32FS" plugin for uploading SPIFFS data.
  - Create a `data/` folder next to your sketch, place your `response.wav` inside it.
  - Use "Tools → ESP32 Sketch Data Upload" to copy files to SPIFFS.

2) PlatformIO
  - Put files in the project's `data/` folder and run:

```powershell
pio run --environment <your_env> --target uploadfs
```

3) Manual alternatives
  - Use an SD card version of the sketch (not included) or a separate upload utility that talks to the ESP32 filesystem.

Serial commands (115200 baud)
- ls or dir — list files in SPIFFS
- play /filename.wav — play the given WAV file from SPIFFS (include leading `/`)
- help — show command usage

Autoplay at boot
- The sketch defines `AUTO_PLAY_FILE` (default: `/response.wav`). If that file exists in SPIFFS the sketch will automatically play it once during `setup()` before printing the serial prompt.
- To disable autoplay set `AUTO_PLAY_FILE` to `""` or `NULL` in the sketch.

Notes & Troubleshooting
- If playback is silent: verify wiring, speaker, and that the amplifier module has power and correct VCC.
- If you see a format error on the serial console, confirm the WAV is 16-bit PCM. You can convert files using ffmpeg:

```powershell
ffmpeg -i input.mp3 -ac 2 -ar 16000 -sample_fmt s16 output.wav
```

- If the project linter in your editor complains about missing includes (e.g., `SPIFFS.h`), ensure your include path is set for the ESP32 Arduino core; the sketch still compiles with the ESP32 toolchain.
- For very large files consider using an SD card implementation (I can add it if needed).

License
- Minimal example provided as-is for testing and development.
