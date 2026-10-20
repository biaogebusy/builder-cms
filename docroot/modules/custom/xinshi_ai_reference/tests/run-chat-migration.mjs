import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

// The parent runner owns this new, network-disabled container and removes it on exit.
const [container, frontend] = process.argv.slice(2);
const metadata = JSON.parse(execFileSync('docker', ['inspect', container], { encoding: 'utf8' }))[0];
assert.equal(metadata.HostConfig.NetworkMode, 'none');
assert.deepEqual(metadata.Mounts, []);
process.umask(0o077);
const workspace = mkdtempSync(join(tmpdir(), 'cms-chat-migration-test-'));
const root = '/tmp/analytics-test/chat-migration';
const fixtureScript = '/app/docroot/modules/custom/xinshi_ai_reference/tests/chat-migration.integration.php';
const script = '/app/scripts/migrate-chat-sessions.php';
const drush = (file, args, success = true) => {
  const result = spawnSync('docker', ['exec', '-e', 'XINSHI_ANALYTICS_ISOLATED_TEST=1',
    container, 'php', '-d', 'extension=pdo_sqlite', '/app/vendor/drush/drush/drush.php',
    '--root=/app/docroot', '--uri=http://127.0.0.1:8080', 'php:script', file, '--', ...args],
  { encoding: 'utf8', timeout: 120_000, maxBuffer: 16 * 1024 * 1024 });
  if (success) {
    assert.equal(result.status, 0, result.stderr + result.stdout);
    process.stdout.write(result.stdout);
    return result.stdout;
  }
  assert.equal(result.status, 1, result.stderr + result.stdout);
  return result.stderr;
};
const fixture = (...args) => drush(fixtureScript, args);
const parseLast = value => JSON.parse(value.trim().split('\n').at(-1));
try {
  const { site } = parseLast(fixture('setup'));
  for (const plan of [false, true]) {
    const name = plan ? 'plan' : 'fields';
    const exported = root + '/' + name + '-export';
    const proposed = root + '/' + name + '-proposals';
    const state = root + '/' + name + '-state';
    const version = plan ? 3 : 2;
    const command = (operation, success = true) => drush(script,
      [...(plan ? ['plan'] : []), operation, site, exported,
        ...(operation === 'export' ? [] : [proposed]),
        ...(['apply', 'rollback', 'activate'].includes(operation) ? [state] : []),
        'writers-stopped'], success);
    command('export');
    const input = join(workspace, name + '.ndjson');
    execFileSync('docker', ['cp', container + ':' + exported + '/sessions.ndjson', input]);
    let output = join(workspace, name + '-proposals');
    let report = JSON.parse(execFileSync(process.execPath,
      [resolve(frontend, 'scripts/prepare-chat-session-migration.mjs'),
        ...(plan ? ['--plan-context'] : []), input, output], { encoding: 'utf8' }));
    if (plan) {
      assert.equal(report.counts.exceptions, 6, 'Legacy Plan records fail closed by default');
      execFileSync('docker', ['cp', output, container + ':' + proposed]);
      assert.match(command('install', false), /incomplete_or_mismatched_plan/);
      execFileSync('docker', ['exec', container, 'mv', proposed, proposed + '-strict']);
      const entries = readFileSync(join(output, 'proposals.ndjson'), 'utf8').trim().split('\n')
        .map(line => JSON.parse(line)).filter(entry => entry.status === 'exception');
      const accepted = join(workspace, 'plan-exceptions.json');
      writeFileSync(accepted, JSON.stringify({ version: 1, operation: 'preserve-plan-exceptions',
        inputSha256: report.inputSha256,
        records: entries.map(({ sourceSha256, code }) => ({ sourceSha256, code })),
      }));
      output = join(workspace, name + '-accepted');
      report = JSON.parse(execFileSync(process.execPath,
        [resolve(frontend, 'scripts/prepare-chat-session-migration.mjs'), '--plan-context',
          '--plan-exceptions', accepted, input, output], { encoding: 'utf8' }));
      assert.equal(report.preservedExceptions.count, 6);
    }
    assert.equal(report.counts.exceptions, 0);
    assert.ok(report.counts.converted > 60, 'All original revisions and translations are converted');
    execFileSync('docker', ['cp', output, container + ':' + proposed]);
    if (plan) {
      const accepted = proposed + '/plan-exceptions.json';
      execFileSync('docker', ['exec', container, 'mv', accepted, accepted + '-withheld']);
      assert.match(command('install', false), /invalid_manifest/);
      execFileSync('docker', ['exec', container, 'mv', accepted + '-withheld', accepted]);
    }
    command('install');
    fixture('fail', name);
    assert.match(command('apply', false), /Chat migration stopped/);
    const partial = parseLast(command('reconcile')).counts;
    assert.ok(partial.applied > 0 && partial.pending > 0,
      'A second-batch SQL failure retains earlier commits and rolls back its own batch');
    fixture('unfail');
    command('apply');
    assert.equal(parseLast(command('apply')).changed, 0, 'Retry never reapplies an already committed row');
    command('activate');
    fixture('verify', String(version));
    command('rollback');
    fixture('verify', String(version - 1));
    assert.equal(parseLast(command('rollback')).changed, 0, 'Rollback is idempotent');
    command('apply');
    command('activate');
    console.log('PASS: ' + name + ' actual export/prepare/install/apply/activate/rollback round trip');
    if (plan) {
      fixture('v3-cas');
      assert.match(command('rollback', false), /source_or_target_rows_changed/);
      console.log('PASS: Newly committed CMS facts prohibit restoring the old migration snapshot');
    }
  }
} finally {
  rmSync(workspace, { recursive: true, force: true });
}
