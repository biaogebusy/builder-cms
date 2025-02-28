<?php

/**
 * @file
 * A database agnostic dump for testing purposes.
 */

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Import range fields config files.
$configs = [
  'field.storage.node.field_decimal',
  'field.storage.node.field_float',
  'field.storage.node.field_integer',
  'field.field.node.page.field_decimal',
  'field.field.node.page.field_float',
  'field.field.node.page.field_integer',
  'core.entity_view_mode.node.default_formatter',
  'core.entity_view_display.node.page.default_formatter',
  'core.entity_view_mode.node.unformatted_formatter',
  'core.entity_view_display.node.page.unformatted_formatter',
];

foreach ($configs as $config) {
  $data = Yaml::decode(file_get_contents(__DIR__ . '/' . $config . '.yml'));
  $connection->insert('config')
    ->fields([
      'collection',
      'name',
      'data',
    ])
    ->values([
      'collection' => '',
      'name' => $config,
      'data' => serialize($data),
    ])
    ->execute();
}

// Update default form display.
$data = unserialize($connection->select('config')
  ->fields('config', ['data'])
  ->condition('name', 'core.entity_form_display.node.page.default')
  ->execute()
  ->fetchField());

// Enable range fields.
$data['dependencies']['config'][] = 'field.field.node.page.field_decimal';
$data['dependencies']['config'][] = 'field.field.node.page.field_float';
$data['dependencies']['config'][] = 'field.field.node.page.field_integer';

$data['dependencies']['module'][] = 'range';

$data['content']['field_decimal'] =
$data['content']['field_float'] =
$data['content']['field_integer'] = [
  'type' => 'range',
  'weight' => 100,
  'region' => 'content',
  'settings' => [],
  'third_party_settings' => [],
];

$connection->update('config')
  ->fields([
    'data' => serialize($data),
  ])
  ->condition('name', 'core.entity_form_display.node.page.default')
  ->execute();

// Ensure that fields have correct schema.
$connection->insert('key_value')
  ->fields([
    'collection',
    'name',
    'value',
  ])
  ->values([
    'collection' => 'entity.storage_schema.sql',
    'name' => 'node.field_schema_data.field_decimal',
    'value' => 'a:2:{s:19:"node__field_decimal";a:4:{s:11:"description";s:42:"Data storage for node field field_decimal.";s:6:"fields";a:8:{s:6:"bundle";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:88:"The field instance bundle to which this row belongs, used when deleting a field instance";}s:7:"deleted";a:5:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;s:7:"default";i:0;s:11:"description";s:60:"A boolean indicating whether this data item has been deleted";}s:9:"entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:38:"The entity id this data is attached to";}s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:47:"The entity revision id this data is attached to";}s:8:"langcode";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:37:"The language code for this data item.";}s:5:"delta";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:67:"The sequence number for this data item, used for multi-value fields";}s:18:"field_decimal_from";a:4:{s:4:"type";s:7:"numeric";s:9:"precision";s:2:"12";s:5:"scale";s:1:"4";s:8:"not null";b:1;}s:16:"field_decimal_to";a:4:{s:4:"type";s:7:"numeric";s:9:"precision";s:2:"12";s:5:"scale";s:1:"4";s:8:"not null";b:1;}}s:11:"primary key";a:4:{i:0;s:9:"entity_id";i:1;s:7:"deleted";i:2;s:5:"delta";i:3;s:8:"langcode";}s:7:"indexes";a:2:{s:6:"bundle";a:1:{i:0;s:6:"bundle";}s:11:"revision_id";a:1:{i:0;s:11:"revision_id";}}}s:28:"node_revision__field_decimal";a:4:{s:11:"description";s:54:"Revision archive storage for node field field_decimal.";s:6:"fields";a:8:{s:6:"bundle";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:88:"The field instance bundle to which this row belongs, used when deleting a field instance";}s:7:"deleted";a:5:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;s:7:"default";i:0;s:11:"description";s:60:"A boolean indicating whether this data item has been deleted";}s:9:"entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:38:"The entity id this data is attached to";}s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:47:"The entity revision id this data is attached to";}s:8:"langcode";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:37:"The language code for this data item.";}s:5:"delta";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:67:"The sequence number for this data item, used for multi-value fields";}s:18:"field_decimal_from";a:4:{s:4:"type";s:7:"numeric";s:9:"precision";s:2:"12";s:5:"scale";s:1:"4";s:8:"not null";b:1;}s:16:"field_decimal_to";a:4:{s:4:"type";s:7:"numeric";s:9:"precision";s:2:"12";s:5:"scale";s:1:"4";s:8:"not null";b:1;}}s:11:"primary key";a:5:{i:0;s:9:"entity_id";i:1;s:11:"revision_id";i:2;s:7:"deleted";i:3;s:5:"delta";i:4;s:8:"langcode";}s:7:"indexes";a:2:{s:6:"bundle";a:1:{i:0;s:6:"bundle";}s:11:"revision_id";a:1:{i:0;s:11:"revision_id";}}}}',
  ])
  ->values([
    'collection' => 'entity.storage_schema.sql',
    'name' => 'node.field_schema_data.field_float',
    'value' => 'a:2:{s:17:"node__field_float";a:4:{s:11:"description";s:40:"Data storage for node field field_float.";s:6:"fields";a:8:{s:6:"bundle";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:88:"The field instance bundle to which this row belongs, used when deleting a field instance";}s:7:"deleted";a:5:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;s:7:"default";i:0;s:11:"description";s:60:"A boolean indicating whether this data item has been deleted";}s:9:"entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:38:"The entity id this data is attached to";}s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:47:"The entity revision id this data is attached to";}s:8:"langcode";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:37:"The language code for this data item.";}s:5:"delta";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:67:"The sequence number for this data item, used for multi-value fields";}s:16:"field_float_from";a:2:{s:4:"type";s:5:"float";s:8:"not null";b:1;}s:14:"field_float_to";a:2:{s:4:"type";s:5:"float";s:8:"not null";b:1;}}s:11:"primary key";a:4:{i:0;s:9:"entity_id";i:1;s:7:"deleted";i:2;s:5:"delta";i:3;s:8:"langcode";}s:7:"indexes";a:2:{s:6:"bundle";a:1:{i:0;s:6:"bundle";}s:11:"revision_id";a:1:{i:0;s:11:"revision_id";}}}s:26:"node_revision__field_float";a:4:{s:11:"description";s:52:"Revision archive storage for node field field_float.";s:6:"fields";a:8:{s:6:"bundle";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:88:"The field instance bundle to which this row belongs, used when deleting a field instance";}s:7:"deleted";a:5:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;s:7:"default";i:0;s:11:"description";s:60:"A boolean indicating whether this data item has been deleted";}s:9:"entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:38:"The entity id this data is attached to";}s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:47:"The entity revision id this data is attached to";}s:8:"langcode";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:37:"The language code for this data item.";}s:5:"delta";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:67:"The sequence number for this data item, used for multi-value fields";}s:16:"field_float_from";a:2:{s:4:"type";s:5:"float";s:8:"not null";b:1;}s:14:"field_float_to";a:2:{s:4:"type";s:5:"float";s:8:"not null";b:1;}}s:11:"primary key";a:5:{i:0;s:9:"entity_id";i:1;s:11:"revision_id";i:2;s:7:"deleted";i:3;s:5:"delta";i:4;s:8:"langcode";}s:7:"indexes";a:2:{s:6:"bundle";a:1:{i:0;s:6:"bundle";}s:11:"revision_id";a:1:{i:0;s:11:"revision_id";}}}}',
  ])
  ->values([
    'collection' => 'entity.storage_schema.sql',
    'name' => 'node.field_schema_data.field_integer',
    'value' => 'a:2:{s:19:"node__field_integer";a:4:{s:11:"description";s:42:"Data storage for node field field_integer.";s:6:"fields";a:8:{s:6:"bundle";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:88:"The field instance bundle to which this row belongs, used when deleting a field instance";}s:7:"deleted";a:5:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;s:7:"default";i:0;s:11:"description";s:60:"A boolean indicating whether this data item has been deleted";}s:9:"entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:38:"The entity id this data is attached to";}s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:47:"The entity revision id this data is attached to";}s:8:"langcode";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:37:"The language code for this data item.";}s:5:"delta";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:67:"The sequence number for this data item, used for multi-value fields";}s:18:"field_integer_from";a:2:{s:4:"type";s:3:"int";s:8:"not null";b:1;}s:16:"field_integer_to";a:2:{s:4:"type";s:3:"int";s:8:"not null";b:1;}}s:11:"primary key";a:4:{i:0;s:9:"entity_id";i:1;s:7:"deleted";i:2;s:5:"delta";i:3;s:8:"langcode";}s:7:"indexes";a:2:{s:6:"bundle";a:1:{i:0;s:6:"bundle";}s:11:"revision_id";a:1:{i:0;s:11:"revision_id";}}}s:28:"node_revision__field_integer";a:4:{s:11:"description";s:54:"Revision archive storage for node field field_integer.";s:6:"fields";a:8:{s:6:"bundle";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:88:"The field instance bundle to which this row belongs, used when deleting a field instance";}s:7:"deleted";a:5:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;s:7:"default";i:0;s:11:"description";s:60:"A boolean indicating whether this data item has been deleted";}s:9:"entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:38:"The entity id this data is attached to";}s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:47:"The entity revision id this data is attached to";}s:8:"langcode";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:37:"The language code for this data item.";}s:5:"delta";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:67:"The sequence number for this data item, used for multi-value fields";}s:18:"field_integer_from";a:2:{s:4:"type";s:3:"int";s:8:"not null";b:1;}s:16:"field_integer_to";a:2:{s:4:"type";s:3:"int";s:8:"not null";b:1;}}s:11:"primary key";a:5:{i:0;s:9:"entity_id";i:1;s:11:"revision_id";i:2;s:7:"deleted";i:3;s:5:"delta";i:4;s:8:"langcode";}s:7:"indexes";a:2:{s:6:"bundle";a:1:{i:0;s:6:"bundle";}s:11:"revision_id";a:1:{i:0;s:11:"revision_id";}}}}',
  ])
  ->execute();

// Enable range module.
$extensions = unserialize($connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField());
$extensions['module']['range'] = 0;
$connection->update('config')
  ->fields([
    'data' => serialize($extensions),
  ])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();

// Set range module schema version.
$connection->insert('key_value')
  ->fields([
    'collection',
    'name',
    'value',
  ])
  ->values([
    'collection' => 'system.schema',
    'name' => 'range',
    'value' => 'i:8100;',
  ])
  ->execute();

// Create tables for the fields themselves.
$connection->schema()->createTable('node__field_decimal', [
  'fields' => [
    'bundle' => [
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ],
    'deleted' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ],
    'entity_id' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'revision_id' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'langcode' => [
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ],
    'delta' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'field_decimal_from' => [
      'type' => 'numeric',
      'not null' => TRUE,
      'precision' => '10',
      'scale' => '2',
    ],
    'field_decimal_to' => [
      'type' => 'numeric',
      'not null' => TRUE,
      'precision' => '10',
      'scale' => '2',
    ],
  ],
  'primary key' => [
    'entity_id',
    'deleted',
    'delta',
    'langcode',
  ],
  'indexes' => [
    'bundle' => [
      'bundle',
    ],
    'revision_id' => [
      'revision_id',
    ],
  ],
  'mysql_character_set' => 'utf8mb4',
]);

$connection->schema()->createTable('node__field_float', [
  'fields' => [
    'bundle' => [
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ],
    'deleted' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ],
    'entity_id' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'revision_id' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'langcode' => [
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ],
    'delta' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'field_float_from' => [
      'type' => 'float',
      'not null' => TRUE,
      'size' => 'normal',
    ],
    'field_float_to' => [
      'type' => 'float',
      'not null' => TRUE,
      'size' => 'normal',
    ],
  ],
  'primary key' => [
    'entity_id',
    'deleted',
    'delta',
    'langcode',
  ],
  'indexes' => [
    'bundle' => [
      'bundle',
    ],
    'revision_id' => [
      'revision_id',
    ],
  ],
  'mysql_character_set' => 'utf8mb4',
]);

$connection->schema()->createTable('node__field_integer', [
  'fields' => [
    'bundle' => [
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ],
    'deleted' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ],
    'entity_id' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'revision_id' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'langcode' => [
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ],
    'delta' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'field_integer_from' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
    ],
    'field_integer_to' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
    ],
  ],
  'primary key' => [
    'entity_id',
    'deleted',
    'delta',
    'langcode',
  ],
  'indexes' => [
    'bundle' => [
      'bundle',
    ],
    'revision_id' => [
      'revision_id',
    ],
  ],
  'mysql_character_set' => 'utf8mb4',
]);

$connection->schema()->createTable('node_revision__field_decimal', [
  'fields' => [
    'bundle' => [
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ],
    'deleted' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ],
    'entity_id' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'revision_id' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'langcode' => [
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ],
    'delta' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'field_decimal_from' => [
      'type' => 'numeric',
      'not null' => TRUE,
      'precision' => '10',
      'scale' => '2',
    ],
    'field_decimal_to' => [
      'type' => 'numeric',
      'not null' => TRUE,
      'precision' => '10',
      'scale' => '2',
    ],
  ],
  'primary key' => [
    'entity_id',
    'revision_id',
    'deleted',
    'delta',
    'langcode',
  ],
  'indexes' => [
    'bundle' => [
      'bundle',
    ],
    'revision_id' => [
      'revision_id',
    ],
  ],
  'mysql_character_set' => 'utf8mb4',
]);

$connection->schema()->createTable('node_revision__field_float', [
  'fields' => [
    'bundle' => [
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ],
    'deleted' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ],
    'entity_id' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'revision_id' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'langcode' => [
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ],
    'delta' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'field_float_from' => [
      'type' => 'float',
      'not null' => TRUE,
      'size' => 'normal',
    ],
    'field_float_to' => [
      'type' => 'float',
      'not null' => TRUE,
      'size' => 'normal',
    ],
  ],
  'primary key' => [
    'entity_id',
    'revision_id',
    'deleted',
    'delta',
    'langcode',
  ],
  'indexes' => [
    'bundle' => [
      'bundle',
    ],
    'revision_id' => [
      'revision_id',
    ],
  ],
  'mysql_character_set' => 'utf8mb4',
]);

$connection->schema()->createTable('node_revision__field_integer', [
  'fields' => [
    'bundle' => [
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ],
    'deleted' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ],
    'entity_id' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'revision_id' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'langcode' => [
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ],
    'delta' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'field_integer_from' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
    ],
    'field_integer_to' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
    ],
  ],
  'primary key' => [
    'entity_id',
    'revision_id',
    'deleted',
    'delta',
    'langcode',
  ],
  'indexes' => [
    'bundle' => [
      'bundle',
    ],
    'revision_id' => [
      'revision_id',
    ],
  ],
  'mysql_character_set' => 'utf8mb4',
]);
