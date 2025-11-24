<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Kernel;

/**
 * Basic test for pulling an entity.
 *
 * This serves as a test that the PullKernelTestBase base class functions
 * correctly.
 *
 * @group entity_share
 * @group entity_share_client
 */
class BasicPullKernelTest extends PullKernelTestBase {

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
    entity_test_create_bundle('entity_test');

    $this->createChannel('entity_test', 'entity_test', 'en');
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesData(): array {
    return [
      'entity_test' => [
        'en' => [
          'source' => [
            'name' => 'Some test data',
            'type' => 'entity_test',
          ],
        ],
      ],
    ];
  }

  /**
   * Tests a basic pull of an entity.
   */
  public function testBasicPull(): void {
    $this->prepareContent();
    $this->pullChannel('entity_test_entity_test_en');

    // We have 2 entities, the source and the pulled.
    $entity_storage = $this->entityTypeManager->getStorage('entity_test');
    $entities = $entity_storage->loadMultiple();
    $this->assertCount(2, $entities);

    $this->assertAllPulledEntitiesMatchSource();

    // Update the source entity and pull again.
    $source_entity = $this->entityRepository->loadEntityByUuid('entity_test', 'source');
    $source_entity->name = 'A new name';
    $source_entity->save();

    $this->pullChannel('entity_test_entity_test_en');

    // We still have only 2 entities, the source and the pulled.
    $entity_storage = $this->entityTypeManager->getStorage('entity_test');
    $entities = $entity_storage->loadMultiple();
    $this->assertCount(2, $entities);

    $this->assertAllPulledEntitiesMatchSource();
  }

}
