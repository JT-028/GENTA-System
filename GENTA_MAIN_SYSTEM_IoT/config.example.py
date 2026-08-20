"""
Example configuration for the GENTA IoT stack.
Copy to config.py or set the matching environment variables.
Do not put production secrets in this file.
"""
import os

BASE_DIR = os.path.abspath(os.path.dirname(__file__))

GENTA_LOG_LEVEL = os.environ.get('GENTA_LOG_LEVEL', 'INFO')

ESP_PLAYBACK_HOST = os.environ.get('GENTA_ESP_PLAYBACK_IP', '192.168.0.11')
ESP_RECORD_IP = os.environ.get('GENTA_ESP_RECORD_IP', '192.168.0.10')

ARDUINO_DATA_DIR = os.environ.get(
    'ARDUINO_DATA_DIR',
    os.path.join(BASE_DIR, 'ARDUINO', 'GENTA2', 'data'),
)
REPEAT_AUDIO_DIR = os.environ.get(
    'REPEAT_AUDIO_DIR',
    os.path.join(BASE_DIR, 'RepeatAudio'),
)
UPLOAD_DIR = os.path.abspath(
    os.environ.get('UPLOAD_FOLDER')
    or os.environ.get('UPLOAD_DIR')
    or os.path.join(BASE_DIR, 'MAIN_SYSTEM', 'uploads')
)

GENAI_API_KEY = os.environ.get('GENAI_API_KEY', '')
GOOGLE_APPLICATION_CREDENTIALS = os.environ.get(
    'GOOGLE_APPLICATION_CREDENTIALS',
    os.path.join(BASE_DIR, 'GoogleCloud', 'key.json'),
)

MYSQL_HOST = os.environ.get('GENTA_MYSQL_HOST', os.environ.get('MYSQL_HOST', 'localhost'))
MYSQL_PORT = int(os.environ.get('GENTA_MYSQL_PORT', os.environ.get('MYSQL_PORT', '3306')))
MYSQL_DB = os.environ.get('GENTA_MYSQL_DB', os.environ.get('MYSQL_DB', 'genta'))
MYSQL_USER = os.environ.get('GENTA_MYSQL_USER', os.environ.get('MYSQL_USER', 'genta'))
MYSQL_PASS = os.environ.get('GENTA_MYSQL_PASS', os.environ.get('MYSQL_PASS', ''))
MYSQL_SSL = os.environ.get('GENTA_MYSQL_SSL', 'false').lower() in ('true', '1', 'yes')

REPORT_UPLOAD_API_KEY = os.environ.get('GENTA_REPORT_UPLOAD_API_KEY', '')

GENTA_TTS_VOICE = os.environ.get('GENTA_TTS_VOICE', 'en-US-Neural2-C')
GENTA_TTS_LANGUAGE = os.environ.get('GENTA_TTS_LANGUAGE', 'en-US')
GENTA_TTS_PITCH = float(os.environ.get('GENTA_TTS_PITCH', '0.0'))
FFMPEG_PATH = os.environ.get('FFMPEG_PATH')
