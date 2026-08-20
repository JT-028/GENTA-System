#!/usr/bin/env python3
"""
Simulate end-to-end: CakePHP -> Flask pending -> admin reject -> Flask callback to CakePHP
This script:
 - starts a tiny HTTP server on 127.0.0.1:8001 to capture callbacks at /users/approvalCallback
 - imports the Flask app from GENTA_Flask.py and uses its test_client to POST /api/pending_teachers
 - calls /api/approve_teacher with action=reject and verifies our local callback server receives the POST
"""
import threading
import http.server
import socketserver
import json
import time
import os
import sys
from urllib.parse import urlparse

CALLBACK_PORT = 8001
CALLBACK_PATH = '/users/approvalCallback'
callbacks_received = []

class CallbackHandler(http.server.BaseHTTPRequestHandler):
    def do_POST(self):
        try:
            length = int(self.headers.get('content-length', 0))
            body = self.rfile.read(length) if length else b''
            content_type = self.headers.get('content-type', '')
            parsed = None
            if 'application/json' in content_type:
                try:
                    parsed = json.loads(body.decode('utf-8'))
                except Exception:
                    parsed = {'raw': body.decode('utf-8', errors='ignore')}
            else:
                # attempt to parse as form data
                try:
                    parsed = {}
                    for part in body.decode('utf-8').split('&'):
                        if '=' in part:
                            k, v = part.split('=', 1)
                            parsed[k] = v
                except Exception:
                    parsed = {'raw': body.decode('utf-8', errors='ignore')}
            callbacks_received.append({'path': self.path, 'headers': dict(self.headers), 'body': parsed})
            # respond
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps({'success': True}).encode('utf-8'))
        except Exception as e:
            self.send_response(500)
            self.end_headers()

    def log_message(self, format, *args):
        # suppress default logging
        return

class ThreadedHTTPServer(object):
    def __init__(self, host='127.0.0.1', port=CALLBACK_PORT):
        self.server = socketserver.TCPServer((host, port), CallbackHandler)
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)

    def start(self):
        print(f"[Sim] Starting callback capture server on http://127.0.0.1:{CALLBACK_PORT}{CALLBACK_PATH}")
        self.thread.start()

    def stop(self):
        try:
            self.server.shutdown()
            self.server.server_close()
        except Exception:
            pass


def import_flask_module(path):
    import importlib.util
    spec = importlib.util.spec_from_file_location('genta_flask_sim', path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def main():
    here = os.path.abspath(os.path.dirname(__file__))
    project_root = os.path.abspath(os.path.join(here, '..'))
    flask_path = os.path.join(project_root, 'GENTA_Flask.py')
    if not os.path.exists(flask_path):
        print('Could not find GENTA_Flask.py at', flask_path)
        sys.exit(2)

    # Start callback capture server
    server = ThreadedHTTPServer(port=CALLBACK_PORT)
    server.start()
    time.sleep(0.3)

    print('[Sim] Importing Flask app...')
    mod = import_flask_module(flask_path)
    if not hasattr(mod, 'app'):
        print('Flask module does not expose `app` object; aborting')
        server.stop()
        sys.exit(2)

    app = mod.app
    # ensure DB initialized
    try:
        mod.init_pending_db()
    except Exception as e:
        print('[Sim] init_pending_db failed:', e)

    tc = app.test_client()

    # Simulate CakePHP registration notifying Flask
    teacher_id = 'sim-teacher-001'
    email = 'sim_teacher@example.test'
    name = 'Sim Teacher'
    callback_url = f'http://127.0.0.1:{CALLBACK_PORT}{CALLBACK_PATH}'

    payload = {
        'teacher_id': teacher_id,
        'email': email,
        'name': name,
        'callback_url': callback_url
    }

    print('[Sim] POST /api/pending_teachers -> adding pending registration')
    resp = tc.post('/api/pending_teachers', json=payload)
    print('[Sim] Flask response:', resp.status_code, resp.get_data(as_text=True))

    print('[Sim] GET /api/pending_teachers -> verify stored')
    resp2 = tc.get('/api/pending_teachers')
    print('[Sim] GET response:', resp2.status_code, resp2.get_data(as_text=True))

    # Now simulate admin rejecting the teacher via admin UI -> Flask approve endpoint
    print('[Sim] POST /api/approve_teacher action=reject -> this should cause Flask to POST back to callback_url')
    resp3 = tc.post('/api/approve_teacher', json={'teacher_id': teacher_id, 'action': 'reject'})
    print('[Sim] Approve endpoint response:', resp3.status_code, resp3.get_data(as_text=True))

    # Wait a short time for callback to arrive
    time.sleep(1.0)

    print('[Sim] Callbacks captured by local server:', len(callbacks_received))
    for i, cb in enumerate(callbacks_received):
        print('--- callback', i+1, '---')
        print('path:', cb['path'])
        print('body:', json.dumps(cb['body'], indent=2))

    # Check pending DB record state after rejection
    resp4 = tc.get('/api/pending_teachers')
    print('[Sim] Final pending list:', resp4.status_code, resp4.get_data(as_text=True))

    server.stop()
    print('[Sim] Done')

if __name__ == '__main__':
    main()
