<?php

/**
 * @file
 * Exercises real configuration import and field retirement against synthetic data.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

$source = importSource();
$name = 'field.field.node.ai_session.summary';
$value = $source->read($name);
$value['label'] = 'Imported site label';
$source->write($name, $value);
importer($source)->import();
check(FieldConfig::loadByName('node', 'ai_session', 'summary')->label() === 'Imported site label',
  'A real configuration import can change site presentation');

// Widening an unshared empty field is a compatible structure repair.
$style = FieldStorageConfig::loadByName('node', 'style_preset');
$style->setSetting('max_length', 16)->save();
$schema->apply();
check(FieldStorageConfig::loadByName('node', 'style_preset')->getSetting('max_length') === 255,
  'Compatible string storage widening reaches the current definition');

\Drupal::service('module_installer')->install(['jsonapi_extras']);
$schema = \Drupal::service('xinshi_chat.schema');
$source = importSource();
$source->write('jsonapi_extras.jsonapi_resource_config.node--ai_session', [
  'id' => 'node--ai_session', 'disabled' => FALSE, 'path' => 'node/ai_session',
  'resourceType' => 'node--ai_session', 'resourceFields' => ['content' => [
    'fieldName' => 'content', 'publicName' => 'renamed_body', 'disabled' => FALSE]],
]);
rejects(fn() => importer($source)->import(), 'Import cannot rename a required JSON:API field', 'public contract');
foreach (['missing', 'storage', 'version', 'web_default', 'extra', 'shared', 'uninstall'] as $case) {
  $source = importSource();
  if ($case === 'missing') {
    $source->delete('field.field.node.ai_session.content');
  }
  if ($case === 'storage') {
    $value = $source->read('field.storage.node.summary');
    $value['settings']['max_length'] = 100;
    $source->write('field.storage.node.summary', $value);
  }
  if ($case === 'version') {
    $value = $source->read('xinshi_chat.settings');
    $value['schema_version'] = 0;
    $source->write('xinshi_chat.settings', $value);
  }
  if ($case === 'web_default') {
    $value = $source->read('field.field.node.conversation.web_search');
    $value['default_value'] = [['value' => 0]];
    $source->write('field.field.node.conversation.web_search', $value);
  }
  if ($case === 'extra' || $case === 'shared') {
    $value = $source->read('field.field.node.ai_session.summary');
    $value['id'] = 'node.conversation.summary';
    $value['bundle'] = 'conversation';
    $source->write('field.field.node.conversation.summary', $value);
  }
  if ($case === 'uninstall') {
    $value = $source->read('core.extension');
    unset($value['module']['xinshi_chat']);
    $source->write('core.extension', $value);
  }
  $before = configSnapshot();
  rejects(fn() => importer($source)->import(), 'Configuration import rejects ' . $case, 'Chat');
  check($before === configSnapshot(), 'Rejected import leaves active structure unchanged: ' . $case);
}

// Explicit retirement must preserve data that exists only in a historical revision.
\Drupal::configFactory()->getEditable('xinshi_chat.settings')->set('enabled', FALSE)->save();
FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => 'old_note', 'type' => 'string'])->save();
FieldConfig::create(['entity_type' => 'node', 'bundle' => 'conversation', 'field_name' => 'old_note'])->save();
$storage = \Drupal::entityTypeManager()->getStorage('node');
$storage->resetCache();
$chat = $storage->load($chat->id());
$chat->set('old_note', 'Retain history')->setNewRevision(TRUE);
$chat->save();
rejects(fn() => $schema->retireField('conversation', 'old_note', ['type' => 'string']),
  'Retirement refuses a populated field', 'current and revision');
$chat->set('old_note', NULL)->setNewRevision(TRUE);
$chat->save();
rejects(fn() => $schema->retireField('conversation', 'old_note', ['type' => 'string']),
  'Retirement refuses historical values after the current value is cleared', 'current and revision');

// A shared, empty Chat instance can retire without deleting another bundle's values.
NodeType::create(['type' => 'fixture_other', 'name' => 'Other'])->save();
FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => 'old_shared', 'type' => 'string'])->save();
foreach (['conversation', 'fixture_other'] as $bundle) {
  FieldConfig::create(['entity_type' => 'node', 'bundle' => $bundle, 'field_name' => 'old_shared'])->save();
}
$other = Node::create(['type' => 'fixture_other', 'title' => 'Other bundle', 'old_shared' => 'Retain other data']);
$other->save();
$schema->retireField('conversation', 'old_shared', ['type' => 'string']);
$storage->resetCache();
check(FieldStorageConfig::loadByName('node', 'old_shared') &&
  $storage->load($other->id())->get('old_shared')->value === 'Retain other data',
  'Retirement retains shared storage and unrelated bundle data');

// The synthetic history remains on disk; remove only the fixture's obsolete definition from checks.
FieldConfig::loadByName('node', 'conversation', 'old_note')->delete();
\Drupal::configFactory()->getEditable('xinshi_chat.settings')->set('enabled', TRUE)->save();
check($schema->inspect()['matches'], 'Lifecycle tests leave the current structure valid');

// Language prefixes must not bypass feature availability or response cache policy.
\Drupal::configFactory()->getEditable('xinshi_chat.settings')->set('enabled', FALSE)->save();
$request = \Symfony\Component\HttpFoundation\Request::create('/fr/api/v2/conversations');
$request->attributes->set('_route', 'xinshi_chat.conversations');
$event = new \Symfony\Component\HttpKernel\Event\RequestEvent(\Drupal::service('http_kernel'), $request,
  \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST);
\Drupal::service('xinshi_chat.availability')->onRequest($event);
check($event->getResponse()?->getStatusCode() === 503, 'Language-prefixed Chat routes honor the disabled switch');
\Drupal::configFactory()->getEditable('xinshi_chat.settings')->set('enabled', TRUE)->save();
