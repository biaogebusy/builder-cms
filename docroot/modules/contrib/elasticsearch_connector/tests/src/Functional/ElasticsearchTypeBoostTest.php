<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;

/**
 * Tests the type booster plugin.
 *
 * @group elasticsearch_connector
 */
class ElasticsearchTypeBoostTest extends BrowserTestBase {
  use ElasticsearchTestViewTrait;
  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dblog',
    'views',
    'elasticsearch_connector',
    'elasticsearch_connector_test',
  ];

  /**
   * Tests the type booster plugin.
   */
  public function testTypeBoostResults(): void {
    // Setup: Set configuration for the type booster plugin.
    $indexConfig = $this->container->get('config.factory')->getEditable('search_api.index.test_elasticsearch_index');
    $processorConfig = $indexConfig->get('processor_settings');
    unset($processorConfig['type_boost']);
    $processorConfig['elasticsearch_type_boost']['boosts']['entity:entity_test_mulrev_changed']['datasource_boost'] = '1.00';
    $processorConfig['elasticsearch_type_boost']['boosts']['entity:entity_test_mulrev_changed']['bundle_boosts']['entity_test_mulrev_changed'] = '0.10';
    $processorConfig['elasticsearch_type_boost']['boosts']['entity:entity_test_mulrev_changed']['bundle_boosts']['item'] = '21.00';
    $processorConfig['elasticsearch_type_boost']['boosts']['entity:entity_test_mulrev_changed']['bundle_boosts']['article'] = '0.10';
    $indexConfig->set('processor_settings', $processorConfig);
    $indexConfig->save();

    // Setup: Create an admin user.
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'access site reports',
      'administer search_api',
      'view test entity',
    ]));

    // Setup: Set up the example content structure and add some example content.
    $this->setUpExampleStructure();
    $this->insertExampleContent();

    // Setup: Re-index content in the test_elasticsearch_index.
    $numberIndexed = $this->indexItems(self::getIndexId());

    // Assert: The number of items indexed matches the number of items inserted.
    $this->assertEquals(\count($this->entities), $numberIndexed, 'The number of items indexed should match the number of items inserted.');

    // SUT: Load a search view.
    $this->drupalGet(Url::fromRoute('view.test_elasticsearch_index_search.page_1'));

    // Assert: The search view is displayed.
    $this->assertSession()->pageTextContains('test_elasticsearch_index_search');

    // SUT: Search for the term "foo".
    $this->submitForm(['search_api_fulltext' => 'foo'], 'Apply');

    // Assert: For the search term 'foo', we should see rows for IDs 1, 2, 4,
    // and 5; but not 3.
    $entity1 = $this->assertTestViewHasEntityRow('1');
    $entity2 = $this->assertTestViewHasEntityRow('2');
    $entity4 = $this->assertTestViewHasEntityRow('4');
    $entity5 = $this->assertTestViewHasEntityRow('5');
    $this->assertTestViewNotShowsEntity('3');

    // Assert: With a boost of 21.0 for 'items', ID 2 should be first, followed
    // by ID 1 (both items); then IDs 4 and 5 (both articles).
    $this->assertTestViewHasEntityIdInPosition('2', 1);
    $this->assertTestViewHasEntityIdInPosition('1', 2);
    $this->assertTestViewHasEntityIdInPosition('4', 3);
    $this->assertTestViewHasEntityIdInPosition('5', 4);
  }

  /**
   * Assert the test view has a row for a given entity ID in the given position.
   *
   * @param string $entityId
   *   The ID of the entity that we're hoping to find in the given position.
   * @param int $position
   *   The position that we're hoping to find the given entity in. Note the
   *   first position is 1 (not 0) since the headers are column 0.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Throws an Element Not Found exception if we can't find a table row with
   *   the given ID in the given position. Note this will throw if the ID exists
   *   in a different position.
   */
  protected function assertTestViewHasEntityIdInPosition(string $entityId, int $position): void {
    $this->assertSession()->elementExists('xpath',
      $this->assertSession()->buildXPathQuery('//tbody/tr[:position]/td[@headers=:column][text()[contains(.,:text)]]', [
        ':position' => $position,
        ':column' => 'view-id-table-column',
        ':text' => $entityId,
      ])
    );
  }

}
