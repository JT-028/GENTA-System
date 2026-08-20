import wave, struct, os
framerate = 16000
nframes = framerate * 1
path = os.path.join(os.getcwd(), 'recording.wav')
with wave.open(path, 'wb') as wf:
    wf.setnchannels(1)
    wf.setsampwidth(2)
    wf.setframerate(framerate)
    silence = struct.pack('<h', 0)
    wf.writeframes(silence * nframes)
print(path, 'created, size:', os.path.getsize(path))
