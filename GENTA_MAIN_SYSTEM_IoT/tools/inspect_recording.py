import binascii
import os

# Resolve canonical uploads directory (env-aware) and use it for test file
_env_upload = os.environ.get('UPLOAD_FOLDER') or os.environ.get('UPLOAD_DIR')
if _env_upload:
    UPLOAD_DIR = os.path.abspath(_env_upload)
else:
    UPLOAD_DIR = os.path.abspath(r"C:\Users\vonti\OneDrive\Desktop\GENTA SYS\MAIN_SYSTEM\uploads")

p = os.path.join(UPLOAD_DIR, 'manual_test_recording.bin')
try:
    b = open(p, 'rb').read(256)
    print('FIRST=', b[:16])
    print('HEX=', binascii.hexlify(b[:16]))
    print('LEN=', len(b))
except Exception as e:
    print('ERROR', e)
