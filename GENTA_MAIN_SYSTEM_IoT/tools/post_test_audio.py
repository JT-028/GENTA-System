import wave
import struct
import math
import requests
import os

# Paths
workspace_root = os.path.dirname(os.path.dirname(__file__))
# Use canonical uploads directory if provided via environment, otherwise fallback to MAIN_SYSTEM/uploads
_env_upload = os.environ.get('UPLOAD_FOLDER') or os.environ.get('UPLOAD_DIR')
if _env_upload:
    UPLOAD_DIR = os.path.abspath(_env_upload)
else:
    UPLOAD_DIR = os.path.abspath(r"C:\Users\vonti\OneDrive\Desktop\GENTA SYS\MAIN_SYSTEM\uploads")
try:
    os.makedirs(UPLOAD_DIR, exist_ok=True)
except Exception:
    pass
wav_path = os.path.join(UPLOAD_DIR, 'recording.wav')

# Generate a 1 second 16kHz mono WAV (simple sine tone at 440Hz)
sample_rate = 16000
duration_sec = 1.0
freq = 440.0
n_samples = int(sample_rate * duration_sec)

with wave.open(wav_path, 'w') as wf:
    wf.setnchannels(1)
    wf.setsampwidth(2)  # 16-bit
    wf.setframerate(sample_rate)
    for i in range(n_samples):
        sample = int(32767 * 0.15 * math.sin(2 * math.pi * freq * (i / sample_rate)))
        wf.writeframes(struct.pack('<h', sample))

print(f"Created test WAV at: {wav_path}")

# POST the file to the Flask upload endpoint
url = 'http://localhost:5000/'
files = {'file': ('recording.wav', open(wav_path, 'rb'), 'audio/wav')}
try:
    resp = requests.post(url, files=files, timeout=10)
    print('POST status:', resp.status_code)
    # Print a short preview of response body
    body = resp.text

except Exception as e:
    print('Failed to POST test audio:', e)
else:
    if resp.status_code == 200:
        print('Upload likely succeeded.')
    else:
        print('Upload returned non-200. Response length:', len(body))
    # Optionally verify GET
    try:
        get_resp = requests.get('http://localhost:5000/recording.wav', timeout=5)
        print('GET /recording.wav status:', get_resp.status_code, 'size:', len(get_resp.content) if get_resp.status_code == 200 else 'N/A')
    except Exception as e:
        print('GET check failed:', e)
