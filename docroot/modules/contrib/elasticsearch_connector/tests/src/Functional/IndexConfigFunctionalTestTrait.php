<?php

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api\Utility\Utility;

/**
 * Functions common to Index-configuration Functional tests.
 */
trait IndexConfigFunctionalTestTrait {

  /**
   * The generated test entities, keyed by ID.
   *
   * @var \Drupal\entity_test\Entity\EntityTestMulRevChanged[]
   */
  protected $entities = [];

  /**
   * The Search API item IDs of the generated entities.
   *
   * @var string[]
   */
  protected $ids = [];

  /**
   * Creates and saves a test entity with the given values.
   *
   * @param int $id
   *   The entity's ID.
   * @param array $values
   *   The entity's property values.
   *
   * @return \Drupal\entity_test\Entity\EntityTestMulRevChanged
   *   The created entity.
   */
  protected function addTestEntity($id, array $values) {
    $entity_type = 'entity_test_mulrev_changed';
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $values['id'] = $id;
    $this->entities[$id] = $storage->create($values);
    $this->entities[$id]->save();
    $this->ids[$id] = Utility::createCombinedId("entity:$entity_type", "$id:en");
    return $this->entities[$id];
  }

  /**
   * Retrieves the search index used by this test.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The search index.
   */
  protected function getIndex(): IndexInterface {
    return Index::load($this->indexId);
  }

  /**
   * Get the number of items in the default index.
   *
   * @return int
   *   The number of items in the default index.
   */
  protected function getNumberOfItemsInIndex(): int {
    $serverBackend = $this->getServer()
      ->getBackend();

    if (!$serverBackend instanceof ElasticSearchBackend) {
      return 0;
    }

    $this->refreshIndex();

    return (int) $serverBackend
      ->getClient()
      ->count(['index' => $this->indexId])
      ->asArray()['count'];
  }

  /**
   * Retrieves the search server used by this test.
   *
   * @return \Drupal\search_api\ServerInterface
   *   The search server.
   */
  protected function getServer(): ServerInterface {
    return Server::load($this->serverId);
  }

  /**
   * Indexes all (unindexed) items on the default index.
   *
   * @return int
   *   The number of successfully indexed items.
   */
  protected function indexItems(): int {
    return $this->getIndex()->indexItems();
  }

  /**
   * Creates several test entities.
   */
  protected function insertExampleContent(): void {
    // To test Unicode compliance, include all kind of strange characters here.
    $smiley = json_decode('"\u1F601"');
    $this->addTestEntity(1, [
      'name' => 'foo bar baz foobaz föö smile' . $smiley,
      'body' => 'test test case Case casE',
      'type' => 'entity_test_mulrev_changed',
      'keywords' => ['Orange', 'orange', 'örange', 'Orange', $smiley],
      'category' => 'item_category',
    ]);
    $this->addTestEntity(2, [
      'name' => 'foo test foobuz',
      'body' => 'bar test casE',
      'type' => 'entity_test_mulrev_changed',
      'keywords' => ['orange', 'apple', 'grape'],
      'category' => 'item_category',
    ]);
    $this->addTestEntity(3, [
      'name' => 'bar',
      'body' => 'test foobar Case',
      'type' => 'entity_test_mulrev_changed',
    ]);
    $this->addTestEntity(4, [
      'name' => 'foo baz',
      'body' => 'test test test',
      'type' => 'entity_test_mulrev_changed',
      'keywords' => ['apple', 'strawberry', 'grape'],
      'category' => 'article_category',
      'width' => '1.0',
    ]);
    $this->addTestEntity(5, [
      'name' => 'bar baz',
      'body' => 'foo',
      'type' => 'entity_test_mulrev_changed',
      'keywords' => ['orange', 'strawberry', 'grape', 'banana'],
      'category' => 'article_category',
      'width' => '2.0',
    ]);
    $count = \Drupal::entityQuery('entity_test_mulrev_changed')
      ->count()
      ->accessCheck(FALSE)
      ->execute();
    $this->assertEquals(5, $count, "$count items inserted.");
  }

  /**
   * Refreshes the indices on the server.
   *
   * This ensures all indexed data is available to searches.
   */
  protected function refreshIndex(): void {
    $backend = $this->getIndex()
      ->getServerInstance()
      ->getBackend();
    if ($backend instanceof ElasticSearchBackend) {
      $backend->getConnector()
        ->getClient()
        ->indices()
        ->refresh();
    }
  }

  /**
   * Set up the index by clearing it, inserting example content, and indexing.
   */
  protected function setUpIndex(): void {
    // Start each test with an empty index, add example content, then index the
    // new content.
    $this->getIndex()->clear();
    $this->refreshIndex();
    $this->insertExampleContent();
    $this->indexItems();
    $this->refreshIndex();
  }

}
