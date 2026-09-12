<?php

/**
 * @file
 * Database addition for entity_usage_update_8206() testing.
 *
 * @see https://www.drupal.org/project/entity_usage/issues/3335488
 * @see \Drupal\Tests\entity_usage\Functional\Update\UpdateTest::testUpdate8206()
 */

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageInterface;

$connection = Database::getConnection();

// Update core.extension.
$extensions = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();
$extensions = unserialize($extensions, ['allowed_classes' => FALSE]);
$extensions['module']['content_lock'] = 0;
$connection->update('config')
  ->fields(['data' => serialize($extensions)])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();

// Add content_lock.settings.
$config_data = Yaml::decode(file_get_contents(__DIR__ . '/content_lock.settings.yml'));
$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'content_lock.settings',
    'data' => serialize($config_data),
  ])
  ->execute();

// Add system.action.node_break_lock_action.
$config_data = Yaml::decode(file_get_contents(__DIR__ . '/system.action.node_break_lock_action.yml'));
$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'system.action.node_break_lock_action',
    'data' => serialize($config_data),
  ])
  ->execute();

// Add system.action.node_break_lock_action.
$config_data = Yaml::decode(file_get_contents(__DIR__ . '/system.action.taxonomy_term_break_lock_action.yml'));
$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'system.action.taxonomy_term_break_lock_action',
    'data' => serialize($config_data),
  ])
  ->execute();

$connection->insert('key_value')
  ->fields(['collection', 'name', 'value'])
  ->values([
    'collection' => 'config.entity.key_store.action',
    'name' => 'uuid:f3df6a5e-1cb2-41cf-a2d4-cb6104ccc524',
    'value' => serialize(['system.action.node_break_lock_action']),
  ])
  ->values([
    'collection' => 'config.entity.key_store.action',
    'name' => 'uuid:d3df6a5e-1cb2-41cf-a2d4-cb6104ccc524',
    'value' => serialize(['system.action.taxonomy_term_break_lock_action']),
  ])
  ->execute();

// Set the schema version.
$connection->insert('key_value')
  ->fields([
    'collection' => 'system.schema',
    'name' => 'content_lock',
    'value' => 'i:8002;',
  ])
  ->execute();

// Add in post-updates.
$existing_updates = $connection->select('key_value')
  ->fields('key_value', ['value'])
  ->condition('collection', 'post_update')
  ->condition('name', 'existing_updates')
  ->execute()
  ->fetchField();
$existing_updates = unserialize($existing_updates, ['allowed_classes' => FALSE]);
$existing_updates[] = 'content_lock_post_update_fixing_views_caching';
$connection->update('key_value')
  ->fields(['value' => serialize($existing_updates)])
  ->condition('collection', 'post_update')
  ->condition('name', 'existing_updates')
  ->execute();

// Create the {content_lock} table.
$connection->schema()->createTable('content_lock', [
  'description' => 'content lock module table.',
  'fields' => [
    'entity_id' => [
      'description' => 'The primary identifier for an entity.',
      'type' => 'int',
      'size' => 'normal',
      'unsigned' => TRUE,
      'not null' => TRUE,
    ],
    'entity_type' => [
      'description' => 'The type of an entity.',
      'type' => 'varchar_ascii',
      'length' => EntityTypeInterface::ID_MAX_LENGTH,
      'not null' => TRUE,
    ],
    'form_op' => [
      'description' => 'The entity form operation.',
      'type' => 'varchar',
      'length' => EntityTypeInterface::ID_MAX_LENGTH,
      'not null' => TRUE,
      'default' => '*',
    ],
    'langcode' => [
      'description' => 'The language code of the entity.',
      'type' => 'varchar_ascii',
      'length' => 12,
      'not null' => TRUE,
      'default' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ],
    'uid' => [
      'description' => 'User that holds the lock.',
      'type' => 'int',
      'size' => 'normal',
      'unsigned' => TRUE,
      'not null' => TRUE,
    ],
    'timestamp' => [
      'description' => 'Time the lock occurred.',
      'size' => 'normal',
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
    ],
  ],
  'indexes' => [
    'user' => ['uid'],
  ],
  'foreign keys' => [
    'uid' => [
      'table' => 'users_field_data',
      'columns' => ['uid' => 'uid'],
    ],
  ],
  'primary key' => ['entity_id', 'entity_type', 'form_op', 'langcode'],
]);
