"""sync_site_packages.py

Compare site-packages in an external virtualenv and the project's `newenv`.
Usage (PowerShell):
  # Dry-run compare only
  python tools\sync_site_packages.py --source "D:\GENTA SYS\newenv\Lib\site-packages" --target "newenv\Lib\site-packages" --dry-run

  # Copy missing packages from source to target (safe: won't overwrite unless --overwrite)
  python tools\sync_site_packages.py --source "D:\GENTA SYS\newenv\Lib\site-packages" --target "newenv\Lib\site-packages" --copy

Notes:
 - Run this on your machine where both paths are accessible.
 - The script copies directories and files that look like packages: folders, .pth, .py, .pyd, .dll, and dist-info.
 - It does not try to run pip; if you prefer, generate a requirements.txt from the source env and pip-install into the target env instead.
"""
from __future__ import annotations
import argparse
import os
import shutil
from pathlib import Path

PACKAGE_EXT_WHITELIST = {'.py', '.pyc', '.pyd', '.dll', '.so', '.pth'}


def list_package_entries(site_packages: Path):
    """Return a set of canonical package names found in site-packages.
    We include: top-level directories, .py files (without extension), .pyd/.dll files, and dist-info names.
    """
    names = set()
    if not site_packages.exists():
        return names
    for p in site_packages.iterdir():
        nm = p.name
        # Skip obvious caches
        if nm in ('__pycache__', 'pip', 'setuptools', 'wheel'):
            continue
        # Directories => use dir name
        if p.is_dir():
            # treat "packagename-version.dist-info" as package 'packagename'
            if nm.endswith('.dist-info'):
                base = nm.rsplit('-', 1)[0]
                names.add(base.lower())
            else:
                names.add(nm.lower())
        else:
            # files: foo.py -> foo ; foo.cp311-win_amd64.pyd -> foo
            stem = p.stem
            # handle names like package-1.2.3.dist-info (already above)
            # remove extensions and known wheel tags
            # for files like 'requests-2.27.1.dist-info' the stem is 'requests-2.27.1.dist-info', skip
            if nm.endswith('.dist-info'):
                base = nm.rsplit('-', 1)[0]
                names.add(base.lower())
                continue
            # For normal py/pyd/dll files, use stem as package
            ext = p.suffix.lower()
            if ext in PACKAGE_EXT_WHITELIST:
                # For names like "_mysql_connector.cp313-win_amd64.pyd", stem may contain dots; take part before first dot
                candidate = stem.split('.')[0]
                names.add(candidate.lower())
    return names


def main():
    parser = argparse.ArgumentParser(description='Compare/copy site-packages between two envs')
    parser.add_argument('--source', required=True, help='Source site-packages path (e.g. D:\\\\GENTA SYS\\newenv\\Lib\\site-packages)')
    parser.add_argument('--target', required=True, help='Target site-packages path (relative to cwd or absolute)')
    parser.add_argument('--copy', action='store_true', help='Copy missing entries from source to target')
    parser.add_argument('--overwrite', action='store_true', help='Allow overwriting existing entries in the target')
    parser.add_argument('--dry-run', action='store_true', help='Do not copy, only show what would be done')
    args = parser.parse_args()

    src = Path(args.source)
    tgt = Path(args.target)
    if not src.exists():
        print(f"ERROR: source path does not exist: {src}")
        return
    if not tgt.exists():
        print(f"Target path does not exist, creating: {tgt}")
        if not args.copy:
            print("(Will only create target if --copy is specified)")
        else:
            tgt.mkdir(parents=True, exist_ok=True)

    print(f"Scanning source: {src}")
    print(f"Scanning target: {tgt}")
    src_names = list_package_entries(src)
    tgt_names = list_package_entries(tgt)

    missing = sorted(n for n in src_names if n not in tgt_names)

    print(f"\nFound {len(src_names)} entries in source, {len(tgt_names)} in target")
    print(f"Missing entries in target: {len(missing)}")
    for m in missing:
        print(" -", m)

    if args.copy and missing:
        if args.dry_run:
            print("\nDry-run: not copying anything. Re-run without --dry-run to perform copy.")
            return
        print(f"\nCopying {len(missing)} entries from source -> target")
        for name in missing:
            # Find matching candidate in source
            # Priority: exact dir, then dist-info, then file matching
            candidates = []
            for p in src.iterdir():
                low = p.name.lower()
                if low == name or low.startswith(name + '-') or low.startswith(name + '.'):
                    candidates.append(p)
                else:
                    # match file stems
                    stem = p.stem.lower().split('.')[0]
                    if stem == name:
                        candidates.append(p)
            if not candidates:
                print(f"Warning: no source candidate found for {name}")
                continue
            for cand in candidates:
                dest = tgt / cand.name
                try:
                    if dest.exists():
                        if args.overwrite:
                            if dest.is_dir():
                                shutil.rmtree(dest)
                            else:
                                dest.unlink()
                        else:
                            print(f"Skipping existing target {dest} (use --overwrite to replace)")
                            continue
                    if cand.is_dir():
                        print(f"Copying dir {cand} -> {dest}")
                        shutil.copytree(cand, dest)
                    else:
                        print(f"Copying file {cand} -> {dest}")
                        shutil.copy2(cand, dest)
                except Exception as e:
                    print(f"Failed to copy {cand} -> {dest}: {e}")
        print("\nCopy complete. Consider running pip install --upgrade --force-reinstall for compiled extensions if needed.")

    print("\nDone.")

if __name__ == '__main__':
    main()
