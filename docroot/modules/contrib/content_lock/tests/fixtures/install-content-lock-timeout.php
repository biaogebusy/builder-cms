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

$connection = Database::getConnection();

// Update core.extension.
$extensions = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();
$extensions = unserialize($extensions, ['allowed_classes' => FALSE]);
$extensions['module']['content_lock_timeout'] = 0;
$connection->update('config')
  ->fields(['data' => serialize($extensions)])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();

// Add entity_usage.settings.
$config_data = Yaml::decode(file_get_contents(__DIR__ . '/content_lock_timeout.settings.yml'));
$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'content_lock_timeout.settings',
    'data' => serialize($config_data),
  ])
  ->execute();

// Set the schema version.
$connection->insert('key_value')
  ->fields([
    'collection' => 'system.schema',
    'name' => 'content_lock_timeout',
    'value' => 'i:8000;',
  ])
  ->execute();
