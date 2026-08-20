#!/usr/bin/env python3
"""
Small helper to download and extract a Vosk model into the workspace `model/` directory.

Usage:
  python tools/install_vosk_model.py --url <MODEL_URL> [--target model]

Defaults to a small English model if no URL is supplied.

See: https://alphacephei.com/vosk/install for official models and guidance.

Note: model archives are large (tens to hundreds of MB). Make sure you have network
connectivity and enough disk space.
"""
import argparse
import os
import sys
import requests
import shutil
import tempfile
import tarfile
import zipfile
from pathlib import Path

def download_file(url, dest_path):
    resp = requests.get(url, stream=True)
    resp.raise_for_status()
    total = resp.headers.get('Content-Length')
    total = int(total) if total is not None else None
    with open(dest_path, 'wb') as f:
        downloaded = 0
        for chunk in resp.iter_content(chunk_size=8192):
            if not chunk:
                continue
            f.write(chunk)
            downloaded += len(chunk)
            if total:
                pct = downloaded * 100 // total
                print(f"\rDownloading... {pct}%", end='', flush=True)
    if total:
        print('\rDownload complete.          ')
    else:
        print('\rDownload complete.')


def extract_archive(archive_path, dest_dir):
    # Try zip first (common for Vosk models), then fall back to tarfile auto-detection.
    try:
        with open(archive_path, 'rb') as fh:
            hdr = fh.read(6)
        # ZIP files start with 'PK'
        if hdr.startswith(b'PK') or archive_path.suffix == '.zip':
            with zipfile.ZipFile(archive_path, 'r') as z:
                z.extractall(dest_dir)
            return
    except zipfile.BadZipFile:
        # continue to tar detection
        pass
    # Fall back to tarfile (supports gz, bz2, xz, plain tar)
    try:
        with tarfile.open(archive_path, 'r:*') as t:
            t.extractall(dest_dir)
        return
    except tarfile.ReadError:
        # Could not extract
        raise RuntimeError('Archive format not recognized (not zip or tar).')


def main():
    parser = argparse.ArgumentParser(description='Download and install a Vosk model into `model/`.')
    parser.add_argument('--url', help='Full URL to the Vosk model archive (zip or tar.gz).', default='https://alphacephei.com/vosk/models/vosk-model-small-en-us-0.15.zip')
    parser.add_argument('--target', help='Target directory to extract model into (default: model)', default='model')
    args = parser.parse_args()

    target_dir = Path(args.target)
    if target_dir.exists() and any(target_dir.iterdir()):
        print(f"Target directory '{target_dir}' already exists and is not empty. Skipping download.")
        sys.exit(0)

    url = args.url
    print(f"Downloading Vosk model from: {url}\nExtracting into: {target_dir}\nThis may take a while...")

    target_dir.mkdir(parents=True, exist_ok=True)

    with tempfile.TemporaryDirectory() as td:
        td = Path(td)
        # try to preserve filename/extension from URL if present
        url_name = url.split('/')[-1] or 'vosk_model_archive'
        archive_name = td / url_name
        try:
            download_file(url, archive_name)
        except Exception as e:
            print('Failed to download model:', e)
            sys.exit(2)

        try:
            extract_archive(archive_name, td)
        except Exception as e:
            print('Failed to extract archive:', e)
            sys.exit(3)

        # move extracted content into target_dir. Some archives contain a single top folder.
        entries = list(td.iterdir())
        # remove the archive file from entries if present
        entries = [p for p in entries if p.name != archive_name.name]
        if len(entries) == 1 and entries[0].is_dir():
            # move contents of this dir into target_dir
            src = entries[0]
            for child in src.iterdir():
                shutil.move(str(child), str(target_dir))
        else:
            # move all entries into target_dir
            for child in entries:
                shutil.move(str(child), str(target_dir))

    print('Vosk model installed into:', target_dir)
    print('If QUIZZER.py still does not pick it up, verify the model path and try again.')

if __name__ == '__main__':
    main()
