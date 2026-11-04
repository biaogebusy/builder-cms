<?php

declare(strict_types=1);

/**
 * @file
 * Repairs a broken synthetic site's raw metadata, then runs actual Drush updates.
 */

use Drupal\Core\Config\DatabaseStorage;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\DrupalKernel;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\xinshi_ai\Service\ImageUsageRecorder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

if (getenv('XINSHI_CHAT_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') || dirname(__DIR__, 3) !== '/app') {
  throw new RuntimeException('Use the disposable source-copy runner.');
}
$complete = FALSE;
register_shutdown_function(static function () use (&$complete): void {
  if (!$complete) { exit(1); }
});
$loader = require '/app/vendor/autoload.php';
$module = '/app/docroot/modules/custom/xinshi_ai';
$install = file_get_contents($module . '/xinshi_ai.install');
$services = Yaml::parseFile($module . '/xinshi_ai.services.yml')['services'];
// Retain actual image usage classes while excluding unrelated provider installation.
rename($module . '/config/install', $module . '/config/fixture-unused');
file_put_contents($module . '/xinshi_ai.info.yml', Yaml::dump([
  'name' => 'Repair fixture AI', 'type' => 'module', 'core_version_requirement' => '^11',
  'dependencies' => ['drupal:user'],
]));
file_put_contents($module . '/xinshi_ai.install', '<?php');
file_put_contents($module . '/xinshi_ai.module', '<?php');
file_put_contents($module . '/xinshi_ai.services.yml', Yaml::dump(['services' => []]));
require '/app/docroot/modules/custom/xinshi_chat/tests/install-fixture.php';
\Drupal::service('module_installer')->install(['layout_builder', 'block_content', 'editor', 'search', 'jsonapi', 'xinshi_ai']);
\Drupal::service('update.update_hook_registry')->setInstalledVersion('xinshi_ai', 10007);
BlockContentType::create(['id' => 'repair_fixture', 'label' => 'Repair fixture'])->save();
FieldStorageConfig::create(['entity_type' => 'block_content', 'field_name' => 'body', 'type' => 'string_long'])->save();
FieldConfig::create(['entity_type' => 'block_content', 'bundle' => 'repair_fixture', 'field_name' => 'body'])->save();
$block = BlockContent::create(['type' => 'repair_fixture', 'info' => 'Retained JSON', 'body' => '{"body":[{"type":"hero-1v1"}]}']);
$block->save();
foreach (['job', 'landing_page'] as $bundle) {
  NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
  EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE])
    ->setThirdPartySetting('layout_builder', 'enabled', TRUE)
    ->setThirdPartySetting('layout_builder', 'allow_custom', TRUE)->save();
}
$node = Node::create(['type' => 'landing_page', 'title' => 'Retained latest landing page', 'status' => 1]);
$section = new Section('layout_onecol');
$section->appendComponent(new SectionComponent(\Drupal::service('uuid')->generate(), 'content', [
  'id' => 'block_content:' . $block->uuid(), 'label' => 'Retained component', 'label_display' => FALSE,
  'provider' => 'block_content',
]));
$node->get('layout_builder__layout')->appendItem(['section' => $section]);
$node->save();
$db = \Drupal::database();
$storage = new DatabaseStorage($db, 'config');
$retired = ['ckeditor', 'ckeditor_textindent', 'codesnippet', 'color', 'conflict', 'key_value',
  'panelizer', 'panels', 'panels_ipe', 'rdf', 'switch_page_theme'];
$extension = $storage->read('core.extension');
foreach ([...$retired, 'xinshi_ai_usage', 'search_node'] as $name) { $extension['module'][$name] = 0; }
$storage->write('core.extension', $extension);
foreach ($retired as $name) { \Drupal::keyValue('system.schema')->set($name, 8000); }
foreach (['job', 'landing_page'] as $bundle) {
  $storage->write('field.field.node.' . $bundle . '.panelizer', [
    'id' => 'node.' . $bundle . '.panelizer', 'field_name' => 'panelizer', 'entity_type' => 'node',
    'bundle' => $bundle, 'field_type' => 'panelizer', 'settings' => [], 'dependencies' => ['module' => ['panelizer']],
  ]);
  $name = 'core.entity_view_display.node.' . $bundle . '.default';
  $display = $storage->read($name);
  $display['content']['panelizer'] = ['type' => 'panelizer'];
  $display['hidden']['panelizer'] = TRUE;
  $display['third_party_settings']['panelizer'] = ['enable' => TRUE];
  $display['dependencies']['config'][] = 'field.field.node.' . $bundle . '.panelizer';
  $display['dependencies']['module'][] = 'panelizer';
  $storage->write($name, $display);
}
$storage->write('field.storage.node.panelizer', ['id' => 'node.panelizer', 'field_name' => 'panelizer',
  'entity_type' => 'node', 'type' => 'panelizer', 'settings' => []]);
$storage->write('editor.editor.repair_legacy', ['format' => 'repair_legacy', 'editor' => 'ckeditor',
  'settings' => ['toolbar' => ['rows' => [['Bold', 'Italic']]]]]);
foreach (['node__panelizer', 'node_revision__panelizer'] as $table) {
  $db->schema()->createTable($table, ['fields' => ['payload' => ['type' => 'blob', 'not null' => TRUE]]]);
  $db->insert($table)->fields(['payload' => "retained\x00legacy"])->execute();
}
file_put_contents($module . '/xinshi_ai.install', $install);
file_put_contents($module . '/xinshi_ai.services.yml', Yaml::dump(['services' =>
  array_intersect_key($services, array_flip(['logger.channel.xinshi_ai', 'xinshi_ai.image_usage_recorder', 'xinshi_ai.prompt_rewrite']))], 8));
file_put_contents($module . '/xinshi_ai.info.yml', Yaml::dump([
  'name' => 'Repair fixture AI', 'type' => 'module', 'core_version_requirement' => '^11',
  'dependencies' => ['drupal:user', 'xinshi_ai_usage:xinshi_ai_usage'],
]));
$checks = 0;
function repairCheck(bool $condition, string $message): void {
  if (!$condition) { throw new RuntimeException($message); }
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}
function repairRun(array $command, bool $success = TRUE): string {
  $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, '/app');
  if (!is_resource($process)) { throw new RuntimeException('Cannot start CLI.'); }
  $output = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  $status = proc_close($process);
  if ($success ? $status !== 0 : $status === 0) { fwrite(STDERR, $output); }
  repairCheck($success ? $status === 0 : $status !== 0, implode(' ', array_slice($command, 1, 2)) . ' expected exit status');
  return $output;
}
function repairRows(string $table): array {
  return $GLOBALS['db']->select($table, 't')->fields('t')->execute()->fetchAll(PDO::FETCH_ASSOC);
}
function repairMetadata(): array {
  return [repairRows('config'), repairRows('key_value')];
}
function repairWith(array $command, string $option, string $value): array {
  return [...array_filter($command, static fn($argument) => !str_starts_with($argument, $option)), $option . $value];
}
$command = ['php', '/app/scripts/repair-retired-layout-metadata.php', '--uri=http://chat-test', '--site-path=sites/default'];
$drush = ['php', '/app/vendor/drush/drush/drush.php', '--root=/app/docroot', '--uri=http://chat-test'];
$failure = repairRun([...$drush, 'cache:rebuild'], FALSE);
repairCheck(str_contains($failure, 'panelizer'), 'Actual cache rebuild reproduces the missing Panelizer plugin');
$before = repairMetadata();
$content = [];
foreach (['node', 'node_field_data', 'node_revision', 'node_field_revision', 'block_content', 'block_content_field_data',
  'block_content_revision', 'block_content_field_revision', 'block_content__body', 'block_content_revision__body',
  'node__layout_builder__layout', 'node_revision__layout_builder__layout', 'node__panelizer', 'node_revision__panelizer'] as $table) {
  $content[$table] = repairRows($table);
}
$preview = json_decode(repairRun($command), TRUE, flags: JSON_THROW_ON_ERROR);
repairCheck(repairMetadata() === $before && !file_exists('/tmp/chat-test/backup.json'), 'Preview changes no metadata or content and writes no backup');
repairCheck($preview['blockers'] === [] && $preview['remove_module_flags'] === $retired, 'Exact retired flags are previewed');
repairCheck($preview['reset_incomplete_modules'] === ['xinshi_ai_usage', 'search_node'], 'Interrupted registrations without installation data are reset');
$apply = [...$command, '--apply', '--layout-migrated', '--confirm=' . $preview['confirmation_hash'], '--backup=/tmp/chat-test/backup.json'];
foreach ([
  [...$command, '--apply'],
  [...$command, '--apply', '--confirm=' . $preview['confirmation_hash'], '--backup=/tmp/chat-test/backup.json'],
  repairWith($command, '--site-path=', 'sites/wrong'),
  repairWith($apply, '--confirm=', 'stale'),
  repairWith($apply, '--backup=', '/app/docroot/unsafe.json'),
  repairWith($apply, '--backup=', '/missing-directory/backup.json'),
  repairWith($apply, '--backup=', '/app/docroot/sites/default/settings.php'),
] as $invalid) {
  repairRun($invalid, FALSE);
  repairCheck(repairMetadata() === $before, 'Rejected invocation preserves metadata');
}
// An unexpected retired field cannot be inferred to have completed migration.
$storage->write('field.field.node.other.panelizer', ['entity_type' => 'node', 'field_name' => 'panelizer', 'field_type' => 'panelizer']);
$blocked = repairMetadata();
repairRun($apply, FALSE);
repairCheck(repairMetadata() === $blocked, 'Unknown Panelizer field prevents mutation');
$storage->delete('field.field.node.other.panelizer');
$field = $storage->read('field.storage.node.panelizer');
$storage->write('field.storage.node.panelizer', [...$field, 'type' => 'string_long']);
$blockedPreview = json_decode(repairRun($command), TRUE, flags: JSON_THROW_ON_ERROR);
repairCheck(in_array('Unexpected field type in field.storage.node.panelizer', $blockedPreview['blockers'], TRUE), 'A known field name with another type blocks removal');
repairRun($apply, FALSE);
$storage->write('field.storage.node.panelizer', $field);
$extension['module']['unreviewed_missing'] = 0;
$storage->write('core.extension', $extension);
$blocked = repairMetadata();
repairRun($apply, FALSE);
repairCheck(repairMetadata() === $blocked, 'Unknown missing module prevents mutation');
unset($extension['module']['unreviewed_missing']);
$storage->write('core.extension', $extension);
$db->schema()->createTable('ai_usage_event', ['fields' => ['payload' => ['type' => 'text']]]);
$db->insert('ai_usage_event')->fields(['payload' => 'existing usage'])->execute();
$blockedPreview = json_decode(repairRun($command), TRUE, flags: JSON_THROW_ON_ERROR);
repairCheck(str_contains(implode(' ', $blockedPreview['blockers']), 'table ai_usage_event'), 'Existing usage table blocks incomplete registration reset');
repairRun($apply, FALSE);
repairCheck(repairRows('ai_usage_event') === [['payload' => 'existing usage']], 'Existing usage facts are retained on rejection');
$db->schema()->dropTable('ai_usage_event');
$storage->write('search.page.node_search', ['id' => 'node_search', 'plugin' => 'node_search']);
$searchConfig = $storage->read('search.page.node_search');
$searchPreview = json_decode(repairRun($command), TRUE, flags: JSON_THROW_ON_ERROR);
repairCheck($searchPreview['blockers'] === [] && in_array('search.page.node_search', $searchPreview['retained_search_node_config'], TRUE), 'Transferred Search optional configuration is retained during reset');
$dependencyPath = '/app/docroot/modules/custom/repair_dependency';
mkdir($dependencyPath);
file_put_contents($dependencyPath . '/repair_dependency.info.yml', Yaml::dump([
  'name' => 'Retained dependency', 'type' => 'module', 'core_version_requirement' => '^11', 'dependencies' => ['legacy:panelizer (>=1)'],
]));
$extension['module']['repair_dependency'] = 0;
$storage->write('core.extension', $extension);
$blockedPreview = json_decode(repairRun($command), TRUE, flags: JSON_THROW_ON_ERROR);
repairCheck(in_array('repair_dependency still requires panelizer', $blockedPreview['blockers'], TRUE), 'Surviving module dependencies block removal');
repairRun($apply, FALSE);
unset($extension['module']['repair_dependency']);
$storage->write('core.extension', $extension);
// The last config write fails after earlier deletes; all database changes must roll back.
$beforeApply = repairMetadata();
$preview = json_decode(repairRun($command), TRUE, flags: JSON_THROW_ON_ERROR);
$apply = [...$command, '--apply', '--layout-migrated', '--confirm=' . $preview['confirmation_hash'], '--backup=/tmp/chat-test/backup.json'];
$db->getClientConnection()->exec(getenv('XINSHI_CHAT_TEST_MYSQL') === '1'
  ? "CREATE TRIGGER repair_abort BEFORE UPDATE ON config FOR EACH ROW BEGIN IF NEW.name = 'core.extension' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture write failure'; END IF; END"
  : "CREATE TRIGGER repair_abort BEFORE UPDATE ON config WHEN NEW.name = 'core.extension' BEGIN SELECT RAISE(ABORT, 'fixture write failure'); END");
repairRun(repairWith($apply, '--backup=', '/tmp/chat-test/rollback.json'), FALSE);
repairCheck(repairMetadata() === $beforeApply && file_exists('/tmp/chat-test/rollback.json'), 'Write failure rolls back prior deletes after backing up');
$db->query('DROP TRIGGER repair_abort');
repairRun(repairWith($apply, '--backup=', '/tmp/chat-test/rollback.json'), FALSE);
repairCheck(repairMetadata() === $beforeApply, 'An existing backup cannot be overwritten');
$result = json_decode(repairRun($apply), TRUE, flags: JSON_THROW_ON_ERROR);
repairCheck($result['status'] === 'applied', 'Confirmed repair commits successfully');
$snapshot = json_decode(file_get_contents('/tmp/chat-test/backup.json'), TRUE, flags: JSON_THROW_ON_ERROR);
repairCheck((fileperms('/tmp/chat-test/backup.json') & 0777) === 0600, 'Backup is readable only by its owner');
foreach (['config' => 0, 'system_schema' => 1] as $type => $index) {
  $original = array_column($beforeApply[$index], NULL, 'name');
  foreach ($snapshot[$type] as $name => $row) {
    $column = $type === 'config' ? 'data' : 'value';
    $row[$column] = base64_decode($row[$column], TRUE);
    repairCheck($row === $original[$name], 'Backup retains exact raw row: ' . $name);
  }
}
$afterConfig = array_column(repairRows('config'), NULL, 'name');
$afterSchema = array_column(repairRows('key_value'), NULL, 'name');
foreach ($beforeApply[1] as $row) {
  if ($row['collection'] === 'system.schema' && in_array($row['name'], $retired, TRUE)) {
    repairCheck(!isset($afterSchema[$row['name']]), 'Retired schema record is removed: ' . $row['name']);
  }
  else {
    $matches = array_filter(repairRows('key_value'), static fn($current) => $current === $row);
    repairCheck($matches !== [], 'Unrelated key/value record is preserved: ' . $row['collection'] . '/' . $row['name']);
  }
}
$repairedExtension = $storage->read('core.extension');
repairCheck(array_intersect([...$retired, 'xinshi_ai_usage', 'search_node'], array_keys($repairedExtension['module'])) === [], 'Exactly the retired and incomplete flags are removed');
foreach ($beforeApply[0] as $row) {
  if (in_array($row['name'], $result['remove_field_config'], TRUE)) {
    repairCheck(!isset($afterConfig[$row['name']]), 'Only the confirmed retired field config is deleted: ' . $row['name']);
  }
  elseif (!in_array($row['name'], $result['update_config'], TRUE)) {
    repairCheck($afterConfig[$row['name']] === $row, 'Unrelated config is preserved: ' . $row['name']);
  }
}
foreach (['job', 'landing_page'] as $bundle) {
  $name = 'core.entity_view_display.node.' . $bundle . '.default';
  $old = unserialize(array_column($beforeApply[0], 'data', 'name')[$name]);
  repairCheck($storage->read($name)['third_party_settings']['layout_builder'] === $old['third_party_settings']['layout_builder'], 'Layout Builder settings survive: ' . $bundle);
}
foreach ($content as $table => $rows) { repairCheck(repairRows($table) === $rows, 'Content and history preserved: ' . $table); }
repairRun($apply, FALSE);
repairCheck(repairRows('node__panelizer') === $content['node__panelizer'], 'Repeated apply rejects the old hash and retains legacy data');
$again = json_decode(repairRun($command), TRUE, flags: JSON_THROW_ON_ERROR);
repairCheck($again['remove_field_config'] === [] && $again['update_config'] === [] && $again['blockers'] === [], 'Repeated preview has no pending changes');
echo repairRun([...$drush, 'updatedb', '-y']);
echo repairRun([...$drush, 'pm:install', 'search_node', '-y']);
echo repairRun([...$drush, 'cache:rebuild']);
$request = Request::create('http://chat-test');
$kernel = DrupalKernel::createFromRequest($request, $loader, 'prod');
$kernel->boot();
$kernel->preHandle($request);
repairCheck(\Drupal::service('update.update_hook_registry')->getInstalledVersion('xinshi_ai') === 10008, 'Actual Drush completes AI update 10008');
repairCheck(\Drupal::service('xinshi_ai.image_usage_recorder') instanceof ImageUsageRecorder, 'Actual image usage service is available');
require_once '/app/docroot/modules/custom/xinshi_ai_usage/xinshi_ai_usage.install';
foreach (array_keys(xinshi_ai_usage_schema()) as $table) { repairCheck($db->schema()->tableExists($table), 'Usage table installed: ' . $table); }
repairCheck(\Drupal::moduleHandler()->moduleExists('search_node'), 'Search node installs normally after metadata repair');
repairCheck($storage->read('search.page.node_search') === $searchConfig, 'Search node installation retains the previous search page configuration');
repairCheck(Node::load($node->id())->label() === 'Retained latest landing page', 'Landing page loads after cache rebuild');
repairCheck(BlockContent::load($block->id())->get('body')->value === '{"body":[{"type":"hero-1v1"}]}', 'Component JSON loads unchanged after cache rebuild');
repairCheck($storage->read('editor.editor.repair_legacy')['editor'] === 'ckeditor', 'Legacy editor configuration remains for separate conversion');
echo 'Retired layout repair checks passed: ' . $checks . PHP_EOL;
$complete = TRUE;
