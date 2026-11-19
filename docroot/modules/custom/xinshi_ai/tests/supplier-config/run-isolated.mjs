import { cpSync, existsSync, mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { dirname, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../../../../..');
const php = process.env.SUPPLIER_PHP_BIN || 'php';
const version = spawnSync(php, ['-r',
  'echo PHP_VERSION_ID >= 80300 && extension_loaded("pdo_sqlite") ? "ready" : "unavailable";'],
{ encoding: 'utf8' });
if (version.status !== 0 || version.stdout !== 'ready') {
  throw new Error('Set SUPPLIER_PHP_BIN to a local PHP 8.3+ executable with PDO SQLite.');
}
const workspace = mkdtempSync(join(tmpdir(), 'xinshi-supplier-tests-'));
try {
  for (const path of ['vendor', 'composer.json', 'composer.lock', 'docroot/core', 'docroot/autoload.php',
    'docroot/index.php', 'docroot/modules/custom/xinshi_ai', 'docroot/modules/custom/xinshi_ai_runtime']) {
    const target = join(workspace, path);
    mkdirSync(dirname(target), { recursive: true });
    cpSync(join(root, path), target, { recursive: true });
  }
  mkdirSync(join(workspace, 'docroot/sites/default'), { recursive: true });
  if (existsSync(join(workspace, 'docroot/sites/default/settings.php'))) throw new Error('Unexpected site settings');
  writeFileSync(join(workspace, '.supplier-fixture'), 'synthetic-only');
  const result = spawnSync(php, [join(workspace,
    'docroot/modules/custom/xinshi_ai/tests/supplier-config/integration.php')], {
    cwd: workspace, stdio: 'inherit', env: { ...process.env, XINSHI_SUPPLIER_ISOLATED_TEST: '1' },
  });
  if (result.error) throw result.error;
  process.exitCode = result.status ?? 1;
} finally {
  rmSync(workspace, { recursive: true, force: true });
}
