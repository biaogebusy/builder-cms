<?php

declare(strict_types=1);

/**
 * @file
 * Test dormant split-field installation on the disposable reference site only.
 */

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\xinshi_ai\Service\ChatSessionFields;

if (getenv('XINSHI_ANALYTICS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') ||
  \Drupal::database()->getConnectionOptions()['database'] !== '/tmp/analytics-test/site.sqlite') {
  throw new RuntimeException('Use the disposable reference runner only.');
}
$service = dirname(__DIR__, 2) . '/xinshi_ai/src/Service/';
require_once $service . 'ChatSessionFields.php';

// Preflight every field before creating the first one, including cross-bundle collisions.
NodeType::create(['type' => 'conflicting_type', 'name' => 'Conflicting type'])->save();
field('conflicting_type', 'storage_version', 'integer');
try {
  ChatSessionFields::install();
  throw new RuntimeException('Unexpected field installation success.');
}
catch (RuntimeException $error) {
  check($error->getMessage() === 'chat_session_field_conflict', 'Cross-bundle conflicts abort installation');
}
check(!FieldStorageConfig::loadByName('node', 'edit_version'), 'Conflict preflight creates no partial fields');
FieldStorageConfig::loadByName('node', 'storage_version')->delete();
field_purge_batch(50);

$storage = \Drupal::entityTypeManager()->getStorage('node');
$storage->resetCache();
$before = $storage->load($session->id());
$content = $before->get('content')->getValue();
$revision = $before->getRevisionId();
ChatSessionFields::install();
ChatSessionFields::install();
$storage->resetCache();
$after = $storage->load($session->id());
check($after->get('content')->getValue() === $content &&
  (string) $after->getRevisionId() === (string) $revision,
  'Repeated field installation preserves old content and revision identity');
foreach (ChatSessionFields::definitions() as $name => $definition) {
  $field_config = FieldStorageConfig::loadByName('node', $name);
  check($field_config && $field_config->getType() === $definition['type'] && $field_config->isRevisionable() &&
    !$field_config->isTranslatable() && $field_config->getCardinality() === ($definition['cardinality'] ?? 1),
    'Storage definition and revision policy match ' . $name);
  check($after->get($name)->isEmpty(), 'Installation does not backfill ' . $name);
}
check(FieldStorageConfig::loadByName('node', 'run_created_at_ms')->getSetting('size') === 'big' &&
  FieldStorageConfig::loadByName('node', 'edit_version')->getSetting('size') === 'big',
  'Milliseconds and CAS version use 64-bit storage');

// The old guard must still close unknown fields until the v2 guard is connected.
$copy = clone $after;
$copy->set('result_reference', json_encode($ref));
rejected(fn() => $copy->save(), 'Dormant new fields cannot bypass the existing reference guard');
$plain = Node::create(['type' => 'ai_session', 'title' => 'Plain message',
  'uid' => $users['owner']->id(), 'content' => 'Original body', 'session_role' => 'assistant']);
$plain->save();
