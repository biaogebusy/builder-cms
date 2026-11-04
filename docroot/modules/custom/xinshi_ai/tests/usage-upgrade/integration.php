<?php

declare(strict_types=1);

/**
 * @file
 * Exercises the missing usage dependency with actual Drush and synthetic SQLite data.
 */

use Drupal\Core\DrupalKernel;
use Drupal\user\Entity\Role;
use Drupal\xinshi_ai\Service\ImageUsageRecorder;
use Drupal\xinshi_ai\Service\PromptRewriteClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

if (getenv('XINSHI_CHAT_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') || dirname(__DIR__, 6) !== '/app') {
  throw new RuntimeException('Use a disposable source-copy container.');
}
$complete = FALSE;
register_shutdown_function(static function () use (&$complete): void {
  if (!$complete) { exit(1); }
});
$loader = require '/app/vendor/autoload.php';
$module = dirname(__DIR__, 2);
$install = file_get_contents($module . '/xinshi_ai.install');
$services = Yaml::parseFile($module . '/xinshi_ai.services.yml')['services'];
// Isolate the real usage service definitions from unrelated AI providers and content configuration.
rename($module . '/config/install', $module . '/config/fixture-unused');
file_put_contents($module . '/xinshi_ai.info.yml', Yaml::dump([
  'name' => 'AI usage upgrade fixture', 'type' => 'module', 'core_version_requirement' => '^11',
  'dependencies' => ['drupal:user'],
]));
file_put_contents($module . '/xinshi_ai.module', '<?php');
file_put_contents($module . '/xinshi_ai.install', '<?php');
file_put_contents($module . '/xinshi_ai.services.yml', Yaml::dump(['services' => [
  'logger.channel.xinshi_ai' => $services['logger.channel.xinshi_ai'],
]], 8));
require dirname(__DIR__, 3) . '/xinshi_chat/tests/install-fixture.php';
\Drupal::service('module_installer')->install(['xinshi_ai']);
\Drupal::service('update.update_hook_registry')->setInstalledVersion('xinshi_ai', 10007);
\Drupal::configFactory()->getEditable('xinshi_ai.settings')->set('fixture_site_value', 'preserve')->save();
$permissions = Role::load('authenticated')->getPermissions();
file_put_contents($module . '/xinshi_ai.install', $install);
file_put_contents($module . '/xinshi_ai.services.yml', Yaml::dump(['services' =>
  array_intersect_key($services, array_flip([
    'logger.channel.xinshi_ai', 'xinshi_ai.image_usage_recorder', 'xinshi_ai.prompt_rewrite',
  ]))], 8));
file_put_contents($module . '/xinshi_ai.info.yml', Yaml::dump([
  'name' => 'AI usage upgrade fixture', 'type' => 'module', 'core_version_requirement' => '^11',
  'dependencies' => ['drupal:user', 'xinshi_ai_usage:xinshi_ai_usage'],
]));

$checks = 0;
function check(bool $condition, string $message): void {
  if (!$condition) { throw new RuntimeException($message); }
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}
function drush(array $arguments, bool $success = TRUE): string {
  $command = array_merge(['php', '/app/vendor/drush/drush/drush.php', '--root=/app/docroot', '--uri=http://chat-test'], $arguments);
  $pipes = [];
  $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, '/app');
  if (!is_resource($process)) { throw new RuntimeException('Cannot start Drush.'); }
  $output = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  $status = proc_close($process);
  echo $output;
  check($success ? $status === 0 : $status !== 0, implode(' ', $arguments) . ' has the expected exit status');
  return $output;
}
function boot(): void {
  $request = Request::create('http://chat-test/');
  $kernel = DrupalKernel::createFromRequest($request, $GLOBALS['loader'], 'prod');
  $kernel->boot();
  $kernel->preHandle($request);
}

check(!\Drupal::moduleHandler()->moduleExists('xinshi_ai_usage'), 'Old installation has no usage module');
$failure = drush(['cache:rebuild'], FALSE);
check(str_contains($failure, 'xinshi_ai.image_usage_recorder') && str_contains($failure, 'xinshi_ai_usage.local_producer'),
  'Cache rebuild reproduces the reported missing service');
drush(['updatedb', '-y']);
drush(['cache:rebuild']);
boot();
check(\Drupal::moduleHandler()->moduleExists('xinshi_ai_usage'), 'Update installs the required usage module');
check(\Drupal::service('update.update_hook_registry')->getInstalledVersion('xinshi_ai') === 10008,
  'Drush records the completed AI update');
check(\Drupal::service('xinshi_ai.image_usage_recorder') instanceof ImageUsageRecorder,
  'The actual image usage recorder can be constructed');
check(\Drupal::service('xinshi_ai.prompt_rewrite') instanceof PromptRewriteClient,
  'The actual prompt rewrite client can be constructed');
require_once dirname($module) . '/xinshi_ai_usage/xinshi_ai_usage.install';
foreach (array_keys(xinshi_ai_usage_schema()) as $table) {
  check(\Drupal::database()->schema()->tableExists($table), 'Usage schema exists: ' . $table);
}
check(\Drupal::config('xinshi_ai.settings')->get('fixture_site_value') === 'preserve', 'Site AI configuration is preserved');
check(Role::load('authenticated')->getPermissions() === $permissions, 'Role permissions are preserved');

// Repeat the update on an installation that already has usage enabled and retained records.
\Drupal::database()->insert('ai_usage_event')->fields([
  'site_id' => 'fixture-site', 'producer_id' => 'cms-image', 'event_id' => 'fixture-event',
  'event_type' => 'attempt_intent', 'schema_version' => 1, 'operation_id' => 'fixture-operation',
  'attempt_id' => 'fixture-attempt', 'observation_revision' => 1, 'payload_hash' => hash('sha256', '{}'),
  'occurred_at' => 1, 'received_at' => 1, 'processed_at' => 1, 'payload_json' => '{}',
])->execute();
$retainedEvents = \Drupal::database()->select('ai_usage_event')->fields('ai_usage_event')->execute()->fetchAll(\PDO::FETCH_ASSOC);
\Drupal::state()->set('usage_upgrade.fixture', ['retained' => TRUE]);
\Drupal::service('update.update_hook_registry')->setInstalledVersion('xinshi_ai', 10007);
drush(['updatedb', '-y']);
drush(['cache:rebuild']);
boot();
check(\Drupal::database()->select('ai_usage_event')->fields('ai_usage_event')->execute()->fetchAll(\PDO::FETCH_ASSOC) === $retainedEvents,
  'Repeated update preserves existing usage records exactly');
check(\Drupal::state()->get('usage_upgrade.fixture') === ['retained' => TRUE], 'Existing site state survives a repeated update');
check(\Drupal::config('xinshi_ai.settings')->get('fixture_site_value') === 'preserve', 'Repeated update preserves AI settings');
check(Role::load('authenticated')->getPermissions() === $permissions, 'Repeated update preserves role permissions');
check(\Drupal::service('xinshi_ai.image_usage_recorder') instanceof ImageUsageRecorder, 'Already enabled usage remains available');
echo 'AI usage upgrade checks passed: ' . $checks . PHP_EOL;
$complete = TRUE;
