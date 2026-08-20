from google.cloud import texttospeech_v1
import os
import requests

# Paths
workspace_root = r"C:\Users\vonti\OneDrive\Desktop\GENTA SYS"
key_path = os.path.join(workspace_root, 'GoogleCloud', 'key.json')
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

# Text to synthesize (the student's spoken answer)
text = 'seventy nine'

# Create client using service account
client = texttospeech_v1.TextToSpeechClient.from_service_account_json(key_path)

voice = texttospeech_v1.VoiceSelectionParams(
    language_code='en-US',
    name='en-US-Wavenet-D'
)

audio_config = texttospeech_v1.AudioConfig(
    audio_encoding=texttospeech_v1.AudioEncoding.LINEAR16,
    sample_rate_hertz=24000,
)

synthesis_input = texttospeech_v1.SynthesisInput(text=text)

response = client.synthesize_speech(
    input=synthesis_input,
    voice=voice,
    audio_config=audio_config
)

# Write the binary audio content to the uploads/recording.wav
with open(wav_path, 'wb') as out:
    out.write(response.audio_content)

print(f"Wrote answer WAV to: {wav_path}")

# POST to the Flask upload endpoint
url = 'http://localhost:5000/'
with open(wav_path, 'rb') as fh:
    files = {'file': ('recording.wav', fh, 'audio/wav')}
    try:
        resp = requests.post(url, files=files, timeout=10)
        print('POST status:', resp.status_code)
    except Exception as e:
        print('POST failed:', e)

# Verify accessible
try:
    get_resp = requests.get('http://localhost:5000/recording.wav', timeout=5)
    print('GET status:', get_resp.status_code, 'size:', len(get_resp.content) if get_resp.status_code == 200 else 'N/A')
except Exception as e:
    print('GET failed:', e)
