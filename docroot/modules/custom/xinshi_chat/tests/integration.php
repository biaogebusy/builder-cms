<?php

declare(strict_types=1);

/**
 * @file
 * Real module installation, schema lifecycle and data-preserving adoption on synthetic SQLite.
 */

use Drupal\Core\Config\MemoryStorage;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_chat\Schema\Definition;

require __DIR__ . '/install-fixture.php';
$checks = 0;
require __DIR__ . '/config-import-functions.php';
function check(bool $result, string $description): void {
  if (!$result) {
    throw new RuntimeException($description);
  }
  $GLOBALS['checks']++;
  echo 'PASS: ' . $description . PHP_EOL;
}
function rejects(callable $operation, string $description, string $expected): void {
  try {
    $operation();
  }
  catch (Throwable $error) {
    check(str_contains($error->getMessage(), $expected), $description . ' (' . $error->getMessage() . ')');
    return;
  }
  throw new RuntimeException('Unexpected success: ' . $description);
}
function configSnapshot(): array {
  $storage = \Drupal::service('config.storage');
  $names = array_merge($storage->listAll('field.'), $storage->listAll('node.type.'),
    $storage->listAll('core.entity_'), $storage->listAll('user.role.'));
  sort($names);
  return $storage->readMultiple($names);
}

if (getenv('CHAT_TEST_SCENARIO') === 'manual') {
  require __DIR__ . '/manual-fixture.php';
}
\Drupal::service('module_installer')->install(['xinshi_chat']);
if (isset($manual_data)) {
  check($manual_data === array_intersect_key(nodeTables(), $manual_data),
    'Adopting manual fields preserves every existing SQL value and revision');
  foreach ($manual_config as $name => $value) {
    if (in_array($name, ['core.entity_form_display.node.conversation.default',
      'core.entity_view_display.node.conversation.default'], TRUE)) {
      // Core records the new hidden field without changing existing display choices.
      $value['hidden']['web_search'] = TRUE;
      ksort($value['hidden']);
      $value['dependencies']['config'][] = 'field.field.node.conversation.web_search';
      sort($value['dependencies']['config']);
    }
    check($value === \Drupal::service('config.storage')->read($name), 'Adoption preserves ' . $name);
  }
}
if (getenv('CHAT_TEST_SCENARIO') !== 'manual') {
  $export = importSource();
  \Drupal::service('module_installer')->uninstall(['xinshi_chat']);
  check(!NodeType::load('conversation') && !NodeType::load('ai_session'),
    'Empty-site uninstall removes module-owned types without touching another module');
  importer($export)->import();
  check(\Drupal::moduleHandler()->moduleExists('xinshi_chat'),
    'A full configuration import can install Chat on an empty site');
}
$schema = \Drupal::service('xinshi_chat.schema');
check($schema->inspect()['matches'], 'Fresh module installation creates the current complete structure');
check(!\Drupal::moduleHandler()->moduleExists('xinshi_ai') &&
  !\Drupal::moduleHandler()->moduleExists('xinshi_ai_usage'), 'Chat installs without AI providers or metering');
check(\Drupal::service('xinshi_ai_reference.storage')->version() === 3, 'An empty site starts directly on storage version 3');
check(count(Definition::fields()['conversation']) === 10 && count(Definition::fields()['ai_session']) === 21,
  'The module includes original conversation fields and all split message fields');
check(!isset(Definition::fields(1)['conversation']['web_search']),
  'The frozen version 1 field contract is unchanged');
$before = configSnapshot();
$schema->apply();
check($before === configSnapshot(), 'Repeated installation preserves configuration UUIDs, labels, displays and roles');
check(!Role::load('authenticated')->hasPermission('create conversation content'), 'Installing Chat does not grant existing roles new permissions');

Role::create(['id' => 'chat_owner', 'label' => 'Chat owner', 'permissions' => [
  'access content', 'create conversation content', 'edit own conversation content',
  'create ai_session content', 'edit own ai_session content', 'view own unpublished content',
  'use text format plain_text',
]])->save();
$owner = User::create(['name' => 'chat-owner', 'status' => 1, 'roles' => ['chat_owner']]);
$owner->save();
\Drupal::currentUser()->setAccount($owner);
$message = Node::create(['type' => 'ai_session', 'title' => 'Question', 'uid' => $owner->id(),
  'session_role' => 'user', 'content' => ['value' => 'Original message', 'format' => 'plain_text'],
  'storage_version' => 3, 'edit_version' => 1, 'storage_mode' => 'inline', 'content_format' => 'text']);
$message->save();
$chat = Node::create(['type' => 'conversation', 'title' => 'Conversation', 'uid' => $owner->id(),
  'model' => 'fixture', 'platform' => 'fixture', 'sessions' => [['target_id' => $message->id()]]]);
$chat->save();
check((bool) $chat->get('web_search')->value === TRUE, 'New conversations default to web search on');
$writer = \Drupal::service('xinshi_chat.conversation_writer');
$writer->update($chat->uuid(), [], ['web_search' => FALSE]);
\Drupal::entityTypeManager()->getStorage('node')->resetCache([$chat->id()]);
check((bool) Node::load($chat->id())->get('web_search')->value === FALSE,
  'The owned conversation saves and reloads its web search choice');
$writer->update($chat->uuid(), [], ['web_search' => FALSE]);
\Drupal::entityTypeManager()->getStorage('node')->resetCache();
$message = Node::load($message->id());
$chat = Node::load($chat->id());
$identity = [$message->id(), $message->uuid(), $message->getRevisionId(), $message->toArray(), $chat->toArray()];
$schema->apply();
\Drupal::entityTypeManager()->getStorage('node')->resetCache();
$saved = Node::load($message->id());
check($identity === [$saved->id(), $saved->uuid(), $saved->getRevisionId(), $saved->toArray(), Node::load($chat->id())->toArray()],
  'Schema updates leave entity identity, revisions, message data and relationships unchanged');
check(count(\Drupal::service('xinshi_chat.uninstall_validator')->validate('xinshi_chat')) === 1,
  'Data prevents ordinary uninstall');
check(count(\Drupal::service('xinshi_chat.uninstall_validator')->validateConfigImport('xinshi_chat', new MemoryStorage())) === 1,
  'Configuration import cannot bypass data-preserving uninstall validation');
rejects(fn() => \Drupal::service('module_installer')->uninstall(['xinshi_chat']), 'The real module installer rejects uninstall with data', 'Chat contains');

$settings = \Drupal::configFactory()->getEditable('xinshi_ai_reference.settings');
$settings->set('storage_version', 1)->save();
$before = configSnapshot();
rejects(fn() => $schema->apply(), 'Old version flags cannot relabel existing data', 'Migrate existing Chat data');
check($before === configSnapshot() && $settings->get('storage_version') === 1,
  'Rejected adoption does not create fields, change roles or relabel old data');
$settings->set('storage_version', 3)->save();

// Model an installed version 1 site, then exercise both pending update hooks.
FieldConfig::loadByName('node', 'conversation', 'web_search')->delete();
\Drupal::configFactory()->getEditable('xinshi_chat.settings')->set('schema_version', 1)->save();
\Drupal::moduleHandler()->loadInclude('xinshi_chat', 'install');
xinshi_chat_update_11001();
check(!FieldConfig::loadByName('node', 'conversation', 'web_search'),
  'The version 1 update does not install version 2 fields');
xinshi_chat_update_11002();
check($schema->inspect()['matches'] &&
  FieldConfig::loadByName('node', 'conversation', 'web_search')->getDefaultValueLiteral() === [['value' => 1]],
  'The version 2 update adds the default-on preference to an existing version 1 site');
\Drupal::entityTypeManager()->getStorage('node')->resetCache();
check(Node::load($message->id())->toArray() === $saved->toArray() &&
  Node::load($chat->id())->get('sessions')->target_id === $message->id(),
  'The version 2 update preserves existing messages and conversation relationships');

// A field can be absent after an interrupted empty-field installation.
$empty = FieldConfig::loadByName('node', 'ai_session', 'knowledge_refs');
$empty->delete();
check(!$schema->inspect()['matches'], 'The structure check detects a missing field');
xinshi_chat_update_11002();
check($schema->inspect()['matches'], 'The versioned update restores a missing compatible field');
$before = configSnapshot();
xinshi_chat_update_11002();
check($before === configSnapshot(), 'Repeating the update is idempotent');

// Site presentation remains configurable after the module adopts the structure.
$field = FieldConfig::loadByName('node', 'ai_session', 'summary');
$field->setLabel('Site summary label')->save();
$schema->apply();
check(FieldConfig::loadByName('node', 'ai_session', 'summary')->label() === 'Site summary label',
  'A structure update retains a site label');

// Empty retired fields are explicit; fields that still hold revision data are refused.
\Drupal::configFactory()->getEditable('xinshi_chat.settings')->set('enabled', FALSE)->save();
FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => 'retired_fixture', 'type' => 'string'])->save();
FieldConfig::create(['entity_type' => 'node', 'bundle' => 'conversation', 'field_name' => 'retired_fixture'])->save();
$schema->retireField('conversation', 'retired_fixture', ['type' => 'string']);
check(!FieldStorageConfig::loadByName('node', 'retired_fixture'), 'An explicitly retired empty field can be removed');
rejects(fn() => $schema->retireField('ai_session', 'content', ['type' => 'text_long']), 'A current protocol field cannot be retired', 'explicitly retired');
\Drupal::configFactory()->getEditable('xinshi_chat.settings')->set('enabled', TRUE)->save();
check($schema->inspect()['matches'], 'The final live schema still matches the current definition');
require __DIR__ . '/lifecycle.php';
echo 'Chat checks passed: ' . $checks . PHP_EOL;
