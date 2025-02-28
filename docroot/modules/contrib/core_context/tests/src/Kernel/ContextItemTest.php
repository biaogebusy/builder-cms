<?php

namespace Drupal\Tests\core_context\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * @group core_context
 */
class ContextItemTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'core_context',
    'core_context_test',
    'ctools',
    'entity_test',
    'field',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
  }

  /**
   * Tests attaching contexts to third-party settings.
   */
  public function testSettings() {
    EntityTestBundle::create([
        'id' => 'foobaz',
      ])
      ->setThirdPartySetting('core_context', 'contexts', [
        'wambooli' => [
          'type' => 'string',
          'label' => 'Pastafazoul!',
          'description' => 'I would try to wow you, but I cannot be arsed.',
          'value' => 'Behold!',
        ],
      ])
      ->save();
  }

  /**
   * Tests attaching contexts to a content entity in a field.
   */
  public function testField() {
    EntityTestBundle::create(['id' => 'foobaz'])->save();

    $storage = FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'context',
      'type' => 'context',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $storage->save();

    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'foobaz',
    ])->save();

    /** @var EntityTest $entity */
    $entity = EntityTest::create(['type' => 'foobaz'])
      ->set('context', [
        'id' => 'wambooli',
        'type' => 'string',
        'label' => 'Pastafazoul!',
        'description' => 'I would try to wow you, but I cannot be arsed.',
        'value' => 'Behold!',
      ]);

    $entity->save();

    // Reload the entity so we can assert that all the values were saved.
    $entity = EntityTest::load($entity->id());

    $this->assertSame('wambooli', $entity->context->id);
    $this->assertSame('string', $entity->context->type);
    $this->assertSame('Pastafazoul!', $entity->context->label);
    $this->assertSame('I would try to wow you, but I cannot be arsed.', $entity->context->description);
    $this->assertSame('Behold!', $entity->context->value);
  }

}
