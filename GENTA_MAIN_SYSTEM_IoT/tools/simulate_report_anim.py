import requests
import time

FLASK = 'http://127.0.0.1:5000'

# Ensure the Flask debug OLED setter is enabled by env var when starting Flask.
# This script will set increasing progress values and finally mark completion_played.

def set_status(progress, completion=False):
    payload = {
        'report_progress': progress,
        'completion_played': bool(completion),
        'completion_shown_at': int(time.time()) if completion else 0,
        'oled_mode': 5
    }
    try:
        r = requests.post(FLASK + '/admin/debug_set_oled_status', json=payload, timeout=3)
        print(f"set_status -> {r.status_code}: {r.text}")
    except Exception as e:
        print(f"Failed to set LAST_OLED_STATUS: {e}")


def poke_oled(progress):
    try:
        r = requests.get(FLASK + '/oled', params={'value': 'report', 'progress': progress}, timeout=2)
        print(f"poke_oled -> {r.status_code if hasattr(r, 'status_code') else 'OK'}")
    except Exception as e:
        print(f"poke_oled failed: {e}")


if __name__ == '__main__':
    sequence = [5, 10, 45, 50, 80, 90, 98, 100]
    for p in sequence:
        print(f"-- Setting progress {p}%")
        set_status(p, completion=(p==100))
        poke_oled(p)
        time.sleep(1.2)
    print('Simulation complete')
