<?php

namespace Drupal\Tests\eck\Kernel\Migrate\d7;

use Drupal\field\Entity\FieldStorageConfig;

/**
 * Migrates Drupal 7 fields.
 *
 * @group eck
 */
class MigrateEckFieldTest extends MigrateEckTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'eck',
    'node',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(static::$modules);
    $this->executeMigrations([
      'd7_eck_type',
      'd7_field',
    ]);
  }

  /**
   * Tests migrating D7 fields to field_storage_config entities.
   */
  public function testFields() {
    $this->assertFieldStorage('complex_entity.field_complex_entity', 'entity_reference', TRUE, 1);
    $this->assertFieldStorage('complex_entity.field_node', 'entity_reference', TRUE, 1);
    $this->assertFieldStorage('complex_entity.field_simple_entities', 'entity_reference', TRUE, -1);
    $this->assertFieldStorage('complex_entity.field_text', 'string', TRUE, 1);
    $this->assertFieldStorage('node.body', 'text_with_summary', TRUE, 1);
    $this->assertFieldStorage('simple_entity.field_text', 'string', TRUE, 1);

    // Assert that the entityreference fields are referencing the correct
    // entity type.
    $field = FieldStorageConfig::load('complex_entity.field_complex_entity');
    $this->assertEquals('complex_entity', $field->getSetting('target_type'));
    $field = FieldStorageConfig::load('complex_entity.field_node');
    $this->assertEquals('node', $field->getSetting('target_type'));
    $field = FieldStorageConfig::load('complex_entity.field_simple_entities');
    $this->assertEquals('simple_entity', $field->getSetting('target_type'));
  }

}
