<?php

use Xinshi\Migration\Runtime;

Runtime::requireEntryPoint();

/** Extend the unused target body storage to match the source summary column. */
use Drupal\Core\Database\Database;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

umask(0077);
$db = Database::getConnection();
if (($db->getConnectionOptions()['database'] ?? '') !== Runtime::targetDatabase() || !file_exists(Runtime::path('target-before-model-backup.json'))) {
  throw new RuntimeException('The prepared target and backup are required.');
}
$storage = FieldStorageConfig::loadByName('block_content', 'body');
if ($storage && $storage->getType() === 'text_with_summary') {
  print json_encode(['status' => 'already_adapted', 'field' => 'block_content.body']) . PHP_EOL;
  return;
}
if (!$storage || $storage->getType() !== 'text_long') {
  throw new RuntimeException('Unexpected block body storage; inspect before adapting.');
}
foreach (['block_content', 'block_content_revision', 'block_content__body', 'block_content_revision__body'] as $table) {
  if ((int) $db->query('SELECT COUNT(*) FROM {' . $table . '}')->fetchField() !== 0) {
    throw new RuntimeException('Block data exists; this empty-model adaptation cannot run.');
  }
}
$active = \Drupal::service('config.storage');
$saved = [];
foreach (['field.field.block_content.basic.body', 'core.entity_form_display.block_content.basic.default', 'core.entity_view_display.block_content.basic.default'] as $name) {
  if ($data = $active->read($name)) {
    $saved[$name] = $data;
  }
}
file_put_contents(Runtime::path('block-body-baseline-config.json'), json_encode($saved, JSON_THROW_ON_ERROR));
$uuid = $storage->uuid();
$storage->delete();
\Drupal::moduleHandler()->loadInclude('field', 'inc', 'field.purge');
field_purge_batch(50, $uuid);
$deleted = \Drupal::service('entity_field.deleted_fields_repository')->getFieldStorageDefinitions();
if (isset($deleted[$uuid])) {
  throw new RuntimeException('The empty old field storage has not been fully purged.');
}
$plan = json_decode(file_get_contents(Runtime::path('model-config-plan.private.json')), TRUE, 512, JSON_THROW_ON_ERROR);
$data = $plan['']['field.storage.block_content.body'];
$data['uuid'] = $uuid;
unset($data['_core']);
FieldStorageConfig::create($data)->save();
if (isset($saved['field.field.block_content.basic.body'])) {
  $field = $saved['field.field.block_content.basic.body'];
  $field['field_type'] = 'text_with_summary';
  $field['settings']['display_summary'] = FALSE;
  $field['settings']['required_summary'] = FALSE;
  FieldConfig::create($field)->save();
}
foreach (array_diff(array_keys($saved), ['field.field.block_content.basic.body']) as $name) {
  $type = \Drupal::service('config.manager')->getEntityTypeIdByName($name);
  $entity = \Drupal::entityTypeManager()->getStorage($type)->load($saved[$name]['id']);
  foreach ($saved[$name] as $property => $value) {
    $entity->set($property, $value);
  }
  $entity->save();
}
\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
$report = ['status' => 'adapted', 'field' => 'block_content.body', 'before' => 'text_long', 'after' => 'text_with_summary', 'target_block_rows_before' => 0, 'target_basic_form_and_display_preserved' => TRUE, 'summary_column_exists' => $db->schema()->fieldExists('block_content__body', 'body_summary')];
file_put_contents(Runtime::path('block-body-adaptation-report.json'), json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
print json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
