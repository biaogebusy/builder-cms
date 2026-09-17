"""Read a private manifest on stdin and report local file integrity on stdout."""

import argparse
from collections import Counter
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import stat
import sys
import time
from urllib.parse import unquote


SOURCE_SHA256 = 'b0ff2fd5f415fa9aed04a26625a758f1e6108bd4034c76c41fc272d09eea90ca'


def resolve_public_path(root, uri):
    root = Path(root).resolve()
    if not isinstance(uri, str) or not uri.startswith('public://'):
        raise ValueError('unsupported_uri')
    relative = uri[len('public://'):]
    if not relative or '\x00' in relative or '\\' in relative:
        raise ValueError('invalid_relative_path')
    path = PurePosixPath(relative)
    if path.is_absolute() or '..' in path.parts:
        raise ValueError('path_outside_files_root')
    resolved = (root / str(path)).resolve()
    try:
        resolved.relative_to(root)
    except ValueError:
        raise ValueError('path_outside_files_root') from None
    return resolved


def inspect_file(root, record):
    result = {key: record[key] for key in ['fid', 'uri', 'filesize', 'kind'] if key in record}
    try:
        path = resolve_public_path(root, record['uri'])
        before = path.stat()
        if not stat.S_ISREG(before.st_mode):
            return dict(result, status='not_regular_file')
        digest = hashlib.sha256()
        with path.open('rb') as source:
            opened = os.fstat(source.fileno())
            for chunk in iter(lambda: source.read(1024 * 1024), b''):
                digest.update(chunk)
            after = os.fstat(source.fileno())
        signature = lambda item: (item.st_dev, item.st_ino, item.st_size, item.st_mtime_ns)
        if signature(before) != signature(opened) or signature(opened) != signature(after):
            return dict(result, status='changed_during_read')
        result.update(actual_bytes=after.st_size, sha256=digest.hexdigest())
        expected = record.get('filesize')
        result['status'] = 'size_mismatch' if expected is not None and int(expected) != after.st_size else 'verified'
    except FileNotFoundError:
        result['status'] = 'missing'
        # A differently decoded name is evidence of a mismatch, not a substitute.
        decoded = unquote(record['uri'], errors='strict')
        if decoded != record['uri']:
            try:
                result['decoded_variant_exists'] = resolve_public_path(root, decoded).is_file()
            except (OSError, ValueError):
                result['decoded_variant_exists'] = False
    except PermissionError:
        result['status'] = 'unreadable'
    except ValueError as error:
        result['status'] = 'invalid_path'
        result['reason'] = str(error)
    except OSError as error:
        result.update(status='io_error', errno=error.errno)
    return result


def verify_manifest(root, manifest, progress=None):
    if manifest.get('source_sha256') != SOURCE_SHA256:
        raise ValueError('source_snapshot_mismatch')
    root = Path(root).resolve(strict=True)
    if not root.is_dir():
        raise ValueError('files_root_is_not_a_directory')
    started = time.monotonic()
    records = [dict(record, kind='managed') for record in manifest['managed']]
    records.extend(manifest['unregistered'])
    allowed = {'managed', 'webp_sidecar', 'literal_candidate', 'template_or_example'}
    if any(record.get('kind') not in allowed for record in records):
        raise ValueError('unreviewed_manifest_category')
    rows = []
    for index, record in enumerate(records, 1):
        if record['kind'] == 'template_or_example':
            rows.append(dict(record, status='manual_reference_review'))
        else:
            rows.append(inspect_file(root, record))
        if progress and index % 100 == 0:
            progress({'checked_records': index, 'total_records': len(records)})
    summary = {
        kind: dict(Counter(row['status'] for row in rows if row['kind'] == kind))
        for kind in sorted(allowed)
    }
    failures = sum(row['status'] != 'verified' for row in rows if row['kind'] != 'template_or_example')
    return {
        'status': 'needs_attention' if failures else 'passed_required_paths',
        'source_sha256': manifest['source_sha256'],
        'files_root': str(root),
        'execution_uid': os.geteuid(),
        'physical_files_checked': True,
        'source_file_digests_available': False,
        'application_user_access_verified': False,
        'summary': summary,
        'required_path_failures': failures,
        'manual_reference_count': sum(row['kind'] == 'template_or_example' for row in rows),
        'elapsed_seconds': round(time.monotonic() - started, 2),
        'files': rows,
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', required=True)
    arguments = parser.parse_args()
    try:
        report = verify_manifest(
            arguments.root,
            json.load(sys.stdin),
            progress=lambda value: print(json.dumps(value), file=sys.stderr, flush=True),
        )
    except (OSError, ValueError, KeyError, TypeError) as error:
        print(json.dumps({'status': 'input_error', 'error_class': type(error).__name__}))
        return 2
    print(json.dumps(report, ensure_ascii=False))
    return 1 if report['required_path_failures'] else 0


if __name__ == '__main__':
    sys.exit(main())
