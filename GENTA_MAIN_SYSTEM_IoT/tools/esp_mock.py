from http.server import HTTPServer, BaseHTTPRequestHandler
import os
from urllib.parse import urlparse, parse_qs


# Lightweight multipart/form-data parser (only extracts first file field named 'file')
def extract_file_from_multipart(headers, body_bytes):
    ctype = headers.get('content-type', '')
    if 'boundary=' not in ctype:
        return None, None
    boundary = ctype.split('boundary=')[-1]
    if boundary.startswith('"') and boundary.endswith('"'):
        boundary = boundary[1:-1]
    bboundary = ('--' + boundary).encode('utf-8')
    parts = body_bytes.split(bboundary)
    for part in parts:
        if not part or part == b'--' or part == b'--\r\n':
            continue
        # part starts with CRLF
        if b'Content-Disposition:' in part:
            # parse headers
            try:
                header_section, data = part.split(b"\r\n\r\n", 1)
            except ValueError:
                continue
            header_lines = header_section.decode('utf-8', errors='ignore').split('\r\n')
            filename = None
            for hl in header_lines:
                if 'Content-Disposition' in hl and 'filename=' in hl:
                    # extract filename
                    idx = hl.find('filename=')
                    fname = hl[idx+9:].strip()
                    if fname.startswith('"') and fname.endswith('"'):
                        fname = fname[1:-1]
                    filename = os.path.basename(fname)
            if filename:
                # strip trailing CRLF if present
                if data.endswith(b"\r\n"):
                    data = data[:-2]
                return filename, data
    return None, None


class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        if self.path == '/upload_welcome':
            try:
                length = int(self.headers.get('content-length', 0))
                body = self.rfile.read(length)
                filename, data = extract_file_from_multipart(self.headers, body)
                if filename and data is not None:
                    os.makedirs('WelcomeAudio', exist_ok=True)
                    fn = os.path.join('WelcomeAudio', filename)
                    with open(fn, 'wb') as f:
                        f.write(data)
                    self.send_response(200)
                    self.end_headers()
                    self.wfile.write(b'OK')
                    print('Saved welcome file:', fn)
                    return
            except Exception as e:
                print('Error parsing upload:', e)
            self.send_response(400)
            self.end_headers()
            self.wfile.write(b'Bad Request')
        else:
            self.send_response(404)
            self.end_headers()

    def do_GET(self):
        parsed = urlparse(self.path)
        if parsed.path == '/play':
            qs = parse_qs(parsed.query)
            file = qs.get('file', [''])[0]
            print('Play requested for:', file)
            self.send_response(200)
            self.end_headers()
            self.wfile.write(b'OK')
        else:
            self.send_response(404)
            self.end_headers()


if __name__ == '__main__':
    port = 8000
    server = HTTPServer(('0.0.0.0', port), Handler)
    print(f'Mock ESP server running on http://0.0.0.0:{port}/')
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print('Shutting down')
        server.server_close()
