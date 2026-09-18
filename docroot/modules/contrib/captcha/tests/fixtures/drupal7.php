<?php

/**
 * @file
 * A database agnostic dump for testing purposes.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$connection->insert('variable')
  ->fields([
    'name',
    'value',
  ])
  ->values([
    'name' => 'captcha_administration_mode',
    'value' => 'i:1;',
  ])
  ->values([
    'name' => 'captcha_allow_on_admin_pages',
    'value' => 'i:0;',
  ])
  ->values([
    'name' => 'captcha_default_challenge',
    'value' => 's:12:"captcha/Math";',
  ])
  ->values([
    'name' => 'captcha_default_challenge_on_nonlisted_forms',
    'value' => 'i:1;',
  ])
  ->values([
    'name' => 'captcha_default_validation',
    'value' => 's:1:"1";',
  ])
  ->values([
    'name' => 'captcha_description',
    'value' => 's:110:"This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.";',
  ])
  ->values([
    'name' => 'captcha_enable_stats',
    'value' => 'i:1;',
  ])
  ->values([
    'name' => 'captcha_error_message',
    'value' => 's:55:"The answer you entered for the CAPTCHA was not correct.";',
  ])
  ->values([
    'name' => 'captcha_log_wrong_responses',
    'value' => 'i:1;',
  ])
  ->values([
    'name' => 'captcha_persistence',
    'value' => 's:1:"1";',
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
    'filename' => 'sites/all/modules/captcha/captcha.module',
    'name' => 'captcha',
    'type' => 'module',
    'owner' => '',
    'status' => '1',
    'bootstrap' => '0',
    'schema_version' => '7001',
    'weight' => '0',
    'info' => 'a:13:{s:4:\"name\";s:7:\"CAPTCHA\";s:11:\"description\";s:61:\"Base CAPTCHA module for adding challenges to arbitrary forms.\";s:7:\"package\";s:12:\"Spam control\";s:4:\"core\";s:3:\"7.x\";s:9:\"configure\";s:27:\"admin/config/people/captcha\";s:5:\"files\";a:5:{i:0;s:14:\"captcha.module\";i:1;s:11:\"captcha.inc\";i:2;s:17:\"captcha.admin.inc\";i:3;s:15:\"captcha.install\";i:4;s:12:\"captcha.test\";}s:7:\"version\";s:7:\"7.x-1.7\";s:7:\"project\";s:7:\"captcha\";s:9:\"datestamp\";s:10:\"1582293280\";s:5:\"mtime\";i:1582293280;s:12:\"dependencies\";a:0:{}s:3:\"php\";s:5:\"5.2.4\";s:9:\"bootstrap\";i:0;}',
  ])
  ->execute();

// Create the Captcha Points D7 Table.
$connection->schema()->createTable('captcha_points', [
  'fields' => [
    'form_id' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => 128,
      'default' => '',
    ],
    'module' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => 64,
      'default' => '',
    ],
    'captcha_type' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => 64,
      'default' => '',
    ],
  ],
  'primary key' => [
    'form_id',
  ],
  'mysql_character_set' => 'utf8',
]);

$connection->insert('captcha_points')
  ->fields([
    'form_id',
    'module',
    'captcha_type',
  ])
  ->values([
    'form_id' => 'comment_node_article_form',
    'module' => 'captcha',
    'captcha_type' => 'Math',
  ])
  ->values([
    'form_id' => 'user_pass',
    'module' => 'captcha',
    'captcha_type' => 'Math',
  ])
  ->execute();

// Create the Captcha Points D7 Table.
$connection->schema()->createTable('captcha_sessions', [
  'fields' => [
    'csid' => [
      'type' => 'serial',
      'not null' => TRUE,
      'size' => 'normal',
    ],
    'token' => [
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => 64,
    ],
    'uid' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'default' => 0,
    ],
    'sid' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => 128,
      'default' => '',
    ],
    'ip_address' => [
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => 128,
    ],
    'timestamp' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'default' => 0,
    ],
    'form_id' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => 128,
      'default' => '',
    ],
    'solution' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => 128,
      'default' => '',
    ],
    'status' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'default' => 0,
    ],
    'attempts' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'default' => 0,
    ],
  ],
  'primary key' => [
    'csid',
  ],
  'indexes' => [
    'csid_ip' => [
      'csid',
      'ip_address',
    ],
  ],
  'mysql_character_set' => 'utf8',
]);

$connection->insert('captcha_sessions')
  ->fields([
    'csid',
    'token',
    'uid',
    'sid',
    'ip_address',
    'timestamp',
    'form_id',
    'solution',
    'status',
    'attempts',
  ])
  ->values([
    'csid' => 1,
    'token' => '69e2767a2c651a887764bb60ea04cd0a',
    'uid' => 0,
    'sid' => 'svBxnT_AK4YFTbiUdCN3g9lCEqhC66NEbxasNNvGRug',
    'ip_address' => '172.18.0.1',
    'timestamp' => 1617948210,
    'form_id' => 'user_login_block',
    'solution' => '11',
    'status' => 0,
    'attempts' => 0,
  ])
  ->values([
    'csid' => 2,
    'token' => '69e2767a2c651a887764bb60ea04cd0b',
    'uid' => 0,
    'sid' => 'avBxnT_AK4YFTbiUdCN3g9lCEqhC66NEbxasNNvGRug',
    'ip_address' => '172.18.0.1',
    'timestamp' => 1617948230,
    'form_id' => 'user_login_block',
    'solution' => '20',
    'status' => 0,
    'attempts' => 0,
  ])
  ->values([
    'csid' => 3,
    'token' => '69e2767a2c651a887764bb60ea04cd0c',
    'uid' => 0,
    'sid' => 'bvBxnT_AK4YFTbiUdCN3g9lCEqhC66NEbxasNNvGRug',
    'ip_address' => '172.18.0.1',
    'timestamp' => 1617948240,
    'form_id' => 'user_login_block',
    'solution' => '25',
    'status' => 0,
    'attempts' => 0,
  ])
  ->execute();
