import requests
import wave
import os

# create a tiny test wav
wav_path = 'tools/test_welcome.wav'
os.makedirs('tools', exist_ok=True)
with wave.open(wav_path, 'wb') as wf:
    wf.setnchannels(1)
    wf.setsampwidth(2)
    wf.setframerate(16000)
    # write a small amount of silence (160 samples = 0.01s)
    wf.writeframes(b'\x00\x00' * 160)

# upload to mock server
url = 'http://localhost:8000/upload_welcome'
with open(wav_path, 'rb') as fh:
    files = {'file': ('test_welcome.wav', fh, 'audio/wav')}
    try:
        r = requests.post(url, files=files, timeout=10)
        print('Upload status:', r.status_code, r.text)
    except Exception as e:
        print('Upload failed:', e)

# trigger playback
try:
    play_url = 'http://localhost:8000/play?file=/WelcomeAudio/test_welcome.wav'
    p = requests.get(play_url, timeout=5)
    print('Play status:', p.status_code, p.text)
except Exception as e:
    print('Play trigger failed:', e)
