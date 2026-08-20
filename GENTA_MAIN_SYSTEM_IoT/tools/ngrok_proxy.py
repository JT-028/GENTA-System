#!/usr/bin/env python3
"""
Simple forwarding proxy for local ESP32 files so ngrok can forward them reliably.
Usage:
  python tools\ngrok_proxy.py --target http://esp32fs.local:80 --port 8000
Then run: ngrok http 8000

This proxy will forward all paths and stream responses back transparently.
"""
import argparse
from flask import Flask, request, Response
import requests

app = Flask(__name__)

parser = argparse.ArgumentParser()
parser.add_argument('--target', required=True, help='Target base URL (e.g. http://esp32fs.local:80)')
parser.add_argument('--port', type=int, default=8000)
args = parser.parse_args()
TARGET_BASE = args.target.rstrip('/')

# Forward any path
@app.route('/', defaults={'path': ''}, methods=['GET','POST','PUT','DELETE','OPTIONS'])
@app.route('/<path:path>', methods=['GET','POST','PUT','DELETE','OPTIONS'])
def proxy(path):
    url = TARGET_BASE + '/' + path
    # Preserve query string
    if request.query_string:
        url += '?' + request.query_string.decode('utf-8')

    try:
        resp = requests.request(
            method=request.method,
            url=url,
            headers={k: v for k, v in request.headers.items() if k.lower() != 'host'},
            data=request.get_data(),
            stream=True,
            timeout=10
        )
    except requests.exceptions.RequestException as e:
        return Response(f'Upstream request failed: {e}', status=502)

    headers = [(name, value) for (name, value) in resp.raw.headers.items()]
    return Response(resp.raw, status=resp.status_code, headers=headers)

if __name__ == '__main__':
    print(f'Proxying to {TARGET_BASE} on port {args.port}')
    app.run(host='127.0.0.1', port=args.port, debug=False)
