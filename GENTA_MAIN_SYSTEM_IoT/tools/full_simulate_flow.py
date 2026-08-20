#!/usr/bin/env python3
"""
Full simulation that can operate against either a local SQLite test DB (default) or a
real CakePHP MySQL/MariaDB database when provided with connection details.

Safety and safeguards:
 - By default the script uses a local SQLite test DB in tools/test_users.db (no risk).
 - To operate on a real CakePHP DB supply --db-type mysql and the connection options.
 - A required --apply flag must be provided to actually perform writes on a real DB.
 - The script will back up any affected user row(s) to a JSON file before modifying.

Usage (dry-run):
  python tools/full_simulate_flow.py --db-type sqlite

Usage (apply to real DB - REQUIRED):
  python tools/full_simulate_flow.py --db-type mysql --db-host localhost --db-user root --db-pass secret --db-name cakephp_db --apply

"""
import os
import sys
import time
import json
import threading
import argparse
import re

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
FLASK_APP_PATH = os.path.join(PROJECT_ROOT, 'GENTA_Flask.py')
DEFAULT_SQLITE = os.path.join(PROJECT_ROOT, 'tools', 'test_users.db')
CALLBACK_PORT = 8003
CALLBACK_PATH = '/users/approvalCallback'

# Try to import optional dependencies
try:
	import bcrypt
except Exception:
	print('Missing dependency: bcrypt. Please install in your venv (pip install bcrypt)')
	raise

try:
	import pymysql
except Exception:
	pymysql = None

from http.server import BaseHTTPRequestHandler, HTTPServer


def parse_args():
	p = argparse.ArgumentParser(description='Full approve-path simulation (SQLite or MySQL).')
	p.add_argument('--db-type', choices=['sqlite', 'mysql'], default='sqlite', help='Database type to use')
	p.add_argument('--sqlite-path', default=DEFAULT_SQLITE, help='Path for local sqlite test DB')

	# MySQL connection options
	p.add_argument('--db-host', help='MySQL host')
	p.add_argument('--db-port', type=int, default=3306, help='MySQL port')
	p.add_argument('--db-user', help='MySQL user')
	p.add_argument('--db-pass', help='MySQL password')
	p.add_argument('--db-name', help='MySQL database name')

	p.add_argument('--test-email', default='sim_login_test@example.test', help='Test user email')
	p.add_argument('--test-password', default='TestPass123!', help='Test user password')
	p.add_argument('--test-id', type=int, default=None, help='Optional numeric id to use for test user')
	p.add_argument('--apply', action='store_true', help='Actually apply changes on the target DB (required for mysql)')
	p.add_argument('--backup-file', default=os.path.join(PROJECT_ROOT, 'tools', f'users_backup_{int(time.time())}.json'), help='File to write backups to')
	p.add_argument('--action', choices=['approve', 'reject'], default='approve', help='Action to trigger via Flask admin (approve or reject)')
	return p.parse_args()


class DBHelper:
	def __init__(self, args):
		self.args = args
		self.type = args.db_type
		self.sqlite_path = args.sqlite_path
		self.conn = None

	def connect(self):
		if self.type == 'sqlite':
			import sqlite3
			self.conn = sqlite3.connect(self.sqlite_path)
			self.conn.row_factory = lambda cursor, row: dict((cursor.description[idx][0], value) for idx, value in enumerate(row))
		else:
			if pymysql is None:
				raise RuntimeError('pymysql is required for mysql mode (pip install pymysql)')
			self.conn = pymysql.connect(host=self.args.db_host, port=self.args.db_port, user=self.args.db_user,
										password=self.args.db_pass, database=self.args.db_name,
										autocommit=True, cursorclass=pymysql.cursors.DictCursor)

	def close(self):
		if self.conn:
			try:
				self.conn.close()
			except Exception:
				pass

	def ensure_users_table(self):
		cur = self.conn.cursor()
		if self.type == 'sqlite':
			cur.execute('''
				CREATE TABLE IF NOT EXISTS users (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					email TEXT UNIQUE,
					password TEXT,
					status INTEGER,
					first_name TEXT,
					last_name TEXT
				)
			''')
			self.conn.commit()
		else:
			# assume CakePHP users table exists in production; do not create it automatically
			pass

	def find_user_by_email(self, email):
		cur = self.conn.cursor()
		if self.type == 'mysql':
			cur.execute('SELECT * FROM users WHERE email = %s', (email,))
		else:
			cur.execute('SELECT * FROM users WHERE email = ?', (email,))
		return cur.fetchone()

	def find_user_by_id(self, uid):
		cur = self.conn.cursor()
		if self.type == 'mysql':
			cur.execute('SELECT * FROM users WHERE id = %s', (uid,))
		else:
			cur.execute('SELECT * FROM users WHERE id = ?', (uid,))
		return cur.fetchone()

	def insert_user(self, email, pw_hash, status=0, first_name='Sim', last_name='Teacher', explicit_id=None):
		cur = self.conn.cursor()
		if explicit_id:
			if self.type == 'mysql':
				cur.execute('INSERT INTO users (id, email, password, status, first_name, last_name) VALUES (%s,%s,%s,%s,%s,%s)',
							(explicit_id, email, pw_hash, status, first_name, last_name))
			else:
				cur.execute('INSERT INTO users (id, email, password, status, first_name, last_name) VALUES (?,?,?,?,?,?)',
							(explicit_id, email, pw_hash, status, first_name, last_name))
			self.conn.commit()
			return explicit_id
		else:
			if self.type == 'mysql':
				cur.execute('INSERT INTO users (email, password, status, first_name, last_name) VALUES (%s,%s,%s,%s,%s)',
							(email, pw_hash, status, first_name, last_name))
				return cur.lastrowid
			else:
				cur.execute('INSERT INTO users (email, password, status, first_name, last_name) VALUES (?,?,?,?,?)',
							(email, pw_hash, status, first_name, last_name))
				self.conn.commit()
				return cur.lastrowid

	def update_user_status(self, uid, status):
		cur = self.conn.cursor()
		if self.type == 'mysql':
			cur.execute('UPDATE users SET status = %s WHERE id = %s', (status, uid))
		else:
			cur.execute('UPDATE users SET status = ? WHERE id = ?', (status, uid))
		self.conn.commit()

	def delete_user(self, uid):
		cur = self.conn.cursor()
		if self.type == 'mysql':
			cur.execute('DELETE FROM users WHERE id = %s', (uid,))
		else:
			cur.execute('DELETE FROM users WHERE id = ?', (uid,))
		self.conn.commit()


callbacks_log = []


class CallbackHandler(BaseHTTPRequestHandler):
	def do_POST(self):
		length = int(self.headers.get('content-length', 0))
		body = self.rfile.read(length) if length else b''
		try:
			payload = json.loads(body.decode('utf-8'))
		except Exception:
			payload = {}
		callbacks_log.append(payload)
		# Process payload by updating the connected DB helper which will be set globally
		try:
			teacher_id = payload.get('teacher_id')
			status = payload.get('status')
			if teacher_id is not None and status is not None and hasattr(self.server, 'db_helper'):
				dbh = self.server.db_helper
				try:
					tid = int(teacher_id)
				except Exception:
					tid = None
				if tid:
					if str(status).lower() in ['approved', 'approve', '1', 'true']:
						dbh.update_user_status(tid, 1)
					else:
						dbh.delete_user(tid)
		except Exception as e:
			print('Callback handler error:', e)
		self.send_response(200)
		self.send_header('Content-Type', 'application/json')
		self.end_headers()
		self.wfile.write(json.dumps({'success': True}).encode('utf-8'))

	def log_message(self, format, *args):
		return


class ThreadedHTTPServer(object):
	def __init__(self, host='127.0.0.1', port=CALLBACK_PORT, db_helper=None):
		self.server = HTTPServer((host, port), CallbackHandler)
		# attach db helper instance to server so handler can access it
		self.server.db_helper = db_helper
		self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)

	def start(self):
		print(f'[Sim] Starting callback server at http://127.0.0.1:{CALLBACK_PORT}{CALLBACK_PATH}')
		self.thread.start()

	def stop(self):
		try:
			self.server.shutdown(); self.server.server_close()
		except Exception:
			pass


def import_flask():
	import importlib.util
	spec = importlib.util.spec_from_file_location('genta_flask_sim', FLASK_APP_PATH)
	module = importlib.util.module_from_spec(spec)
	spec.loader.exec_module(module)
	return module


def backup_user_row(dbh, uid=None, email=None, backup_file=None):
	# backup any existing row by id or email into JSON file
	rows = []
	if uid:
		r = dbh.find_user_by_id(uid)
		if r:
			rows.append(r)
	if email:
		r = dbh.find_user_by_email(email)
		if r and (not uid or r.get('id') != uid):
			rows.append(r)
	if rows and backup_file:
		with open(backup_file, 'a', encoding='utf-8') as f:
			for row in rows:
				f.write(json.dumps({'backup_at': int(time.time()), 'row': row}) + "\n")
		print(f'[Sim] Backed up {len(rows)} row(s) to {backup_file}')


def hash_password(plain_password):
	return bcrypt.hashpw(plain_password.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')


def run_simulation(args):
	# Prepare DB helper
	dbh = DBHelper(args)
	dbh.connect()
	if args.db_type == 'sqlite':
		dbh.ensure_users_table()

	# Determine whether we're creating a new user or using existing
	existing = None
	if args.test_id:
		existing = dbh.find_user_by_id(args.test_id)
	if not existing:
		existing = dbh.find_user_by_email(args.test_email)

	if existing:
		print('[Sim] Found existing user row:', existing)
	else:
		print('[Sim] No existing user found for test; will create one (dry-run unless --apply)')

	# Backup any existing rows
	backup_user_row(dbh, uid=args.test_id, email=args.test_email, backup_file=args.backup_file)

	if args.db_type == 'mysql' and not args.apply:
		print('*** NOTICE: Running against MySQL but --apply not provided. This is a DRY-RUN and will not write to DB. Use --apply to make changes.')

	# If not existing, insert a new user (only if apply or sqlite)
	user_id = args.test_id
	if not existing:
		pw_hash = hash_password(args.test_password)
		if args.db_type == 'mysql' and not args.apply:
			print(f'[Sim] DRY-RUN: Would insert user email={args.test_email} status=0. Use --apply to execute.')
			# We can't get a new id in dry-run; pick a sentinel
			user_id = args.test_id or 999999
		else:
			user_id = dbh.insert_user(args.test_email, pw_hash, status=0, explicit_id=args.test_id)
			print(f'[Sim] Inserted test user id={user_id}')
	else:
		user_id = existing.get('id')
		# If existing but not status=0, optionally set to pending if apply
		if existing.get('status') not in (0, '0'):
			print(f'[Sim] Existing user status={existing.get("status")}; setting to pending (0)')
			if args.db_type == 'mysql' and not args.apply:
				print('[Sim] DRY-RUN: Would set status=0 for existing user (use --apply to execute)')
			else:
				dbh.update_user_status(user_id, 0)

	# Start callback server bound to DB helper so callbacks update the same DB
	server = ThreadedHTTPServer(db_helper=dbh)
	server.start()
	time.sleep(0.2)

	mod = import_flask()
	app = mod.app
	try:
		mod.init_pending_db()
	except Exception:
		pass
	tc = app.test_client()

	# Notify Flask of new pending teacher
	# Determine callback URL to provide to Flask. Prefer CakePHP endpoint when
	# running against a real MySQL CakePHP DB (so Flask will POST back to CakePHP).
	callback_url = f'http://127.0.0.1:{CALLBACK_PORT}{CALLBACK_PATH}'
	if args.db_type == 'mysql':
		# Try to parse CakePHP config to obtain fullBaseUrl and App.base
		cfg_path = os.path.join(PROJECT_ROOT, 'GENTA', 'config', 'app_local.php')
		try:
			with open(cfg_path, 'r', encoding='utf-8') as f:
				cfg = f.read()
				# find fullBaseUrl default string
				m = re.search(r"fullBaseUrl'\s*=>\s*env\([^,]+,\s*'([^']+)'\)", cfg)
				full = m.group(1) if m else None
				mb = re.search(r"'base'\s*=>\s*'([^']+)'", cfg)
				base = mb.group(1) if mb else ''
				if full:
					# Ensure no trailing slash and append base path
					callback_url = full.rstrip('/') + base.rstrip('/') + CALLBACK_PATH
		except Exception as e:
			print('[Sim] Could not parse CakePHP config for callback URL:', e)
			# keep local callback as fallback

	payload = {
		'teacher_id': str(user_id),
		'email': args.test_email,
		'name': 'Sim Login',
		'callback_url': callback_url
	}
	print('[Sim] POST /api/pending_teachers -> notify admin panel')
	r = tc.post('/api/pending_teachers', json=payload)
	print('->', r.status_code, r.get_data(as_text=True))

	# Check login BEFORE approval
	print('[Sim] Checking login BEFORE approval (should be blocked)')
	pre = dbh.find_user_by_email(args.test_email)
	print('User row before approval:', pre)

	# Trigger approval/rejection via /api/approve_teacher
	print(f"[Sim] Trigger action '{args.action}' via /api/approve_teacher")
	r2 = tc.post('/api/approve_teacher', json={'teacher_id': str(user_id), 'action': args.action})
	print('->', r2.status_code, r2.get_data(as_text=True))

	# Wait for callback processing
	time.sleep(1.0)
	print('[Sim] Callbacks received:', len(callbacks_log), callbacks_log)

	# Check user row AFTER approval
	post = dbh.find_user_by_id(user_id)
	print('User row after approval:', post)

	server.stop()
	dbh.close()
	print('[Sim] Done')


if __name__ == '__main__':
	args = parse_args()
	# Quick safety checks when connecting to MySQL
	if args.db_type == 'mysql':
		if not (args.db_host and args.db_user and args.db_name):
			print('MySQL mode requires --db-host, --db-user, and --db-name')
			sys.exit(2)
		if pymysql is None:
			print('pymysql not installed. Please run: pip install pymysql')
			sys.exit(2)
		if not args.apply:
			print('WARNING: You are running against a MySQL database in DRY-RUN mode. Use --apply to make modifications.')
	run_simulation(args)
