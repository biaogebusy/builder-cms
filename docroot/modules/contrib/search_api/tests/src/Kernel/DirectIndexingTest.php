<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;

/**
 * Provides tests for the "index_directly" functionality.
 *
 * @group search_api
 */
class DirectIndexingTest extends KernelTestBase {

  use PostRequestIndexingTrait;

  /**
   * The search server used for testing.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_test',
    'user',
    'system',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('search_api_task');
    $this->installConfig('search_api');

    // Create a test server.
    $this->server = Server::create([
      'name' => 'Test server',
      'id' => 'test',
      'status' => 1,
      'backend' => 'search_api_test',
    ]);
    $this->server->save();
  }

  /**
   * Tests index_directly works and is overridden by start/stopBatchTracking().
   */
  public function testDirectIndexing(): void {
    // Create a test entity for indexing.
    $entity = EntityTest::create([
      'name' => 'Test entity',
      'type' => 'entity_test',
    ]);
    $entity->save();
    // Create a second test entity that never gets updated and should never get
    // directly indexed.
    EntityTest::create([
      'name' => 'Test entity 2',
      'type' => 'entity_test',
    ])->save();

    // Create two indexes to ensure batch tracking is isolated.
    $index_1 = $this->createIndex();
    $index_1->save();
    $tracker_1 = $index_1->getTrackerInstance();
    $index_2 = $this->createIndex();
    $index_2->save();
    $tracker_2 = $index_2->getTrackerInstance();

    // At first nothing is indexed.
    $this->assertEquals(2, $tracker_1->getTotalItemsCount());
    $this->assertEquals(0, $tracker_1->getIndexedItemsCount());
    $this->assertEquals(2, $tracker_2->getTotalItemsCount());
    $this->assertEquals(0, $tracker_2->getIndexedItemsCount());

    // Start batch tracking mode for index 1 only.
    $index_1->startBatchTracking();
    $entity->save();
    $this->triggerPostRequestIndexing();

    // Index 1 shouldn't have indexed the entity; index 2 should've indexed as
    // normal.
    $this->assertEquals(2, $tracker_1->getTotalItemsCount());
    $this->assertEquals(0, $tracker_1->getIndexedItemsCount());
    $this->assertEquals(2, $tracker_2->getTotalItemsCount());
    $this->assertEquals(1, $tracker_2->getIndexedItemsCount());

    // Start batch tracking mode a second time for index 1.
    $index_1->startBatchTracking();
    $entity->save();
    $this->triggerPostRequestIndexing();

    // Index 1 shouldn't have indexed anything.
    $this->assertEquals(2, $tracker_1->getTotalItemsCount());
    $this->assertEquals(0, $tracker_1->getIndexedItemsCount());

    // Make a call to stop batch tracking: because we've started it twice, this
    // shouldn't actually stop batch tracking.
    $index_1->stopBatchTracking();
    $entity->save();
    $this->triggerPostRequestIndexing();

    // Index 1 still shouldn't have indexed the entity because it's in batch
    // tracking mode.
    $this->assertEquals(2, $tracker_1->getTotalItemsCount());
    $this->assertEquals(0, $tracker_1->getIndexedItemsCount());

    // Make a second call to stop batch tracking: this should actually stop
    // batch tracking mode.
    $index_1->stopBatchTracking();
    $entity->save();
    $this->triggerPostRequestIndexing();

    // Index 1 should now have indexed the entity because batch tracking mode's
    // been stopped.
    $this->assertEquals(2, $tracker_1->getTotalItemsCount());
    $this->assertEquals(1, $tracker_1->getIndexedItemsCount());

    // An exception should be thrown if you try to stop batch tracking again.
    $this->expectException(SearchApiException::class);
    $index_1->stopBatchTracking();
  }

  /**
   * Creates a test index.
   *
   * @return \Drupal\search_api\IndexInterface
   *   A test index.
   */
  protected function createIndex(): IndexInterface {
    return Index::create([
      'name' => $this->getRandomGenerator()->string(),
      'id' => $this->getRandomGenerator()->name(),
      'status' => 1,
      'datasource_settings' => [
        'entity:entity_test' => [],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
      'server' => $this->server->id(),
      'options' => ['index_directly' => TRUE],
    ]);
  }

}
