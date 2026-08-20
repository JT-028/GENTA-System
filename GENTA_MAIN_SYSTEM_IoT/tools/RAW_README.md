raw I2S to WAV conversion

This folder contains a small Python utility to convert the raw I2S DMA dump `raw_i2s.bin`
produced by the ESP32 recorder sketch into several candidate 16-bit PCM WAV files using
different decoding strategies.

Requirements
- Python 3.8+ (Windows)

How to run (PowerShell)

1. Copy `raw_i2s.bin` from the ESP32 SPIFFS to your PC (via the web UI or FTP).
2. Run the converter (example):

```powershell
python .\tools\raw_i2s_to_wavs.py C:\path\to\raw_i2s.bin C:\path\to\out_dir 16000
```

3. The script will write multiple WAV files into `out_dir`. Listen to each (`raw_cast_shift.wav`,
   `raw_be_shift.wav`, `raw_24_012.wav`, `raw_24_123.wav`) and pick the one that sounds correct.

If one of them matches, tell me which filename matched and I will patch the live decoder in
`ARDUINO/GENTA/GENTA.ino` to use that conversion permanently.
