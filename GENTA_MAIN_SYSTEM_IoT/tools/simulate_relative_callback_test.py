#!/usr/bin/env python3
"""
Test that Flask will not POST back when callback_url is relative (doesn't start with http)
This uses the app.test_client() from GENTA_Flask.
"""
import importlib.util
import os, sys, time

project_root = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
flask_path = os.path.join(project_root, 'GENTA_Flask.py')
if not os.path.exists(flask_path):
    print('GENTA_Flask.py not found at', flask_path); sys.exit(2)
spec = importlib.util.spec_from_file_location('genta_flask_sim', flask_path)
mod = importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)
app = mod.app
mod.init_pending_db()
client = app.test_client()

teacher_id = 'rel-test-001'
payload = {'teacher_id': teacher_id, 'email': 'a@b.test', 'name': 'Rel Test', 'callback_url': '/users/approvalCallback'}
print('POSTing pending with relative callback_url')
r = client.post('/api/pending_teachers', json=payload)
print('POST status', r.status_code, r.get_data(as_text=True))
print('Trigger reject (should NOT callback)')
r2 = client.post('/api/approve_teacher', json={'teacher_id': teacher_id, 'action': 'reject'})
print('Approve response:', r2.status_code, r2.get_data(as_text=True))
print('If Flask skipped callback (due to non-http callback_url) it would not have attempted external POST.')
print('Check pending list:', client.get('/api/pending_teachers').get_data(as_text=True))
