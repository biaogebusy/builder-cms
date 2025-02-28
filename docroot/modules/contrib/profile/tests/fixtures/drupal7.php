<?php
// phpcs:ignoreFile
/**
 * @file
 * A database agnostic dump for testing purposes.
 */


use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$connection->insert('field_config')
  ->fields([
    'id',
    'field_name',
    'type',
    'module',
    'active',
    'storage_type',
    'storage_module',
    'storage_active',
    'locked',
    'data',
    'cardinality',
    'translatable',
    'deleted',
  ])
  ->values([
    'id' => '63',
    'field_name' => 'field_main_profile_email_field',
    'type' => 'email',
    'module' => 'email',
    'active' => '1',
    'storage_type' => 'field_sql_storage',
    'storage_module' => 'field_sql_storage',
    'storage_active' => '1',
    'locked' => '0',
    'data' => 'a:8:{s:12:"translatable";i:0;s:14:"entity_id_type";N;s:12:"entity_types";a:0:{}s:8:"settings";a:2:{s:16:"profile2_private";i:0;s:23:"entity_translation_sync";b:0;}s:7:"storage";a:5:{s:4:"type";s:17:"field_sql_storage";s:8:"settings";a:0:{}s:6:"module";s:17:"field_sql_storage";s:6:"active";s:1:"1";s:7:"details";a:1:{s:3:"sql";a:2:{s:18:"FIELD_LOAD_CURRENT";a:1:{s:41:"field_data_field_main_profile_email_field";a:1:{s:5:"email";s:36:"field_main_profile_email_field_email";}}s:19:"FIELD_LOAD_REVISION";a:1:{s:45:"field_revision_field_main_profile_email_field";a:1:{s:5:"email";s:36:"field_main_profile_email_field_email";}}}}}s:12:"foreign keys";a:0:{}s:7:"indexes";a:0:{}s:2:"id";s:2:"63";}',
    'cardinality' => '1',
    'translatable' => '0',
    'deleted' => '0',
  ])
  ->values([
    'id' => '64',
    'field_name' => 'field_test_profile_phone',
    'type' => 'phone',
    'module' => 'phone',
    'active' => '1',
    'storage_type' => 'field_sql_storage',
    'storage_module' => 'field_sql_storage',
    'storage_active' => '1',
    'locked' => '0',
    'data' => 'a:8:{s:12:"translatable";i:0;s:14:"entity_id_type";N;s:12:"entity_types";a:0:{}s:8:"settings";a:3:{s:7:"country";s:2:"ca";s:16:"profile2_private";i:0;s:23:"entity_translation_sync";b:0;}s:7:"storage";a:5:{s:4:"type";s:17:"field_sql_storage";s:8:"settings";a:0:{}s:6:"module";s:17:"field_sql_storage";s:6:"active";s:1:"1";s:7:"details";a:1:{s:3:"sql";a:2:{s:18:"FIELD_LOAD_CURRENT";a:1:{s:35:"field_data_field_test_profile_phone";a:1:{s:5:"value";s:30:"field_test_profile_phone_value";}}s:19:"FIELD_LOAD_REVISION";a:1:{s:39:"field_revision_field_test_profile_phone";a:1:{s:5:"value";s:30:"field_test_profile_phone_value";}}}}}s:12:"foreign keys";a:0:{}s:7:"indexes";a:0:{}s:2:"id";s:2:"64";}',
    'cardinality' => '1',
    'translatable' => '0',
    'deleted' => '0',
  ])
  ->execute();

$connection->insert('field_config_instance')
  ->fields([
    'id',
    'field_id',
    'field_name',
    'entity_type',
    'bundle',
    'data',
    'deleted',
  ])
  ->values([
    'id' => '95',
    'field_id' => '63',
    'field_name' => 'field_main_profile_email_field',
    'entity_type' => 'profile2',
    'bundle' => 'main',
    'data' => 'a:7:{s:5:"label";s:24:"Main profile email field";s:6:"widget";a:5:{s:6:"weight";s:1:"1";s:4:"type";s:15:"email_textfield";s:6:"module";s:5:"email";s:6:"active";i:1;s:8:"settings";a:1:{s:4:"size";s:2:"60";}}s:8:"settings";a:2:{s:18:"user_register_form";b:0;s:23:"entity_translation_sync";b:0;}s:7:"display";a:1:{s:7:"default";a:5:{s:5:"label";s:5:"above";s:4:"type";s:13:"email_default";s:6:"weight";s:1:"0";s:8:"settings";a:0:{}s:6:"module";s:5:"email";}}s:8:"required";i:0;s:11:"description";s:0:"";s:13:"default_value";N;}',
    'deleted' => '0',
  ])
  ->values([
    'id' => '96',
    'field_id' => '64',
    'field_name' => 'field_test_profile_phone',
    'entity_type' => 'profile2',
    'bundle' => 'test_profile_type',
    'data' => 'a:7:{s:5:"label";s:18:"Test profile phone";s:6:"widget";a:5:{s:6:"weight";s:1:"1";s:4:"type";s:15:"phone_textfield";s:6:"module";s:5:"phone";s:6:"active";i:0;s:8:"settings";a:0:{}}s:8:"settings";a:7:{s:18:"phone_country_code";i:0;s:18:"ca_phone_separator";s:1:"-";s:20:"ca_phone_parentheses";i:1;s:26:"phone_default_country_code";s:1:"1";s:20:"phone_int_max_length";i:15;s:18:"user_register_form";b:0;s:23:"entity_translation_sync";b:0;}s:7:"display";a:1:{s:7:"default";a:5:{s:5:"label";s:6:"inline";s:4:"type";s:5:"phone";s:6:"weight";s:1:"0";s:8:"settings";a:0:{}s:6:"module";s:5:"phone";}}s:8:"required";i:0;s:11:"description";s:0:"";s:13:"default_value";N;}',
    'deleted' => '0',
  ])
  ->execute();

$connection->schema()
  ->createTable('field_data_field_main_profile_email_field', [
    'fields' => [
      'entity_type' => [
        'type' => 'varchar',
        'not null' => TRUE,
        'length' => '128',
        'default' => '',
      ],
      'bundle' => [
        'type' => 'varchar',
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
        'not null' => FALSE,
        'size' => 'normal',
        'unsigned' => TRUE,
      ],
      'language' => [
        'type' => 'varchar',
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
      'field_main_profile_email_field_email' => [
        'type' => 'varchar',
        'not null' => FALSE,
        'length' => '255',
      ],
    ],
    'primary key' => [
      'entity_type',
      'entity_id',
      'deleted',
      'delta',
      'language',
    ],
    'indexes' => [
      'entity_type' => [
        'entity_type',
      ],
      'bundle' => [
        'bundle',
      ],
      'deleted' => [
        'deleted',
      ],
      'entity_id' => [
        'entity_id',
      ],
      'revision_id' => [
        'revision_id',
      ],
      'language' => [
        'language',
      ],
    ],
    'mysql_character_set' => 'utf8',
  ]);

$connection->insert('field_data_field_main_profile_email_field')
  ->fields([
    'entity_type',
    'bundle',
    'deleted',
    'entity_id',
    'revision_id',
    'language',
    'delta',
    'field_main_profile_email_field_email',
  ])
  ->values([
    'entity_type' => 'profile2',
    'bundle' => 'main',
    'deleted' => '0',
    'entity_id' => '1',
    'revision_id' => '1',
    'language' => 'und',
    'delta' => '0',
    'field_main_profile_email_field_email' => 'odo@odo.odo',
  ])
  ->values([
    'entity_type' => 'profile2',
    'bundle' => 'main',
    'deleted' => '0',
    'entity_id' => '3',
    'revision_id' => '3',
    'language' => 'und',
    'delta' => '0',
    'field_main_profile_email_field_email' => 'bob@bob.bob',
  ])
  ->execute();

$connection->schema()->createTable('field_data_field_test_profile_phone', [
  'fields' => [
    'entity_type' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ],
    'bundle' => [
      'type' => 'varchar',
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
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'language' => [
      'type' => 'varchar',
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
    'field_test_profile_phone_value' => [
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => '255',
    ],
  ],
  'primary key' => [
    'entity_type',
    'entity_id',
    'deleted',
    'delta',
    'language',
  ],
  'indexes' => [
    'entity_type' => [
      'entity_type',
    ],
    'bundle' => [
      'bundle',
    ],
    'deleted' => [
      'deleted',
    ],
    'entity_id' => [
      'entity_id',
    ],
    'revision_id' => [
      'revision_id',
    ],
    'language' => [
      'language',
    ],
  ],
  'mysql_character_set' => 'utf8',
]);

$connection->insert('field_data_field_test_profile_phone')
  ->fields([
    'entity_type',
    'bundle',
    'deleted',
    'entity_id',
    'revision_id',
    'language',
    'delta',
    'field_test_profile_phone_value',
  ])
  ->values([
    'entity_type' => 'profile2',
    'bundle' => 'test_profile_type',
    'deleted' => '0',
    'entity_id' => '2',
    'revision_id' => '2',
    'language' => 'und',
    'delta' => '0',
    'field_test_profile_phone_value' => '(555) 555-1234',
  ])
  ->values([
    'entity_type' => 'profile2',
    'bundle' => 'test_profile_type',
    'deleted' => '0',
    'entity_id' => '4',
    'revision_id' => '4',
    'language' => 'und',
    'delta' => '0',
    'field_test_profile_phone_value' => '(250) 555-0199',
  ])
  ->execute();

$connection->schema()
  ->createTable('field_revision_field_main_profile_email_field', [
    'fields' => [
      'entity_type' => [
        'type' => 'varchar',
        'not null' => TRUE,
        'length' => '128',
        'default' => '',
      ],
      'bundle' => [
        'type' => 'varchar',
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
      'language' => [
        'type' => 'varchar',
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
      'field_main_profile_email_field_email' => [
        'type' => 'varchar',
        'not null' => FALSE,
        'length' => '255',
      ],
    ],
    'primary key' => [
      'entity_type',
      'entity_id',
      'revision_id',
      'deleted',
      'delta',
      'language',
    ],
    'indexes' => [
      'entity_type' => [
        'entity_type',
      ],
      'bundle' => [
        'bundle',
      ],
      'deleted' => [
        'deleted',
      ],
      'entity_id' => [
        'entity_id',
      ],
      'revision_id' => [
        'revision_id',
      ],
      'language' => [
        'language',
      ],
    ],
    'mysql_character_set' => 'utf8',
  ]);

$connection->insert('field_revision_field_main_profile_email_field')
  ->fields([
    'entity_type',
    'bundle',
    'deleted',
    'entity_id',
    'revision_id',
    'language',
    'delta',
    'field_main_profile_email_field_email',
  ])
  ->values([
    'entity_type' => 'profile2',
    'bundle' => 'main',
    'deleted' => '0',
    'entity_id' => '1',
    'revision_id' => '1',
    'language' => 'und',
    'delta' => '0',
    'field_main_profile_email_field_email' => 'odo@odo.odo',
  ])
  ->values([
    'entity_type' => 'profile2',
    'bundle' => 'main',
    'deleted' => '0',
    'entity_id' => '3',
    'revision_id' => '3',
    'language' => 'und',
    'delta' => '0',
    'field_main_profile_email_field_email' => 'bob@bob.bob',
  ])
  ->execute();

$connection->schema()->createTable('field_revision_field_test_profile_phone', [
  'fields' => [
    'entity_type' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ],
    'bundle' => [
      'type' => 'varchar',
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
    'language' => [
      'type' => 'varchar',
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
    'field_test_profile_phone_value' => [
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => '255',
    ],
  ],
  'primary key' => [
    'entity_type',
    'entity_id',
    'revision_id',
    'deleted',
    'delta',
    'language',
  ],
  'indexes' => [
    'entity_type' => [
      'entity_type',
    ],
    'bundle' => [
      'bundle',
    ],
    'deleted' => [
      'deleted',
    ],
    'entity_id' => [
      'entity_id',
    ],
    'revision_id' => [
      'revision_id',
    ],
    'language' => [
      'language',
    ],
  ],
  'mysql_character_set' => 'utf8',
]);

$connection->insert('field_revision_field_test_profile_phone')
  ->fields([
    'entity_type',
    'bundle',
    'deleted',
    'entity_id',
    'revision_id',
    'language',
    'delta',
    'field_test_profile_phone_value',
  ])
  ->values([
    'entity_type' => 'profile2',
    'bundle' => 'test_profile_type',
    'deleted' => '0',
    'entity_id' => '2',
    'revision_id' => '2',
    'language' => 'und',
    'delta' => '0',
    'field_test_profile_phone_value' => '(555) 555-1234',
  ])
  ->values([
    'entity_type' => 'profile2',
    'bundle' => 'test_profile_type',
    'deleted' => '0',
    'entity_id' => '4',
    'revision_id' => '4',
    'language' => 'und',
    'delta' => '0',
    'field_test_profile_phone_value' => '(250) 555-0199',
  ])
  ->execute();

$connection->schema()->createTable('profile', [
  'fields' => [
    'pid' => [
      'type' => 'serial',
      'not null' => TRUE,
      'size' => 'normal',
    ],
    'vid' => [
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'type' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ],
    'uid' => [
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'label' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '255',
      'default' => '',
    ],
    'created' => [
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
    ],
    'changed' => [
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
    ],
  ],
  'primary key' => [
    'pid',
  ],
  'unique keys' => [
    'vid' => [
      'vid',
    ],
  ],
  'indexes' => [
    'uid' => [
      'uid',
    ],
  ],
  'mysql_character_set' => 'utf8',
]);

$connection->insert('profile')
  ->fields([
    'pid',
    'vid',
    'type',
    'uid',
    'label',
    'created',
    'changed',
  ])
  ->values([
    'pid' => '1',
    'vid' => '1',
    'type' => 'main',
    'uid' => '2',
    'label' => 'Main profile',
    'created' => '1708105549',
    'changed' => '1708105549',
  ])
  ->values([
    'pid' => '2',
    'vid' => '2',
    'type' => 'test_profile_type',
    'uid' => '2',
    'label' => 'Test profile type',
    'created' => '1708105649',
    'changed' => '1708105649',
  ])
  ->values([
    'pid' => '3',
    'vid' => '3',
    'type' => 'main',
    'uid' => '3',
    'label' => 'Main profile',
    'created' => '1708105711',
    'changed' => '1708105711',
  ])
  ->values([
    'pid' => '4',
    'vid' => '4',
    'type' => 'test_profile_type',
    'uid' => '3',
    'label' => 'Test profile type',
    'created' => '1708105804',
    'changed' => '1708105804',
  ])
  ->execute();

$connection->schema()->createTable('profile_revision', [
  'fields' => [
    'pid' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'default' => '0',
      'unsigned' => TRUE,
    ],
    'vid' => [
      'type' => 'serial',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'authorid' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'default' => '0',
    ],
    'label' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '255',
      'default' => '',
    ],
    'log' => [
      'type' => 'text',
      'not null' => TRUE,
      'size' => 'big',
    ],
    'timestamp' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'default' => '0',
    ],
  ],
  'primary key' => [
    'vid',
  ],
  'indexes' => [
    'pid' => [
      'pid',
    ],
    'authorid' => [
      'authorid',
    ],
  ],
  'mysql_character_set' => 'utf8',
]);

$connection->insert('profile_revision')
  ->fields([
    'pid',
    'vid',
    'authorid',
    'label',
    'log',
    'timestamp',
  ])
  ->values([
    'pid' => '1',
    'vid' => '1',
    'authorid' => '1',
    'label' => 'Main profile',
    'log' => 'Initial revision.',
    'timestamp' => '1708105549',
  ])
  ->values([
    'pid' => '2',
    'vid' => '2',
    'authorid' => '1',
    'label' => 'Test profile type',
    'log' => 'Initial revision.',
    'timestamp' => '1708105649',
  ])
  ->values([
    'pid' => '3',
    'vid' => '3',
    'authorid' => '1',
    'label' => 'Main profile',
    'log' => 'Initial revision.',
    'timestamp' => '1708105711',
  ])
  ->values([
    'pid' => '4',
    'vid' => '4',
    'authorid' => '1',
    'label' => 'Test profile type',
    'log' => 'Initial revision.',
    'timestamp' => '1708105804',
  ])
  ->execute();

$connection->schema()->createTable('profile_type', [
  'fields' => [
    'id' => [
      'type' => 'serial',
      'not null' => TRUE,
      'size' => 'normal',
    ],
    'type' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '32',
    ],
    'label' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '255',
      'default' => '',
    ],
    'weight' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ],
    'data' => [
      'type' => 'text',
      'not null' => FALSE,
      'size' => 'big',
    ],
    'status' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '1',
    ],
    'module' => [
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => '255',
    ],
  ],
  'primary key' => [
    'id',
  ],
  'unique keys' => [
    'type' => [
      'type',
    ],
  ],
  'mysql_character_set' => 'utf8',
]);

$connection->insert('profile_type')
  ->fields([
    'id',
    'type',
    'label',
    'weight',
    'data',
    'status',
    'module',
  ])
  ->values([
    'id' => '1',
    'type' => 'main',
    'label' => 'Main profile',
    'weight' => '0',
    'data' => 'a:7:{s:12:"registration";i:1;s:9:"revisions";i:0;s:14:"revision_admin";i:0;s:5:"roles";a:2:{i:2;s:1:"2";i:3;s:1:"3";}s:8:"use_page";i:0;s:10:"edit_label";i:0;s:7:"use_tab";i:0;}',
    'status' => '1',
    'module' => NULL,
  ])
  ->values([
    'id' => '2',
    'type' => 'test_profile_type',
    'label' => 'Test profile type',
    'weight' => '0',
    'data' => 'a:7:{s:12:"registration";i:0;s:9:"revisions";i:0;s:14:"revision_admin";i:0;s:5:"roles";a:1:{i:3;s:1:"3";}s:8:"use_page";i:1;s:10:"edit_label";i:1;s:7:"use_tab";i:0;}',
    'status' => '1',
    'module' => NULL,
  ])
  ->execute();

$connection->insert('registry')
  ->fields([
    'name',
    'type',
    'filename',
    'module',
    'weight',
  ])
  ->values([
    'name' => 'Profile2CRUDTestCase',
    'type' => 'class',
    'filename' => 'sites/all/modules/profile2/profile2.test',
    'module' => 'profile2',
    'weight' => '0',
  ])
  ->values([
    'name' => 'Profile2MetadataController',
    'type' => 'class',
    'filename' => 'sites/all/modules/profile2/profile2.info.inc',
    'module' => 'profile2',
    'weight' => '0',
  ])
  ->values([
    'name' => 'Profile2TypeUIController',
    'type' => 'class',
    'filename' => 'sites/all/modules/profile2/profile2.admin.inc',
    'module' => 'profile2',
    'weight' => '0',
  ])
  ->execute();

$connection->insert('system')
  ->fields([
    'filename',
    'name',
    'type',
    'owner',
    'status',
    'bootstrap',
    'schema_version',
    'weight',
    'info',
  ])
  ->values([
    'filename' => 'sites/all/modules/profile2/profile2.module',
    'name' => 'profile2',
    'type' => 'module',
    'owner' => '',
    'status' => '1',
    'bootstrap' => '0',
    'schema_version' => '7106',
    'weight' => '1',
    'info' => 'a:13:{s:4:"name";s:8:"Profile2";s:11:"description";s:36:"Supports configurable user profiles.";s:4:"core";s:3:"7.x";s:5:"files";a:3:{i:0;s:18:"profile2.admin.inc";i:1;s:17:"profile2.info.inc";i:2;s:13:"profile2.test";}s:12:"dependencies";a:1:{i:0;s:6:"entity";}s:9:"configure";s:24:"admin/structure/profiles";s:7:"version";s:7:"7.x-2.0";s:7:"project";s:8:"profile2";s:9:"datestamp";s:10:"1609945618";s:5:"mtime";i:1609945618;s:7:"package";s:5:"Other";s:3:"php";s:5:"5.3.3";s:9:"bootstrap";i:0;}',
  ])
  ->execute();
