#!/usr/bin/env python3
"""Safely extract and merge a SignWork implementation package into an app folder."""

from __future__ import annotations

import argparse
import shutil
import sys
import tempfile
import zipfile
from pathlib import Path


EXCLUDED_NAMES = {".git", "vendor", "node_modules"}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Ekstrak paket SignWork dan salin seluruh source ke folder aplikasi Laravel."
    )
    parser.add_argument("--zip", dest="package_zip", type=Path, required=True, help="Path file ZIP paket SignWork.")
    parser.add_argument("--target", type=Path, required=True, help="Folder root aplikasi SignWork.")
    parser.add_argument("--dry-run", action="store_true", help="Tampilkan file tanpa menyalin perubahan.")
    return parser.parse_args()


def validate_archive(package_zip: Path) -> None:
    if not package_zip.is_file():
        raise FileNotFoundError(f"File ZIP tidak ditemukan: {package_zip}")

    with zipfile.ZipFile(package_zip) as archive:
        for member in archive.infolist():
            member_path = Path(member.filename)
            if member_path.is_absolute() or ".." in member_path.parts:
                raise ValueError(f"Entry ZIP tidak aman: {member.filename}")


def find_source_root(extracted_root: Path) -> Path:
    children = [child for child in extracted_root.iterdir() if child.name not in {"__MACOSX"}]
    if len(children) == 1 and children[0].is_dir():
        return children[0]
    return extracted_root


def iter_source_files(source_root: Path):
    for path in source_root.rglob("*"):
        relative = path.relative_to(source_root)
        if any(part in EXCLUDED_NAMES for part in relative.parts):
            continue
        if path.is_file():
            yield relative, path


def copy_package(package_zip: Path, target: Path, dry_run: bool = False) -> int:
    validate_archive(package_zip)
    target = target.expanduser().resolve()

    with tempfile.TemporaryDirectory(prefix="signwork-package-") as temporary_directory:
        extraction_root = Path(temporary_directory)
        with zipfile.ZipFile(package_zip) as archive:
            archive.extractall(extraction_root)

        source_root = find_source_root(extraction_root)
        copied = 0
        for relative, source in sorted(iter_source_files(source_root), key=lambda item: str(item[0])):
            destination = target / relative
            print(f"{'[DRY-RUN] ' if dry_run else ''}{relative}")
            if not dry_run:
                destination.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(source, destination)
            copied += 1

    return copied


def main() -> int:
    arguments = parse_args()
    try:
        copied = copy_package(arguments.package_zip, arguments.target, arguments.dry_run)
    except (OSError, ValueError, zipfile.BadZipFile) as error:
        print(f"Gagal menyalin paket: {error}", file=sys.stderr)
        return 1

    action = "akan disalin" if arguments.dry_run else "berhasil disalin"
    print(f"{copied} file {action} ke {arguments.target.expanduser().resolve()}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
