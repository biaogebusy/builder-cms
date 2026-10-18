<?php

/**
 * @file
 * Builds an existing v3 site from the independent, frozen migration field definitions.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\xinshi_ai\Service\ChatSessionFieldScan;
use Drupal\xinshi_ai\Service\ChatSessionFields;

require $root . '/docroot/modules/custom/xinshi_ai/src/Service/ChatSessionFieldScan.php';
require $root . '/docroot/modules/custom/xinshi_ai/src/Service/ChatSessionFields.php';
\Drupal::service('module_installer')->install(['xinshi_ai_reference', 'simple_oauth', 'language']);
foreach (ChatSessionFieldScan::sourceDefinitions() as $bundle => $fields) {
  NodeType::create(['type' => $bundle, 'name' => 'Existing ' . $bundle, 'new_revision' => TRUE])->save();
  foreach ($fields as $name => $definition) {
    FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => $name,
      'type' => $definition['type'], 'settings' => $definition['settings'] ?? [],
      'cardinality' => $definition['cardinality'] ?? 1, 'translatable' => FALSE])->save();
    FieldConfig::create(['entity_type' => 'node', 'bundle' => $bundle, 'field_name' => $name,
      'label' => 'Existing ' . $name, 'required' => $definition['required'] ?? FALSE,
      'translatable' => FALSE, 'settings' => $name === 'sessions' ? ['handler' => 'default:node',
        'handler_settings' => ['target_bundles' => ['ai_session' => 'ai_session']]] : []])->save();
  }
  \Drupal::service('entity_display.repository')->getFormDisplay('node', $bundle)
    ->setComponent('title', ['weight' => 17])->save();
  \Drupal::service('entity_display.repository')->getViewDisplay('node', $bundle)
    ->setComponent('model', ['label' => 'hidden'])->save();
}
ChatSessionFields::install(3);
\Drupal::configFactory()->getEditable('xinshi_ai_reference.settings')->set('storage_version', 3)->save();
\Drupal\language\Entity\ConfigurableLanguage::createFromLangcode('fr')->save();
$old_message = Node::create(['type' => 'ai_session', 'title' => 'Existing message', 'uid' => 1,
  'langcode' => 'en', 'session_role' => 'user', 'storage_version' => 3, 'storage_mode' => 'inline',
  'edit_version' => 1, 'content' => ['value' => 'Existing body', 'format' => 'plain_text']]);
$old_message->save();
$old_message->setNewRevision(TRUE);
$old_message->save();
$old_chat = Node::create(['type' => 'conversation', 'title' => 'Existing conversation', 'uid' => 1,
  'langcode' => 'en', 'model' => 'existing', 'platform' => 'existing',
  'sessions' => [['target_id' => $old_message->id()]]]);
$old_chat->save();
$old_chat->addTranslation('fr', ['title' => 'Conversation existante', 'uid' => 1]);
$old_chat->setNewRevision(TRUE);
$old_chat->save();
function nodeTables(): array {
  $result = [];
  foreach (\Drupal::database()->schema()->findTables('node%') as $table) {
    $result[$table] = \Drupal::database()->select($table, 'n')->fields('n')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }
  return $result;
}
$manual_data = nodeTables();
$manual_config = array_filter(configSnapshot(), static fn($name) => !str_starts_with($name, 'node.type.'), ARRAY_FILTER_USE_KEY);
