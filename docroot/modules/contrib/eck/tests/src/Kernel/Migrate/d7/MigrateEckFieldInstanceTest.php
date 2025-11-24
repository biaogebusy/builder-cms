<?php

namespace Drupal\Tests\eck\Kernel\Migrate\d7;

use Drupal\field\Entity\FieldConfig;

/**
 * Migrates Drupal 7 field instances.
 *
 * @group field
 */
class MigrateEckFieldInstanceTest extends MigrateEckTestBase {

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
      'd7_node_type',
      'd7_comment_type',
      'd7_eck_bundle',
      'd7_field',
      'd7_field_instance',
    ]);
  }

  /**
   * Asserts the settings of an entity reference field config entity.
   *
   * @param string $id
   *   The entity ID in the form ENTITY_TYPE.BUNDLE.FIELD_NAME.
   * @param string[]|null $target_bundles
   *   An array of expected target bundles.
   */
  protected function assertEntityReferenceFields($id, $target_bundles) {
    $field = FieldConfig::load($id);
    $handler_settings = $field->getSetting('handler_settings');
    $this->assertArrayHasKey('target_bundles', $handler_settings);
    if ($handler_settings['target_bundles']) {
      foreach ($handler_settings['target_bundles'] as $target_bundle) {
        $this->assertContains($target_bundle, $target_bundles);
      }
    }
    else {
      $this->assertNULL($handler_settings['target_bundles']);
    }
  }

  /**
   * Tests migrating D7 field instances to field_config entities.
   */
  public function testFieldInstances() {
    $this->assertFieldInstance('complex_entity.complex_entity.field_complex_entity', 'Complex entity', 'entity_reference', FALSE, FALSE);
    $this->assertFieldInstance('complex_entity.complex_entity.field_node', 'Node', 'entity_reference', TRUE, FALSE);
    $this->assertFieldInstance('complex_entity.complex_entity.field_simple_entities', 'Simple entities', 'entity_reference', FALSE, FALSE);
    $this->assertFieldInstance('complex_entity.complex_entity.field_text', 'Text', 'string', TRUE, TRUE);
    $this->assertFieldInstance('simple_entity.simple_entity.field_text', 'Text', 'string', FALSE, TRUE);
    $this->assertFieldInstance('node.article.body', 'Body', 'text_with_summary', FALSE, FALSE);

    $this->assertEntityReferenceFields('complex_entity.complex_entity.field_complex_entity', NULL);
    $this->assertEntityReferenceFields('complex_entity.complex_entity.field_node', NULL);
    $this->assertEntityReferenceFields('complex_entity.complex_entity.field_simple_entities', ['simple_entity']);
  }

}
