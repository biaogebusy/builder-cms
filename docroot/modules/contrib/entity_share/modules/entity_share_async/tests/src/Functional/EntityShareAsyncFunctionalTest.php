<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_async\Functional;

use Drupal\node\NodeInterface;
use Drupal\Tests\entity_share_client\Functional\EntityShareClientFunctionalTestBase;

/**
 * General functional test class.
 *
 * @group entity_share
 * @group entity_share_async
 */
class EntityShareAsyncFunctionalTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_share_async',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  protected static $entityBundleId = 'es_test';

  /**
   * {@inheritdoc}
   */
  protected static $entityLangcode = 'en';

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
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        'en' => [
          'es_test' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
          'es_test_not_asynced' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test async feature.
   */
  public function testAsync() {
    $queue_factory = $this->container->get('queue');
    $state_storage = $this->container->get('state');
    $channel_id = static::$entityTypeId . '_' . static::$entityBundleId . '_' . static::$entityLangcode;

    /** @var \Drupal\entity_share_async\Service\QueueHelperInterface $queue_helper */
    $queue_helper = $this->container->get('entity_share_async.queue_helper');
    $queue_helper->enqueue($this->remote->id(), $channel_id, $this::IMPORT_CONFIG_ID, ['es_test']);

    // Test that the entity had been enqueued and is present in the state.
    $queue = $queue_factory->get('entity_share_async_import');
    $this->assertEquals(1, $queue->numberOfItems(), 'The entity had been enqueued.');
    $async_states = $state_storage->get('entity_share_async.states', []);
    $this->assertTrue(isset($async_states[$this->remote->id()][$channel_id]['es_test']), 'The entity is marked for syncing.');

    // Test that both contents had been deleted.
    $entity_id = $this->getEntityId('node', 'es_test');
    $this->assertEmpty($entity_id, 'The node with the UUID es_test had been deleted.');
    $entity_id = $this->getEntityId('node', 'es_test_not_asynced');
    $this->assertEmpty($entity_id, 'The node with the UUID es_test_not_asynced had been deleted.');

    $this->container->get('cron')->run();

    // Test that the queue is empty and that the entity is no more in the state.
    $queue = $queue_factory->get('entity_share_async_import');
    $this->assertEquals(0, $queue->numberOfItems(), 'The entity had been processed by the queue.');
    $async_states = $state_storage->get('entity_share_async.states', []);
    $this->assertFalse(isset($async_states[$this->remote->id()][$channel_id]['es_test']), 'The entity is no more marked for syncing.');

    // Test that only the enqueued content had been synced.
    $entity_id = $this->getEntityId('node', 'es_test');
    $this->assertNotEmpty($entity_id, 'The node with the UUID es_test has been imported.');
    $entity_id = $this->getEntityId('node', 'es_test_not_asynced');
    $this->assertEmpty($entity_id, 'The node with the UUID es_test_not_asynced has not been imported.');
  }

  /**
   * {@inheritdoc}
   */
  protected function populateRequestService() {
    parent::populateRequestService();
    // Needs to make the requests when only the referencing content will be
    // required.
    $selected_entities = [
      'es_test',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->discoverJsonApiEndpoints($prepared_url);
  }

}
