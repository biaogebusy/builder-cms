<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Kernel;

use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;

/**
 * Test entity references.
 *
 * @group entity_share
 * @group entity_share_client
 */
class EntityReferencePullTest extends PullKernelTestBase {
  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');

    entity_test_create_bundle('pulled');
    entity_test_create_bundle('referenced');

    $this->createEntityReferenceField('entity_test', 'pulled', 'field_ref', 'Ref', 'entity_test');

    $this->createChannel('entity_test', 'pulled', 'en');
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesData(): array {
    return [
      'entity_test' => [
        'en' => [
          // Entity not in a channel which is referenced by an entity in a
          // channel.
          'referenced' => [
            'name' => 'Some test data',
            'type' => 'referenced',
          ],
          // Entity in a channel which is pulled explicitly.
          'pulled' => [
            'name' => 'Some test data',
            'type' => 'pulled',
            'field_ref' => [
              0 => ['entity_test', 'referenced'],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Tests that a referenced entity outside of a channel is pulled.
   */
  public function testImportReferencedEntityOutsideChannel(): void {
    $this->prepareContent();

    $this->pullChannel('entity_test_pulled_en');

    $entity_storage = $this->entityTypeManager->getStorage('entity_test');
    $entities = $entity_storage->loadMultiple();

    $this->assertCount(4, $entities);

    $this->assertAllPulledEntitiesMatchSource();
  }

}
