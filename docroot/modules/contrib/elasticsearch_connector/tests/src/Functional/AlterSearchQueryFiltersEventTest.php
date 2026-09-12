<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\facets\Functional\ExampleContentTrait;

// cspell:ignore ~pomme

/**
 * Test altering a search query's filters before a search query is built.
 *
 * @see \Drupal\elasticsearch_connector\Event\AlterSearchQueryFiltersEvent
 * @see \Drupal\elasticsearch_connector_test\EventSubscriber\SearchQueryFilterSubscriber
 *
 * @group elasticsearch_connector
 */
class AlterSearchQueryFiltersEventTest extends BrowserTestBase {
  use ElasticsearchTestViewTrait;
  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'facets',
    'views',
    'elasticsearch_connector_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test altering a search query's filters before a search query is built.
   */
  public function testSearchFilterParamsEvent(): void {
    // Setup: Log in as a user with permission to see the test entities.
    $this->drupalLogin($this->drupalCreateUser([
      'view test entity',
    ]));

    // Setup: Set up the example content structure and add some example content.
    $this->setUpExampleStructure();
    $this->insertExampleContent();

    // Setup: Re-index content in the test_elasticsearch_index.
    $numberIndexed = $this->indexItems(self::getIndexId());
    $this->assertEquals(\count($this->entities), $numberIndexed, 'The number of items indexed should match the number of items inserted.');

    // SUT: Visit the facets search page, pre-filling in the search facet to
    // 'pomme', all lower-case. This won't match the conditions for altering the
    // search filter in SearchFilterSubscriber. Note this test assumes the term
    // 'pomme' will never exist in the test data.
    $this->drupalGet(Url::fromRoute('view.test_elasticsearch_index_search_facets.page_1'), [
      'query' => ['f[0]' => 'keywords:pomme'],
    ]);

    // Assert: There should be no results.
    $this->assertTestViewNotShowsEntity('1');
    $this->assertTestViewNotShowsEntity('2');
    $this->assertTestViewNotShowsEntity('3');
    $this->assertTestViewNotShowsEntity('4');
    $this->assertTestViewNotShowsEntity('5');

    // SUT: Visit the facets search page, pre-filling in the search facet to
    // 'Pomme', capitalized. This will match the conditions for altering the
    // search filter in SearchFilterSubscriber, so we should get results that
    // match the keyword 'apple'.
    $this->drupalGet(Url::fromRoute('view.test_elasticsearch_index_search_facets.page_1'), [
      'query' => ['f[0]' => 'keywords:Pomme'],
    ]);

    // Assert: We should see the search results with IDs 2, and 4.
    $this->assertTestViewNotShowsEntity('1');
    $this->assertTestViewShowsEntity('2');
    $this->assertTestViewNotShowsEntity('3');
    $this->assertTestViewShowsEntity('4');
    $this->assertTestViewNotShowsEntity('5');
  }

}
