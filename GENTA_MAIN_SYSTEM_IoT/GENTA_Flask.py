from flask import Flask, request, render_template, send_file, render_template_string, Response, redirect, url_for, flash, jsonify, get_flashed_messages, session
from flask_cors import CORS
import os
import requests
import time
import subprocess
import json
import socket
import threading
import queue
import zipfile
import shutil
import datetime
import traceback
import tempfile
from pathlib import Path
import re
import sys
import uuid
import tempfile
import hashlib
import secrets
from functools import wraps

# MySQL credentials come from environment variables only.
# See GENTA_MAIN_SYSTEM_IoT/.env.example — never hardcode production secrets.

# Base URL for the ESP device (can be overridden with environment variable ESP_BASE)
# These are fallback defaults - auto-discovery via UDP is preferred
ESP_BASE = os.environ.get('ESP_BASE', 'http://192.168.43.43')  # GENTA.ino (Recorder) - FALLBACK
ESP_SPEAKER = os.environ.get('ESP_SPEAKER', 'http://192.168.43.32')  # GENTA2.ino (Speaker + State) - FALLBACK

print("="*70)
print("FLASK SERVER CONFIGURATION")
print("="*70)
print(f"ESP Recorder (fallback): {ESP_BASE}")
print(f"ESP Speaker (fallback):  {ESP_SPEAKER}")
print("Note: These are fallback IPs. Flask will use auto-discovered IPs when available.")
print("="*70 + "\n")

# ===== RECORDING NOTIFICATION SYSTEM =====
# Thread-safe queue for recording ready notifications
recording_ready_queue = queue.Queue()
recording_notification_lock = threading.Lock()
last_recording_timestamp = 0

app = Flask(__name__)
# Generate a secure random secret key for production
app.secret_key = os.environ.get('FLASK_SECRET_KEY', secrets.token_hex(32))
# Session configuration for enhanced security
app.config['SESSION_COOKIE_SECURE'] = False  # Set to True if using HTTPS
app.config['SESSION_COOKIE_HTTPONLY'] = True
app.config['SESSION_COOKIE_SAMESITE'] = 'Lax'
app.config['PERMANENT_SESSION_LIFETIME'] = datetime.timedelta(hours=12)
CORS(app)

# ===== AUTHENTICATION CONFIGURATION =====
# Set ADMIN_USERNAME and ADMIN_PASSWORD_HASH (SHA-256 hex) in the environment.
ADMIN_CREDENTIALS = {
    'username': os.environ.get('ADMIN_USERNAME', 'admin'),
    'password_hash': os.environ.get('ADMIN_PASSWORD_HASH', ''),
}

# Maximum login attempts before temporary lockout
MAX_LOGIN_ATTEMPTS = 5
# Lockout duration in seconds (15 minutes)
LOCKOUT_DURATION = 900
# Dictionary to track failed login attempts: {ip_address: {'attempts': count, 'locked_until': timestamp}}
login_attempts = {}

def hash_password(password):
    """Hash password using SHA-256"""
    return hashlib.sha256(password.encode()).hexdigest()

def check_lockout(ip_address):
    """Check if IP is currently locked out"""
    if ip_address in login_attempts:
        attempt_data = login_attempts[ip_address]
        if 'locked_until' in attempt_data:
            if time.time() < attempt_data['locked_until']:
                remaining = int(attempt_data['locked_until'] - time.time())
                return True, remaining
            else:
                # Lockout expired, reset attempts
                del login_attempts[ip_address]
    return False, 0

def record_failed_attempt(ip_address):
    """Record a failed login attempt"""
    if ip_address not in login_attempts:
        login_attempts[ip_address] = {'attempts': 0}
    
    login_attempts[ip_address]['attempts'] += 1
    
    if login_attempts[ip_address]['attempts'] >= MAX_LOGIN_ATTEMPTS:
        login_attempts[ip_address]['locked_until'] = time.time() + LOCKOUT_DURATION
        return True  # Locked out
    return False  # Not locked out yet

def reset_login_attempts(ip_address):
    """Reset login attempts after successful login"""
    if ip_address in login_attempts:
        del login_attempts[ip_address]

def login_required(f):
    """Decorator to protect routes that require authentication and check for idle timeout"""
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if 'logged_in' not in session or not session['logged_in']:
            return redirect(url_for('login', next=request.url))
        
        # Check for idle timeout (30 minutes = 1800 seconds)
        IDLE_TIMEOUT = 1800
        last_activity = session.get('last_activity', 0)
        current_time = time.time()
        
        if current_time - last_activity > IDLE_TIMEOUT:
            # Session expired due to inactivity
            session.clear()
            flash('Your session expired due to inactivity. Please login again.', 'warning')
            return redirect(url_for('login'))
        
        # Update last activity timestamp
        session['last_activity'] = current_time
        
        return f(*args, **kwargs)
    return decorated_function

@app.route('/login', methods=['GET', 'POST'])
def login():
    """Admin login page"""
    if request.method == 'POST':
        username = request.form.get('username', '').strip()
        password = request.form.get('password', '')
        remember = request.form.get('remember') == 'on'
        ip_address = request.remote_addr
        
        # Check if IP is locked out
        is_locked, remaining = check_lockout(ip_address)
        if is_locked:
            minutes = remaining // 60
            seconds = remaining % 60
            error = f"Too many failed attempts. Please try again in {minutes}m {seconds}s."
            return render_template('login.html', error=error)
        
        # Validate credentials
        password_hash = hash_password(password)
        if (username == ADMIN_CREDENTIALS['username'] and 
            password_hash == ADMIN_CREDENTIALS['password_hash']):
            # Successful login
            session.clear()
            session['logged_in'] = True
            session['username'] = username
            session['login_time'] = time.time()
            session['last_activity'] = time.time()  # Track activity for idle timeout
            session['ip_address'] = ip_address
            
            # Make session permanent if "remember me" is checked
            if remember:
                session.permanent = True
            
            # Reset failed attempts
            reset_login_attempts(ip_address)
            
            # Log successful login
            print(f"[Auth] Successful login: {username} from {ip_address}")
            
            # Redirect to requested page or home
            next_page = request.args.get('next')
            if next_page and next_page.startswith('/'):
                return redirect(next_page)
            return redirect(url_for('upload_file'))
        else:
            # Failed login
            is_locked_now = record_failed_attempt(ip_address)
            
            if is_locked_now:
                error = f"Too many failed attempts. Account locked for {LOCKOUT_DURATION // 60} minutes."
                print(f"[Auth] Account locked: IP {ip_address} after {MAX_LOGIN_ATTEMPTS} failed attempts")
            else:
                attempts_left = MAX_LOGIN_ATTEMPTS - login_attempts[ip_address]['attempts']
                error = f"Invalid username or password. {attempts_left} attempt(s) remaining."
                print(f"[Auth] Failed login attempt: {username} from {ip_address}")
            
            return render_template('login.html', error=error)
    
    # GET request - show login form
    # If already logged in, redirect to admin panel
    if 'logged_in' in session and session['logged_in']:
        return redirect(url_for('upload_file'))
    
    return render_template('login.html')

@app.route('/logout')
def logout():
    """Logout the current user"""
    username = session.get('username', 'Unknown')
    ip_address = session.get('ip_address', request.remote_addr)
    
    session.clear()
    flash('You have been logged out successfully.', 'success')
    
    print(f"[Auth] User logged out: {username} from {ip_address}")
    return redirect(url_for('login'))

@app.route('/api/session_status')
def session_status():
    """Check if session is still active (for idle timeout monitoring)"""
    if 'logged_in' in session and session['logged_in']:
        last_activity = session.get('last_activity', 0)
        current_time = time.time()
        idle_seconds = int(current_time - last_activity)
        
        return jsonify({
            'active': True,
            'username': session.get('username'),
            'idle_seconds': idle_seconds,
            'max_idle': 1800  # 30 minutes
        }), 200
    else:
        return jsonify({'active': False}), 401

# PRIMARY upload folder: MAIN_SYSTEM/uploads (where GENTA7 saves all reports)
# This is where CakePHP will fetch reports via ngrok tunnel
PRIMARY_UPLOAD_FOLDER = os.environ.get('UPLOAD_FOLDER', os.path.join(os.getcwd(), 'MAIN_SYSTEM', 'uploads'))
# Fallback/alternate folder (old location, kept for backward compatibility)
ALT_UPLOAD_FOLDER = os.path.join(os.getcwd(), 'uploads')

# Resolve to absolute paths so the Flask app sees the same folders regardless of
# the current working directory used when starting the process.
PRIMARY_UPLOAD_FOLDER = os.path.abspath(PRIMARY_UPLOAD_FOLDER)
ALT_UPLOAD_FOLDER = os.path.abspath(ALT_UPLOAD_FOLDER)

app.config['UPLOAD_FOLDER'] = PRIMARY_UPLOAD_FOLDER  # MAIN_SYSTEM/uploads (primary)
app.config['ALT_UPLOAD_FOLDER'] = ALT_UPLOAD_FOLDER  # uploads (fallback)

ALLOWED_EXTENSIONS = {'m4a', 'txt', 'mp3', 'wav', 'docx'}

def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

def get_description(filename):
    if filename == 'output.txt':
        return 'GENTA Answer Output Text File'
    elif filename == 'GENTA_response.mp3':
        return 'GENTA Answer Output Audio File'
    elif filename == 'recording.wav':
        return 'Student Audio Input'
    elif filename == 'Welcome1.wav':
        return '''Hello, ako si GENTA, aralin natin 'to ng magkasama! Anong part ang kailangan mong ipa-explain?'''
    elif filename == 'Welcome2.wav':
        return '''Hello, ako si GENTA, gusto mo bang magkaroon ng masayang study session? Ako'y nandito para magbigay ng saya at tulong!'''
    elif filename == 'Welcome3.wav':
        return '''Hello, ako si GENTA, handa ka na bang mag-aral? Ako'y nandito para magbigay ng tulong!'''
    elif filename == 'Welcome4.wav':
        return '''Hello, ako si GENTA, huwag ka nang kabahan sa iyon assignment! Dala ko ang aking brain power para sa iyo!'''
    elif filename == 'Welcome5.wav':
        return '''Hello, ako si GENTA, isang malaking Go, fight, win! para sa iyong pag-aaral! Ano ang kailangan mong ipa-explain?'''
    elif filename == 'Welcome6.wav':
        return '''Hello, I'm GENTA. Please ask your question — I'm here to help!'''
    elif filename == 'Welcome7.wav':
        return '''Hello, ako si GENTA, sino ang gusto ng study buddy? Ako'y handang maging kasama mo!'''
    elif filename == 'Welcome8.wav':
        return '''Hello, ako si GENTA, sino ang handa para sa learning adventure? Ako'y handa na!'''
    elif filename == 'Welcome9.wav':
        return '''Hello, ako si GENTA, tara, tutulungan kita sa iyong pag-aaral!'''
    elif filename == 'Welcome10.wav':
        return '''Hello, ako si GENTA, wow, aral mode na naman! May kailangan ka bang ipa-explain?'''
    elif filename == 'Repeat1.wav':
        return '''Hello, ako si GENTA!'''
    elif filename == 'Repeat2.wav':
        return '''Kumusta! Ako si GENTA, at handa akong tulungan ka!'''
    elif filename == 'Repeat3.wav':
        return '''Pwede pa ba kitang matulungan?'''
    else:
        return 'No description available'

# ===== ON-DEMAND SYSTEM PROCESS MANAGEMENT =====
genta_process = None
import collections
genta_logs = collections.deque(maxlen=200) # Keep last 200 lines

def _read_process_logs(proc):
    global genta_logs
    try:
        for line in iter(proc.stdout.readline, b''):
            decoded = line.decode('utf-8', errors='replace').strip()
            if decoded:
                genta_logs.append(decoded)
                print("[GENTA7]", decoded)
    except:
        pass

def api_or_login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        # 1. Check API Key first
        if _check_api_key():
            return f(*args, **kwargs)
        # 2. Check Session
        if 'logged_in' in session and session['logged_in']:
            return f(*args, **kwargs)
        return jsonify({'error': 'Unauthorized', 'message': 'Invalid API Key or not logged in'}), 401
    return decorated_function

@app.route('/api/system/start', methods=['POST'])
@api_or_login_required
def start_system():
    global genta_process, genta_logs
    if genta_process and genta_process.poll() is None:
        return jsonify({'status': 'error', 'message': 'System is already running'}), 400
    
    try:
        genta_logs.clear()
        genta_logs.append("System starting up...")
        
        creationflags = subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0
        genta_process = subprocess.Popen(
            [sys.executable, '-u', 'GENTA7.py'],
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            bufsize=1,
            cwd=os.path.dirname(os.path.abspath(__file__)),
            creationflags=creationflags
        )
        
        # Thread to read logs seamlessly
        log_thread = threading.Thread(target=_read_process_logs, args=(genta_process,))
        log_thread.daemon = True
        log_thread.start()
        
        return jsonify({'status': 'success', 'message': 'System successfully started.'})
    except Exception as e:
        return jsonify({'status': 'error', 'message': str(e)}), 500

@app.route('/api/system/stop', methods=['POST'])
@api_or_login_required
def stop_system():
    global genta_process, genta_logs
    if not genta_process or genta_process.poll() is not None:
        return jsonify({'status': 'error', 'message': 'System is not running'}), 400
    
    try:
        genta_process.terminate()
        genta_process.wait(timeout=3)
        genta_logs.append("System stopped safely.")
        return jsonify({'status': 'success', 'message': 'System stopped'})
    except Exception as e:
        try:
            genta_process.kill()
            genta_logs.append("System forced to stop.")
            return jsonify({'status': 'success', 'message': 'System forcefully stopped'})
        except Exception as kill_err:
            return jsonify({'status': 'error', 'message': f'Failed to stop: {str(kill_err)}'}), 500

@app.route('/api/system/status', methods=['GET'])
@api_or_login_required
def system_status():
    global genta_process
    is_running = genta_process is not None and genta_process.poll() is None
    return jsonify({
        'status': 'success',
        'is_running': is_running
    })

@app.route('/api/system/logs', methods=['GET'])
@api_or_login_required
def system_logs():
    global genta_logs
    return jsonify({
        'status': 'success',
        'logs': list(genta_logs)
    })

@app.route('/', methods=['GET', 'POST'])
@login_required
def upload_file():
    if request.method == 'POST':
        file = request.files['file']
        if file and allowed_file(file.filename):
            filename = file.filename
            save_path = os.path.join(app.config['UPLOAD_FOLDER'], filename)
            os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)
            file.save(save_path)
            # Show admin view without audio files by default
            files = _list_all_upload_files(exclude_audio=True)
            return render_template('upload.html', success_message="File uploaded successfully!", files=files, get_description=get_description)
        else:
            files = _list_all_upload_files(exclude_audio=True)
            return render_template('upload.html', error_message="Only m4a, txt, mp3, and wav files are allowed!", files=files, get_description=get_description)
    # Get the list of files in the configured upload folders (primary + MAIN_SYSTEM/uploads)
    # For admin UI we prefer to hide audio files (they are noisy for file management)
    files = _list_all_upload_files(exclude_audio=True)
    # Provide backup information to admin template
    try:
        backups = _list_backups()
    except Exception:
        backups = []
    try:
        pre_restores = _list_pre_restore()
    except Exception:
        pre_restores = []
    try:
        backup_cfg = load_backup_config()
    except Exception:
        backup_cfg = {'enabled': False, 'interval': 'weekly', 'last_run': None}

    # Surface flashed messages into template variables so the JS toast can display them
    flashed = get_flashed_messages(with_categories=True)
    success_message = None
    error_message = None
    try:
        for cat, msg in flashed:
            if cat in ('success', 'info') and not success_message:
                success_message = msg
            elif cat in ('error', 'danger') and not error_message:
                error_message = msg
    except Exception:
        # In case flashed messages were simple strings (no categories)
        try:
            if flashed:
                # flashed may be list of strings
                if isinstance(flashed[0], str):
                    success_message = flashed[0]
        except Exception:
            pass

    return render_template('upload.html', files=files, get_description=get_description, backups=backups, pre_restores=pre_restores, backup_cfg=backup_cfg, success_message=success_message, error_message=error_message)


def _list_all_upload_files(exclude_audio=False):
    """Return a combined sorted list of files from primary and MAIN_SYSTEM upload folders.
    Each entry is a dict: { 'name': filename, 'source': 'primary'|'main_system', 'path': relpath }
    """
    results = []
    p = app.config.get('UPLOAD_FOLDER')
    alt = app.config.get('ALT_UPLOAD_FOLDER')
    try:
        if p and os.path.exists(p):
            for fn in os.listdir(p):
                results.append({'name': fn, 'source': 'primary', 'relpath': fn})
    except Exception:
        pass
    try:
        if alt and os.path.exists(alt):
            for fn in os.listdir(alt):
                # avoid duplicate names; mark source accordingly
                if not any(r['name'] == fn for r in results):
                    results.append({'name': fn, 'source': 'main_system', 'relpath': fn})
                else:
                    # if duplicate, prefer primary but note duplicates by adding suffix
                    results.append({'name': fn, 'source': 'main_system', 'relpath': fn})
    except Exception:
        pass
    # Sort by name
    results.sort(key=lambda x: x['name'].lower())
    # Optionally filter out audio files for the admin UI
    if exclude_audio:
        audio_exts = ('.m4a', '.mp3', '.wav')
        results = [r for r in results if not r['name'].lower().endswith(audio_exts)]
    return results

# Existing imports and setup code
# Cache last known oled_status payload so callers can get a helpful fallback
LAST_OLED_STATUS = None
# Cache the last-known speaker state returned by /state.txt so we can
# detect changes and notify the host (GENTA7) via UDP to avoid repeated polling.
LAST_KNOWN_STATE = None
# Timestamp we last updated `LAST_OLED_STATUS` (epoch seconds)
_LAST_OLED_STATUS_TS = 0
# How long (seconds) a cached oled status is considered fresh enough to return
LAST_OLED_STATUS_TTL = 10
# Guard: when True, prevent discovery registry clears (used during host report animation)
REPORT_CREATION_GUARD = False
# Sticky mapping: remember last-known IP by role so brief discovery gaps won't force fallback
LAST_KNOWN_DEVICE_BY_ROLE = {}
# Cache recent OLED-forward failures to avoid repeated timeouts when device offline
_oled_fail_cache = {}
# Queue and background worker to forward /oled requests to devices without blocking Flask.
oled_forward_queue = queue.Queue()

def _oled_forward_worker():
    """Background worker that sends /oled requests to devices from a queue.
    This ensures the Flask request handler returns immediately and device
    forwarding is retried/logged by the worker without blocking incoming requests.
    """
    while True:
        job = None
        try:
            job = oled_forward_queue.get()
            if not job:
                continue

            params = job.get('params', {})
            # Support both legacy single-device jobs and the newer 'candidates' list
            candidates = job.get('candidates')
            device_base = job.get('device_base')

            targets = []
            if candidates and isinstance(candidates, (list, tuple)) and len(candidates) > 0:
                targets = candidates
            elif device_base:
                targets = [device_base]

            tried_hosts = []
            success = False

            for base in targets:
                if not base:
                    continue
                host = base.replace('http://', '').replace('https://', '')
                tried_hosts.append(host)
                url = f"{base}/oled"
                try:
                    # Try a connect+read timeout tuple: (connect, read)
                    try:
                        resp = requests.get(url, params=params, timeout=(3, 6))
                    except requests.exceptions.ReadTimeout as rte:
                        # Device accepted connection but did not reply quickly; retry once with a longer read timeout
                        app.logger.debug(f"/oled worker: ReadTimeout on {url}, retrying with longer read timeout")
                        time.sleep(0.25)
                        try:
                            resp = requests.get(url, params=params, timeout=(3, 10))
                        except Exception as e2:
                            # Treat as failure for this host after retry
                            app.logger.warning(f"/oled worker: retry failed for {url}: {type(e2).__name__}: {e2}")
                            try:
                                _oled_fail_cache[host] = time.time()
                            except Exception:
                                pass
                            continue
                    except Exception:
                        # Other errors on first attempt - small backoff and one quick retry
                        time.sleep(0.25)
                        resp = requests.get(url, params=params, timeout=(3, 6))

                    app.logger.debug(f"/oled worker forwarded to {url} params={params} -> status={getattr(resp, 'status_code', None)}")

                    # Clear any recent failure record on success
                    try:
                        if host in _oled_fail_cache:
                            del _oled_fail_cache[host]
                    except Exception:
                        pass

                    success = True
                    break
                except requests.exceptions.RequestException as e:
                    # For connect/read timeouts and other request errors, record a warning.
                    app.logger.warning(f"/oled worker: failed to forward to device {url}: {type(e).__name__}: {e}")
                    # Mark host as failed for connect-level errors, but avoid marking hosts that only had a ReadTimeout
                    try:
                        if not isinstance(e, requests.exceptions.ReadTimeout):
                            _oled_fail_cache[host] = time.time()
                    except Exception:
                        pass
                    continue
                except Exception as e:
                    # Non-requests exceptions - log and mark host failed
                    app.logger.exception(f"/oled worker: unexpected error forwarding to {url}: {e}")
                    try:
                        _oled_fail_cache[host] = time.time()
                    except Exception:
                        pass
                    continue

            if not success:
                app.logger.warning(f"/oled worker: all targets failed for params={params} tried={tried_hosts}")

        except Exception:
            # Keep worker alive on unexpected errors
            app.logger.exception('OLED forward worker encountered an error')
        finally:
            try:
                if job is not None:
                    oled_forward_queue.task_done()
            except Exception:
                pass

# Start the oled forward worker thread (daemon)
try:
    threading.Thread(target=_oled_forward_worker, daemon=True).start()
except Exception:
    pass


@app.route('/cancel_recording_wait', methods=['GET'])
def cancel_recording_wait():
    """Signal the background `wait_for_recording` long-poll to return immediately
    with a cancellation marker so callers (host) can abort waiting when state changes.
    This is implemented by enqueuing a special 'cancelled' payload into the
    recording_ready_queue so the `wait_for_recording` endpoint will deliver it.
    """
    try:
        # purge any stale items first to avoid delivering old ready events
        try:
            while not recording_ready_queue.empty():
                recording_ready_queue.get_nowait()
        except Exception:
            pass

        # Put a cancellation notification that `wait_for_recording` will return
        try:
            recording_ready_queue.put_nowait({'status': 'cancelled', 'timestamp': time.time()})
            print('[Flask] cancel_recording_wait: enqueued cancellation')
        except queue.Full:
            # If the queue is full, clear one and try again
            try:
                recording_ready_queue.get_nowait()
            except Exception:
                pass
            try:
                recording_ready_queue.put_nowait({'status': 'cancelled', 'timestamp': time.time()})
            except Exception:
                pass
        return {'status': 'cancelled'}, 200
    except Exception as e:
        return {'error': str(e)}, 500


@app.route('/debug/oled_fail_cache', methods=['GET'])
def debug_oled_fail_cache():
    """Return the current cached OLED forward failures for debugging."""
    try:
        return jsonify({'_oled_fail_cache': _oled_fail_cache}), 200
    except Exception as e:
        return {'error': str(e)}, 500


@app.route('/debug/clear_oled_fail_cache', methods=['POST', 'GET'])
def clear_oled_fail_cache():
    """Clear the internal OLED forward failure cache so forwarding will be attempted again."""
    try:
        _oled_fail_cache.clear()
        print('[Flask] Cleared _oled_fail_cache via debug endpoint')
        return {'status': 'cleared'}, 200
    except Exception as e:
        return {'error': str(e)}, 500


@app.route('/debug/oled_queue', methods=['GET'])
def debug_oled_queue():
    """Return a snapshot of the OLED forward queue and fail cache for debugging.
    This is non-destructive and safe to call in production for diagnostics.
    """
    try:
        # Convert queue contents to a serializable list without dequeuing
        snapshot = []
        try:
            qlist = list(oled_forward_queue.queue)
            for item in qlist:
                # Simplify job representation
                job = {
                    'candidates': item.get('candidates') if isinstance(item, dict) else None,
                    'device_base': item.get('device_base') if isinstance(item, dict) else None,
                    'params': item.get('params') if isinstance(item, dict) else None,
                }
                snapshot.append(job)
        except Exception:
            snapshot = []

        return jsonify({'queue_length': oled_forward_queue.qsize(), 'queue': snapshot, '_oled_fail_cache': _oled_fail_cache}), 200
    except Exception as e:
        return {'error': str(e)}, 500


@app.route('/debug/oled_now', methods=['GET'])
def debug_oled_now():
    """Synchronous diagnostic: try contacting recorder candidates now.
    Query params:
      - ip: optional explicit IP to test (skips discovery)
      - send: optional OLED value to send (e.g. 'happy')
    Returns per-candidate results with timings and any error messages.
    """
    ip_override = request.args.get('ip')
    send_value = request.args.get('send')

    # Build candidates similar to /oled_status
    candidates = []
    try:
        if ip_override:
            candidates.append(f'http://{ip_override}')
        else:
            discovered_ip = find_device_ip('recorder')
            if discovered_ip:
                candidates.append(f'http://{discovered_ip}')
    except Exception:
        pass

    try:
        for host_var in ('esp_record_host', 'esp_playback_host'):
            hv = globals().get(host_var)
            if hv:
                if isinstance(hv, str) and hv.startswith('http'):
                    candidates.append(hv)
                else:
                    candidates.append(f'http://{hv}')
    except Exception:
        pass

    try:
        if ESP_BASE and ESP_BASE not in candidates:
            candidates.append(ESP_BASE)
    except Exception:
        pass

    # Deduplicate
    seen = set(); uniq = []
    for c in candidates:
        if c and c not in seen:
            seen.add(c); uniq.append(c)
    candidates = uniq

    results = []
    for base in candidates:
        entry = {'base': base, 'status': 'unknown', 'elapsed_ms': None, 'http_status': None, 'error': None}
        status_url = f"{base}/oled_status"
        try:
            start = time.time()
            resp = requests.get(status_url, timeout=4)
            elapsed = (time.time() - start) * 1000.0
            entry['elapsed_ms'] = int(elapsed)
            entry['http_status'] = getattr(resp, 'status_code', None)
            try:
                entry['body'] = resp.json()
            except Exception:
                entry['body'] = resp.text[:1024]
            entry['status'] = 'ok'
            # clear fail cache for this host on success
            try:
                host = base.replace('http://','').replace('https://','')
                if host in _oled_fail_cache:
                    del _oled_fail_cache[host]
            except Exception:
                pass
        except Exception as e:
            entry['status'] = 'failed'
            entry['error'] = f"{type(e).__name__}: {e}"
            try:
                entry['elapsed_ms'] = int((time.time() - start) * 1000.0)
            except Exception:
                pass
            # mark fail cache
            try:
                host = base.replace('http://','').replace('https://','')
                _oled_fail_cache[host] = time.time()
            except Exception:
                pass

        # Optionally send an OLED command to the candidate
        if send_value and entry.get('status') == 'ok':
            try:
                send_url = f"{base}/oled"
                try:
                    sstart = time.time()
                    sresp = requests.get(send_url, params={'value': send_value}, timeout=(3, 6))
                except requests.exceptions.ReadTimeout:
                    # retry once with a longer read timeout
                    time.sleep(0.25)
                    sstart = time.time()
                    sresp = requests.get(send_url, params={'value': send_value}, timeout=(3, 10))
                entry['send'] = {'http_status': getattr(sresp, 'status_code', None), 'elapsed_ms': int((time.time()-sstart)*1000.0)}
            except Exception as se:
                entry['send'] = {'error': f"{type(se).__name__}: {se}"}

        results.append(entry)

    return jsonify({'candidates_tried': candidates, 'results': results, '_oled_fail_cache': _oled_fail_cache}), 200
# Allow a guarded debug endpoint to set LAST_OLED_STATUS for local testing.
# Only enabled when GENTA_ALLOW_DEBUG_OLED=1 in the environment so it
# cannot be accidentally exposed in production.
    
@app.route('/download_recording', methods=['GET'])
def download_recording():
    # Priority: 1) URL param override, 2) Auto-discovered IP, 3) Fallback ENV/default
    ip_override = request.args.get('ip')
    if ip_override:
        recorder_host = ip_override
    else:
        discovered_ip = find_device_ip('recorder')
        if discovered_ip:
            recorder_host = discovered_ip
            app.logger.debug(f"Using auto-discovered recorder IP: {recorder_host}")
        else:
            recorder_host = ESP_BASE.replace('http://', '')
            app.logger.debug(f"Using fallback recorder IP: {recorder_host}")
    
    recording_url = f'http://{recorder_host}/recording.wav'
    try:
        response = requests.get(recording_url, timeout=60)  # Increased to 60 seconds for slow SPIFFS reads
        if response.status_code == 200:
            # Set up response headers for downloading the file
            return response.content, 200, {
                'Content-Type': 'audio/wav',
                'Content-Disposition': f'attachment; filename="recording.wav"'
            }
        else:
            return "Failed to download recording", 404
    except requests.ConnectionError as ce:
        return render_template_string('<script>alert("Connection error: {{ error }}");</script>', error=ce), 500
    except requests.Timeout as te:
        return render_template_string('<script>alert("Request timeout: {{ error }}");</script>', error=te), 500
    except requests.RequestException as e:
        return render_template_string('<script>alert("An error occurred: {{ error }}");</script>', error=e), 500


@app.route('/clear', methods=['GET'])
def clear_recording():
    """Proxy /clear to ESP32 to delete recording file"""
    ip_override = request.args.get('ip')
    if ip_override:
        recorder_host = ip_override
    else:
        discovered_ip = find_device_ip('recorder')
        recorder_host = discovered_ip if discovered_ip else ESP_BASE.replace('http://', '')
    
    clear_url = f'http://{recorder_host}/clear'
    try:
        response = requests.get(clear_url, timeout=5)
        return response.content, response.status_code, {'Content-Type': 'text/plain'}
    except requests.RequestException as e:
        return f"Failed to clear recording: {e}", 500


@app.route('/stop', methods=['GET'])
def stop_recording():
    """Proxy /stop to ESP32 to stop active recording"""
    ip_override = request.args.get('ip')
    if ip_override:
        recorder_host = ip_override
    else:
        discovered_ip = find_device_ip('recorder')
        recorder_host = discovered_ip if discovered_ip else ESP_BASE.replace('http://', '')
    
    stop_url = f'http://{recorder_host}/stop'
    try:
        response = requests.get(stop_url, timeout=5)
        return response.content, response.status_code, {'Content-Type': 'text/plain'}
    except requests.RequestException as e:
        return f"Failed to stop recording: {e}", 500


@app.route('/oled', methods=['GET'])
def set_oled_expression():
    """Proxy /oled to ESP32 (Recorder) to control OLED eye expressions - SILENT mode"""
    expression = request.args.get('value', 'idle')
    
    # Forward any query args (value, progress, etc.) to the device using params dict
    params = {k: v for k, v in request.args.items()}

    # Build a candidate list of device bases to try, preferring auto-discovered
    candidates = []
    try:
        discovered_ip = find_device_ip('recorder')
        if discovered_ip:
            candidates.append(f'http://{discovered_ip}')
    except Exception:
        pass

    # Include known globals if present (these are host/IP strings without scheme)
    try:
        for host_var in ('esp_record_host', 'esp_playback_host'):
            hv = globals().get(host_var)
            if hv:
                # normalize to include scheme
                if isinstance(hv, str) and hv.startswith('http'):
                    candidates.append(hv)
                else:
                    candidates.append(f'http://{hv}')
    except Exception:
        pass

    # Finally include configured fallback ESP_BASE (already contains scheme)
    try:
        if ESP_BASE and ESP_BASE not in candidates:
            candidates.append(ESP_BASE)
    except Exception:
        pass

    # Deduplicate while preserving order
    seen = set()
    uniq = []
    for c in candidates:
        if c and c not in seen:
            seen.add(c)
            uniq.append(c)
    candidates = uniq

    # Non-blocking: enqueue a job with candidate list for the worker to attempt
    try:
        oled_forward_queue.put_nowait({'candidates': candidates, 'params': params})
        # Log at info so it's visible in normal logs; also print for quick console visibility
        app.logger.info(f"/oled: enqueued forward to candidates={candidates} params={params}")
        try:
            print(f"[Flask] /oled enqueued -> candidates={candidates} params={params}")
        except Exception:
            pass
    except queue.Full:
        app.logger.warning('/oled: forward queue full; dropping request')

    return 'OK', 200, {'Content-Type': 'text/plain'}


@app.route('/size', methods=['GET'])
def get_size():
    """Proxy /size to ESP32 to check recording file size"""
    ip_override = request.args.get('ip')
    if ip_override:
        recorder_host = ip_override
    else:
        discovered_ip = find_device_ip('recorder')
        recorder_host = discovered_ip if discovered_ip else ESP_BASE.replace('http://', '')
    
    size_url = f'http://{recorder_host}/size'
    try:
        response = requests.get(size_url, timeout=5)
        return response.content, response.status_code, {'Content-Type': 'text/plain'}
    except requests.RequestException as e:
        return "0", 500


@app.route('/status', methods=['GET'])
def get_status():
    """Proxy /status to ESP32 to check if recording is active"""
    ip_override = request.args.get('ip')
    if ip_override:
        recorder_host = ip_override
    else:
        discovered_ip = find_device_ip('recorder')
        recorder_host = discovered_ip if discovered_ip else ESP_BASE.replace('http://', '')
    
    # ESP firmware exposes the WiFi/recording status at /wifi/status
    status_url = f'http://{recorder_host}/wifi/status'
    try:
        response = requests.get(status_url, timeout=5)
        return response.content, response.status_code, {'Content-Type': 'text/plain'}
    except requests.RequestException as e:
        return "error", 500


@app.route('/oled_status', methods=['GET'])
def oled_status():
    """Proxy /oled_status to recorder ESP so host can poll report completion."""
    # Build candidate list similar to /oled forwarding so we try discovered IPs
    candidates = []
    try:
        discovered_ip = find_device_ip('recorder')
        if discovered_ip:
            candidates.append(f'http://{discovered_ip}')
    except Exception:
        pass

    try:
        for host_var in ('esp_record_host', 'esp_playback_host'):
            hv = globals().get(host_var)
            if hv:
                if isinstance(hv, str) and hv.startswith('http'):
                    candidates.append(hv)
                else:
                    candidates.append(f'http://{hv}')
    except Exception:
        pass

    try:
        if ESP_BASE and ESP_BASE not in candidates:
            candidates.append(ESP_BASE)
    except Exception:
        pass

    # Deduplicate while preserving order
    seen = set()
    uniq = []
    for c in candidates:
        if c and c not in seen:
            seen.add(c)
            uniq.append(c)
    candidates = uniq

    tried = []
    for base in candidates:
        try:
            status_url = f"{base}/oled_status"
            tried.append(status_url)
            app.logger.debug(f"/oled_status: trying {status_url}")
            response = requests.get(status_url, timeout=4)
            app.logger.debug(f"/oled_status: device returned status={getattr(response, 'status_code', None)} for {status_url}")
            try:
                data = response.json()
                global LAST_OLED_STATUS, _LAST_OLED_STATUS_TS
                LAST_OLED_STATUS = data
                _LAST_OLED_STATUS_TS = time.time()
            except Exception:
                pass
            return response.content, response.status_code, {'Content-Type': 'application/json'}
        except requests.RequestException as e:
            app.logger.warning(f"/oled_status: failed to contact device {status_url}: {type(e).__name__}: {e}")
            try:
                host = base.replace('http://', '').replace('https://', '')
                _oled_fail_cache[host] = time.time()
            except Exception:
                pass
            continue

    # If all candidates failed, try to return cached status if fresh
    try:
        if LAST_OLED_STATUS is not None and (time.time() - _LAST_OLED_STATUS_TS) <= LAST_OLED_STATUS_TTL:
            app.logger.debug(f"/oled_status: all candidates failed ({tried}); returning cached LAST_OLED_STATUS (age={(time.time() - _LAST_OLED_STATUS_TS):.1f}s)")
            return json.dumps(LAST_OLED_STATUS), 200, {'Content-Type': 'application/json'}
    except Exception:
        pass

    app.logger.warning(f"/oled_status: all candidates failed for recorder; tried={tried}")
    return {'error': 'Could not contact recorder for oled_status', 'tried': tried}, 500


@app.route('/state.txt', methods=['GET'])
def get_state():
    """Proxy /state.txt to ESP32 (Speaker) to get current mode"""
    # Use auto-discovered player IP if available
    discovered_ip = find_device_ip('player')
    if discovered_ip:
        state_url = f'http://{discovered_ip}/state.txt'
        app.logger.debug(f"Using auto-discovered player IP for state: {discovered_ip}")
    else:
        state_url = f'{ESP_SPEAKER}/state.txt'
        app.logger.debug(f"Using fallback player IP for state: {ESP_SPEAKER}")
    
    # Retry logic with increased timeout (ESP32 might be busy with audio tasks)
    for attempt in range(3):
        try:
            response = requests.get(state_url, timeout=10)  # Increased from 5s to 10s
            # If we successfully retrieved the state, compare with last known
            # and notify the local host via UDP if it changed.
            try:
                if getattr(response, 'status_code', None) == 200:
                    body = None
                    try:
                        body = response.text.strip()
                    except Exception:
                        body = None
                    # Normalize body to a single char '0' or '1' where possible
                    norm = None
                    if body in ('0', '1'):
                        norm = body
                    else:
                        # try to extract first digit
                        m = re.search(r"([01])", body or "")
                        if m:
                            norm = m.group(1)

                    global LAST_KNOWN_STATE
                    try:
                        if norm is not None and norm != LAST_KNOWN_STATE:
                            LAST_KNOWN_STATE = norm
                            # send UDP notification to localhost:52000
                            try:
                                s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
                                s.settimeout(0.5)
                                msg = f"state:{norm}".encode('utf-8')
                                s.sendto(msg, ('127.0.0.1', 52000))
                                try:
                                    s.close()
                                except Exception:
                                    pass
                                print(f"[Flask] Sent state notification to host: {norm}")
                            except Exception as e:
                                print(f"[Flask] Failed to send state UDP notification: {e}")
                    except Exception:
                        pass

            except Exception:
                pass

            return response.content, response.status_code, {'Content-Type': 'text/plain'}
        except requests.Timeout:
            if attempt < 2:  # Retry up to 3 times
                print(f"State request timeout (attempt {attempt + 1}/3), retrying...")
                time.sleep(0.5)
                continue
            return f"Failed to get state: Request timed out after 3 attempts", 500
        except requests.RequestException as e:
            return f"Failed to get state: {e}", 500
    
    return "Failed to get state: Max retries exceeded", 500


@app.route('/enable_state_button', methods=['GET'])
def enable_state_button():
    """Proxy /enable_state_button to ESP32 (Speaker)"""
    discovered_ip = find_device_ip('player')
    url = f'http://{discovered_ip}/enable_state_button' if discovered_ip else f'{ESP_SPEAKER}/enable_state_button'
    try:
        response = requests.get(url, timeout=10)  # Increased timeout
        return response.content, response.status_code, {'Content-Type': 'text/plain'}
    except requests.RequestException as e:
        return f"Failed to enable state button: {e}", 500


@app.route('/disable_state_button', methods=['GET'])
def disable_state_button():
    """Proxy /disable_state_button to ESP32 (Speaker)"""
    discovered_ip = find_device_ip('player')
    url = f'http://{discovered_ip}/disable_state_button' if discovered_ip else f'{ESP_SPEAKER}/disable_state_button'
    try:
        response = requests.get(url, timeout=10)  # Increased timeout
        return response.content, response.status_code, {'Content-Type': 'text/plain'}
    except requests.RequestException as e:
        return f"Failed to disable state button: {e}", 500


# ===== RECORDING NOTIFICATION ENDPOINTS =====
@app.route('/notify_recording_ready', methods=['POST'])
def notify_recording_ready():
    """
    Webhook endpoint that ESP32 or monitoring service can call
    when a new recording is ready. This immediately notifies waiting clients.
    """
    global last_recording_timestamp
    with recording_notification_lock:
        last_recording_timestamp = time.time()
        # Put notification in queue (non-blocking, with size limit)
        try:
            recording_ready_queue.put_nowait({'timestamp': last_recording_timestamp, 'ready': True})
            print(f"[Flask] Recording ready notification received at {last_recording_timestamp}")
        except queue.Full:
            # Queue full, clear old items
            try:
                recording_ready_queue.get_nowait()
            except:
                pass
            recording_ready_queue.put_nowait({'timestamp': last_recording_timestamp, 'ready': True})
    
    return {'status': 'success', 'message': 'Recording notification received'}, 200


@app.route('/wait_for_recording', methods=['GET'])
def wait_for_recording():
    """
    Long-polling endpoint that blocks until a recording is ready.
    GENTA7 can call this instead of polling /size repeatedly.
    Timeout after 35 seconds to prevent connection issues.
    """
    timeout = int(request.args.get('timeout', 35))
    start_time = time.time()
    
    print(f"[Flask] Client waiting for recording (timeout={timeout}s)...")
    
    try:
        # Wait for notification from queue with timeout
        notification = recording_ready_queue.get(timeout=timeout)
        elapsed = time.time() - start_time
        print(f"[Flask] Recording ready notification delivered after {elapsed:.1f}s")
        # Support cancellation marker: if a caller enqueued a cancellation
        # payload, return it so the host can abort waiting immediately.
        try:
            if isinstance(notification, dict) and notification.get('status') == 'cancelled':
                return {'status': 'cancelled', 'timestamp': notification.get('timestamp', time.time()), 'elapsed': elapsed}, 200
        except Exception:
            pass

        return {
            'status': 'ready',
            'timestamp': notification.get('timestamp', time.time()),
            'elapsed': elapsed
        }, 200
    except queue.Empty:
        # Timeout reached, no recording ready
        elapsed = time.time() - start_time
        print(f"[Flask] Wait for recording timed out after {elapsed:.1f}s")
        return {
            'status': 'timeout',
            'elapsed': elapsed
        }, 408  # Request Timeout status code


@app.route('/reset_recording_notification', methods=['GET'])
def reset_recording_notification():
    """
    Clears any stale notifications from the recording queue.
    Called by GENTA7 after clearing a recording to prevent false-positive detections.
    """
    cleared_count = 0
    try:
        while not recording_ready_queue.empty():
            recording_ready_queue.get_nowait()
            cleared_count += 1
    except queue.Empty:
        pass
    
    print(f"[Flask] Recording notification queue reset ({cleared_count} notifications cleared)")
    return {'status': 'ok', 'cleared': cleared_count}, 200


@app.route('/poll_recording_status', methods=['GET'])
def poll_recording_status():
    """
    Fast polling endpoint that checks if recording is ready without blocking.
    Returns immediately with current status.
    """
    # Use auto-discovered recorder IP if available
    discovered_ip = find_device_ip('recorder')
    recorder_host = discovered_ip if discovered_ip else ESP_BASE.replace('http://', '')
    
    size_url = f'http://{recorder_host}/size'
    
    try:
        response = requests.get(size_url, timeout=2)
        if response.status_code == 200:
            size = int(response.text.strip())
            # Recording is ready if size > threshold (e.g., 1KB)
            is_ready = size > 1024
            return {
                'status': 'ready' if is_ready else 'not_ready',
                'size': size,
                'timestamp': time.time()
            }, 200
        else:
            return {'status': 'error', 'message': 'Failed to check size'}, 500
    except Exception as e:
        return {'status': 'error', 'message': str(e)}, 500


@app.route('/set_state', methods=['GET'])
def set_state():
    """Proxy /set_state to ESP32 (Speaker) to change mode
    Usage: /set_state?value=0 (Assisting) or /set_state?value=1 (Quiz)
    """
    value = request.args.get('value', '0')
    if value not in ['0', '1']:
        return "Invalid state value. Use 0 or 1", 400
    
    # Use auto-discovered player IP if available
    discovered_ip = find_device_ip('player')
    if discovered_ip:
        url = f'http://{discovered_ip}/set_state?value={value}'
        app.logger.debug(f"Using auto-discovered player IP for set_state: {discovered_ip}")
    else:
        url = f'{ESP_SPEAKER}/set_state?value={value}'
        app.logger.debug(f"Using fallback player IP for set_state: {ESP_SPEAKER}")
    
    try:
        response = requests.get(url, timeout=10)
        return response.content, response.status_code, {'Content-Type': 'text/plain'}
    except requests.RequestException as e:
        return f"Failed to set state: {e}", 500


@app.route('/download_raw', methods=['GET'])
def download_raw():
    """Proxy and download the raw_i2s.bin file from the ESP device.
    This lets the public ngrok URL serve the raw dump even if the ESP is on a different host.
    """
    ip_override = request.args.get('ip')
    recorder_host = ip_override or ESP_BASE.replace('http://', '')
    raw_url = f'http://{recorder_host}/raw_i2s.bin'
    # Before attempting fetch, poll ESP's /raw_meta so we wait until the file exists and is non-empty
    meta_url = f'http://{recorder_host}/raw_meta'
    try:
        mresp = requests.get(meta_url, timeout=3)
        if mresp.status_code == 200:
            try:
                meta = mresp.json()
            except Exception:
                meta = None
            if meta and meta.get('exists') and meta.get('size', 0) > 0:
                app.logger.info(f"download_raw: meta shows file ready (size={meta.get('size')})")
            else:
                # wait a short while for the ESP to finish writing/closing the file
                wait_start = time.time()
                wait_timeout = 5.0
                ready = False
                while time.time() - wait_start < wait_timeout:
                    time.sleep(0.4)
                    try:
                        mr = requests.get(meta_url, timeout=2)
                        if mr.status_code == 200:
                            mm = None
                            try:
                                mm = mr.json()
                            except Exception:
                                mm = None
                            if mm and mm.get('exists') and mm.get('size', 0) > 0:
                                app.logger.info(f"download_raw: meta became ready (size={mm.get('size')})")
                                ready = True
                                break
                    except Exception:
                        pass
                if not ready:
                    return f'ESP raw file not ready (meta={mresp.text})', 502
        else:
            app.logger.warning(f"download_raw: meta endpoint returned status {mresp.status_code}; continuing to fetch raw file")
    except requests.RequestException as e:
        app.logger.warning(f"download_raw: could not reach meta endpoint: {e}; continuing to fetch raw file")

    # Retry a few times in case there's a race/flush delay on the ESP writing/closing the file
    attempts = 3
    attempt_logs = []
    last_exc = None
    for attempt in range(1, attempts + 1):
        try:
            resp = requests.get(raw_url, timeout=20)
            status = resp.status_code
            if status == 200:
                data = resp.content
                msg = f"attempt {attempt}: fetched {len(data)} bytes (200)"
                app.logger.info(f"download_raw: {msg} from {raw_url}")
                attempt_logs.append(msg)
                if len(data) == 0:
                    # empty result; wait and retry
                    if attempt < attempts:
                        time.sleep(0.5)
                        continue
                    else:
                        # return diagnostics so the caller (and ngrok logs) show why it failed
                        body = "\n".join(attempt_logs) + "\nFinal result: ESP returned empty file after retries"
                        return body, 502
                headers = {
                    'Content-Type': 'application/octet-stream',
                    'Content-Disposition': 'attachment; filename="raw_i2s.bin"'
                }
                return Response(data, headers=headers)
            else:
                msg = f"attempt {attempt}: HTTP {status}"
                app.logger.error(f"download_raw: {msg} for {raw_url}")
                attempt_logs.append(msg)
                if attempt < attempts:
                    time.sleep(0.5)
                    continue
                body = "\n".join(attempt_logs) + f"\nFinal result: ESP returned status {status}"
                return body, 502
        except requests.RequestException as e:
            last_exc = e
            msg = f"attempt {attempt}: exception: {type(e).__name__}: {str(e)}"
            app.logger.exception(f'download_raw: {msg}')
            attempt_logs.append(msg)
            if attempt < attempts:
                time.sleep(0.5)
                continue
            # Return the attempt logs and the exception text in the response body for easier diagnosis
            body = "\n".join(attempt_logs) + "\nFinal exception: " + repr(e)
            return body, 500


@app.route('/<filename>')
def download_file(filename):
    # Try primary uploads first
    primary = os.path.join(app.config['UPLOAD_FOLDER'], filename)
    alt = os.path.join(app.config.get('ALT_UPLOAD_FOLDER', ''), filename)
    if os.path.exists(primary):
        return send_file(primary, as_attachment=True)
    elif os.path.exists(alt):
        return send_file(alt, as_attachment=True)
    else:
        return 'File not found', 404


@app.route('/preview')
def preview_file():
    """Preview text-based analysis files (txt, md) using a simple styled template.
    Usage: /preview?file=output.txt
    """
    filename = request.args.get('file')
    if not filename:
        return 'file query parameter required', 400
    # locate the file in primary or alt
    primary = os.path.join(app.config['UPLOAD_FOLDER'], filename)
    alt = os.path.join(app.config.get('ALT_UPLOAD_FOLDER', ''), filename)
    path = None
    if os.path.exists(primary):
        path = primary
    elif os.path.exists(alt):
        path = alt
    else:
        return 'File not found', 404

    # Only preview text-like files
    ext = filename.rsplit('.', 1)[-1].lower() if '.' in filename else ''
    if ext in ('txt', 'md'):
        try:
            with open(path, 'r', encoding='utf-8') as fh:
                content = fh.read()
        except Exception:
            content = 'Could not read file.'
        # Simple conversion: preserve paragraphs
        generated_at = time.strftime('%Y-%m-%d %H:%M:%S')
        return render_template('analysis_preview.html', title=filename, body=content, generated_at=generated_at)

    # Non-previewable types: redirect to download
    return redirect(url_for('download_file', filename=filename))

@app.route('/set_student', methods=['POST'])
def set_student():
    student_id = request.form.get('student_id')
    if student_id and student_id.isdigit():
        try:
            filepath = os.path.join(app.config['UPLOAD_FOLDER'], 'student_id.txt')
            with open(filepath, 'w') as f:
                f.write(student_id)
            
            success_msg = f"Student ID successfully set to {student_id}."
            files = _list_all_upload_files(exclude_audio=True)
            return render_template('upload.html', success_message=success_msg, files=files, get_description=get_description)
        except Exception as e:
            error_msg = f"An error occurred while setting the student ID: {e}"
            files = _list_all_upload_files(exclude_audio=True)
            return render_template('upload.html', error_message=error_msg, files=files, get_description=get_description)
    
    error_msg = "Invalid Student ID provided. Please enter a numeric ID."
    files = _list_all_upload_files(exclude_audio=True)
    return render_template('upload.html', error_message=error_msg, files=files, get_description=get_description)


# ============================================================================
# WiFi Management Routes
# ============================================================================

WIFI_PROFILES_FILE = 'wifi_profiles.json'

def load_wifi_profiles():
    """Load WiFi profiles from JSON file"""
    if os.path.exists(WIFI_PROFILES_FILE):
        try:
            with open(WIFI_PROFILES_FILE, 'r') as f:
                return json.load(f)
        except:
            return []
    return []


# ===== BACKUP SYSTEM =====
BACKUP_DIR = os.path.join(os.getcwd(), 'backups')
BACKUP_CONFIG_FILE = os.path.join(BACKUP_DIR, 'config.json')

def _ensure_backup_dir():
    try:
        os.makedirs(BACKUP_DIR, exist_ok=True)
    except Exception:
        pass

def load_backup_config():
    _ensure_backup_dir()
    defaults = {
        'enabled': False,
        'interval': 'weekly',
        'last_run': None,
        'keep_last': 10,
        'max_age_days': None,
        # which MySQL tables (best-effort) to export into backups as JSON
        'export_mysql_tables': ['users', 'questions', 'teachers', 'student_quiz_questions', 'pending_teachers'],
        # mysql_backup_mode: 'json' (data-only JSON exports) or 'sql' (use mysqldump to create SQL dumps)
        'mysql_backup_mode': 'json'
    }
    if os.path.exists(BACKUP_CONFIG_FILE):
        try:
            with open(BACKUP_CONFIG_FILE, 'r', encoding='utf-8') as fh:
                cfg = json.load(fh)
                # merge with defaults
                for k, v in defaults.items():
                    if k not in cfg:
                        cfg[k] = v
                return cfg
        except Exception:
            return defaults
    return defaults

def save_backup_config(cfg):
    _ensure_backup_dir()
    with open(BACKUP_CONFIG_FILE, 'w', encoding='utf-8') as fh:
        json.dump(cfg, fh, indent=2)

def _list_backups():
    _ensure_backup_dir()
    items = []
    for fn in sorted(os.listdir(BACKUP_DIR), reverse=True):
        if fn.endswith('.zip'):
            path = os.path.join(BACKUP_DIR, fn)
            try:
                stat = os.stat(path)
                # human readable mtime
                mtime_human = datetime.datetime.fromtimestamp(stat.st_mtime).strftime('%Y-%m-%d %H:%M:%S')
                item = {'name': fn, 'path': path, 'size': stat.st_size, 'mtime': stat.st_mtime, 'mtime_human': mtime_human}
                # try to load sidecar meta (fast) or fallback to reading inside the zip
                try:
                    sidecar = path + '.meta.json'
                    meta = None
                    if os.path.exists(sidecar):
                        try:
                            with open(sidecar, 'r', encoding='utf-8') as fh:
                                meta = json.load(fh)
                        except Exception:
                            meta = None
                    else:
                        # attempt to read db_dumps/backup-meta.json inside the zip
                        try:
                            with zipfile.ZipFile(path, 'r') as zf:
                                try:
                                    with zf.open('db_dumps/backup-meta.json') as mfh:
                                        txt = mfh.read().decode('utf-8', errors='ignore')
                                        meta = json.loads(txt)
                                except KeyError:
                                    meta = None
                                except Exception:
                                    meta = None
                        except Exception:
                            meta = None

                    if meta is not None:
                        item['meta'] = meta
                        # derive simple status for quick UI badges
                        try:
                            mysql_info = meta.get('mysql', {}) if isinstance(meta, dict) else {}
                            errors = mysql_info.get('errors', []) if isinstance(mysql_info, dict) else []
                            exported = mysql_info.get('exported_tables', []) if isinstance(mysql_info, dict) else []
                            if errors:
                                item['status'] = 'partial'
                            elif exported:
                                item['status'] = 'ok'
                            else:
                                item['status'] = 'no_mysql'
                        except Exception:
                            item['status'] = 'unknown'
                    else:
                        item['meta'] = None
                        item['status'] = 'no_meta'
                except Exception:
                    item['meta'] = None
                    item['status'] = 'no_meta'

                items.append(item)
            except Exception:
                items.append({'name': fn, 'path': path})
    return items


def _list_pre_restore():
    """List pre-restore snapshots under BACKUP_DIR/pre_restore if present."""
    pre_dir = os.path.join(BACKUP_DIR, 'pre_restore')
    results = []
    try:
        if not os.path.isdir(pre_dir):
            return results
        for fn in sorted(os.listdir(pre_dir), reverse=True):
            if not fn.endswith('.zip'):
                continue
            path = os.path.join(pre_dir, fn)
            try:
                stat = os.stat(path)
                mtime_human = datetime.datetime.fromtimestamp(stat.st_mtime).strftime('%Y-%m-%d %H:%M:%S')
                item = {'name': fn, 'path': path, 'size': stat.st_size, 'mtime': stat.st_mtime, 'mtime_human': mtime_human}
                # try to load sidecar meta or fallback to reading inside zip
                meta = None
                try:
                    sidecar = path + '.meta.json'
                    if os.path.exists(sidecar):
                        with open(sidecar, 'r', encoding='utf-8') as fh:
                            meta = json.load(fh)
                    else:
                        try:
                            with zipfile.ZipFile(path, 'r') as zf:
                                try:
                                    with zf.open('db_dumps/backup-meta.json') as mfh:
                                        txt = mfh.read().decode('utf-8', errors='ignore')
                                        meta = json.loads(txt)
                                except Exception:
                                    meta = None
                        except Exception:
                            meta = None
                except Exception:
                    meta = None

                if meta is not None:
                    item['meta'] = meta
                    try:
                        mysql_info = meta.get('mysql', {}) if isinstance(meta, dict) else {}
                        errors = mysql_info.get('errors', []) if isinstance(mysql_info, dict) else []
                        exported = mysql_info.get('exported_tables', []) if isinstance(mysql_info, dict) else []
                        if errors:
                            item['status'] = 'partial'
                        elif exported:
                            item['status'] = 'ok'
                        else:
                            item['status'] = 'no_mysql'
                    except Exception:
                        item['status'] = 'unknown'
                else:
                    item['meta'] = None
                    item['status'] = 'no_meta'

                results.append(item)
            except Exception:
                results.append({'name': fn, 'path': path})
    except Exception:
        pass
    return results

def create_backup(include_paths=None):
    """Create a zip backup of selected files/dirs. Returns created filename."""
    _ensure_backup_dir()
    ts = datetime.datetime.now().strftime('%Y%m%d-%H%M%S')
    name = f'backup-{ts}.zip'
    dest = os.path.join(BACKUP_DIR, name)
    # Default include set
    if include_paths is None:
        include_paths = [
            'conversation_history.json',
            'conversation_history.txt',
            'transcribed_text.txt',
            'templates',
            'static',
            'GENTA_Flask.py'
        ]
    try:
        # build list of files to include
        files_to_add = []
        # prefer configured upload folders
        defaults = []
        up = app.config.get('UPLOAD_FOLDER')
        alt = app.config.get('ALT_UPLOAD_FOLDER')
        if up:
            defaults.append(up)
        if alt:
            defaults.append(alt)
        # add configured defaults (these can be paths or names relative to cwd)
        defaults += include_paths if include_paths is not None else []

        # also include templates and static if present
        defaults += ['templates', 'static', 'GENTA_Flask.py', 'conversation_history.json', 'conversation_history.txt', 'transcribed_text.txt']

        seen = set()
        for p in defaults:
            # allow absolute paths or relative
            p_abs = p if os.path.isabs(p) else os.path.join(os.getcwd(), p)
            p_abs = os.path.abspath(p_abs)
            if p_abs in seen:
                continue
            seen.add(p_abs)
            if os.path.exists(p_abs):
                if os.path.isdir(p_abs):
                    for root, dirs, files in os.walk(p_abs):
                        # skip backups directory if accidentally inside
                        if os.path.abspath(root).startswith(os.path.abspath(BACKUP_DIR)):
                            continue
                        for f in files:
                            full = os.path.join(root, f)
                            # skip pyc and venv artifacts
                            if any(part in full for part in ['.venv', '__pycache__', 'backups', 'node_modules']):
                                continue
                            arcname = os.path.relpath(full, os.getcwd())
                            files_to_add.append((full, arcname))
                else:
                    # single file
                    arcname = os.path.relpath(p_abs, os.getcwd())
                    files_to_add.append((p_abs, arcname))

        if not files_to_add:
            # nothing to add — don't create empty zip
            raise RuntimeError('No files found to include in backup')

        with zipfile.ZipFile(dest, 'w', compression=zipfile.ZIP_DEFLATED) as zf:
            for full, arcname in files_to_add:
                try:
                    zf.write(full, arcname)
                except Exception:
                    app.logger.debug(f'Failed to add {full} to backup')

            # Add database dumps (SQLite files and optionally MySQL tables)
            try:
                # Prepare metadata container for this backup. NOTE: sqlite DB dumps are
                # intentionally omitted (teacher approval DB is not backed up per admin request).
                backup_meta = {'created_at': time.time(), 'mysql': {'exported_tables': [], 'errors': []}}

                def dump_databases_to_zip(zfhandle):
                    # MySQL: attempt best-effort export of a small set of tables as JSON
                    try:
                        import mysql.connector as mysql_connector
                        # discover credentials from env or fallbacks used elsewhere in repo
                        db_host = os.environ.get('MYSQL_HOST') or os.environ.get('DB_HOST') or 'localhost'
                        db_port = int(os.environ.get('MYSQL_PORT') or os.environ.get('DB_PORT') or '3306')
                        db_user = os.environ.get('MYSQL_USER') or os.environ.get('DB_USER') or ''
                        db_pass = os.environ.get('MYSQL_PASS') or os.environ.get('DB_PASS') or os.environ.get('MYSQL_PASSWORD') or ''
                        db_name = os.environ.get('MYSQL_DB') or os.environ.get('DB_NAME') or ''

                        # record connection info in meta for debugging
                        try:
                            backup_meta['mysql']['host'] = db_host
                            backup_meta['mysql']['port'] = db_port
                            backup_meta['mysql']['db'] = db_name
                        except Exception:
                            pass

                        conn = None
                        try:
                            conn = mysql_connector.connect(host=db_host, port=db_port, user=db_user, password=db_pass, database=db_name, connection_timeout=5)
                        except Exception as e:
                            # record connection failure
                            try:
                                backup_meta['mysql']['errors'].append(f'mysql_connect_failed:{str(e)}')
                                backup_meta['mysql']['connected'] = False
                            except Exception:
                                pass
                            conn = None

                        if conn:
                            try:
                                backup_meta['mysql']['connected'] = True
                                # candidate tables to export (only export if present)
                                cfg = load_backup_config()
                                candidate_tables = cfg.get('export_mysql_tables') or ['users', 'questions', 'teachers', 'student_quiz_questions', 'pending_teachers']
                                exported = []
                                for tbl in candidate_tables:
                                    cur = None
                                    try:
                                        # Use a per-table buffered cursor so we can safely fetch results
                                        # and avoid `Unread result found` when issuing multiple queries.
                                        cur = conn.cursor(buffered=True)
                                        # Attempt to select all rows from the table (will raise if table missing)
                                        cur.execute(f"SELECT * FROM `{tbl}`")
                                        cols = [d[0] for d in cur.description] if cur.description else []
                                        rows = cur.fetchall()
                                        out = []
                                        for r in rows:
                                            obj = {}
                                            for i, c in enumerate(cols):
                                                try:
                                                    val = r[i]
                                                except Exception:
                                                    val = None
                                                # ensure JSON serializable
                                                if isinstance(val, (bytes, bytearray)):
                                                    try:
                                                        val = val.decode('utf-8', errors='ignore')
                                                    except Exception:
                                                        val = str(val)
                                                obj[c] = val
                                            out.append(obj)
                                        arcname = os.path.join('db_dumps', f'mysql-{db_name}-{tbl}.json')
                                        zfhandle.writestr(arcname, json.dumps(out, indent=2, default=str))
                                        exported.append(tbl)
                                    except Exception as e:
                                        # table missing or read error; record and continue with error detail
                                        try:
                                            backup_meta['mysql']['errors'].append(f'failed_export:{tbl}:{str(e)}')
                                        except Exception:
                                            pass
                                        continue
                                    finally:
                                        try:
                                            if cur is not None:
                                                cur.close()
                                        except Exception:
                                            pass
                                # write per-db meta for MySQL exports
                                try:
                                    meta = {'db': db_name, 'host': db_host, 'exported_tables': exported, 'exported_at': time.time()}
                                    zfhandle.writestr(os.path.join('db_dumps', f'mysql-{db_name}-meta.json'), json.dumps(meta, indent=2))
                                    backup_meta['mysql']['exported_tables'].extend(exported)
                                except Exception:
                                    pass
                            finally:
                                try:
                                    cur.close()
                                except Exception:
                                    pass
                                try:
                                    conn.close()
                                except Exception:
                                    pass
                    except Exception as e:
                        # mysql connector not available or failed — record error detail
                        try:
                            backup_meta['mysql']['errors'].append(f'mysql_connector_unavailable:{str(e)}')
                        except Exception:
                            pass

                # perform dumps into the zip
                try:
                    dump_databases_to_zip(zf)
                except Exception:
                    app.logger.debug('Database dump step failed during backup but continuing')

                # write backup metadata into the zip as well as a sidecar file for quick access
                try:
                    zf.writestr(os.path.join('db_dumps', 'backup-meta.json'), json.dumps(backup_meta, indent=2, default=str))
                except Exception:
                    app.logger.debug('Failed to write backup meta into zip')

                try:
                    sidecar = dest + '.meta.json'
                    with open(sidecar, 'w', encoding='utf-8') as fh:
                        json.dump(backup_meta, fh, indent=2, default=str)
                except Exception:
                    app.logger.debug('Failed to write sidecar backup meta file')
            except Exception:
                app.logger.debug('Database dump step failed during backup but continuing')

        # Update config last_run
        cfg = load_backup_config()
        cfg['last_run'] = time.time()
        save_backup_config(cfg)
        # enforce retention
        try:
            enforce_retention()
        except Exception:
            pass
        return name
    except Exception:
        traceback.print_exc()
        raise


def create_pre_restore_backup():
    """Create a pre-restore backup and move it into backups/pre_restore/ for easy identification.
    Returns the final path of the pre-restore zip (or None on failure).
    """
    try:
        name = create_backup()
        src = os.path.join(BACKUP_DIR, name)
        sidecar = src + '.meta.json'
        pre_dir = os.path.join(BACKUP_DIR, 'pre_restore')
        os.makedirs(pre_dir, exist_ok=True)
        dst = os.path.join(pre_dir, name)
        dst_sidecar = dst + '.meta.json'
        try:
            # Move zip and sidecar into pre_restore
            if os.path.exists(src):
                shutil.move(src, dst)
            if os.path.exists(sidecar):
                shutil.move(sidecar, dst_sidecar)
        except Exception:
            # best-effort; if moving fails, leave files in main backups dir
            pass
        return dst if os.path.exists(dst) else src
    except Exception:
        app.logger.exception('Pre-restore backup creation failed')
        return None


@app.route('/admin/backups', methods=['GET'])
@login_required
def admin_list_backups():
    try:
        items = _list_backups()
        pre = _list_pre_restore()
        return jsonify({'backups': items, 'pre_restores': pre}), 200
    except Exception as e:
        return jsonify({'error': str(e)}), 500


# Debug-only endpoint: allow setting the cached OLED status for local testing.
# Enabled only when env var GENTA_ALLOW_DEBUG_OLED is set to '1'.
if os.environ.get('GENTA_ALLOW_DEBUG_OLED', '').strip() == '1':
    @app.route('/admin/debug_set_oled_status', methods=['POST'])
    def debug_set_oled_status():
        global LAST_OLED_STATUS
        try:
            if not request.is_json:
                return jsonify({'error': 'JSON body required'}), 400
            data = request.get_json()
            # Accept a dict with keys like report_progress, completion_played, oled_mode
            if not isinstance(data, dict):
                return jsonify({'error': 'JSON object expected'}), 400
            LAST_OLED_STATUS = data
            app.logger.info(f"[Debug] LAST_OLED_STATUS set to: {data}")
            return jsonify({'status': 'ok', 'set': data}), 200
        except Exception as e:
            app.logger.exception('debug_set_oled_status failed')
            return jsonify({'error': str(e)}), 500


@app.route('/admin/backup', methods=['POST'])
@login_required
def admin_create_backup():
    try:
        # Allow on-the-fly export settings from the Create Backup form.
        # If the form includes export_mysql_tables or mysql_backup_mode, persist them
        # into the backup config so the immediate backup uses the requested options.
        try:
            cfg = load_backup_config()
            # support both comma-separated single field and multiple values
            tables_raw = request.form.get('export_mysql_tables')
            if not tables_raw:
                # maybe check for repeated fields
                tables_list = request.form.getlist('export_mysql_tables')
                if tables_list:
                    tables_raw = ','.join(tables_list)
            if tables_raw is not None:
                tables_raw = tables_raw.strip()
                if tables_raw == '':
                    cfg['export_mysql_tables'] = []
                else:
                    cfg['export_mysql_tables'] = [t.strip() for t in tables_raw.split(',') if t.strip()]

            mode = request.form.get('mysql_backup_mode')
            if mode in ('json', 'sql'):
                cfg['mysql_backup_mode'] = mode

            save_backup_config(cfg)
        except Exception:
            # non-fatal; continue with defaults
            pass

        # If admin requested this backup to be saved as a pre-restore snapshot,
        # create a pre-restore backup (which will move the zip + sidecar into backups/pre_restore/).
        try:
            create_pre = request.form.get('create_pre_restore') in ('1', 'true', 'on')
        except Exception:
            create_pre = False

        if create_pre:
            # create_pre_restore_backup returns the final path (or None on failure)
            created_path = None
            try:
                created_path = create_pre_restore_backup()
            except Exception:
                created_path = None

            if created_path:
                name = os.path.basename(created_path)
            else:
                # fallback to normal backup creation
                name = create_backup()
            flash(f'Pre-restore backup created: {name}', 'success')
        else:
            name = create_backup()
            flash(f'Backup created: {name}', 'success')
        return redirect(url_for('upload_file'))
    except Exception as e:
        flash(f'Backup failed: {e}', 'error')
        return redirect(url_for('upload_file'))


@app.route('/admin/backup/download/<path:name>')
@login_required
def admin_download_backup(name):
    path = os.path.join(BACKUP_DIR, name)
    if os.path.exists(path):
        return send_file(path, as_attachment=True)
    return 'Not found', 404


@app.route('/admin/backup/preview/<path:name>')
@login_required
def admin_preview_backup(name):
    """Return JSON listing of files inside a backup and whether they'd overwrite existing files."""
    path = os.path.join(BACKUP_DIR, name)
    if not os.path.exists(path):
        return jsonify({'error': 'not found'}), 404
    entries = []
    try:
        with zipfile.ZipFile(path, 'r') as zf:
            for info in zf.infolist():
                # skip directories
                if info.is_dir():
                    continue
                arc = info.filename
                # normalize and prevent path traversal
                target = os.path.normpath(os.path.join(os.getcwd(), arc))
                if not target.startswith(os.getcwd()):
                    # skip unsafe
                    continue
                exists = os.path.exists(target)
                entries.append({'path': arc, 'size': info.file_size, 'will_overwrite': exists})
        total = len(entries)
        overwrite_count = sum(1 for e in entries if e['will_overwrite'])
        return jsonify({'files': entries, 'total': total, 'overwrite_count': overwrite_count}), 200
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/admin/backup/restore/<path:name>', methods=['POST'])
@login_required
def admin_restore_backup(name):
    """Restore files from backup. Supports dry-run via form param dry_run=1.
    For safety, this will create a pre-restore backup automatically before applying real changes.
    """
    path = os.path.join(BACKUP_DIR, name)
    if not os.path.exists(path):
        flash('Backup not found', 'error')
        return redirect(url_for('upload_file'))

    dry_run = request.form.get('dry_run') in ('1', 'true', 'on')
    try:
        # Build list of entries to restore
        entries = []
        with zipfile.ZipFile(path, 'r') as zf:
            for info in zf.infolist():
                if info.is_dir():
                    continue
                arc = info.filename
                target = os.path.normpath(os.path.join(os.getcwd(), arc))
                if not target.startswith(os.getcwd()):
                    # skip unsafe
                    continue
                entries.append((arc, target))

        overwrite_count = sum(1 for a, t in entries if os.path.exists(t))
        if dry_run:
            flash(f'Dry-run: {len(entries)} files would be restored; {overwrite_count} would overwrite existing files.', 'info')
            return redirect(url_for('upload_file'))

        # Real restore: create pre-restore backup automatically (moved into pre_restore/)
        pre_restore_path = None
        try:
            pre_restore_path = create_pre_restore_backup()
        except Exception:
            app.logger.warning('Pre-restore backup failed; continuing with restore')

        # Extract safely to temp dir then move files into place to avoid partial writes
        tmpdir = tempfile.mkdtemp(prefix='genta_restore_')
        try:
            with zipfile.ZipFile(path, 'r') as zf:
                # extract selected entries only into tmpdir
                for arc, target in entries:
                    # ensure directories
                    member_path = os.path.normpath(arc)
                    # Guard path
                    if member_path.startswith('..'):
                        continue
                    dest_path = os.path.join(tmpdir, member_path)
                    os.makedirs(os.path.dirname(dest_path), exist_ok=True)
                    with zf.open(arc) as srcf, open(dest_path, 'wb') as outf:
                        shutil.copyfileobj(srcf, outf)

            # Now move files from tmpdir to actual cwd locations (overwriting)
            moved = 0
            for root, dirs, files in os.walk(tmpdir):
                for f in files:
                    src = os.path.join(root, f)
                    rel = os.path.relpath(src, tmpdir)
                    dest = os.path.normpath(os.path.join(os.getcwd(), rel))
                    if not dest.startswith(os.getcwd()):
                        continue
                    os.makedirs(os.path.dirname(dest), exist_ok=True)
                    shutil.move(src, dest)
                    moved += 1

            flash(f'Restore completed: {moved} files restored (pre-restore backup created).', 'success')
            # Auto-delete pre-restore snapshot if restore succeeded
            try:
                if pre_restore_path and os.path.exists(pre_restore_path):
                    os.remove(pre_restore_path)
                # remove sidecar if present
                sc = (pre_restore_path + '.meta.json') if pre_restore_path else None
                if sc and os.path.exists(sc):
                    os.remove(sc)
                    app.logger.info(f'Deleted pre-restore backup {pre_restore_path} after successful restore')
            except Exception:
                app.logger.warning('Failed to delete pre-restore backup after successful restore')
            return redirect(url_for('upload_file'))
        finally:
            try:
                shutil.rmtree(tmpdir)
            except Exception:
                pass

    except Exception as e:
        app.logger.exception('Restore failed')
        flash(f'Restore failed: {e}', 'error')
        return redirect(url_for('upload_file'))


@app.route('/admin/backup/restore_select/<path:name>', methods=['POST'])
@login_required
def admin_restore_selected(name):
    """Restore only selected files from a backup (JSON body { files: [...], dry_run: bool }).
    Returns JSON response suitable for AJAX.
    """
    path = os.path.join(BACKUP_DIR, name)
    if not os.path.exists(path):
        return jsonify({'error': 'not found'}), 404

    data = None
    try:
        if request.is_json:
            data = request.get_json()
        else:
            # support form
            files = request.form.getlist('files')
            data = {'files': files, 'dry_run': request.form.get('dry_run') in ('1', 'true', 'on')}
    except Exception:
        data = {'files': [], 'dry_run': True}

    selected = data.get('files') or []
    dry_run = data.get('dry_run', True)
    # normalize selected
    sel_set = set([s for s in selected if s])

    try:
        entries = []
        with zipfile.ZipFile(path, 'r') as zf:
            for info in zf.infolist():
                if info.is_dir():
                    continue
                arc = info.filename
                if sel_set and arc not in sel_set:
                    continue
                target = os.path.normpath(os.path.join(os.getcwd(), arc))
                if not target.startswith(os.getcwd()):
                    continue
                entries.append((arc, target, info.file_size))

        total = len(entries)
        overwrite = sum(1 for a, t, s in entries if os.path.exists(t))

        if dry_run:
            return jsonify({'status': 'dry-run', 'would_restore': total, 'would_overwrite': overwrite}), 200

        # perform restore for selected entries
        # pre-restore backup (moved into pre_restore/)
        pre_restore_path = None
        try:
            pre_restore_path = create_pre_restore_backup()
        except Exception:
            app.logger.warning('Pre-restore backup failed for selective restore')

        tmpdir = tempfile.mkdtemp(prefix='genta_restore_sel_')
        try:
            with zipfile.ZipFile(path, 'r') as zf:
                for arc, target, fsize in entries:
                    # safe extraction into tmpdir
                    member_path = os.path.normpath(arc)
                    if member_path.startswith('..'):
                        continue
                    dest_path = os.path.join(tmpdir, member_path)
                    os.makedirs(os.path.dirname(dest_path), exist_ok=True)
                    with zf.open(arc) as srcf, open(dest_path, 'wb') as outf:
                        shutil.copyfileobj(srcf, outf)

            restored = 0
            for root, dirs, files in os.walk(tmpdir):
                for f in files:
                    src = os.path.join(root, f)
                    rel = os.path.relpath(src, tmpdir)
                    dest = os.path.normpath(os.path.join(os.getcwd(), rel))
                    if not dest.startswith(os.getcwd()):
                        continue
                    os.makedirs(os.path.dirname(dest), exist_ok=True)
                    shutil.move(src, dest)
                    restored += 1

            # Auto-delete pre-restore snapshot if selective restore succeeded
            try:
                if pre_restore_path and os.path.exists(pre_restore_path):
                    os.remove(pre_restore_path)
                sc = (pre_restore_path + '.meta.json') if pre_restore_path else None
                if sc and os.path.exists(sc):
                    os.remove(sc)
                app.logger.info(f'Deleted pre-restore backup {pre_restore_path} after successful selective restore')
            except Exception:
                app.logger.warning('Failed to delete pre-restore backup after selective restore')
            return jsonify({'status': 'ok', 'restored': restored}), 200
        finally:
            try:
                shutil.rmtree(tmpdir)
            except Exception:
                pass

    except Exception as e:
        app.logger.exception('Selective restore failed')
        return jsonify({'error': str(e)}), 500


@app.route('/admin/backup/mysql_tables', methods=['GET'])
@login_required
def admin_mysql_tables():
    """Return a JSON list of available MySQL tables (best-effort).
    This is used by the admin UI to show checkboxes for export selection.
    """
    try:
        try:
            import mysql.connector as mysql_connector
        except Exception:
            return jsonify({'error': 'mysql connector not available'}), 500

        db_host = os.environ.get('MYSQL_HOST') or os.environ.get('DB_HOST') or 'localhost'
        db_port = int(os.environ.get('MYSQL_PORT') or os.environ.get('DB_PORT') or '3306')
        db_user = os.environ.get('MYSQL_USER') or os.environ.get('DB_USER') or 'root'
        db_pass = os.environ.get('MYSQL_PASS') or os.environ.get('DB_PASS') or os.environ.get('MYSQL_PASSWORD') or ''
        db_name = os.environ.get('MYSQL_DB') or os.environ.get('DB_NAME') or 'my_app'

        try:
            conn = mysql_connector.connect(host=db_host, port=db_port, user=db_user, password=db_pass, database=db_name, connection_timeout=5)
        except Exception as e:
            return jsonify({'error': f'Could not connect to MySQL: {e}'}), 500

        try:
            cur = conn.cursor()
            cur.execute('SHOW TABLES')
            rows = cur.fetchall()
            tables = [r[0] for r in rows]
            return jsonify({'tables': tables, 'db': db_name}), 200
        finally:
            try:
                cur.close()
            except Exception:
                pass
            try:
                conn.close()
            except Exception:
                pass
    except Exception as e:
        app.logger.exception('mysql table discovery failed')
        return jsonify({'error': str(e)}), 500


@app.route('/api/mysql_users', methods=['GET'])
def api_mysql_users():
    """Return rows from the `users` table in the configured MySQL database (best-effort).
    Returns JSON: { columns: [...], rows: [ {...}, ... ] }
    """
    try:
        try:
            import mysql.connector as mysql_connector
        except Exception:
            return jsonify({'error': 'mysql connector not available on this host'}), 500

        db_host = os.environ.get('MYSQL_HOST') or os.environ.get('DB_HOST') or 'localhost'
        db_port = int(os.environ.get('MYSQL_PORT') or os.environ.get('DB_PORT') or '3306')
        db_user = os.environ.get('MYSQL_USER') or os.environ.get('DB_USER') or ''
        db_pass = os.environ.get('MYSQL_PASS') or os.environ.get('DB_PASS') or os.environ.get('MYSQL_PASSWORD') or ''
        db_name = os.environ.get('MYSQL_DB') or os.environ.get('DB_NAME') or ''

        try:
            conn = mysql_connector.connect(host=db_host, port=db_port, user=db_user, password=db_pass, database=db_name, connection_timeout=5)
        except Exception as e:
            return jsonify({'error': f'Could not connect to MySQL: {e}'}), 500

        try:
            cur = conn.cursor()
            # Fetch a reasonable number of rows to avoid huge responses
            # Only show users with status=1 (approved/active accounts)
            cur.execute('SELECT * FROM users WHERE status = 1 LIMIT 500')
            rows = cur.fetchall()
            cols = [d[0] for d in cur.description] if cur.description else []
            out_rows = []
            for r in rows:
                obj = {}
                for i, c in enumerate(cols):
                    try:
                        val = r[i]
                    except Exception:
                        val = None
                    # Convert bytes to str if necessary
                    if isinstance(val, (bytes, bytearray)):
                        try:
                            val = val.decode('utf-8', errors='ignore')
                        except Exception:
                            val = str(val)
                    obj[c] = val
                out_rows.append(obj)
            return jsonify({'columns': cols, 'rows': out_rows, 'count': len(out_rows), 'db': db_name}), 200
        except Exception as e:
            app.logger.exception('Failed to query users table')
            return jsonify({'error': str(e)}), 500
        finally:
            try:
                cur.close()
            except Exception:
                pass
            try:
                conn.close()
            except Exception:
                pass
    except Exception as e:
        app.logger.exception('api_mysql_users unexpected error')
        return jsonify({'error': str(e)}), 500


@app.route('/api/mysql_users/delete/<int:user_id>', methods=['DELETE', 'POST'])
def api_delete_user(user_id):
    """Delete a user account from the users table.
    Accepts DELETE or POST method for flexibility.
    Returns JSON with success status.
    """
    try:
        try:
            import mysql.connector as mysql_connector
        except Exception:
            return jsonify({'error': 'mysql connector not available'}), 500

        db_host = os.environ.get('MYSQL_HOST') or os.environ.get('DB_HOST') or 'localhost'
        db_port = int(os.environ.get('MYSQL_PORT') or os.environ.get('DB_PORT') or '3306')
        db_user = os.environ.get('MYSQL_USER') or os.environ.get('DB_USER') or ''
        db_pass = os.environ.get('MYSQL_PASS') or os.environ.get('DB_PASS') or os.environ.get('MYSQL_PASSWORD') or ''
        db_name = os.environ.get('MYSQL_DB') or os.environ.get('DB_NAME') or ''

        try:
            conn = mysql_connector.connect(host=db_host, port=db_port, user=db_user, password=db_pass, database=db_name, connection_timeout=5)
        except Exception as e:
            return jsonify({'error': f'Could not connect to MySQL: {e}'}), 500

        try:
            cur = conn.cursor()
            # First check if user exists
            cur.execute('SELECT id, email FROM users WHERE id = %s', (user_id,))
            user = cur.fetchone()
            
            if not user:
                return jsonify({'error': 'User not found'}), 404
            
            user_email = user[1] if len(user) > 1 else 'unknown'
            
            # Delete the user
            cur.execute('DELETE FROM users WHERE id = %s', (user_id,))
            conn.commit()
            
            app.logger.info(f'Admin deleted user {user_id} ({user_email})')
            return jsonify({'success': True, 'message': f'User {user_email} deleted successfully'}), 200
            
        except Exception as e:
            conn.rollback()
            app.logger.exception(f'Failed to delete user {user_id}')
            return jsonify({'error': str(e)}), 500
        finally:
            try:
                cur.close()
            except Exception:
                pass
            try:
                conn.close()
            except Exception:
                pass
    except Exception as e:
        app.logger.exception('api_delete_user unexpected error')
        return jsonify({'error': str(e)}), 500


@app.route('/admin/backup/restore_db/<path:name>', methods=['POST'])
@login_required
def admin_restore_db(name):
    """Restore database contents from a backup archive.
    Supports dry-run (form param 'dry_run') which will report what would be done.
    SQLite: executes SQL dumps found under db_dumps/sqlite-<filename>.sql and atomically replaces the target .db file.
    MySQL: loads JSON dumps under db_dumps/mysql-<db>-<table>.json and attempts to TRUNCATE/INSERT rows.
    This endpoint is best-effort and will return a summary JSON.
    """
    path = os.path.join(BACKUP_DIR, name)
    if not os.path.exists(path):
        return jsonify({'error': 'not found'}), 404

    dry_run = request.form.get('dry_run') in ('1', 'true', 'on')
    results = {'sqlite': [], 'mysql': [], 'errors': []}

    try:
        with zipfile.ZipFile(path, 'r') as zf:
            # Identify db dump entries
            sqlite_entries = []
            mysql_entries = []
            for info in zf.infolist():
                if info.is_dir():
                    continue
                fn = info.filename
                if fn.startswith('db_dumps/sqlite-') and fn.endswith('.sql'):
                    # arcname format: db_dumps/sqlite-<name>.sql where <name> is original DB filename
                    sqlite_entries.append(fn)
                if fn.startswith('db_dumps/mysql-') and fn.endswith('.json'):
                    # mysql-<db>-<table>.json or mysql-<db>-meta.json
                    mysql_entries.append(fn)

            # Dry-run summary
            if dry_run:
                results['sqlite'] = sqlite_entries
                # Provide a structured summary for MySQL entries: { db, table, rows }
                mysql_summary = []
                for arc in mysql_entries:
                    try:
                        base = os.path.basename(arc)
                        if base.endswith('-meta.json'):
                            continue
                        # parse mysql-<db>-<table>.json
                        parts = base[len('mysql-'):-len('.json')].split('-', 1)
                        if len(parts) == 2:
                            arc_db, tbl = parts
                        else:
                            arc_db = parts[0]
                            tbl = ''
                        # attempt to count rows inside the json dump
                        try:
                            with zf.open(arc) as fh:
                                txt = fh.read().decode('utf-8', errors='ignore')
                                data = json.loads(txt)
                                count = len(data) if isinstance(data, list) else 0
                        except Exception:
                            count = 0
                        mysql_summary.append({'db': arc_db, 'table': tbl, 'rows': count, 'arc': arc})
                    except Exception:
                        # fallback: include arc name only
                        mysql_summary.append({'db': None, 'table': None, 'rows': 0, 'arc': arc})

                results['mysql'] = mysql_summary
                results['message'] = f'Would restore {len(sqlite_entries)} sqlite dumps and {len(mysql_entries)} mysql json exports.'
                return jsonify(results), 200

            # Real restore: create pre-restore backup for safety (moved into pre_restore/)
        pre_restore_path = None
        try:
            pre_restore_path = create_pre_restore_backup()
        except Exception:
            app.logger.warning('Pre-restore backup failed; continuing with DB restore')

        # Re-open the zip and apply restores (SQLite then MySQL)
        with zipfile.ZipFile(path, 'r') as zf:
            # Apply SQLite restores
            for arc in sqlite_entries:
                try:
                    with zf.open(arc) as fh:
                        sql_text = fh.read().decode('utf-8', errors='ignore')
                    # extract original db filename from arc
                    base = os.path.basename(arc)  # sqlite-<name>.sql
                    if base.startswith('sqlite-') and base.endswith('.sql'):
                        db_name = base[len('sqlite-'):-len('.sql')]
                    else:
                        db_name = base
                    # target path in cwd
                    target_db = os.path.abspath(os.path.join(os.getcwd(), db_name))
                    if not target_db.startswith(os.path.abspath(os.getcwd())):
                        results['errors'].append(f'Skipping unsafe target {target_db}')
                        continue

                    # write to a temp file and execute SQL into it
                    fd, tmp_path = tempfile.mkstemp(suffix='.db', prefix='genta_sql_restore_')
                    os.close(fd)
                    try:
                        import sqlite3 as _sqlite
                        conn = _sqlite.connect(tmp_path)
                        try:
                            conn.executescript(sql_text)
                            conn.commit()
                        finally:
                            conn.close()
                        # backup existing db file
                        try:
                            if os.path.exists(target_db):
                                shutil.copy2(target_db, target_db + '.pre_restore.bak')
                        except Exception:
                            pass
                        # replace target with restored temp db
                        os.makedirs(os.path.dirname(target_db), exist_ok=True)
                        shutil.move(tmp_path, target_db)
                        results['sqlite'].append(db_name)
                    finally:
                        try:
                            if os.path.exists(tmp_path):
                                os.remove(tmp_path)
                        except Exception:
                            pass
                except Exception as e:
                    app.logger.exception('Failed to restore sqlite dump')
                    results['errors'].append(f'sqlite {arc}: {e}')

            # Apply MySQL restores
            try:
                try:
                    import mysql.connector as mysql_connector
                except Exception as e:
                    mysql_connector = None
                    results['errors'].append(f'mysql_connector_unavailable:{e}')

                if mysql_connector:
                    db_host = os.environ.get('MYSQL_HOST') or os.environ.get('DB_HOST') or 'localhost'
                    db_port = int(os.environ.get('MYSQL_PORT') or os.environ.get('DB_PORT') or '3306')
                    db_user = os.environ.get('MYSQL_USER') or os.environ.get('DB_USER') or ''
                    db_pass = os.environ.get('MYSQL_PASS') or os.environ.get('DB_PASS') or os.environ.get('MYSQL_PASSWORD') or ''
                    db_name = os.environ.get('MYSQL_DB') or os.environ.get('DB_NAME') or ''
                    try:
                        conn = mysql_connector.connect(host=db_host, port=db_port, user=db_user, password=db_pass, database=db_name, connection_timeout=5)
                    except Exception as e:
                        conn = None
                        results['errors'].append(f'Could not connect to MySQL: {e}')

                    if conn:
                        try:
                            cur = conn.cursor()
                            for arc in mysql_entries:
                                try:
                                    base = os.path.basename(arc)
                                    # skip meta
                                    if base.endswith('-meta.json'):
                                        continue
                                    # parse mysql-<db>-<table>.json
                                    parts = base[len('mysql-'):-len('.json')].split('-', 1)
                                    if len(parts) == 2:
                                        arc_db, tbl = parts
                                    else:
                                        arc_db = db_name
                                        tbl = parts[0]
                                    # only restore if target DB matches configured DB (avoid accidental cross-db)
                                    if arc_db and arc_db != db_name:
                                        # skip different DBs
                                        results['errors'].append(f'Skipping mysql restore for {arc} (different target db: {arc_db})')
                                        continue
                                    with zf.open(arc) as fh:
                                        txt = fh.read().decode('utf-8', errors='ignore')
                                        rows = json.loads(txt)
                                    if not isinstance(rows, list):
                                        continue
                                    if not rows:
                                        # nothing to insert
                                        results['mysql'].append({'table': tbl, 'inserted': 0})
                                        continue
                                    # safer restore: get CREATE statement, rename original to backup, create fresh table, insert rows, then drop backup on success
                                    try:
                                        # Get the CREATE statement for the table
                                        cur.execute(f"SHOW CREATE TABLE `{tbl}`")
                                        create_row = cur.fetchone()
                                        if not create_row or len(create_row) < 2:
                                            results['errors'].append(f'Could not get CREATE TABLE for {tbl}; skipping')
                                            continue
                                        create_sql = create_row[1]
                                    except Exception:
                                        results['errors'].append(f'Table {tbl} does not exist or cannot be queried; skipping')
                                        continue

                                    cols = list(rows[0].keys())
                                    placeholders = ','.join(['%s'] * len(cols))
                                    colnames = ','.join([f'`{c}`' for c in cols])
                                    values = []
                                    for r in rows:
                                        vals = [r.get(c) for c in cols]
                                        values.append(vals)

                                    backup_tbl = f"{tbl}_backup_{int(time.time())}"
                                    tmp_tbl = f"{tbl}_restore_tmp_{int(time.time())}"
                                    try:
                                        # Create the new table under a temporary name by rewriting the CREATE
                                        # statement so constraint names are made unique. This avoids
                                        # errno 121 duplicate-key errors caused by reusing the same
                                        # foreign-key constraint names when the original table still
                                        # exists (we keep it as a backup until the restore succeeds).
                                        import re as _re
                                        ts_suffix = f"_r{int(time.time())}"
                                        # Replace the CREATE TABLE `<tbl>` with CREATE TABLE `<tmp_tbl>` (first occurrence)
                                        create_tmp = _re.sub(r'(?i)^\s*CREATE\s+TABLE\s+`' + _re.escape(tbl) + r'`',
                                                             f'CREATE TABLE `{tmp_tbl}`', create_sql, count=1)
                                        # Rename any CONSTRAINT `name` occurrences to be unique
                                        create_tmp = _re.sub(r'CONSTRAINT\s+`([^`]+)`',
                                                             lambda m: f"CONSTRAINT `{m.group(1)}{ts_suffix}`", create_tmp, flags=_re.IGNORECASE)

                                        # Now create the temporary table
                                        cur.execute(create_tmp)

                                        # Insert rows into the temp table if we have data
                                        if values:
                                            # Build an insert targeted at tmp_tbl
                                            cur.executemany(f'INSERT INTO `{tmp_tbl}` ({colnames}) VALUES ({placeholders})', values)
                                        conn.commit()

                                        # At this point the temp table is ready. Swap with original:
                                        try:
                                            # Rename original to backup
                                            cur.execute(f"RENAME TABLE `{tbl}` TO `{backup_tbl}`")
                                            # Rename temp into original name
                                            cur.execute(f"RENAME TABLE `{tmp_tbl}` TO `{tbl}`")
                                            conn.commit()
                                        except Exception:
                                            # If rename swap fails, attempt to restore original state
                                            conn.rollback()
                                            try:
                                                # If tmp still exists, drop it
                                                cur.execute(f'DROP TABLE IF EXISTS `{tmp_tbl}`')
                                                conn.commit()
                                            except Exception:
                                                pass
                                            results['errors'].append(f'Failed to swap restored table into place for {tbl}')
                                            # Try to leave original as-is (already unchanged) and continue
                                            continue

                                        # Drop backup if everything succeeded (best-effort)
                                        try:
                                            cur.execute(f'DROP TABLE IF EXISTS `{backup_tbl}`')
                                            conn.commit()
                                        except Exception:
                                            # keep backup if drop fails — admin can inspect manually
                                            pass

                                        results['mysql'].append({'table': tbl, 'inserted': len(values)})
                                    except Exception as e:
                                        conn.rollback()
                                        # cleanup temp table if present
                                        try:
                                            cur.execute(f'DROP TABLE IF EXISTS `{tmp_tbl}`')
                                            conn.commit()
                                        except Exception:
                                            pass
                                        results['errors'].append(f'Failed to restore {tbl}: {e}')
                                except Exception as e:
                                    app.logger.exception('mysql restore error')
                                    results['errors'].append(f'{arc}: {e}')
                        finally:
                            try:
                                cur.close()
                            except Exception:
                                pass
                            try:
                                conn.close()
                            except Exception:
                                pass
            except Exception:
                app.logger.exception('MySQL restore step failed')

        # If no errors were recorded, remove the pre-restore snapshot automatically
        try:
            if not results.get('errors') and pre_restore_path:
                if os.path.exists(pre_restore_path):
                    os.remove(pre_restore_path)
                sc = pre_restore_path + '.meta.json'
                if os.path.exists(sc):
                    os.remove(sc)
                app.logger.info(f'Deleted pre-restore backup {pre_restore_path} after successful DB restore')
        except Exception:
            app.logger.warning('Failed to delete pre-restore backup after DB restore')

        return jsonify(results), 200
    except Exception as e:
        app.logger.exception('DB restore failed')
        return jsonify({'error': str(e)}), 500


@app.route('/admin/backup/preview_db/<path:name>')
@login_required
def admin_preview_db(name):
    """Return a JSON preview of what a DB restore would change: row counts per table from
    MySQL JSON exports and estimated row counts from SQLite SQL dumps (counts INSERTs).
    """
    path = os.path.join(BACKUP_DIR, name)
    if not os.path.exists(path):
        return jsonify({'error': 'not found'}), 404

    preview = {'mysql': {}, 'sqlite': {}, 'total': {'mysql_tables': 0, 'sqlite_tables': 0}}
    try:
        with zipfile.ZipFile(path, 'r') as zf:
            for info in zf.infolist():
                if info.is_dir():
                    continue
                fn = info.filename
                # MySQL JSON exports: db_dumps/mysql-<db>-<table>.json
                if fn.startswith('db_dumps/mysql-') and fn.endswith('.json'):
                    base = os.path.basename(fn)
                    if base.endswith('-meta.json'):
                        continue
                    try:
                        parts = base[len('mysql-'):-len('.json')].split('-', 1)
                        if len(parts) == 2:
                            arc_db, tbl = parts
                        else:
                            arc_db = parts[0]
                            tbl = ''
                        with zf.open(fn) as fh:
                            txt = fh.read().decode('utf-8', errors='ignore')
                            try:
                                rows = json.loads(txt)
                                count = len(rows) if isinstance(rows, list) else 0
                            except Exception:
                                count = 0
                        preview['mysql'].setdefault(arc_db, {})
                        preview['mysql'][arc_db][tbl] = count
                        preview['total']['mysql_tables'] += 1
                    except Exception:
                        continue

                # SQLite SQL dumps: db_dumps/sqlite-<name>.sql
                if fn.startswith('db_dumps/sqlite-') and fn.endswith('.sql'):
                    base = os.path.basename(fn)
                    try:
                        db_filename = base[len('sqlite-'):-len('.sql')]
                        with zf.open(fn) as fh:
                            txt = fh.read().decode('utf-8', errors='ignore')
                        # Find INSERT INTO occurrences and attribute to table
                        # regex supports unquoted, double-quoted, single-quoted, or backtick-quoted names
                        pattern = re.compile(r"INSERT\s+INTO\s+(?:`(?P<t1>[^`]+)`|\"(?P<t2>[^\"]+)\"|'(?P<t3>[^']+)'|(?P<t4>[A-Za-z0-9_]+))", re.IGNORECASE)
                        counts = {}
                        for m in pattern.finditer(txt):
                            tbl = m.group('t1') or m.group('t2') or m.group('t3') or m.group('t4')
                            if not tbl:
                                continue
                            counts[tbl] = counts.get(tbl, 0) + 1
                        preview['sqlite'][db_filename] = counts
                        preview['total']['sqlite_tables'] += len(counts)
                    except Exception:
                        continue

        return jsonify(preview), 200
    except Exception as e:
        app.logger.exception('Preview DB failed')
        return jsonify({'error': str(e)}), 500


def enforce_retention():
    """Enforce retention policy from config: keep_last and max_age_days."""
    cfg = load_backup_config()
    keep = int(cfg.get('keep_last') or 0)
    max_age = cfg.get('max_age_days')
    files = []
    for fn in sorted(os.listdir(BACKUP_DIR)):
        if not fn.endswith('.zip'):
            continue
        path = os.path.join(BACKUP_DIR, fn)
        try:
            stat = os.stat(path)
            files.append((path, stat.st_mtime))
        except Exception:
            pass
    # sort by mtime desc (newest first)
    files.sort(key=lambda x: x[1], reverse=True)

    to_delete = []
    if keep > 0 and len(files) > keep:
        for p, _ in files[keep:]:
            to_delete.append(p)

    if max_age:
        try:
            max_age = float(max_age)
            cutoff = time.time() - (max_age * 24 * 3600)
            for p, mtime in files:
                if mtime < cutoff and p not in to_delete:
                    to_delete.append(p)
        except Exception:
            pass

    for p in set(to_delete):
        try:
            os.remove(p)
            app.logger.info(f'Retention deleted backup {p}')
        except Exception:
            app.logger.warning(f'Failed to delete {p} during retention enforcement')


@app.route('/admin/backup/delete/<path:name>', methods=['POST'])
@login_required
def admin_delete_backup(name):
    path = os.path.join(BACKUP_DIR, name)
    try:
        if os.path.exists(path):
            os.remove(path)
            flash(f'Deleted backup {name}', 'success')
        else:
            flash('Backup not found', 'error')
    except Exception as e:
        flash(f'Failed to delete: {e}', 'error')
    return redirect(url_for('upload_file'))


@app.route('/admin/backup/schedule', methods=['POST'])
@login_required
def admin_update_schedule():
    enabled = request.form.get('enabled') == 'on'
    interval = request.form.get('interval', 'weekly')
    keep_last = request.form.get('keep_last')
    max_age_days = request.form.get('max_age_days')
    cfg = load_backup_config()
    cfg['enabled'] = enabled
    cfg['interval'] = interval
    try:
        cfg['keep_last'] = int(keep_last) if keep_last not in (None, '') else cfg.get('keep_last', 10)
    except Exception:
        cfg['keep_last'] = cfg.get('keep_last', 10)
    try:
        cfg['max_age_days'] = float(max_age_days) if max_age_days not in (None, '') else cfg.get('max_age_days')
    except Exception:
        cfg['max_age_days'] = cfg.get('max_age_days')
    # allow admin to update the MySQL export whitelist (comma-separated list)
    try:
        tables_raw = request.form.get('export_mysql_tables', '')
        if tables_raw is not None:
            tables_raw = tables_raw.strip()
            if tables_raw != '':
                # parse comma-separated list and strip entries
                tables = [t.strip() for t in tables_raw.split(',') if t.strip()]
                if tables:
                    cfg['export_mysql_tables'] = tables
    except Exception:
        pass
    # mysql backup mode: 'json' or 'sql'
    try:
        mode = request.form.get('mysql_backup_mode')
        if mode in ('json', 'sql'):
            cfg['mysql_backup_mode'] = mode
    except Exception:
        pass
    save_backup_config(cfg)
    try:
        enforce_retention()
    except Exception:
        pass
    flash('Backup schedule updated', 'success')
    return redirect(url_for('upload_file'))


def _interval_seconds(interval):
    # support: 'daily','weekly','monthly','hourly', or numeric seconds
    if isinstance(interval, (int, float)):
        return int(interval)
    if not isinstance(interval, str):
        return 7 * 24 * 3600
    iv = interval.lower()
    if iv in ('daily', 'day'):
        return 24 * 3600
    if iv in ('hourly', 'hour'):
        return 3600
    if iv in ('weekly', 'week', '7d'):
        return 7 * 24 * 3600
    if iv in ('monthly', 'month'):
        return 30 * 24 * 3600
    try:
        return int(iv)
    except Exception:
        return 7 * 24 * 3600


def backup_scheduler_thread():
    print('[Flask Backup] Scheduler thread started')
    while True:
        try:
            cfg = load_backup_config()
            if cfg.get('enabled'):
                last = cfg.get('last_run') or 0
                iv = _interval_seconds(cfg.get('interval', 'weekly'))
                nowt = time.time()
                if (nowt - last) >= iv:
                    try:
                        name = create_backup()
                        print(f'[Flask Backup] Auto backup created: {name}')
                    except Exception as e:
                        print(f'[Flask Backup] Auto backup failed: {e}')
            # sleep short and check again periodically
        except Exception as e:
            print(f'[Flask Backup] Scheduler error: {e}')
        time.sleep(60)


def save_wifi_profiles(profiles):
    """Save WiFi profiles to JSON file"""
    with open(WIFI_PROFILES_FILE, 'w') as f:
        json.dump(profiles, f, indent=2)


# ------------------------------------------------------------------
# Teacher approval integration (SQLite-backed store + API key guard)
# ------------------------------------------------------------------
import sqlite3
import hashlib
import hmac
import math

PENDING_DB = os.environ.get('PENDING_TEACHERS_DB', 'pending_teachers.db')
FLASK_API_KEY = os.environ.get('FLASK_API_KEY', '')  # set a strong key in production
FLASK_CALLBACK_SECRET = os.environ.get('CALLBACK_SECRET', '')  # Shared secret to sign callbacks to CakePHP

def _get_db_conn():
    conn = sqlite3.connect(PENDING_DB)
    conn.row_factory = sqlite3.Row
    return conn

def init_pending_db():
    conn = _get_db_conn()
    try:
        cur = conn.cursor()
        cur.execute('''
            CREATE TABLE IF NOT EXISTS pending_teachers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                teacher_id TEXT UNIQUE,
                email TEXT,
                name TEXT,
                callback_url TEXT,
                status TEXT,
                created_at REAL,
                approved_at REAL
            )
        ''')
        conn.commit()
    finally:
        conn.close()

def get_pending_teachers():
    init_pending_db()
    conn = _get_db_conn()
    try:
        cur = conn.cursor()
        cur.execute("SELECT teacher_id, email, name, callback_url, status, created_at, approved_at FROM pending_teachers ORDER BY created_at DESC")
        rows = cur.fetchall()
        return [dict(r) for r in rows]
    finally:
        conn.close()

def add_or_update_pending(teacher_id, email, name, callback_url):
    init_pending_db()
    conn = _get_db_conn()
    try:
        cur = conn.cursor()
        ts = time.time()
        # upsert
        cur.execute('''
            INSERT INTO pending_teachers (teacher_id, email, name, callback_url, status, created_at)
            VALUES (?, ?, ?, ?, 'pending', ?)
            ON CONFLICT(teacher_id) DO UPDATE SET
                email=excluded.email,
                name=excluded.name,
                callback_url=excluded.callback_url,
                status='pending'
        ''', (str(teacher_id), email, name or '', callback_url or '', ts))
        conn.commit()
    finally:
        conn.close()

def set_pending_status(teacher_id, status):
    init_pending_db()
    conn = _get_db_conn()
    try:
        cur = conn.cursor()
        ts = time.time()
        cur.execute('UPDATE pending_teachers SET status = ?, approved_at = ? WHERE teacher_id = ?', (status, ts, str(teacher_id)))
        conn.commit()
        cur.execute('SELECT teacher_id, email, name, callback_url, status, created_at, approved_at FROM pending_teachers WHERE teacher_id = ?', (str(teacher_id),))
        row = cur.fetchone()
        return dict(row) if row else None
    finally:
        conn.close()


def _check_api_key():
    if not FLASK_API_KEY:
        return True  # no key configured (dev mode)
    # Accept header X-API-Key or Authorization: Bearer <key>
    key = request.headers.get('X-API-Key')
    if not key:
        auth = request.headers.get('Authorization', '')
        if auth.startswith('Bearer '):
            key = auth.split(' ', 1)[1].strip()
    return key == FLASK_API_KEY


def sync_pending_teachers_from_mysql():
    """
    Sync pending/unapproved teachers from MySQL users table into local SQLite.
    This allows the admin panel to see registrations even when CakePHP can't POST to Flask.
    Returns number of new pending teachers found.
    """
    try:
        import mysql.connector as mysql_connector
    except Exception:
        return 0

    db_host = os.environ.get('MYSQL_HOST') or os.environ.get('DB_HOST') or 'localhost'
    db_port = int(os.environ.get('MYSQL_PORT') or os.environ.get('DB_PORT') or '3306')
    db_user = os.environ.get('MYSQL_USER') or os.environ.get('DB_USER') or ''
    db_pass = os.environ.get('MYSQL_PASS') or os.environ.get('DB_PASS') or os.environ.get('MYSQL_PASSWORD') or ''
    db_name = os.environ.get('MYSQL_DB') or os.environ.get('DB_NAME') or ''

    try:
        conn = mysql_connector.connect(host=db_host, port=db_port, user=db_user, password=db_pass, database=db_name, connection_timeout=5)
    except Exception as e:
        app.logger.warning(f"Could not connect to MySQL for teacher sync: {e}")
        return 0

    count = 0
    try:
        cur = conn.cursor()
        # Look for users with status=0 (pending approval) AND email_verified=1 from the users table
        # Only show in admin panel if user has verified their email
        # Based on your database structure: status=0 means pending, status=1 means approved
        cur.execute("""
            SELECT id, email, first_name, last_name, created 
            FROM users 
            WHERE status = 0
            AND email_verified = 1
            AND email IS NOT NULL
            LIMIT 100
        """)
        rows = cur.fetchall()
        
        for row in rows:
            user_id = row[0]
            email = row[1]
            firstname = row[2] if len(row) > 2 else ''
            lastname = row[3] if len(row) > 3 else ''
            name = f"{firstname} {lastname}".strip() if (firstname or lastname) else email
            
            # Add to local pending database if not already there
            add_or_update_pending(user_id, email, name, None)
            count += 1
            
        conn.close()
    except Exception as e:
        app.logger.warning(f"Error syncing teachers from MySQL: {e}")
        try:
            conn.close()
        except:
            pass
    
    return count


@app.route('/api/sync_teachers', methods=['POST'])
@login_required
def api_sync_teachers():
    """Manually trigger sync of pending teachers from MySQL database."""
    try:
        count = sync_pending_teachers_from_mysql()
        return jsonify({'success': True, 'count': count}), 200
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/api/pending_teachers', methods=['GET', 'POST'])
@login_required
def api_pending_teachers():
    """GET: list pending teachers. POST: accept new teacher notifications from CakePHP (server-to-server).
    Requires X-API-Key header when FLASK_API_KEY is configured.
    """
    if request.method == 'GET':
        # Auto-sync from MySQL before returning list (best effort - ignore errors)
        try:
            sync_pending_teachers_from_mysql()
        except Exception:
            pass
        
        records = get_pending_teachers()
        pending = [r for r in records if r.get('status') == 'pending']
        return jsonify({'pending': pending, 'count': len(pending)}), 200

    # POST: new registration notification - require API key
    if not _check_api_key():
        return jsonify({'success': False, 'error': 'unauthorized'}), 401

    try:
        payload = request.get_json(force=True)
    except Exception:
        payload = request.form.to_dict() or {}

    teacher_id = payload.get('teacher_id') or payload.get('id')
    email = payload.get('email')
    name = payload.get('name')
    callback_url = payload.get('callback_url') or payload.get('notify_callback')

    if not teacher_id or not email:
        return jsonify({'success': False, 'error': 'teacher_id and email required'}), 400

    add_or_update_pending(teacher_id, email, name, callback_url)
    return jsonify({'success': True, 'message': 'Teacher registration stored for approval'}), 200


@app.route('/api/approve_teacher', methods=['POST'])
@login_required
def api_approve_teacher():
    """Approve or reject a teacher. POST JSON: { 'teacher_id': '...', 'action': 'approve'|'reject' }
    This endpoint is intended to be called from the admin UI.
    Updates both local SQLite and MySQL database.
    """
    try:
        data = request.get_json(force=True)
    except Exception:
        data = request.form.to_dict() or {}

    teacher_id = data.get('teacher_id')
    action = (data.get('action') or 'approve').lower()

    if not teacher_id:
        return jsonify({'success': False, 'error': 'teacher_id required'}), 400

    new_status = 'approved' if action == 'approve' else 'rejected'
    
    # Update local SQLite database
    rec = set_pending_status(teacher_id, new_status)
    if not rec:
        return jsonify({'success': False, 'error': 'teacher not found in local database'}), 404
    
    # Update MySQL database - set status=1 for approved, status=-1 for rejected
    mysql_updated = False
    try:
        import mysql.connector as mysql_connector
        
        db_host = os.environ.get('MYSQL_HOST') or os.environ.get('DB_HOST') or 'localhost'
        db_port = int(os.environ.get('MYSQL_PORT') or os.environ.get('DB_PORT') or '3306')
        db_user = os.environ.get('MYSQL_USER') or os.environ.get('DB_USER') or ''
        db_pass = os.environ.get('MYSQL_PASS') or os.environ.get('DB_PASS') or os.environ.get('MYSQL_PASSWORD') or ''
        db_name = os.environ.get('MYSQL_DB') or os.environ.get('DB_NAME') or ''
        
        conn = mysql_connector.connect(host=db_host, port=db_port, user=db_user, password=db_pass, database=db_name, connection_timeout=5)
        cur = conn.cursor()
        
        # Set status=1 for approved, delete for rejected
        if action == 'approve':
            cur.execute("UPDATE users SET status = 1 WHERE id = %s", (teacher_id,))
        else:
            # Delete rejected user from database
            cur.execute("DELETE FROM users WHERE id = %s", (teacher_id,))
        
        conn.commit()
        mysql_updated = True
        conn.close()
    except Exception as e:
        app.logger.warning(f"Failed to update MySQL database for teacher {teacher_id}: {e}")
    
    # Try callback if available
    callback = rec.get('callback_url')
    if callback and callback.startswith('http'):
        try:
            body_obj = {'teacher_id': rec.get('teacher_id'), 'status': rec.get('status')}
            body_bytes = json.dumps(body_obj).encode('utf-8')
            headers = {'Content-Type': 'application/json'}
            if FLASK_CALLBACK_SECRET:
                try:
                    sig = hmac.new(FLASK_CALLBACK_SECRET.encode('utf-8'), body_bytes, hashlib.sha256).hexdigest()
                    headers['X-Callback-Signature'] = 'sha256=' + sig
                except Exception as _e:
                    app.logger.warning(f"Failed to compute callback signature: {_e}")
            else:
                app.logger.warning('No CALLBACK_SECRET configured in Flask; sending unsigned callback')
            requests.post(callback, data=body_bytes, headers=headers, timeout=8)
        except Exception as e:
            app.logger.warning(f"Failed to call callback {callback}: {e}")
    
    msg = f'{action}d successfully'
    if not mysql_updated:
        msg += ' (local only - MySQL update failed)'
    
    return jsonify({'success': True, 'message': msg, 'mysql_updated': mysql_updated}), 200


def get_registered_teachers():
    """Return a list of registered/approved teachers.
    Return registered users from MySQL `users` table (normalized to common keys).
    Do NOT fall back to the local sqlite pending_teachers DB; return an empty
    list when MySQL is unavailable.
    """
    try:
        try:
            import mysql.connector as mysql_connector
        except Exception:
            mysql_connector = None

        if not mysql_connector:
            return []

        db_host = os.environ.get('MYSQL_HOST') or os.environ.get('DB_HOST') or 'localhost'
        db_port = int(os.environ.get('MYSQL_PORT') or os.environ.get('DB_PORT') or '3306')
        db_user = os.environ.get('MYSQL_USER') or os.environ.get('DB_USER') or ''
        db_pass = os.environ.get('MYSQL_PASS') or os.environ.get('DB_PASS') or os.environ.get('MYSQL_PASSWORD') or ''
        db_name = os.environ.get('MYSQL_DB') or os.environ.get('DB_NAME') or ''

        try:
            conn = mysql_connector.connect(host=db_host, port=db_port, user=db_user, password=db_pass, database=db_name, connection_timeout=5)
        except Exception:
            return []

        try:
            cur = conn.cursor()
            # Read a bounded set of rows from users table and normalize fields
            # Only show users with status=1 (approved/active accounts)
            cur.execute('SELECT * FROM users WHERE status = 1 LIMIT 500')
            rows = cur.fetchall()
            cols = [d[0] for d in cur.description] if cur.description else []
            out = []
            for r in rows:
                obj = {}
                for i, c in enumerate(cols):
                    try:
                        val = r[i]
                    except Exception:
                        val = None
                    # convert bytes
                    if isinstance(val, (bytes, bytearray)):
                        try:
                            val = val.decode('utf-8', errors='ignore')
                        except Exception:
                            val = str(val)
                    obj[c] = val

                # Normalize to preferred keys if possible
                normalized = {
                    'email': obj.get('email') or obj.get('EMAIL') or obj.get('mail') or None,
                    'first_name': obj.get('first_name') or obj.get('firstname') or obj.get('given_name') or obj.get('fname') or None,
                    'last_name': obj.get('last_name') or obj.get('lastname') or obj.get('surname') or obj.get('lname') or None,
                    'created': obj.get('created') or obj.get('created_at') or obj.get('approved_at') or obj.get('createdAt') or None,
                    # keep original row for debug if needed
                    '_raw': obj
                }
                out.append(normalized)
            return out
        except Exception:
            return []
        finally:
            try:
                cur.close()
            except Exception:
                pass
            try:
                conn.close()
            except Exception:
                pass
    except Exception:
        return []


@app.route('/api/registered_teachers', methods=['GET'])
def api_registered_teachers():
    """Return JSON list of registered/approved teachers for admin UI."""
    try:
        recs = get_registered_teachers()
        return jsonify({'registered': recs, 'count': len(recs)}), 200
    except Exception as e:
        app.logger.exception('failed to list registered teachers')
        return jsonify({'error': str(e)}), 500


@app.route('/admin/packages/config', methods=['GET', 'POST'])
def admin_packages_config():
    """GET: return JSON { packages: [...] }
       POST: accept JSON { packages: [...] } to update config. Requires API key.
    """
    if request.method == 'GET':
        try:
            pkgs = load_package_config()
            return jsonify({'packages': pkgs}), 200
        except Exception as e:
            return jsonify({'error': str(e)}), 500

    # POST -> update
    # Require API key to change persistent config
    if not _check_api_key():
        return jsonify({'error': 'unauthorized'}), 401
    try:
        try:
            data = request.get_json(force=True)
        except Exception:
            data = None
        if not data:
            # support form body
            data = request.form.to_dict()
        pkgs = None
        if isinstance(data, dict):
            if 'packages' in data and isinstance(data['packages'], list):
                pkgs = data['packages']
            elif 'packages' in data and isinstance(data['packages'], str):
                pkgs = [s.strip() for s in data['packages'].split('\n') if s.strip()]
        if pkgs is None:
            return jsonify({'error': 'packages list required'}), 400
        ok = save_package_config(pkgs)
        if ok:
            return jsonify({'success': True, 'packages': pkgs}), 200
        else:
            return jsonify({'error': 'failed to save'}), 500
    except Exception as e:
        app.logger.exception('failed to save package config')
        return jsonify({'error': str(e)}), 500



# In-memory registry for discovered ESP32 devices (populated by UDP broadcasts)
# Use a lock to make access thread-safe
esp_devices = {}
esp_devices_lock = threading.Lock()
# Sticky map: remember last-known IP per role so short discovery gaps don't force
# immediate fallback to hardcoded ESP_BASE. Protected by its own lock.
LAST_KNOWN_DEVICE_BY_ROLE = {}
last_known_lock = threading.Lock()

# Persistent override file for recorder IP (auto-updated on UDP announcements)
RECORDER_OVERRIDE_FILE = os.environ.get('RECORDER_OVERRIDE_FILE', os.path.join(os.getcwd(), 'recorder_ip_override.txt'))

def _read_recorder_override():
    try:
        if os.path.exists(RECORDER_OVERRIDE_FILE):
            with open(RECORDER_OVERRIDE_FILE, 'r', encoding='utf-8') as fh:
                ip = fh.read().strip()
                if ip:
                    return ip
    except Exception:
        pass
    return None

def _write_recorder_override(ip):
    try:
        with open(RECORDER_OVERRIDE_FILE, 'w', encoding='utf-8') as fh:
            fh.write(str(ip))
        return True
    except Exception:
        return False

def _clear_recorder_override():
    try:
        if os.path.exists(RECORDER_OVERRIDE_FILE):
            os.remove(RECORDER_OVERRIDE_FILE)
        return True
    except Exception:
        return False


# Expiry settings: devices not seen for this many seconds will be pruned
DEVICE_EXPIRY_SECONDS = 300  # 5 minutes
DEVICE_PRUNE_INTERVAL = 60   # run prune every 60 seconds


def _udp_listener(port=5005):
    """Background thread that listens for UDP broadcast announcements from ESP32 devices.
    Expected payload is JSON with at least a `mac` and `role` field. The source IP will be
    recorded as the device's IP address.
    """
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    # Allow reuse of the socket
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    try:
        sock.bind(("", port))
        app.logger.info(f"UDP discovery listener started on port {port}")
    except Exception as e:
        app.logger.error(f"Failed to bind UDP listener to port {port}: {e}")
        return
    while True:
        try:
            data, addr = sock.recvfrom(2048)
            payload = data.decode('utf-8', errors='ignore')
            app.logger.info(f"UDP packet received from {addr[0]}: {payload}")
            try:
                obj = json.loads(payload)
            except Exception as e:
                app.logger.warning(f"Failed to parse JSON from {addr[0]}: {e}")
                obj = {'raw': payload}
            ip = addr[0]
            mac = obj.get('mac') or obj.get('id') or ip
            obj['ip'] = ip
            obj['last_seen'] = time.time()
            # thread-safe write
            try:
                esp_devices_lock.acquire()
                esp_devices[mac] = obj
                app.logger.info(f"Registered device: {obj.get('role', 'unknown')} @ {ip} (MAC: {mac})")
                # If this announcement claims to be a recorder, persist it as the override
                try:
                    if obj.get('role') == 'recorder':
                        _write_recorder_override(ip)
                        # also clear any fail-cache entry so forwarding will try it immediately
                        try:
                            host = ip.replace('http://','').replace('https://','')
                            if host in _oled_fail_cache:
                                del _oled_fail_cache[host]
                        except Exception:
                            pass
                        app.logger.info(f"Recorder override updated to {ip} based on UDP announcement")
                except Exception:
                    pass
                # Update sticky last-known mapping by role so short gaps won't immediately
                # cause the proxy to give up and use fallback addresses.
                try:
                    role = obj.get('role')
                    if role:
                        try:
                            last_known_lock.acquire()
                            LAST_KNOWN_DEVICE_BY_ROLE[role] = ip
                        finally:
                            last_known_lock.release()
                except Exception:
                    pass
            finally:
                esp_devices_lock.release()
        except Exception as e:
            # Ignore malformed packets and continue listening
            app.logger.debug(f"UDP listener error: {e}")
            continue


# Start UDP listener thread (daemon) so it doesn't block Flask shutdown
try:
    t = threading.Thread(target=_udp_listener, args=(5005,), daemon=True)
    t.start()
    print("[Flask] UDP discovery listener started on port 5005")
    print("[Flask] Waiting for ESP32 device announcements...")
except Exception as e:
    print(f"[Flask] WARNING: Failed to start UDP listener: {e}")
    print("[Flask] ESP32 auto-discovery will not work!")
    pass


# -------------------------------
# Package update background runner
# -------------------------------
# In-memory job registry: job_id -> { status: 'pending'|'running'|'done'|'failed', pid: int, log: path, started: ts, finished: ts, returncode: int }
package_update_jobs = {}
package_update_jobs_lock = threading.Lock()
CONFIG_PACKAGES_FILE = os.path.join(os.getcwd(), 'config_packages.json')


def load_package_config():
    """Return a list of packages from config file or a default curated list."""
    default = [
        'genai',
        'google-cloud-storage',
        'google-cloud-texttospeech',
        'google-cloud-speech',
        'google-api-python-client',
        'openai',
        'mysql-connector-python',
        'requests',
        'flask'
    ]
    try:
        if os.path.exists(CONFIG_PACKAGES_FILE):
            with open(CONFIG_PACKAGES_FILE, 'r', encoding='utf-8') as fh:
                data = json.load(fh)
                if isinstance(data, dict):
                    pkgs = data.get('packages') or data.get('pkg') or data.get('list') or []
                elif isinstance(data, list):
                    pkgs = data
                else:
                    pkgs = []
                # normalize to list of strings
                out = [str(x).strip() for x in pkgs if x]
                return out if out else default
    except Exception:
        pass
    return default


def save_package_config(pkgs):
    try:
        data = {'packages': list(pkgs)}
        with open(CONFIG_PACKAGES_FILE, 'w', encoding='utf-8') as fh:
            json.dump(data, fh, indent=2)
        return True
    except Exception:
        return False


def _is_local_request():
    try:
        addr = request.remote_addr or ''
        if addr in ('127.0.0.1', '::1'):
            return True
        # behind proxies may set X-Forwarded-For
        xff = request.headers.get('X-Forwarded-For', '')
        if xff:
            first = xff.split(',')[0].strip()
            if first in ('127.0.0.1', '::1'):
                return True
    except Exception:
        pass
    return False

def _tail_file(path, lines=200):
    try:
        with open(path, 'rb') as fh:
            fh.seek(0, os.SEEK_END)
            size = fh.tell()
            block = 1024
            data = b''
            while size > 0 and data.count(b'\n') <= lines:
                seek = max(0, size - block)
                fh.seek(seek)
                data = fh.read(min(block, size)) + data
                size = seek
            text = data.decode('utf-8', errors='replace')
            parts = text.splitlines()
            return '\n'.join(parts[-lines:])
    except Exception:
        return ''

def _package_update_worker(job_id, use_pip_path=None):
    entry = None
    try:
        with package_update_jobs_lock:
            entry = package_update_jobs.get(job_id)
            if not entry:
                return
            entry['status'] = 'running'
            entry['started'] = time.time()

        logpath = entry.get('log')

        # Load curated package list from config (or default list if not present)
        pkgs = load_package_config()
        if not pkgs:
            # nothing to do
            with open(logpath, 'a', encoding='utf-8', errors='replace') as lf:
                lf.write('No packages configured for update. Exiting.\n')
            entry['status'] = 'done'
            entry['finished'] = time.time()
            entry['returncode'] = 0
            return

        if use_pip_path:
            cmd = [use_pip_path, 'install', '--upgrade'] + pkgs
        else:
            cmd = [sys.executable, '-m', 'pip', 'install', '--upgrade'] + pkgs

        with open(logpath, 'a', encoding='utf-8', errors='replace') as lf:
            lf.write(f"=== Package update started: {time.strftime('%Y-%m-%d %H:%M:%S')} (job {job_id}) ===\n")
            lf.flush()
            proc = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
            entry['pid'] = getattr(proc, 'pid', None)

            # Stream output to log
            while True:
                line = proc.stdout.readline()
                if not line:
                    break
                try:
                    lf.write(line.decode('utf-8', errors='replace'))
                except Exception:
                    try:
                        lf.write(str(line))
                    except Exception:
                        pass
                lf.flush()

            proc.wait()
            entry['returncode'] = proc.returncode
            entry['finished'] = time.time()
            entry['status'] = 'done' if proc.returncode == 0 else 'failed'
            with open(logpath, 'a', encoding='utf-8', errors='replace') as lf2:
                lf2.write(f"=== Finished: returncode={proc.returncode} at {time.strftime('%Y-%m-%d %H:%M:%S')} ===\n")
    except Exception as e:
        try:
            with package_update_jobs_lock:
                if entry:
                    entry['status'] = 'failed'
                    entry['finished'] = time.time()
                    entry['error'] = str(e)
        except Exception:
            pass


@app.route('/admin/update_packages', methods=['POST'])
def admin_update_packages():
    """Start a background job to update packages. Returns job_id.
    The job writes logs to a temp file and runs in background. The caller can
    poll `/admin/update_packages/status?job_id=...` to get progress and logs.
    """
    try:
        # Require localhost OR valid API key to start update for safety
        if not (_is_local_request() or _check_api_key()):
            return jsonify({'error': 'unauthorized'}), 401

        # decide pip executable path inside repo venv if present
        venv_pip = os.path.join(os.getcwd(), 'newenv', 'Scripts', 'pip.exe')
        use_pip = venv_pip if os.path.exists(venv_pip) else None

        job_id = str(uuid.uuid4())
        fd, logpath = tempfile.mkstemp(prefix=f'genta_pkg_update_{job_id}_', suffix='.log', dir=os.getcwd())
        os.close(fd)

        job_entry = {
            'status': 'pending',
            'pid': None,
            'log': logpath,
            'started': None,
            'finished': None,
            'returncode': None
        }
        with package_update_jobs_lock:
            package_update_jobs[job_id] = job_entry

        t = threading.Thread(target=_package_update_worker, args=(job_id, use_pip), daemon=True)
        t.start()

        return jsonify({'job_id': job_id, 'log': f'/admin/update_packages/status?job_id={job_id}'}), 200
    except Exception as e:
        app.logger.exception('Failed to start package update job')
        return jsonify({'error': str(e)}), 500


@app.route('/admin/update_packages/status', methods=['GET'])
def admin_update_packages_status():
    """Return JSON status and recent log tail for a given job_id."""
    job_id = request.args.get('job_id')
    if not job_id:
        return jsonify({'error': 'job_id required'}), 400
    try:
        # allow status checks from localhost or when presenting the API key
        if not (_is_local_request() or _check_api_key()):
            return jsonify({'error': 'unauthorized'}), 401

        with package_update_jobs_lock:
            entry = package_update_jobs.get(job_id)
            if not entry:
                return jsonify({'error': 'job not found'}), 404

        tail = _tail_file(entry.get('log'), lines=400)
        resp = {
            'job_id': job_id,
            'status': entry.get('status'),
            'pid': entry.get('pid'),
            'started': entry.get('started'),
            'finished': entry.get('finished'),
            'returncode': entry.get('returncode'),
            'log_tail': tail
        }
        return jsonify(resp), 200
    except Exception as e:
        app.logger.exception('status check failed')
        return jsonify({'error': str(e)}), 500


def _prune_devices_loop():
    """Background thread that periodically removes stale devices from esp_devices."""
    while True:
        try:
            # If the host has set the report-creation guard, skip pruning to avoid
            # removing discovered devices while a long-running report animation is active.
            try:
                if globals().get('REPORT_CREATION_GUARD'):
                    app.logger.info('[Pruner] REPORT_CREATION_GUARD active - skipping prune iteration')
                    time.sleep(DEVICE_PRUNE_INTERVAL)
                    continue
            except Exception:
                pass
            now = time.time()
            stale = []
            esp_devices_lock.acquire()
            try:
                for mac, info in list(esp_devices.items()):
                    last = info.get('last_seen', 0)
                    if (now - last) > DEVICE_EXPIRY_SECONDS:
                        stale.append(mac)
                for mac in stale:
                    # Log removal with age diagnostic
                    try:
                        info = esp_devices.get(mac, {})
                        last_seen = info.get('last_seen', 0)
                        age = now - (last_seen or 0)
                        app.logger.info(f"Pruner: removing device {mac} (role={info.get('role')} ip={info.get('ip')} age={age:.1f}s)")
                    except Exception:
                        app.logger.info(f"Pruner: removing device {mac} (details unavailable)")
                    esp_devices.pop(mac, None)
                # If registry became empty, log a diagnostic marker so we can correlate with host actions
                try:
                    if not esp_devices:
                        app.logger.info('[Pruner] esp_devices is now EMPTY after pruning')
                except Exception:
                    pass
            finally:
                esp_devices_lock.release()
        except Exception:
            pass
        time.sleep(DEVICE_PRUNE_INTERVAL)


# Start pruner thread
try:
    p = threading.Thread(target=_prune_devices_loop, daemon=True)
    p.start()
except Exception:
    pass


def get_esp32_status(ip):
    """Get WiFi status from ESP32. Returns status dict with 'reachable' flag."""
    try:
        response = requests.get(f'http://{ip}/wifi/status', timeout=3)
        if response.status_code == 200:
            status = response.json()
            status['reachable'] = True  # Device responded
            return status
        else:
            return {'connected': False, 'ssid': 'Unknown', 'ip': 'N/A', 'rssi': 0, 'reachable': False, 'error': f'HTTP {response.status_code}'}
    except requests.Timeout:
        return {'connected': False, 'ssid': 'Unknown', 'ip': 'N/A', 'rssi': 0, 'reachable': False, 'error': 'Timeout'}
    except requests.ConnectionError:
        return {'connected': False, 'ssid': 'Unknown', 'ip': 'N/A', 'rssi': 0, 'reachable': False, 'error': 'Connection refused'}
    except Exception as e:
        return {'connected': False, 'ssid': 'Unknown', 'ip': 'N/A', 'rssi': 0, 'reachable': False, 'error': str(e)}


def find_device_ip(role):
    """Look up a discovered device by role ('recorder' or 'player') and return its IP.
    Falls back to None if not found.
    """
    # 0) If there is a persistent recorder override, prefer it if reachable.
    try:
        override = _read_recorder_override()
        if override:
            try:
                # quick probe to ensure it's still live
                status = requests.get(f'http://{override}/wifi/status', timeout=2)
                if status.status_code == 200:
                    return override
            except Exception:
                # clear stale override and continue discovery
                try:
                    _clear_recorder_override()
                except Exception:
                    pass

    except Exception:
        pass

    try:
        esp_devices_lock.acquire()
        try:
            # 1) Prefer the most recently seen device that matches the role
            best = None
            best_ts = 0
            for dev in esp_devices.values():
                if not isinstance(dev, dict):
                    continue
                if dev.get('role') != role:
                    continue
                ts = dev.get('last_seen', 0) or 0
                if ts > best_ts:
                    best_ts = ts
                    best = dev
            if best and 'ip' in best:
                # persist this recorder IP so the host keeps using it across restarts
                try:
                    if role == 'recorder':
                        _write_recorder_override(best.get('ip'))
                except Exception:
                    pass
                return best.get('ip')

            # 2) Fallback: if no device with the requested role is present,
            #    return the most-recently seen device overall (helps when
            #    the ESP announcement doesn't include a role or discovery
            #    missed the role field).
            overall_best = None
            overall_ts = 0
            for dev in esp_devices.values():
                if not isinstance(dev, dict):
                    continue
                ts = dev.get('last_seen', 0) or 0
                if ts > overall_ts:
                    overall_ts = ts
                    overall_best = dev
            if overall_best and 'ip' in overall_best:
                try:
                    if role == 'recorder':
                        _write_recorder_override(overall_best.get('ip'))
                except Exception:
                    pass
                return overall_best.get('ip')
        finally:
            esp_devices_lock.release()
    except Exception:
        pass
    # 3) As a final effort, consult the sticky last-known mapping for this role
    try:
        last_ip = None
        try:
            last_known_lock.acquire()
            last_ip = LAST_KNOWN_DEVICE_BY_ROLE.get(role)
        finally:
            last_known_lock.release()
        if last_ip:
            try:
                # Quick probe to ensure it's reachable
                status = requests.get(f'http://{last_ip}/wifi/status', timeout=2)
                if getattr(status, 'status_code', None) == 200:
                    try:
                        if role == 'recorder':
                            _write_recorder_override(last_ip)
                    except Exception:
                        pass
                    return last_ip
            except Exception:
                pass
    except Exception:
        pass
    return None

@app.route('/api/discovered_devices', methods=['GET'])
def api_discovered_devices():
    """API endpoint to get all discovered ESP32 devices for GENTA7.py auto-discovery.
    Returns JSON: {'MAC': {'ip': '...', 'role': '...', 'last_seen': ...}, ...}
    """
    try:
        esp_devices_lock.acquire()
        try:
            # Return a copy of the devices dict
            devices_copy = dict(esp_devices)
        finally:
            esp_devices_lock.release()
        return jsonify(devices_copy), 200
    except Exception as e:
        return jsonify({'error': str(e)}), 500

def set_esp32_wifi(ip, ssid, password):
    """Configure WiFi on ESP32"""
    try:
        url = f'http://{ip}/wifi/configure'
        # Try JSON POST first (expected by the ESP handler)
        try:
            response = requests.post(url, json={'ssid': ssid, 'password': password}, timeout=10)
            if response.status_code == 200:
                app.logger.debug(f"set_esp32_wifi: POST json -> {url} OK")
                return True
            else:
                app.logger.debug(f"set_esp32_wifi: POST json -> {url} returned {response.status_code} {response.text}")
        except Exception as e:
            app.logger.debug(f"set_esp32_wifi: JSON POST to {url} failed: {e}")

        # Fallback: try form-encoded body in case the device library expects urlencoded data
        try:
            response = requests.post(url, data={'ssid': ssid, 'password': password}, timeout=10)
            if response.status_code == 200:
                app.logger.debug(f"set_esp32_wifi: POST form -> {url} OK")
                return True
            else:
                app.logger.debug(f"set_esp32_wifi: POST form -> {url} returned {response.status_code} {response.text}")
        except Exception as e:
            app.logger.debug(f"set_esp32_wifi: form POST to {url} failed: {e}")

        return False
    except:
        return False

def restart_esp32(ip):
    """Restart ESP32"""
    try:
        requests.get(f'http://{ip}/restart', timeout=3)
        return True
    except:
        return False

def clear_device_from_registry(role):
    """Remove a device from the discovery registry (used before WiFi change to force re-discovery)"""
    try:
        # If host indicates report creation is active, refuse to clear registry
        try:
            if globals().get('REPORT_CREATION_GUARD'):
                app.logger.info(f"clear_device_from_registry: Guard active - refusing to remove role={role}")
                return
        except Exception:
            pass
        esp_devices_lock.acquire()
        try:
            # Find and remove device by role
            to_remove = None
            for mac, info in list(esp_devices.items()):
                if info.get('role') == role:
                    to_remove = mac
                    break
            if to_remove:
                esp_devices.pop(to_remove, None)
                # Log stack trace context so we can see who invoked this in logs
                try:
                    import traceback as _tb
                    stack = _tb.format_stack()[-6:]
                    stack_summary = ''.join(stack)
                except Exception:
                    stack_summary = '<no-stack-available>'
                app.logger.info(f"Cleared {role} from discovery registry (MAC: {to_remove}); remaining_devices={len(esp_devices)}; stack:\n{stack_summary}")
        finally:
            esp_devices_lock.release()
    except Exception as e:
        app.logger.error(f"Failed to clear {role} from registry: {e}")


@app.route('/debug/set_report_guard', methods=['POST', 'GET'])
def debug_set_report_guard():
    """Set/unset an internal guard to prevent registry clears during host report animation.
    Usage: /debug/set_report_guard?active=1 or active=0
    """
    try:
        active = request.args.get('active') if request.args.get('active') is not None else request.form.get('active')
        val = False
        if isinstance(active, str) and active.lower() in ('1', 'true', 'yes', 'on'):
            val = True
        try:
            globals()['REPORT_CREATION_GUARD'] = val
        except Exception:
            pass
        app.logger.info(f"/debug/set_report_guard -> set to {val}")
        return {'report_creation_guard': val}, 200
    except Exception as e:
        return {'error': str(e)}, 500


@app.route('/debug/get_report_guard', methods=['GET'])
def debug_get_report_guard():
    """Return current REPORT_CREATION_GUARD state for diagnostics."""
    try:
        val = bool(globals().get('REPORT_CREATION_GUARD'))
        return {'report_creation_guard': val}, 200
    except Exception as e:
        return {'error': str(e)}, 500


@app.route('/debug/last_known', methods=['GET'])
def debug_get_last_known():
    """Return the LAST_KNOWN_DEVICE_BY_ROLE sticky mapping for diagnostics."""
    try:
        try:
            last_known_lock.acquire()
            data = dict(LAST_KNOWN_DEVICE_BY_ROLE)
        finally:
            last_known_lock.release()
        return {'last_known': data}, 200
    except Exception as e:
        return {'error': str(e)}, 500

def wait_for_device_rediscovery(role, timeout=45):
    """Wait for a device to re-announce after WiFi change. Returns new IP or None."""
    app.logger.info(f"Waiting up to {timeout}s for {role} to re-announce...")
    start = time.time()
    while (time.time() - start) < timeout:
        ip = find_device_ip(role)
        if ip:
            app.logger.info(f"✓ {role} re-discovered at {ip}")
            return ip
        time.sleep(2)
    app.logger.warning(f"✗ {role} did not re-announce within {timeout}s")
    return None

@app.route('/wifi-management')
def wifi_management():
    """WiFi Management Dashboard"""
    profiles = load_wifi_profiles()
    
    # Prefer discovered IPs from UDP announcements; fall back to configured constants
    recorder_ip = find_device_ip('recorder') or ESP_BASE.replace('http://', '')
    player_ip = find_device_ip('player') or ESP_SPEAKER.replace('http://', '')

    recorder_status = get_esp32_status(recorder_ip) if recorder_ip else {'connected': False, 'ssid': 'Unknown', 'ip': 'N/A', 'rssi': 0}
    player_status = get_esp32_status(player_ip) if player_ip else {'connected': False, 'ssid': 'Unknown', 'ip': 'N/A', 'rssi': 0}

    # Provide a snapshot of discovered devices for the admin UI
    try:
        esp_devices_lock.acquire()
        try:
            discovered_devices = list(esp_devices.values())
        finally:
            esp_devices_lock.release()
    except Exception:
        discovered_devices = []

    return render_template('wifi_management.html',
                         profiles=profiles,
                         recorder_status=recorder_status,
                         player_status=player_status,
                         recorder_ip=recorder_ip,
                         player_ip=player_ip,
                         discovered_devices=discovered_devices)


@app.route('/esp32/devices')
def esp32_devices():
    """Return the list of discovered ESP32 devices (address and metadata)."""
    # Return as a list for easier consumption (thread-safe copy)
    try:
        esp_devices_lock.acquire()
        try:
            devices = list(esp_devices.values())
        finally:
            esp_devices_lock.release()
        # Diagnostic: if empty, log a snapshot so we can correlate with host actions
        try:
            if not devices:
                app.logger.info('[esp32/devices] returning EMPTY list; esp_devices snapshot empty at ' + time.strftime('%Y-%m-%d %H:%M:%S'))
        except Exception:
            pass
        return Response(json.dumps(devices, indent=2), mimetype='application/json')
    except Exception:
        return Response('[]', mimetype='application/json')

@app.route('/esp32/trigger-announcement', methods=['POST'])
def trigger_announcement():
    """Trigger ESP32s to re-announce by restarting them"""
    try:
        # Try to restart both devices to trigger announcements
        recorder_ip = find_device_ip('recorder') or ESP_BASE.replace('http://', '')
        player_ip = find_device_ip('player') or ESP_SPEAKER.replace('http://', '')
        
        results = []
        for role, ip in [('recorder', recorder_ip), ('player', player_ip)]:
            try:
                requests.get(f'http://{ip}/restart', timeout=3)
                results.append(f'{role} @ {ip}')
            except:
                pass
        
        if results:
            flash(f'Triggered restart on: {", ".join(results)}', 'success')
        else:
            flash('Could not reach any devices', 'error')
    except Exception as e:
        flash(f'Error: {str(e)}', 'error')
    
    return redirect(url_for('wifi_management'))

@app.route('/esp32/register-manual', methods=['POST'])
def esp32_register_manual():
    """Manually register ESP32 devices (for cross-subnet scenarios or when UDP discovery fails)"""
    try:
        recorder_ip = request.form.get('recorder_ip', '').strip()
        player_ip = request.form.get('player_ip', '').strip()
        
        if recorder_ip:
            # Try to get status from recorder to confirm it's reachable and get MAC
            try:
                status = requests.get(f'http://{recorder_ip}/wifi/status', timeout=5).json()
                mac = status.get('mac', recorder_ip)
                ssid = status.get('ssid', 'Unknown')
            except:
                mac = recorder_ip
                ssid = 'Unknown'
            
            esp_devices_lock.acquire()
            try:
                esp_devices[mac] = {
                    'mac': mac,
                    'role': 'recorder',
                    'ssid': ssid,
                    'ip': recorder_ip,
                    'last_seen': time.time()
                }
            finally:
                esp_devices_lock.release()
            # Sticky map update
            try:
                last_known_lock.acquire()
                LAST_KNOWN_DEVICE_BY_ROLE['recorder'] = recorder_ip
            finally:
                last_known_lock.release()
            flash(f'✓ Recorder registered at {recorder_ip}', 'success')
        
        if player_ip:
            # Try to get status from player to confirm it's reachable and get MAC
            try:
                status = requests.get(f'http://{player_ip}/wifi/status', timeout=5).json()
                mac = status.get('mac', player_ip)
                ssid = status.get('ssid', 'Unknown')
            except:
                mac = player_ip
                ssid = 'Unknown'
            
            esp_devices_lock.acquire()
            try:
                esp_devices[mac] = {
                    'mac': mac,
                    'role': 'player',
                    'ssid': ssid,
                    'ip': player_ip,
                    'last_seen': time.time()
                }
            finally:
                esp_devices_lock.release()
            # Sticky map update
            try:
                last_known_lock.acquire()
                LAST_KNOWN_DEVICE_BY_ROLE['player'] = player_ip
            finally:
                last_known_lock.release()
            flash(f'✓ Player registered at {player_ip}', 'success')
        
        if not recorder_ip and not player_ip:
            flash('Please enter at least one IP address', 'error')
        
        return redirect(url_for('wifi_management'))
    except Exception as e:
        flash(f'Error: {str(e)}', 'error')
        return redirect(url_for('wifi_management'))
        
        results = []
        for name, ip in [('Recorder', recorder_ip), ('Player', player_ip)]:
            try:
                requests.get(f'http://{ip}/restart', timeout=3)
                results.append(f'{name} restarted')
            except:
                results.append(f'{name} restart failed')
        
        return {'success': True, 'message': ', '.join(results)}
    except Exception as e:
        return {'success': False, 'error': str(e)}

@app.route('/esp32/manual-register', methods=['POST'])
def manual_register():
    """Manually register a device (for testing/recovery)"""
    try:
        ip = request.json.get('ip')
        role = request.json.get('role', 'unknown')
        
        if not ip:
            return {'success': False, 'error': 'IP required'}
        
        # Try to get status from device to verify it's reachable
        try:
            status_response = requests.get(f'http://{ip}/wifi/status', timeout=3)
            if status_response.status_code == 200:
                status_data = status_response.json()
                mac = status_data.get('mac', ip)
                ssid = status_data.get('ssid', '')
            else:
                mac = ip
                ssid = ''
        except:
            mac = ip
            ssid = ''
        
        # Register device
        obj = {
            'mac': mac,
            'role': role,
            'ip': ip,
            'ssid': ssid,
            'last_seen': time.time(),
            'manual': True
        }
        
        try:
            esp_devices_lock.acquire()
            esp_devices[mac] = obj
            app.logger.info(f"Manually registered {role} @ {ip}")
        finally:
            esp_devices_lock.release()
        # Sticky last-known mapping
        try:
            if role:
                last_known_lock.acquire()
                LAST_KNOWN_DEVICE_BY_ROLE[role] = ip
        finally:
            try:
                last_known_lock.release()
            except Exception:
                pass
        
        return {'success': True, 'device': obj}
    except Exception as e:
        return {'success': False, 'error': str(e)}


@app.route('/debug/set_recorder_ip', methods=['GET'])
def debug_set_recorder_ip():
    """Helper for quick testing: manually register a recorder IP and clear fail cache.
    Usage: /debug/set_recorder_ip?ip=192.168.0.111
    """
    ip = request.args.get('ip')
    if not ip:
        return {'success': False, 'error': 'ip query param required'}, 400
    try:
        # Probe device for status to get MAC if possible
        mac = ip
        ssid = ''
        try:
            resp = requests.get(f'http://{ip}/wifi/status', timeout=3)
            if resp.status_code == 200:
                try:
                    st = resp.json()
                    mac = st.get('mac', mac)
                    ssid = st.get('ssid', '')
                except Exception:
                    pass
        except Exception:
            pass

        obj = {
            'mac': mac,
            'role': 'recorder',
            'ip': ip,
            'ssid': ssid,
            'last_seen': time.time(),
            'manual': True
        }
        esp_devices_lock.acquire()
        try:
            esp_devices[mac] = obj
        finally:
            esp_devices_lock.release()

        # Clear any fail-cache entry for this host so forwarding will try it immediately
        try:
            host = ip.replace('http://', '').replace('https://', '')
            if host in _oled_fail_cache:
                del _oled_fail_cache[host]
        except Exception:
            pass

        app.logger.info(f"Manually registered recorder @ {ip} (MAC: {mac}) via debug endpoint")
        # Update sticky last-known map for recorder
        try:
            last_known_lock.acquire()
            LAST_KNOWN_DEVICE_BY_ROLE['recorder'] = ip
        finally:
            last_known_lock.release()
        return {'success': True, 'device': obj}, 200
    except Exception as e:
        return {'success': False, 'error': str(e)}, 500


@app.route('/debug/get_recorder_override', methods=['GET'])
def debug_get_recorder_override():
    ip = _read_recorder_override()
    return {'override': ip}, 200


@app.route('/debug/clear_recorder_override', methods=['POST','GET'])
def debug_clear_recorder_override():
    ok = _clear_recorder_override()
    if ok:
        return {'success': True}, 200
    return {'success': False}, 500

@app.route('/wifi-management/configure', methods=['POST'])
def wifi_configure():
    """Apply WiFi configuration to ESP32s"""
    ssid = request.form.get('ssid', '').strip()
    password = request.form.get('password', '').strip()
    target = request.form.get('target', 'both')
    # Checkbox in the form uses value="yes"; accept several truthy values for robustness
    restart = (request.form.get('restart') or '').lower() in ('on', 'yes', 'true', '1')
    
    if not ssid:
        flash('SSID is required', 'error')
        return redirect(url_for('wifi_management'))
    
    # Allow form overrides (useful when discovery found a different IP)
    recorder_override = request.form.get('recorder_ip_override', '').strip()
    player_override = request.form.get('player_ip_override', '').strip()

    # Use overrides if provided, otherwise discovered IPs, otherwise configured defaults
    recorder_ip = recorder_override or find_device_ip('recorder') or ESP_BASE.replace('http://', '')
    player_ip = player_override or find_device_ip('player') or ESP_SPEAKER.replace('http://', '')
    
    success = []
    failed = []
    devices_to_rediscover = []
    
    # Configure based on target
    if target in ['both', 'recorder']:
        app.logger.info(f"Configuring Recorder at {recorder_ip} -> SSID:'{ssid}'")
        if set_esp32_wifi(recorder_ip, ssid, password):
            success.append(f'Recorder ({recorder_ip})')
            # Clear from registry so we wait for fresh announcement
            clear_device_from_registry('recorder')
            devices_to_rediscover.append('recorder')
            if restart:
                restart_esp32(recorder_ip)
        else:
            failed.append(f'Recorder ({recorder_ip})')
    
    if target in ['both', 'player']:
        app.logger.info(f"Configuring Player at {player_ip} -> SSID:'{ssid}'")
        if set_esp32_wifi(player_ip, ssid, password):
            success.append(f'Player ({player_ip})')
            # Clear from registry so we wait for fresh announcement
            clear_device_from_registry('player')
            devices_to_rediscover.append('player')
            if restart:
                restart_esp32(player_ip)
        else:
            failed.append(f'Player ({player_ip})')
    
    # Wait for devices to re-announce with new IPs (if restart was requested)
    if restart and devices_to_rediscover:
        flash('ESP32s are restarting and connecting to new WiFi... Waiting for discovery...', 'info')
        rediscovered = []
        not_found = []
        
        for role in devices_to_rediscover:
            new_ip = wait_for_device_rediscovery(role, timeout=45)
            if new_ip:
                rediscovered.append(f'{role} @ {new_ip}')
            else:
                not_found.append(role)
        
        if rediscovered:
            flash(f'✓ Re-discovered: {", ".join(rediscovered)}', 'success')
        if not_found:
            flash(f'⚠ Could not re-discover: {", ".join(not_found)}. Check devices are powered on and connected to "{ssid}".', 'warning')
    
    # Show configuration results
    if success:
        flash(f'WiFi configured successfully on: {", ".join(success)}', 'success')
    if failed:
        flash(f'Failed to configure: {", ".join(failed)}', 'error')
    
    if not restart and success:
        flash('Configuration saved. Restart devices manually or check "Restart immediately" to apply.', 'info')
    
    return redirect(url_for('wifi_management'))

@app.route('/wifi-management/profiles/add', methods=['POST'])
def wifi_add_profile():
    """Add a new WiFi profile"""
    name = request.form.get('profile_name', '').strip()
    ssid = request.form.get('profile_ssid', '').strip()
    password = request.form.get('profile_password', '').strip()
    
    if not name or not ssid:
        flash('Profile name and SSID are required', 'error')
        return redirect(url_for('wifi_management'))
    
    profiles = load_wifi_profiles()
    profiles.append({
        'name': name,
        'ssid': ssid,
        'password': password
    })
    save_wifi_profiles(profiles)
    
    flash(f'Profile "{name}" added successfully', 'success')
    return redirect(url_for('wifi_management'))

@app.route('/wifi-management/profiles/delete/<int:index>', methods=['POST'])
def wifi_delete_profile(index):
    """Delete a WiFi profile"""
    profiles = load_wifi_profiles()
    if 0 <= index < len(profiles):
        deleted = profiles.pop(index)
        save_wifi_profiles(profiles)
        flash(f'Profile "{deleted["name"]}" deleted', 'success')
    else:
        flash('Profile not found', 'error')
    
    return redirect(url_for('wifi_management'))

@app.route('/wifi-management/scan/<device>')
def wifi_scan(device):
    """Scan WiFi networks on ESP32"""
    # Allow optional ip_override query parameter
    ip_override = request.args.get('ip')
    if ip_override:
        ip = ip_override
    else:
        if device == 'recorder':
            ip = find_device_ip('recorder') or ESP_BASE.replace('http://', '')
        elif device == 'player':
            ip = find_device_ip('player') or ESP_SPEAKER.replace('http://', '')
        else:
            return {'error': 'Invalid device'}, 400
    
    
    try:
        response = requests.get(f'http://{ip}/wifi/scan', timeout=15)
        if response.status_code == 200:
            result = response.json()
            # Normalize to {success: True, networks: [...]}
            if isinstance(result, dict) and 'networks' in result:
                return {'success': True, 'networks': result.get('networks', [])}
            else:
                return {'success': True, 'networks': []}
    except Exception as e:
        return {'error': str(e)}, 500
    
    return {'networks': []}, 200

@app.route('/wifi-management/test-connection/<device>')
def wifi_test_connection(device):
    """Test connection to ESP32"""
    # Allow optional ip_override via query param
    ip_override = request.args.get('ip')
    if ip_override:
        ip = ip_override
    else:
        if device == 'recorder':
            ip = find_device_ip('recorder') or ESP_BASE.replace('http://', '')
        elif device == 'player':
            ip = find_device_ip('player') or ESP_SPEAKER.replace('http://', '')
        else:
            return {'error': 'Invalid device'}, 400
    
    
    try:
        url = f'http://{ip}/wifi/status'
        print(f"[WiFi Test] testing connection to {url} for device {device}")
        response = requests.get(url, timeout=5)
        if response.status_code == 200:
            status = response.json()
            return {'success': True, 'ip': status.get('ip'), 'status': status}
        else:
            return {'success': False, 'error': f"HTTP {response.status_code}"}, 500
    except Exception as e:
        print(f"[WiFi Test] connection failed to {ip}: {str(e)}")
        return {'success': False, 'error': str(e), 'ip': ip}, 500


# ===== BACKGROUND RECORDING MONITOR =====
def recording_monitor_thread():
    """
    Background thread that monitors ESP32 for new recordings.
    When detected, it automatically notifies waiting clients via the queue.
    This eliminates the need for GENTA7 to poll repeatedly.
    """
    print("[Flask Monitor] Starting recording monitor thread...")
    
    last_known_size = 0
    monitoring_active = True
    check_interval = 0.5  # Check every 500ms
    
    while monitoring_active:
        try:
            # Use auto-discovered recorder IP if available, otherwise fall back to ESP_BASE
            discovered_ip = find_device_ip('recorder')
            if discovered_ip:
                recorder_host = discovered_ip
            else:
                recorder_host = ESP_BASE.replace('http://', '')
            
            size_url = f'http://{recorder_host}/size'
            
            # Quick check of recording size
            response = requests.get(size_url, timeout=1.5)
            if response.status_code == 200:
                current_size = int(response.text.strip())
                
                # New recording detected if:
                # 1. Size jumped from <1KB to >1KB (new recording appeared)
                # 2. Size is >1KB and changed significantly (recording growing)
                if current_size > 1024 and last_known_size < 1024:
                    # NEW recording appeared!
                    print(f"[Flask Monitor] NEW recording detected on {recorder_host}! Size: {current_size} bytes")
                    
                    # Wait briefly for recording to stabilize (200ms)
                    time.sleep(0.2)
                    
                    # Check size again to confirm stable
                    confirm_response = requests.get(size_url, timeout=1.5)
                    if confirm_response.status_code == 200:
                        stable_size = int(confirm_response.text.strip())
                        if stable_size >= current_size and stable_size > 1024:
                            # Recording is stable and ready!
                            print(f"[Flask Monitor] Recording stable at {stable_size} bytes - notifying clients")
                            
                            # Trigger notification
                            global last_recording_timestamp
                            with recording_notification_lock:
                                last_recording_timestamp = time.time()
                                try:
                                    recording_ready_queue.put_nowait({
                                        'timestamp': last_recording_timestamp,
                                        'ready': True,
                                        'size': stable_size
                                    })
                                except queue.Full:
                                    # Clear old item and retry
                                    try:
                                        recording_ready_queue.get_nowait()
                                    except:
                                        pass
                                    recording_ready_queue.put_nowait({
                                        'timestamp': last_recording_timestamp,
                                        'ready': True,
                                        'size': stable_size
                                    })
                
                last_known_size = current_size
                
        except Exception as e:
            # Silently ignore connection errors (ESP32 might be busy/unavailable)
            # But log critical errors
            if "Connection refused" not in str(e) and "timed out" not in str(e):
                print(f"[Flask Monitor] Error: {e}")
            pass
        
        time.sleep(check_interval)
    
    print("[Flask Monitor] Recording monitor thread stopped")


# ============================================================================
# REPORT DOWNLOAD ENDPOINTS (for CakePHP website integration)
# ============================================================================

def require_api_key(f):
    """Decorator to protect endpoints with API key authentication.
    
    Checks for API key in:
    1. X-GENTA-API-KEY header (recommended)
    2. Authorization: Bearer <token> header
    3. api_key query parameter (less secure, but convenient for testing)
    
    Returns 401 Unauthorized if API key is missing or invalid.
    """
    @wraps(f)
    def decorated_function(*args, **kwargs):
        # Load API key from environment or config
        expected_key = os.environ.get('GENTA_REPORT_UPLOAD_API_KEY')
        
        if not expected_key:
            # No API key configured - allow access (for development/testing)
            print("⚠ WARNING: GENTA_REPORT_UPLOAD_API_KEY not set - API key authentication disabled!")
            return f(*args, **kwargs)
        
        # Check multiple auth methods
        provided_key = None
        
        # Method 1: X-GENTA-API-KEY header
        provided_key = request.headers.get('X-GENTA-API-KEY')
        
        # Method 2: Authorization: Bearer token
        if not provided_key:
            auth_header = request.headers.get('Authorization', '')
            if auth_header.startswith('Bearer '):
                provided_key = auth_header[7:]  # Remove 'Bearer ' prefix
        
        # Method 3: api_key query parameter (less secure)
        if not provided_key:
            provided_key = request.args.get('api_key')
        
        # Validate
        if not provided_key or provided_key != expected_key:
            print(f"[API Auth] Unauthorized access attempt from {request.remote_addr}")
            return jsonify({
                'error': 'Unauthorized',
                'message': 'Valid API key required. Provide via X-GENTA-API-KEY header, Authorization: Bearer token, or api_key query parameter.'
            }), 401
        
        # Success
        return f(*args, **kwargs)
    
    return decorated_function


@app.route('/analysis_report', methods=['GET'])
@require_api_key
def get_analysis_report():
    """Download analysis report for a student.
    
    Query parameters:
    - lrn (string, required): Student LRN (12 digits)
    - student_name (string, optional): Student name for filename matching
    - filename (string, optional): Specific filename to download
    - format (string, optional): 'file' (default) returns binary, 'json' returns metadata
    
    Returns:
    - 200: File download (binary) or JSON metadata
    - 400: Bad request (missing parameters)
    - 404: Report not found
    - 401: Unauthorized (invalid API key)
    
    Example:
    curl -H "X-GENTA-API-KEY: YourKeyHere" \
         "https://nonbasic-bob-inimical.ngrok-free.dev/analysis_report?lrn=107048090462"
    """
    lrn = request.args.get('lrn')
    student_name = request.args.get('student_name')
    filename = request.args.get('filename')
    response_format = request.args.get('format', 'file')
    
    # Validate input
    if not lrn and not filename:
        return jsonify({
            'error': 'Bad Request',
            'message': 'Either lrn or filename parameter is required'
        }), 400
    
    # Search for matching file in upload directories
    upload_dirs = [app.config['UPLOAD_FOLDER']]
    if app.config.get('ALT_UPLOAD_FOLDER'):
        upload_dirs.append(app.config['ALT_UPLOAD_FOLDER'])
    
    found_file = None
    found_path = None
    
    for upload_dir in upload_dirs:
        if not os.path.exists(upload_dir):
            continue
        
        # Try exact filename match first
        if filename:
            candidate = os.path.join(upload_dir, filename)
            if os.path.isfile(candidate):
                found_file = filename
                found_path = candidate
                break
        
        # Search by LRN pattern (analysis_result_<name>_<lrn>.docx)
        if lrn:
            for file in os.listdir(upload_dir):
                if file.startswith('analysis_result_') and lrn in file and file.endswith('.docx'):
                    found_file = file
                    found_path = os.path.join(upload_dir, file)
                    break
            
            if found_path:
                break
    
    # Not found
    if not found_path:
        return jsonify({
            'error': 'Not Found',
            'message': f'Analysis report not found for LRN: {lrn}' if lrn else f'File not found: {filename}'
        }), 404
    
    # Return based on format
    if response_format == 'json':
        # Return metadata JSON
        file_size = os.path.getsize(found_path)
        return jsonify({
            'ok': True,
            'file_name': found_file,
            'file_size': file_size,
            'download_url': f'/analysis_report?filename={found_file}',
            'lrn': lrn,
            'report_type': 'analysis'
        }), 200
    else:
        # Return file download
        return send_file(
            found_path,
            as_attachment=True,
            download_name=found_file,
            mimetype='application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        )


@app.route('/tailored_module', methods=['GET'])
@require_api_key
def get_tailored_module():
    """Download tailored learning module for a student.
    
    Query parameters:
    - lrn (string, required): Student LRN (12 digits)
    - student_name (string, optional): Student name for filename matching
    - filename (string, optional): Specific filename to download
    - format (string, optional): 'file' (default) returns binary, 'json' returns metadata
    
    Returns:
    - 200: File download (binary) or JSON metadata
    - 400: Bad request (missing parameters)
    - 404: Report not found
    - 401: Unauthorized (invalid API key)
    
    Example:
    curl -H "X-GENTA-API-KEY: YourKeyHere" \
         "https://nonbasic-bob-inimical.ngrok-free.dev/tailored_module?lrn=107048090462"
    """
    lrn = request.args.get('lrn')
    student_name = request.args.get('student_name')
    filename = request.args.get('filename')
    response_format = request.args.get('format', 'file')
    
    # Validate input
    if not lrn and not filename:
        return jsonify({
            'error': 'Bad Request',
            'message': 'Either lrn or filename parameter is required'
        }), 400
    
    # Search for matching file in upload directories
    upload_dirs = [app.config['UPLOAD_FOLDER']]
    if app.config.get('ALT_UPLOAD_FOLDER'):
        upload_dirs.append(app.config['ALT_UPLOAD_FOLDER'])
    
    found_file = None
    found_path = None
    
    for upload_dir in upload_dirs:
        if not os.path.exists(upload_dir):
            continue
        
        # Try exact filename match first
        if filename:
            candidate = os.path.join(upload_dir, filename)
            if os.path.isfile(candidate):
                found_file = filename
                found_path = candidate
                break
        
        # Search by LRN pattern (tailored_module_<name>_<lrn>.docx)
        if lrn:
            for file in os.listdir(upload_dir):
                if file.startswith('tailored_module_') and lrn in file and file.endswith('.docx'):
                    found_file = file
                    found_path = os.path.join(upload_dir, file)
                    break
            
            if found_path:
                break
    
    # Not found
    if not found_path:
        return jsonify({
            'error': 'Not Found',
            'message': f'Tailored module not found for LRN: {lrn}' if lrn else f'File not found: {filename}'
        }), 404
    
    # Return based on format
    if response_format == 'json':
        # Return metadata JSON
        file_size = os.path.getsize(found_path)
        return jsonify({
            'ok': True,
            'file_name': found_file,
            'file_size': file_size,
            'download_url': f'/tailored_module?filename={found_file}',
            'lrn': lrn,
            'report_type': 'tailored_module'
        }), 200
    else:
        # Return file download
        return send_file(
            found_path,
            as_attachment=True,
            download_name=found_file,
            mimetype='application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        )


if __name__ == '__main__':
    # Ensure upload folder exists
    os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)
    # Also ensure alternate folder exists (create only if parent exists to avoid creating MAIN_SYSTEM unexpectedly)
    try:
        alt = app.config.get('ALT_UPLOAD_FOLDER')
        if alt:
            parent = os.path.dirname(alt)
            # Create alt only if MAIN_SYSTEM exists, otherwise skip to avoid accidental folder creation
            if os.path.exists(parent):
                os.makedirs(alt, exist_ok=True)
    except Exception:
        pass
    
    # ===== SECURITY NOTICE =====
    print("\n" + "="*70)
    print("🔐 GENTA ADMIN PANEL - SECURITY NOTICE")
    print("="*70)
    print("Admin login is now REQUIRED to access the admin panel.")
    print("")
    print("Set ADMIN_USERNAME and ADMIN_PASSWORD_HASH in the environment.")
    print("See .env.example for the required variables.")
    print("="*70 + "\n")
    
    # ===== START BACKGROUND RECORDING MONITOR =====
    print("[Flask] Starting background recording monitor thread...")
    monitor_thread = threading.Thread(target=recording_monitor_thread, daemon=True)
    monitor_thread.start()
    # ===== START BACKUP SCHEDULER THREAD =====
    try:
        _ensure_backup_dir()
        scheduler_thread = threading.Thread(target=backup_scheduler_thread, daemon=True)
        scheduler_thread.start()
        print('[Flask] Backup scheduler thread started')
    except Exception:
        print('[Flask] Failed to start backup scheduler')
    
    # Bind to 0.0.0.0 so ngrok (or other remote tunnels) can reach the Flask app
    app.run(host='0.0.0.0', port=5000, debug=True)


@app.route('/debug_uploads')
def debug_uploads():
    """Return JSON diagnostics about the primary and alternate upload folders so you can see
    what the Flask process actually sees (paths, existence, file lists)."""
    primary = app.config.get('UPLOAD_FOLDER')
    alt = app.config.get('ALT_UPLOAD_FOLDER')
    def _list(p):
        try:
            if not p:
                return {'exists': False, 'files': []}
            return {'exists': os.path.exists(p), 'abs_path': os.path.abspath(p), 'files': os.listdir(p) if os.path.exists(p) else []}
        except Exception as e:
            return {'exists': False, 'error': str(e), 'files': []}

    info = {
        'primary': _list(primary),
        'alternate': _list(alt)
    }
    return Response(json.dumps(info, indent=2), mimetype='application/json')