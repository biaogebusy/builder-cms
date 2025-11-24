<?php

/**
 * @file
 * Fixtures to test config conversion to Drupal 10.1.
 *
 * @see \Drupal\Tests\allowed_formats\Functional\Formats2CoreUpdateTest::testFormats2Core()
 */

use Drupal\Core\Database\Database;

$db = Database::getConnection();

// Enable allowed_formats module.
$extensions = unserialize($db->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField());
$extensions['module']['allowed_formats'] = 0;
$db->update('config')
  ->fields(['data' => serialize($extensions)])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();

// Limit formats for node.article.body.
$field_config = unserialize($db->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'field.field.node.article.body')
  ->execute()
  ->fetchField());
$field_config['third_party_settings']['allowed_formats']['allowed_formats'] = [
  'full_html',
  'restricted_html',
];
// Also add allowed formats in the standard Drupal 10.1.x way, to test whether
// they are properly overridden.
$field_config['settings']['allowed_formats'] = [
  'basic_html',
];
$db->update('config')
  ->fields(['data' => serialize($field_config)])
  ->condition('collection', '')
  ->condition('name', 'field.field.node.article.body')
  ->execute();
