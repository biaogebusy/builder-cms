<?php

declare(strict_types=1);

/**
 * @file
 * Exercises the actual recovery CLI on a new synthetic Drupal database.
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Symfony\Component\Yaml\Yaml;

if (getenv('XINSHI_CHAT_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') || dirname(__DIR__, 3) !== '/app') {
  throw new RuntimeException('Use the disposable source-copy runner.');
}
$complete = FALSE;
register_shutdown_function(static function () use (&$complete): void { if (!$complete) { exit(1); } });
require '/app/vendor/autoload.php';
$editor = '/app/docroot/modules/custom/xinshi_editor';
if (is_dir($editor . '/config/install')) {
  rename($editor . '/config/install', $editor . '/config/fixture-unused');
}
file_put_contents($editor . '/xinshi_editor.info.yml', Yaml::dump([
  'name' => 'Recovery fixture editor', 'type' => 'module', 'core_version_requirement' => '^11',
  'dependencies' => ['drupal:block_content'],
]));
// Retain the actual block plugin override without unrelated editor installation.
$module = file_get_contents($editor . '/xinshi_editor.module');
preg_match('/function xinshi_editor_block_alter\(.*?\n\}/s', $module, $match);
file_put_contents($editor . '/xinshi_editor.module', '<?php ' . $match[0]);
file_put_contents($editor . '/xinshi_editor.install', '<?php');
require '/app/docroot/modules/custom/xinshi_chat/tests/install-fixture.php';
\Drupal::service('module_installer')->install(['language', 'content_translation', 'layout_builder_at', 'block_content', 'xinshi_editor']);
foreach (['zh-hans', 'zh-hant'] as $langcode) {
  ConfigurableLanguage::createFromLangcode($langcode)->save();
}
BlockContentType::create(['id' => 'recovery_fixture', 'label' => 'Recovery fixture'])->save();
FieldStorageConfig::create(['entity_type' => 'block_content', 'field_name' => 'body',
  'type' => 'string_long', 'translatable' => TRUE])->save();
FieldConfig::create(['entity_type' => 'block_content', 'bundle' => 'recovery_fixture',
  'field_name' => 'body', 'translatable' => TRUE])->save();
$blocks = [];
foreach (['a', 'b'] as $name) {
  $block = BlockContent::create(['type' => 'recovery_fixture', 'info' => $name, 'reusable' => TRUE,
    'body' => "old-$name-en"]);
  foreach (['zh-hans', 'zh-hant'] as $langcode) {
    $block->addTranslation($langcode, ['info' => "$name-$langcode", 'body' => "old-$name-$langcode"]);
  }
  $block->save();
  $old = $block->getRevisionId();
  $block->setNewRevision(TRUE);
  foreach (['en', 'zh-hans', 'zh-hant'] as $langcode) {
    $block->getTranslation($langcode)->set('body', "new-$name-$langcode");
  }
  $block->save();
  $blocks[$name] = ['uuid' => $block->uuid(), 'old' => $old, 'new' => $block->getRevisionId()];
}
foreach (['job', 'landing_page'] as $bundle) {
  NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
  EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => $bundle,
    'mode' => 'default', 'status' => TRUE, 'hidden' => ['links' => TRUE]])->save();
}
// Install shared field storage through another bundle; the target field is absent.
\Drupal::entityTypeManager()->getStorage('entity_view_display')->load('node.job.default')
  ->setOverridable(TRUE)->save();
$node = Node::create(['type' => 'landing_page', 'title' => 'First', 'status' => 1, 'changed' => 1234]);
foreach (['zh-hans', 'zh-hant'] as $langcode) {
  $node->addTranslation($langcode, ['title' => "First-$langcode", 'status' => 1]);
}
$node->save();
$versions = [$node->getRevisionId() => 'old'];
$node->setNewRevision(TRUE);
$node->setTitle('Current')->save();
$versions[$node->getRevisionId()] = 'new';
$currentVid = $node->getRevisionId();
$node->setNewRevision(TRUE);
$node->isDefaultRevision(FALSE);
$node->setTitle('Unpublished draft')->setUnpublished()->save();
$versions[$node->getRevisionId()] = 'new';
// More than one source batch proves that history is not truncated at 50 rows.
for ($index = 0; $index < 15; $index++) {
  $node->setNewRevision(TRUE);
  $node->setTitle('Later unpublished draft ' . $index)->save();
  $versions[$node->getRevisionId()] = 'new';
}
$nid = (int) $node->id();
$existing = Node::create(['type' => 'landing_page', 'title' => 'Already migrated', 'status' => 1]);
$existing->save();
$db = \Drupal::database();
$schema = [
  'fields' => [
    'bundle' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE],
    'deleted' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
    'entity_id' => ['type' => 'int', 'not null' => TRUE],
    'revision_id' => ['type' => 'int', 'not null' => TRUE],
    'langcode' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
    'delta' => ['type' => 'int', 'not null' => TRUE],
    'panelizer_view_mode' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
    'panelizer_panels_display' => ['type' => 'blob', 'not null' => TRUE],
  ],
  'primary key' => ['entity_id', 'revision_id', 'langcode', 'delta', 'panelizer_view_mode'],
];
foreach (['node__panelizer', 'node_revision__panelizer'] as $table) { $db->schema()->createTable($table, $schema); }
function layoutSource(int $id, int $vid, string $langcode, string $version, bool $reverse = FALSE): array {
  $blocks = [];
  foreach ($reverse ? ['b', 'a'] : ['a', 'b'] as $weight => $name) {
    $blocks[] = ['id' => 'block_content:' . $GLOBALS['blocks'][$name]['uuid'],
      'provider' => 'block_content', 'label' => "$name-$langcode", 'weight' => $weight - 2,
      'vid' => $GLOBALS['blocks'][$name][$version]];
  }
  return ['bundle' => 'landing_page', 'deleted' => 0, 'entity_id' => $id, 'revision_id' => $vid,
    'langcode' => $langcode, 'delta' => 0, 'panelizer_view_mode' => 'full',
    'panelizer_panels_display' => serialize(['blocks' => $blocks])];
}
foreach ($versions as $vid => $version) {
  foreach (['en', 'zh-hans', 'zh-hant'] as $langcode) {
    $row = layoutSource($nid, (int) $vid, $langcode, $version, $langcode === 'zh-hant');
    $db->insert('node_revision__panelizer')->fields($row)->execute();
    if ($vid == $currentVid) { $db->insert('node__panelizer')->fields($row)->execute(); }
  }
}
$already = layoutSource((int) $existing->id(), (int) $existing->getRevisionId(), 'en', 'old');
foreach (['node__panelizer', 'node_revision__panelizer'] as $table) {
  $db->insert($table)->fields($already)->execute();
}
$section = new Section('layout_onecol');
$section->appendComponent(new SectionComponent(\Drupal::service('uuid')->generate(), 'content', [
  'id' => 'block_content:' . $blocks['b']['uuid'], 'provider' => 'block_content', 'label_display' => FALSE,
  'label' => 'Keep this newer layout', 'vid' => $blocks['b']['new'],
]));
$saved = array_intersect_key($already, array_flip(['bundle', 'deleted', 'entity_id', 'revision_id', 'langcode', 'delta']));
$saved['layout_builder__layout_section'] = serialize($section);
foreach (['node__layout_builder__layout', 'node_revision__layout_builder__layout'] as $table) {
  $db->insert($table)->fields($saved)->execute();
}
$checks = 0;
function layoutCheck(bool $condition, string $message): void {
  if (!$condition) { throw new RuntimeException($message); }
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}
function layoutRows(string $table): array {
  return $GLOBALS['db']->select($table, 't')->fields('t')->execute()->fetchAll(PDO::FETCH_ASSOC);
}
function layoutImmutable(): array {
  $data = [];
  foreach (['node', 'node_field_data', 'node_revision', 'node_field_revision',
    'block_content', 'block_content_field_data', 'block_content_revision',
    'block_content_field_revision', 'block_content__body', 'block_content_revision__body',
    'node__panelizer', 'node_revision__panelizer'] as $table) { $data[$table] = layoutRows($table); }
  $data['job_config'] = \Drupal::service('config.storage')->read('core.entity_view_display.node.job.default');
  return $data;
}
function layoutTargets(): array {
  return [layoutRows('node__layout_builder__layout'), layoutRows('node_revision__layout_builder__layout'),
    \Drupal::service('config.storage')->read('core.entity_view_display.node.landing_page.default'),
    \Drupal::service('config.storage')->read('field.field.node.landing_page.layout_builder__layout')];
}
function layoutRun(array $options = [], bool $success = TRUE, string $script = 'scripts/restore-panelizer-layouts.php'): array {
  $command = [PHP_BINARY, '/app/vendor/drush/drush/drush.php', '--root=/app/docroot',
    '--uri=http://chat-test', 'php:script', $script, '--', ...$options];
  $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, '/app');
  $output = stream_get_contents($pipes[1]);
  $errors = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $code = proc_close($process);
  if ($success ? $code !== 0 : $code === 0) { fwrite(STDERR, $output . $errors); }
  elseif ($success && $errors) { fwrite(STDERR, $errors); }
  layoutCheck($success ? $code === 0 : $code !== 0, 'CLI expected exit status');
  return $success ? json_decode($output, TRUE, 512, JSON_THROW_ON_ERROR) : ['output' => $output . $errors];
}
\Drupal::state()->set('system.maintenance_mode', TRUE);
$backup = '/tmp/chat-test/recovery-backup.sql';
$dump = layoutRun([], TRUE);
if (getenv('XINSHI_CHAT_TEST_MYSQL') === '1') {
  $process = proc_open([PHP_BINARY, '/app/vendor/drush/drush/drush.php', '--root=/app/docroot',
    '--uri=http://chat-test', 'sql:dump', '--result-file=' . $backup], [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, '/app');
  $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
  $exit = proc_close($process);
  if ($exit !== 0) { fwrite(STDERR, $output); }
  layoutCheck($exit === 0, 'MariaDB dump succeeds');
}
else {
  $backup = '/tmp/chat-test/recovery-backup.sqlite';
  $db->query('VACUUM INTO :backup', [':backup' => $backup]);
}
layoutCheck(is_file($backup) && filesize($backup) > 0, 'Synthetic database backup exists');
$options = ['--apply', '--site-uuid=' . $dump['site_uuid'], '--confirm=' . $dump['confirmation_hash'], '--backup=' . $backup];
$before = layoutImmutable();
$targets = layoutTargets();
$again = layoutRun();
layoutCheck($dump === $again && layoutImmutable() === $before && layoutTargets() === $targets,
  'Repeated previews preserve source, target and configuration');
$expectedRestored = 3 + count($versions) * 3;
layoutCheck(array_sum($dump['restore']) === $expectedRestored && array_sum($dump['existing']) === 2,
  'Preview counts all current translations and more than 50 historical layouts, preserving existing layouts');
$single = layoutRun(['--nid=' . $nid]);
layoutCheck(array_sum($single['restore']) === $expectedRestored && array_sum($single['existing']) === 0,
  'Node selection limits the recovery scope');
layoutRun(['--apply', '--site-uuid=wrong', '--confirm=' . $dump['confirmation_hash'], '--backup=' . $backup], FALSE);
layoutRun(['--apply', '--site-uuid=' . $dump['site_uuid'], '--confirm=wrong', '--backup=' . $backup], FALSE);
layoutRun(['--apply'], FALSE);
layoutRun(['--confirm=' . $dump['confirmation_hash']], FALSE);
layoutRun(['--nid=0'], FALSE);
layoutRun(['--unknown'], FALSE);
\Drupal::state()->set('system.maintenance_mode', FALSE);
layoutRun($options, FALSE);
\Drupal::state()->set('system.maintenance_mode', TRUE);
layoutCheck(layoutTargets() === $targets && layoutImmutable() === $before, 'Rejected write requests leave all data unchanged');

$source = layoutRows('node__panelizer')[0];
foreach (['corrupt' => 'not serialized',
  'object' => serialize((object) ['blocks' => []]),
  'unsupported plugin' => serialize(['blocks' => [['id' => 'views_block:example-block']]]),
  'missing UUID' => serialize(['blocks' => [['id' => 'block_content:00000000-0000-4000-8000-000000000000']]]),
  'mismatched revision' => serialize(['blocks' => [['id' => 'block_content:' . $blocks['a']['uuid'], 'vid' => $blocks['b']['new']]]]),
  'missing revision' => serialize(['blocks' => [['id' => 'block_content:' . $blocks['a']['uuid'], 'vid' => 999999]]]),
  'invalid revision' => serialize(['blocks' => [['id' => 'block_content:' . $blocks['a']['uuid'], 'vid' => -1]]]),
  'invalid weight' => serialize(['blocks' => [['id' => 'block_content:' . $blocks['a']['uuid'], 'weight' => 'large']]])] as $name => $raw) {
  $db->update('node__panelizer')->fields(['panelizer_panels_display' => $raw])
    ->condition('entity_id', $source['entity_id'])->condition('langcode', $source['langcode'])->execute();
  $failure = layoutRun([], FALSE);
  layoutCheck(str_contains($failure['output'], 'blockers') && layoutTargets() === $targets,
    "$name is blocked during preview");
  layoutRun($options, FALSE);
}
$db->update('node__panelizer')->fields(['panelizer_panels_display' => $source['panelizer_panels_display']])
  ->condition('entity_id', $source['entity_id'])->condition('langcode', $source['langcode'])->execute();
$duplicate = $source; $duplicate['delta'] = 1;
$db->insert('node__panelizer')->fields($duplicate)->execute();
$failure = layoutRun([], FALSE);
layoutCheck(str_contains($failure['output'], 'Multiple full layouts'), 'Duplicate display is blocked');
$db->delete('node__panelizer')->condition('delta', 1)->execute();
$validChange = layoutSource($nid, (int) $currentVid, $source['langcode'], 'old');
foreach (['node__panelizer', 'node_revision__panelizer'] as $table) {
  $db->update($table)->fields(['panelizer_panels_display' => $validChange['panelizer_panels_display']])
    ->condition('entity_id', $nid)->condition('revision_id', $currentVid)->condition('langcode', $source['langcode'])->execute();
}
layoutRun($options, FALSE);
layoutCheck(layoutTargets() === $targets, 'A valid source change also invalidates the reviewed plan');
foreach (['node__panelizer', 'node_revision__panelizer'] as $table) {
  $db->update($table)->fields(['panelizer_panels_display' => $source['panelizer_panels_display']])
    ->condition('entity_id', $nid)->condition('revision_id', $currentVid)->condition('langcode', $source['langcode'])->execute();
}
// A block may change in place without changing either its own or the page's revision ID.
$body = $db->select('block_content_revision__body', 'b')->fields('b')
  ->condition('revision_id', $blocks['a']['new'])->condition('langcode', 'en')->execute()->fetchAssoc();
$db->update('block_content_revision__body')->fields(['body_value' => 'Changed without a new revision'])
  ->condition('revision_id', $blocks['a']['new'])->condition('langcode', 'en')->execute();
layoutRun($options, FALSE);
layoutCheck(layoutTargets() === $targets, 'An in-place block update invalidates the reviewed plan');
$db->update('block_content_revision__body')->fields(['body_value' => $body['body_value']])
  ->condition('revision_id', $blocks['a']['new'])->condition('langcode', 'en')->execute();
layoutRun($options, FALSE, 'scripts/tests/layout-recovery/fail.php');
layoutCheck(layoutImmutable() === $before && layoutTargets() === $targets,
  'Failure after the first layout insert rolls back layouts and field/display configuration');
$result = layoutRun($options);
layoutCheck($result['status'] === 'applied' && layoutImmutable() === $before,
  'Recovery preserves all source content, timestamps, publication and revision identities');
$after = layoutRun();
layoutCheck(array_sum($after['restore']) === 0 && array_sum($after['existing']) === $expectedRestored + 2,
  'Fresh preview after success has no pending writes');
layoutRun(['--apply', '--site-uuid=' . $after['site_uuid'], '--confirm=' . $after['confirmation_hash'], '--backup=' . $backup]);
layoutCheck(layoutImmutable() === $before, 'Reapplying a new reviewed plan is idempotent');
\Drupal::service('config.factory')->reset();
\Drupal::entityTypeManager()->clearCachedDefinitions();
\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
$storage = \Drupal::entityTypeManager()->getStorage('node');
$storage->resetCache();
$live = $storage->load($nid);
layoutCheck($live->getRevisionId() == $currentVid && $live->isPublished() && $live->label() === 'Current',
  'Default published revision stays separate from the latest unpublished draft');
foreach (['en', 'zh-hans', 'zh-hant'] as $langcode) {
  $display = \Drupal::entityTypeManager()->getStorage('entity_view_display')->load('node.landing_page.default');
  $view = $display->build($live->getTranslation($langcode));
  $rendered = [];
  foreach ($view['_layout_builder'] ?? [] as $section) {
    foreach ($section['content'] ?? [] as $component) {
      if (isset($component['content']['#block_content'])) {
        $rendered[] = $component['content']['#block_content']->uuid();
      }
    }
  }
  $names = $langcode === 'zh-hant' ? ['b', 'a'] : ['a', 'b'];
  layoutCheck($rendered === array_map(static fn($name) => $blocks[$name]['uuid'], $names),
    'Actual Layout Builder render exposes ordered blocks at the public page reader path');
}
foreach ($versions as $vid => $version) {
  $revision = $storage->loadRevision($vid);
  foreach (['en', 'zh-hans', 'zh-hant'] as $langcode) {
    $layout = $revision->getTranslation($langcode)->get('layout_builder__layout')->getSections();
    layoutCheck(count($layout) === 1 && count($layout[0]->getComponents()) === 2,
      "Revision $vid / $langcode has its own stored layout");
    $names = $langcode === 'zh-hant' ? ['b', 'a'] : ['a', 'b'];
    foreach (array_values($layout[0]->getComponents()) as $index => $component) {
      $configuration = $component->toArray()['configuration'];
      $name = $names[$index];
      layoutCheck($configuration['vid'] == $blocks[$name][$version] && $component->getWeight() === $index - 2,
        'Component preserves block UUID, revision and ordering');
      $plugin = $component->getPlugin();
      layoutCheck($plugin instanceof \Drupal\xinshi_editor\Plugin\Block\BlockContentBlock &&
        $plugin->getBlockContentEntity()->getTranslation($langcode)->get('body')->value === "$version-$name-$langcode",
        'Actual editor block plugin reads the pinned revision and translation');
    }
  }
}
foreach (['node__layout_builder__layout', 'node_revision__layout_builder__layout'] as $index => $table) {
  $actual = $db->select($table, 't')->fields('t')->condition('entity_id', $existing->id())->execute()->fetchAssoc();
  layoutCheck($actual === $targets[$index][0], 'Existing new layout remains byte-for-byte unchanged');
}
echo "Layout recovery integration: $checks checks passed.\n";
$complete = TRUE;
