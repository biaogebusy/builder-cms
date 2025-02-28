<?php
// @codingStandardsIgnoreFile
/**
 * @file
 * A database agnostic dump for testing purposes.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

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
    'filename' => 'sites/all/modules/contrib/entity_print/entity_print.module',
    'name' => 'entity_print',
    'type' => 'module',
    'owner' => '',
    'status' => '1',
    'bootstrap' => '0',
    'schema_version' => '7001',
    'weight' => '0',
    'info' => 'a:14:{s:4:\"name\";s:12:\"Entity Print\";s:4:\"core\";s:3:\"7.x\";s:7:\"package\";s:12:\"Entity Print\";s:9:\"configure\";s:32:\"admin/config/content/entityprint\";s:12:\"dependencies\";a:2:{i:0;s:6:\"entity\";i:1;s:14:\"phpwkhtmltopdf\";}s:5:\"files\";a:1:{i:0;s:23:\"tests/entity_print.test\";}s:17:\"test_dependencies\";a:3:{i:0;s:6:\"entity\";i:1;s:9:\"libraries\";i:2;s:14:\"phpwkhtmltopdf\";}s:7:\"version\";s:7:\"7.x-1.5\";s:7:\"project\";s:12:\"entity_print\";s:9:\"datestamp\";s:10:\"1481237300\";s:5:\"mtime\";i:1481237300;s:11:\"description\";s:0:\"\";s:3:\"php\";s:5:\"5.2.4\";s:9:\"bootstrap\";i:0;}'
  ])
  ->execute();


$connection->insert('variable')
  ->fields([
    'name',
    'value',
  ])
  ->values([
    'name' => 'entity_print_default_css',
    'value' => 'i:0;',
  ])
  ->execute();
