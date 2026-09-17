"""Export a validated local Docker migration as a private deployment SQL archive."""

import argparse
from datetime import datetime, timezone
import gzip
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys


def sha256(path):
    result = hashlib.sha256()
    with path.open('rb') as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b''):
            result.update(chunk)
    return result.hexdigest()


def require(condition, message):
    if not condition:
        raise ValueError(message)


def main():
    os.umask(0o077)
    project = Path(__file__).resolve().parents[2]
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--batch', type=Path, default=project / '.migration-private/batch')
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--container', default='builder-pro')
    parser.add_argument('--database-container', default='mariadb')
    parser.add_argument('--app-root', default='/var/www/html')
    args = parser.parse_args()
    batch = args.batch.resolve(strict=True)
    destination = args.output.resolve()
    public = (project / 'docroot').resolve()
    require(public not in batch.parents and batch != public, 'The batch must be outside docroot.')
    require(public not in destination.parents, 'The SQL archive must be outside docroot.')
    require(destination.name.endswith('.sql.gz'), 'Use a private .sql.gz output path.')
    partial = Path(str(destination) + '.partial')
    report_path = Path(str(destination) + '.report.json')
    require(not any(path.exists() for path in [destination, partial, report_path]), 'Existing output or partial export must be reviewed, not replaced.')
    runtime = json.loads((batch / 'batch-runtime.json').read_text())
    require(runtime.get('environment') == 'local', 'This exporter is for a locally validated migration.')
    manifest = json.loads((batch / 'input-manifest.json').read_text())
    for name, expected in manifest['files'].items():
        path = batch / name
        require(not Path(name).is_absolute() and '..' not in Path(name).parts, 'Invalid input manifest path.')
        require(batch in path.resolve().parents and sha256(path) == expected, 'Reviewed migration input changed.')
    plan = json.loads((batch / 'full-snapshot-plan.private.json').read_text())
    dispositions = json.loads((batch / 'source-exception-dispositions.private.json').read_text())['items']
    accepted = runtime.get('accepted_source_exceptions', [])
    require(not set(accepted) - set(dispositions), 'Unknown source exception decision.')
    require(all(item['approval'] == 'approved' or key in accepted for key, item in dispositions.items()), 'Source exception decisions are still pending.')
    verified_reports = {}
    for name in ['model-validation-report.json', 'full-snapshot-validation-report.json', 'entity-loading-report.json', 'views-and-forms-report.json', 'api-and-access-report.json']:
        path = batch / name
        require(path.is_file(), 'Required local verification report is missing: ' + name)
        value = json.loads(path.read_text())
        require(value.get('status') == 'passed' and value.get('error_count', 0) == 0, 'Local verification did not pass: ' + name)
        verified_reports[name] = sha256(path)
    validation = json.loads((batch / 'full-snapshot-validation-report.json').read_text())
    imported = json.loads((batch / 'full-snapshot-import-report.json').read_text())
    require(imported['status'] == 'imported' and validation['source_sha256'] == plan['source_sha256'], 'The verified import does not match the reviewed source.')
    require(validation['source_rows'] == imported['mapped_rows'] and len(validation['tables']) == len(plan['tables']), 'The full planned dataset must be validated before export.')
    database = runtime['target_database']
    require(re.fullmatch(r'[A-Za-z0-9_]+', database) is not None, 'Unsupported target database name.')

    # Read the actual site's default connection in memory. Credentials are never
    # printed, saved in reports, or passed as command-line argument values.
    parameters = json.dumps({'root': args.app_root, 'database': database, 'uuid': plan['target_uuid']})
    php = '''<?php
$parameters = json_decode(stream_get_contents(STDIN), TRUE, 512, JSON_THROW_ON_ERROR);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$loader = require $parameters['root'] . '/vendor/autoload.php';
\\Drupal\\Core\\Site\\Settings::initialize($parameters['root'] . '/docroot', 'sites/default', $loader);
$db = \\Drupal\\Core\\Database\\Database::getConnection();
$options = $db->getConnectionOptions();
$serialized = $db->select('config', 'c')->fields('c', ['data'])->condition('collection', '')->condition('name', 'system.site')->execute()->fetchField();
$site = unserialize($serialized, ['allowed_classes' => FALSE]);
if ($db->driver() !== 'mysql' || !empty($options['prefix']) || $db->query('SELECT DATABASE()')->fetchField() !== $parameters['database'] || ($site['uuid'] ?? NULL) !== $parameters['uuid']) {
  throw new \\RuntimeException('The live database does not match the verified target.');
}
$nonTransactional = (int) $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' AND engine <> 'InnoDB'")->fetchField();
if ($nonTransactional) { throw new \\RuntimeException('A consistent InnoDB export is required.'); }
echo json_encode(['username' => $options['username'], 'password' => $options['password'], 'site_config_sha256' => hash('sha256', $serialized), 'tables' => $db->query('SHOW TABLES')->fetchCol()], JSON_THROW_ON_ERROR);
'''
    # Supply the PHP program as an argument without credentials, and the small
    # environment description on stdin. No shell interpretation is involved.
    info = subprocess.run(['docker', 'exec', '-i', '--workdir', args.app_root + '/docroot', args.container, 'timeout', '30', 'php', '-r', php[len('<?php\n'):]], input=parameters.encode(), capture_output=True, timeout=45)
    require(info.returncode == 0, 'Cannot read the verified target connection; database export was not started.')
    metadata = json.loads(info.stdout)
    environment = dict(os.environ)
    environment['MYSQL_PWD'] = metadata.pop('password')
    username = metadata.pop('username')
    client = ['docker', 'exec', '-i', '--user', '0', '--env', 'MYSQL_PWD', args.database_container]
    identity_sql = "SELECT SHA2(data,256) FROM config WHERE collection='' AND name='system.site';\n"
    identity = subprocess.run(client + ['mariadb', '--user=' + username, '--batch', '--skip-column-names', database], input=identity_sql.encode(), capture_output=True, env=environment, timeout=30)
    require(identity.returncode == 0 and identity.stdout.decode().strip() == metadata['site_config_sha256'], 'The dump container does not contain the verified target database.')
    tables = metadata['tables']
    require(all(re.fullmatch(r'[A-Za-z0-9_]+', name) for name in tables), 'Unexpected table identifier.')
    transient = {'batch', 'sessions', 'semaphore', 'flood', 'queue', 'key_value_expire', 'watchdog'}
    omitted_data = [name for name in tables if name in transient or name.startswith('cache_') or name == 'oauth2_token' or name.startswith('oauth2_token_')]
    # All business/history tables, config, Migrate maps and ownership records are
    # retained. Only the contents of these runtime tables are omitted, not DDL.
    require(not set(omitted_data) & set(plan['tables']), 'A planned business table cannot be treated as transient.')
    destination.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    error_path = Path(str(destination) + '.errors.private.log')
    dump = client + ['mariadb-dump', '--user=' + username, '--single-transaction', '--quick', '--hex-blob', '--routines', '--events', '--triggers', '--default-character-set=utf8mb4']
    dump.extend('--ignore-table-data=' + database + '.' + name for name in omitted_data)
    dump.append(database)
    raw_hash = hashlib.sha256()
    raw_bytes = 0
    started = datetime.now(timezone.utc).isoformat()
    with error_path.open('xb') as errors, partial.open('xb') as raw_output:
        process = subprocess.Popen(dump, stdout=subprocess.PIPE, stderr=errors, env=environment)
        with gzip.GzipFile(filename='', mode='wb', fileobj=raw_output, mtime=0) as archive:
            for chunk in iter(lambda: process.stdout.read(1024 * 1024), b''):
                archive.write(chunk)
                raw_hash.update(chunk)
                raw_bytes += len(chunk)
        process.stdout.close()
        exit_code = process.wait()
    require(exit_code == 0, 'Database export failed; private diagnostics and partial output were retained.')
    created = set()
    inserted = set()
    with gzip.open(partial, 'rb') as stream:
        for line in stream:
            if line.startswith(b'CREATE TABLE `'):
                created.add(line.split(b'`', 2)[1].decode())
            elif line.startswith(b'INSERT INTO `'):
                inserted.add(line.split(b'`', 2)[1].decode())
            require(not re.match(rb'\s*(?:USE\s|CREATE\s+DATABASE|DROP\s+DATABASE)', line, re.I), 'The archive must not choose or recreate an online database.')
    require(created == set(tables), 'The exported schema does not match the target table set.')
    require(not inserted & set(omitted_data), 'Runtime table contents unexpectedly entered the deployment export.')
    # Publish without replacing a file that appeared while the dump was running.
    os.link(partial, destination)
    partial.unlink()
    result = {
        'status': 'validated_local_database_exported', 'database': database,
        'started_at': started, 'completed_at': datetime.now(timezone.utc).isoformat(),
        'file': destination.name, 'table_count': len(created),
        'runtime_tables_without_data': sorted(omitted_data),
        'archive_bytes': destination.stat().st_size, 'archive_sha256': sha256(destination),
        'uncompressed_bytes': raw_bytes, 'uncompressed_sha256': raw_hash.hexdigest(),
        'source_snapshot_sha256': plan['source_sha256'], 'input_manifest_sha256': sha256(batch / 'input-manifest.json'),
        'validated_source_rows': validation['source_rows'], 'verification_reports_sha256': verified_reports,
        'physical_files_included': False, 'physical_files_verified_locally': False,
        'production_environment_settings_required': True, 'online_database_modified': False,
    }
    report_path.write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps({key:value for key,value in result.items() if key not in ['verification_reports_sha256', 'runtime_tables_without_data']}, ensure_ascii=False))
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (OSError, ValueError, KeyError, TypeError, subprocess.SubprocessError) as error:
        print(json.dumps({'status': 'export_failed', 'reason': str(error) if isinstance(error, ValueError) and not isinstance(error, json.JSONDecodeError) else type(error).__name__}))
        sys.exit(1)
