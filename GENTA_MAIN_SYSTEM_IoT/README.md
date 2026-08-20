# GENTA IoT Stack

Python Flask device hub, Gemini-backed voice/quiz engine (`GENTA7.py`), and ESP32 firmware.

This folder is the **IoT** side of the [GENTA System](../README.md) monorepo.

## Pieces

| File / dir | Role |
| --- | --- |
| `GENTA_Flask.py` | HTTP + UDP hub: ESP32 discovery, recording notify, OLED, admin, report API |
| `GENTA7.py` | STT → assist/quiz → Gemini analysis + tailored module → TTS |
| `config.py` | Env-driven settings (copy from `config.example.py` if needed) |
| `templates/` | Admin HTML (login, upload/control, Wi‑Fi) |
| `ARDUINO/` | ESP32 recorder + player sketches |
| `requirements.txt` | Python dependencies |

## Setup

```bash
python -m venv .venv
.venv\Scripts\activate          # Windows
pip install -r requirements.txt
copy .env.example .env
# Add GoogleCloud/key.json (service account) — gitignored
python GENTA_Flask.py
python GENTA7.py
```

Install **ffmpeg** locally and put it on `PATH`. The full windows build (~800 MB) is not in this repo.

Speech acoustic `model/` folders are also excluded (tens of MB). Restore them from your local backup if a feature still needs them.

## Firmware

See [`ARDUINO/README.md`](ARDUINO/README.md). Wi‑Fi is stored in ESP32 NVS; sketches must not ship with a real SSID/password.

## Security

Never commit `GoogleCloud/key.json`, `.env`, `*.wav` session audio, or `MAIN_SYSTEM/uploads/` (student reports).
