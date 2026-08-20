"""
Centralized configuration for GENTA project.
Values are loaded from environment variables when available.
Do NOT store secrets directly in this file for published repos.
"""
import os
import pathlib

# Logging
GENTA_LOG_LEVEL = os.environ.get('GENTA_LOG_LEVEL', 'INFO')

# ESP defaults
ESP_PLAYBACK_HOST = os.environ.get('GENTA_ESP_PLAYBACK_IP', '192.168.50.62')
ESP_RECORD_IP = os.environ.get('GENTA_ESP_RECORD_IP', '192.168.50.62')

# Paths (override with environment variables)
BASE_DIR = os.path.abspath(os.path.dirname(__file__))
ARDUINO_DATA_DIR = os.environ.get('ARDUINO_DATA_DIR', os.path.join(BASE_DIR, 'ARDUINO', 'GENTA2', 'data'))
REPEAT_AUDIO_DIR = os.environ.get('REPEAT_AUDIO_DIR', os.path.join(BASE_DIR, 'RepeatAudio'))

# Uploads directory - ALL GENTA7 reports go here (CakePHP fetches from this location via Flask/ngrok)
UPLOAD_DIR = os.path.abspath(os.environ.get('UPLOAD_FOLDER') or os.environ.get('UPLOAD_DIR') or os.path.join(BASE_DIR, 'MAIN_SYSTEM', 'uploads'))

# ngrok / tunnel endpoints (for fallback/testing only). Prefer setting production endpoints via env.
BAKURL_STATE = os.environ.get('GENTA_BAKURL_STATE', "https://nonbasic-bob-inimical.ngrok-free.dev/download/state.txt")
BAK_AUDIO_RAW_URL = os.environ.get('GENTA_BAK_AUDIO_RAW_URL', "https://nonbasic-bob-inimical.ngrok-free.dev/download/recording.wav")
URL_STATE = os.environ.get('GENTA_URL_STATE', "https://nonbasic-bob-inimical.ngrok-free.dev/state.txt")
AUDIO_RAW_URL = os.environ.get('GENTA_AUDIO_RAW_URL', "https://nonbasic-bob-inimical.ngrok-free.dev/download_recording")
STUDENT_ID_URL = os.environ.get('GENTA_STUDENT_ID_URL', "https://nonbasic-bob-inimical.ngrok-free.dev/student_id.txt")

# Google application credentials (path to JSON). Prefer setting in environment externally.
GOOGLE_APPLICATION_CREDENTIALS = os.environ.get('GOOGLE_APPLICATION_CREDENTIALS', os.path.join(BASE_DIR, 'GoogleCloud', 'key.json'))

# GenAI / Google generative model API key
# Get your API key from: https://makersuite.google.com/app/apikey
# Prefer setting this in the environment. Do NOT hardcode secrets in source.
# Use environment variable `GENAI_API_KEY` to override the default below.
GENAI_API_KEY = os.environ.get('GENAI_API_KEY', '')

# MySQL (override via env — never commit real credentials)
MYSQL_HOST = os.environ.get('GENTA_MYSQL_HOST', os.environ.get('MYSQL_HOST', 'localhost'))
MYSQL_PORT = int(os.environ.get('GENTA_MYSQL_PORT', os.environ.get('MYSQL_PORT', '3306')))
MYSQL_DB = os.environ.get('GENTA_MYSQL_DB', os.environ.get('MYSQL_DB', 'genta'))
MYSQL_USER = os.environ.get('GENTA_MYSQL_USER', os.environ.get('MYSQL_USER', 'genta'))
MYSQL_PASS = os.environ.get('GENTA_MYSQL_PASS', os.environ.get('MYSQL_PASS', ''))
MYSQL_SSL = os.environ.get('GENTA_MYSQL_SSL', 'false').lower() in ('true', '1', 'yes')

# Other runtime tuning
FFMPEG_PATH = os.environ.get('FFMPEG_PATH')  # Optional explicit ffmpeg path

# Report upload API key (for securing Flask endpoints that serve generated reports)
# Set via environment variable GENTA_REPORT_UPLOAD_API_KEY
REPORT_UPLOAD_API_KEY = os.environ.get('GENTA_REPORT_UPLOAD_API_KEY', '')

# Ensure upload dir exists
try:
    os.makedirs(UPLOAD_DIR, exist_ok=True)
except Exception:
    pass

# -------------------------------
# Text-to-Speech defaults (configurable)
# - Use env vars `GENTA_TTS_VOICE`, `GENTA_TTS_LANGUAGE`, `GENTA_TTS_PITCH` to override
# - Do NOT store private keys or credentials here in public repos; prefer environment variables
# -------------------------------
GENTA_TTS_VOICE = os.environ.get('GENTA_TTS_VOICE', 'en-US-Neural2-C')
GENTA_TTS_LANGUAGE = os.environ.get('GENTA_TTS_LANGUAGE', 'en-US')
try:
    GENTA_TTS_PITCH = float(os.environ.get('GENTA_TTS_PITCH', os.environ.get('GENTA_TTS_PITCH', '0.0')))
except Exception:
    GENTA_TTS_PITCH = 0.0
