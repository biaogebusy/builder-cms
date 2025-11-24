<?php

namespace Drupal\Tests\address\Kernel\Formatter;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Provides a base test for kernel formatter tests.
 */
abstract class FormatterTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'language',
    'text',
    'entity_test',
    'user',
    'address',
    'content_translation',
  ];

  /**
   * The generated field name.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The entity display.
   *
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  protected $display;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (\Drupal::entityTypeManager()->hasDefinition('path_alias')) {
      $this->installEntitySchema('path_alias');
    }
    $this->installConfig(['system']);
    $this->installConfig(['field']);
    $this->installConfig(['text']);
    $this->installConfig(['address']);
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_mul');

    $this->fieldName = mb_strtolower($this->randomMachineName());
    $this->container->get('content_translation.manager')->setEnabled('entity_test_mul', 'entity_test_mul', TRUE);
  }

  /**
   * Creates an entity_test field of the given type.
   *
   * @param string $field_type
   *   The field type.
   * @param string $formatter_id
   *   The formatter ID.
   */
  protected function createField($field_type, $formatter_id) {
    $field_storage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'entity_test_mul',
      'type' => $field_type,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test_mul',
      'label' => $this->randomMachineName(),
    ]);
    $field->save();

    $this->display = \Drupal::service('entity_display.repository')->getViewDisplay('entity_test_mul', 'entity_test_mul', 'default');
    $this->display->setComponent($this->fieldName, [
      'type' => $formatter_id,
      'settings' => [],
    ]);
    $this->display->save();
  }

  /**
   * Renders fields of a given entity with a given display.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity object with attached fields to render.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The display to render the fields in.
   *
   * @return string
   *   The rendered entity fields.
   */
  protected function renderEntityFields(FieldableEntityInterface $entity, EntityViewDisplayInterface $display) {
    $content = $display->build($entity);
    $content = $this->render($content);
    return $content;
  }

}
