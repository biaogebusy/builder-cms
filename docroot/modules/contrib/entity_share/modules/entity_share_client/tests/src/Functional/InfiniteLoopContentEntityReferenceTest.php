<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\node\NodeInterface;

/**
 * Functional test class for infinite loop in content entity reference field.
 *
 * @group entity_share
 * @group entity_share_client
 */
class InfiniteLoopContentEntityReferenceTest extends InfiniteLoopTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareContent() {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Create two nodes referencing each other.
    $node_1 = $node_storage->create([
      'uuid' => 'es_test_content_reference_one',
      'type' => static::$entityBundleId,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
    ]);
    $node_1->save();

    $node_2 = $node_storage->create([
      'uuid' => 'es_test_content_reference_two',
      'type' => static::$entityBundleId,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      'field_es_test_content_reference' => $node_1->id(),
    ]);
    $node_2->save();

    $node_1->set('field_es_test_content_reference', $node_2->id());
    $node_1->save();

    $this->entities = [
      'node' => [
        'es_test_content_reference_one' => $node_1,
        'es_test_content_reference_two' => $node_2,
      ],
    ];
  }

}
