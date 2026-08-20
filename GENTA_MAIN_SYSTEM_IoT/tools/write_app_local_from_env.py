#!/usr/bin/env python3
"""
Safely write DB credentials and fullBaseUrl into `GENTA/config/app_local.php` from
environment variables or a local `.env` file.

By default the script prints a preview of changes. Use --apply to actually write the file.
It will create a timestamped backup of the original `app_local.php` before overwriting.

Supported env vars (preferred):
 - DB_HOST
 - DB_PORT
 - DB_USER
 - DB_PASS
 - DB_NAME
 - FULL_BASE_URL
 - DEBUG
 - SECURITY_SALT

If a `.env` file exists in the project root it will be read for values not present in the environment.

Usage (preview):
  python tools/write_app_local_from_env.py

Usage (apply):
  python tools/write_app_local_from_env.py --apply

WARNING: Do not commit credentials to source control. This script writes directly to `config/app_local.php`.
"""
import os
import re
import sys
import time
import argparse
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
APP_LOCAL = ROOT / 'GENTA' / 'config' / 'app_local.php'

ENG_VARS = ['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASS', 'DB_NAME', 'FULL_BASE_URL', 'DEBUG', 'SECURITY_SALT']


def load_dotenv(dotenv_path: Path):
    data = {}
    if not dotenv_path.exists():
        return data
    with dotenv_path.open('r', encoding='utf-8') as fh:
        for ln in fh:
            ln = ln.strip()
            if not ln or ln.startswith('#'):
                continue
            if '=' not in ln:
                continue
            k, v = ln.split('=', 1)
            k = k.strip()
            v = v.strip().strip('"').strip("'")
            data.setdefault(k, v)
    return data


def gather_values():
    env = dict(os.environ)
    dot = load_dotenv(ROOT / '.env')
    values = {}
    # prefer environment variables; fall back to .env
    values['DB_HOST'] = env.get('DB_HOST') or dot.get('DB_HOST')
    values['DB_PORT'] = env.get('DB_PORT') or dot.get('DB_PORT')
    values['DB_USER'] = env.get('DB_USER') or dot.get('DB_USER')
    values['DB_PASS'] = env.get('DB_PASS') or dot.get('DB_PASS')
    values['DB_NAME'] = env.get('DB_NAME') or dot.get('DB_NAME')
    values['FULL_BASE_URL'] = env.get('FULL_BASE_URL') or dot.get('FULL_BASE_URL') or env.get('APP_BASE_URL') or dot.get('APP_BASE_URL')
    values['DEBUG'] = env.get('DEBUG') or dot.get('DEBUG')
    values['SECURITY_SALT'] = env.get('SECURITY_SALT') or dot.get('SECURITY_SALT')
    return values


def replace_in_app_local(text: str, vals: dict) -> str:
    out = text
    # fullBaseUrl replacement (looks for the env('FULL_BASE_URL', '...') pattern)
    if vals.get('FULL_BASE_URL'):
        fb = vals['FULL_BASE_URL'].replace("'", "\\'")
        out = re.sub(r"('fullBaseUrl'\s*=>\s*env\('FULL_BASE_URL',\s*')[^']*('\)\s*)",
                     lambda m: m.group(1) + fb + m.group(2), out, flags=re.MULTILINE)

    # host
    if vals.get('DB_HOST'):
        h = vals['DB_HOST'].replace("'", "\\'")
        out = re.sub(r"('host'\s*=>\s*)'[^']*'(\s*,)", r"\1'{}'\2".format(h), out, flags=re.MULTILINE)

    # port (if provided, uncomment or replace; otherwise leave as-is)
    if vals.get('DB_PORT'):
        p = vals['DB_PORT']
        # replace commented or uncommented port line within default datasource block
        out = re.sub(r"^\s*//\s*'port'\s*=>\s*'[^']*',\s*$",
                     "            'port' => '{}' ,".format(p), out, flags=re.MULTILINE)
        out = re.sub(r"^\s*'port'\s*=>\s*'[^']*',\s*$",
                     "            'port' => '{}' ,".format(p), out, flags=re.MULTILINE)

    # username
    if vals.get('DB_USER'):
        u = vals['DB_USER'].replace("'", "\\'")
        out = re.sub(r"('username'\s*=>\s*)'[^']*'(\s*,)", r"\1'{}'\2".format(u), out, flags=re.MULTILINE)

    # password
    if vals.get('DB_PASS'):
        pw = vals['DB_PASS'].replace("'", "\\'")
        out = re.sub(r"('password'\s*=>\s*)'[^']*'(\s*,)", r"\1'{}'\2".format(pw), out, flags=re.MULTILINE)

    # database
    if vals.get('DB_NAME'):
        dbn = vals['DB_NAME'].replace("'", "\\'")
        out = re.sub(r"('database'\s*=>\s*)'[^']*'(\s*,)", r"\1'{}'\2".format(dbn), out, flags=re.MULTILINE)

    # DEBUG
    if vals.get('DEBUG') is not None:
        # replace the filter_var(env('DEBUG', true)...) line's default value
        dv = vals['DEBUG']
        dv_php = 'true' if str(dv).lower() in ('1', 'true', 'yes', 'on') else 'false'
        out = re.sub(r"('debug'\s*=>\s*filter_var\(env\('DEBUG',\s*)[^\),]+(,\s*FILTER_VALIDATE_BOOLEAN\)\),)",
                     lambda m: "'debug' => filter_var(env('DEBUG', {}), FILTER_VALIDATE_BOOLEAN),".format(dv_php),
                     out, flags=re.MULTILINE)

    # SECURITY_SALT replacement if provided
    if vals.get('SECURITY_SALT'):
        s = vals['SECURITY_SALT'].replace("'", "\\'")
        out = re.sub(r"('salt'\s*=>\s*env\('SECURITY_SALT',\s*')[^']*('\)\s*,)",
                     lambda m: m.group(1) + s + m.group(2), out, flags=re.MULTILINE)

    return out


def main():
    parser = argparse.ArgumentParser(description='Write app_local.php values from environment or .env')
    parser.add_argument('--apply', action='store_true', help='Write changes to file (creates a backup).')
    parser.add_argument('--file', default=str(APP_LOCAL), help='Path to app_local.php')
    args = parser.parse_args()

    if not Path(args.file).exists():
        print('Could not find app_local.php at', args.file)
        sys.exit(2)

    vals = gather_values()
    if not any(vals.values()):
        print('No values found in environment or .env. Nothing to change.')
        sys.exit(0)

    orig_text = Path(args.file).read_text(encoding='utf-8')
    new_text = replace_in_app_local(orig_text, vals)

    if orig_text == new_text:
        print('No replacements were made (existing file already matches provided values).')
        sys.exit(0)

    print('Preview of changes (diff-like):')
    # Simple diff: show lines that differ
    import difflib
    for ln in difflib.unified_diff(orig_text.splitlines(), new_text.splitlines(), fromfile='original', tofile='new', lineterm=''):
        print(ln)

    if args.apply:
        # create backup
        bak = Path(args.file + f'.bak.{int(time.time())}')
        bak.write_text(orig_text, encoding='utf-8')
        Path(args.file).write_text(new_text, encoding='utf-8')
        # make the file read-only for group? leave as normal file - user manages perms
        print(f'Wrote changes to {args.file}; backup saved to {bak}')
    else:
        print('\nRun with --apply to write the changes to the file (a backup will be created).')


if __name__ == '__main__':
    main()
