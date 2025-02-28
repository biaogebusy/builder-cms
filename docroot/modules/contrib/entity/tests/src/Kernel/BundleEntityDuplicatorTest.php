<?php

namespace Drupal\Tests\entity\Kernel;

use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\system\Entity\Action;

/**
 * Tests the bundle entity duplicator.
 *
 * @coversDefaultClass \Drupal\entity\BundleEntityDuplicator
 * @group entity
 */
class BundleEntityDuplicatorTest extends EntityKernelTestBase {

  /**
   * A test bundle entity.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityInterface
   */
  protected $bundleEntity;

  /**
   * The bundle entity duplicator.
   *
   * @var \Drupal\entity\BundleEntityDuplicator
   */
  protected $duplicator;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('action');

    $this->bundleEntity = EntityTestBundle::create([
      'id' => 'test',
      'label' => 'Test',
      'description' => 'This is the original description!',
    ]);
    $this->bundleEntity->save();
    $this->duplicator = $this->container->get('entity.bundle_entity_duplicator');
  }

  /**
   * @covers ::duplicate
   */
  public function testDuplicateInvalidEntity() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The "action" entity type is not a bundle entity type.');
    $this->duplicator->duplicate(Action::create(), []);
  }

  /**
   * @covers ::duplicate
   */
  public function testDuplicateNoId() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The $values[\'id\'] key is empty or missing.');
    $this->duplicator->duplicate($this->bundleEntity, []);
  }

  /**
   * @covers ::duplicate
   */
  public function testDuplicate() {
    $duplicated_bundle_entity = $this->duplicator->duplicate($this->bundleEntity, [
      'id' => 'test2',
      'label' => 'Test2',
    ]);
    $this->assertFalse($duplicated_bundle_entity->isNew());
    $this->assertEquals('test2', $duplicated_bundle_entity->id());
    $this->assertEquals('Test2', $duplicated_bundle_entity->label());
    $this->assertEquals($this->bundleEntity->get('description'), $duplicated_bundle_entity->get('description'));
  }

  /**
   * @covers ::duplicate
   * @covers ::duplicateFields
   * @covers ::duplicateDisplays
   */
  public function testDuplicateWithFieldAndDisplays() {
    $this->createTextField('field_text', 'test', 'Test text');
    $form_display = $this->getDisplay('entity_test_with_bundle', 'test', 'form');
    $form_display->setComponent('field_text', [
      'type' => 'text_textfield',
      'weight' => 0,
    ]);
    $form_display->save();
    $view_display = $this->getDisplay('entity_test_with_bundle', 'test', 'view');
    $view_display->setComponent('field_text', [
      'type' => 'text_default',
      'weight' => 0,
    ]);
    $view_display->save();

    $duplicated_bundle_entity = $this->duplicator->duplicate($this->bundleEntity, [
      'id' => 'test2',
      'label' => 'Test2',
    ]);
    $this->assertFalse($duplicated_bundle_entity->isNew());
    $this->assertEquals('test2', $duplicated_bundle_entity->id());
    $this->assertEquals('Test2', $duplicated_bundle_entity->label());
    $this->assertEquals($this->bundleEntity->get('description'), $duplicated_bundle_entity->get('description'));

    // Confirm that the field was copied to the new bundle.
    $entity = EntityTestWithBundle::create(['type' => 'test2']);
    $this->assertTrue($entity->hasField('field_text'));

    // Confirm that the entity displays were copied.
    $form_display = $this->getDisplay('entity_test_with_bundle', 'test2', 'form');
    $this->assertNotEmpty($form_display->getComponent('field_text'));

    $view_display = $this->getDisplay('entity_test_with_bundle', 'test2', 'view');
    $this->assertNotEmpty($view_display->getComponent('field_text'));
  }

  /**
   * @covers ::duplicateFields
   */
  public function testDuplicateFieldsInvalidEntity() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The "action" entity type is not a bundle entity type.');
    $this->duplicator->duplicateFields(Action::create(), 'test2');
  }

  /**
   * @covers ::duplicateFields
   */
  public function testDuplicateFieldsEmptyTarget() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The $target_bundle_id must not be empty.');
    $this->duplicator->duplicateFields($this->bundleEntity, '');
  }

  /**
   * @covers ::duplicateFields
   */
  public function testDuplicateFields() {
    $this->createTextField('field_text', 'test', 'Test text');
    $this->createTextField('field_text2', 'test', 'Test text2');

    $second_bundle_entity = EntityTestBundle::create([
      'id' => 'test2',
      'label' => 'Test2',
    ]);
    $second_bundle_entity->save();
    $entity = EntityTestWithBundle::create(['type' => 'test2']);
    $this->assertFalse($entity->hasField('field_text'));
    $this->assertFalse($entity->hasField('field_text2'));

    $this->duplicator->duplicateFields($this->bundleEntity, 'test2');
    $entity = EntityTestWithBundle::create(['type' => 'test2']);
    $this->assertTrue($entity->hasField('field_text'));
    $this->assertTrue($entity->hasField('field_text2'));
  }

  /**
   * @covers ::duplicateDisplays
   */
  public function testDuplicateDisplaysInvalidEntity() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The "action" entity type is not a bundle entity type.');
    $this->duplicator->duplicateDisplays(Action::create(), 'test2');
  }

  /**
   * @covers ::duplicateDisplays
   */
  public function testDuplicateDisplaysEmptyTarget() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The $target_bundle_id must not be empty.');
    $this->duplicator->duplicateDisplays($this->bundleEntity, '');
  }

  /**
   * @covers ::duplicateDisplays
   */
  public function testDuplicateDisplays() {
    $this->createTextField('field_text', 'test', 'Test text');
    $form_display = $this->getDisplay('entity_test_with_bundle', 'test', 'form');
    $form_display->setComponent('field_text', [
      'type' => 'text_textfield',
      'weight' => 0,
    ]);
    $form_display->save();
    $view_display = $this->getDisplay('entity_test_with_bundle', 'test', 'view');
    $view_display->setComponent('field_text', [
      'type' => 'text_default',
      'weight' => 0,
    ]);
    $view_display->save();

    $second_bundle_entity = EntityTestBundle::create([
      'id' => 'test2',
      'label' => 'Test2',
    ]);
    $second_bundle_entity->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_with_bundle',
      'field_name' => 'field_text',
      'bundle' => 'test2',
      'label' => 'Test text',
    ])->save();

    $this->duplicator->duplicateDisplays($this->bundleEntity, 'test2');
    $form_display = $this->getDisplay('entity_test_with_bundle', 'test2', 'form');
    $this->assertNotEmpty($form_display->getComponent('field_text'));

    $view_display = $this->getDisplay('entity_test_with_bundle', 'test2', 'view');
    $this->assertNotEmpty($view_display->getComponent('field_text'));
  }

  /**
   * Creates a text field on the "entity_test_with_bundle" entity.
   *
   * @param string $field_name
   *   The field name.
   * @param string $bundle
   *   The target bundle.
   * @param string $label
   *   The field label.
   */
  protected function createTextField($field_name, $bundle, $label) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test_with_bundle',
      'type' => 'text',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_with_bundle',
      'field_name' => $field_name,
      'bundle' => $bundle,
      'label' => $label,
    ])->save();
  }

  /**
   * Gets the entity display for the given entity type and bundle.
   *
   * The entity display will be created if missing.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $display_context
   *   The display context ('view' or 'form').
   *
   * @throws \InvalidArgumentException
   *   Thrown when an invalid display context is provided.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   The entity display.
   */
  protected function getDisplay($entity_type, $bundle, $display_context) {
    if (!in_array($display_context, ['view', 'form'])) {
      throw new \InvalidArgumentException(sprintf('Invalid display_context %s passed to _commerce_product_get_display().', $display_context));
    }

    $entity_type_manager = $this->container->get('entity_type.manager');
    $storage = $entity_type_manager->getStorage('entity_' . $display_context . '_display');
    $display = $storage->load($entity_type . '.' . $bundle . '.default');
    if (!$display) {
      $display = $storage->create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }

    return $display;
  }

}
