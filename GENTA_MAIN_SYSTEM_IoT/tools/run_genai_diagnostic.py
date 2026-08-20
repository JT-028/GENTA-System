"""
Lightweight diagnostic for in-place GenAI environment checks.
- Prints python executable and version
- Attempts to import key GenAI/proto-related modules and prints their versions
- Reports presence (not value) of GENAI_API_KEY and GOOGLE_APPLICATION_CREDENTIALS
"""
import sys
import importlib
import os
import traceback

print('--- GenAI environment diagnostic ---')
print('Python executable:', sys.executable)
print('Python version:', sys.version)
print()

modules_to_check = [
    'google.generativeai',
    'google.ai.generativelanguage',
    'google_ai_generativelanguage',
    'proto',
    'proto_plus',
    'google.auth',
    'requests',
]

for name in modules_to_check:
    try:
        mod = importlib.import_module(name)
        ver = getattr(mod, '__version__', None) or getattr(mod, 'VERSION', None) or 'n/a'
        print(f'Imported {name!r}: version={ver}')
    except Exception as e:
        print(f'Import {name!r} failed: {e.__class__.__name__}: {e}')
        tb = traceback.format_exc()
        print(tb)

print()
# Report key env vars presence (don't print secrets)
for env in ('GENAI_API_KEY','GOOGLE_APPLICATION_CREDENTIALS'):
    v = os.environ.get(env)
    if v:
        print(f'{env}: present, length={len(v)}')
    else:
        print(f'{env}: NOT set')

print('\nDiagnostic script finished.')
