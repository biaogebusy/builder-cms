<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;

// cspell:ignore ~Supercalifragilisticexpialidocious smileὠ1

/**
 * Test altering the search query parameters before the query is run.
 *
 * @see \Drupal\elasticsearch_connector\Event\AlterSearchQueryParamsEvent
 * @see \Drupal\elasticsearch_connector_test\EventSubscriber\SearchQueryEventSubscriber
 *
 * @group elasticsearch_connector
 */
class AlterSearchQueryParamsEventTest extends BrowserTestBase {
  use ElasticsearchTestViewTrait;
  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'elasticsearch_connector_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test altering the search query parameters before the query is run.
   */
  public function testAlterSearchQueryParams(): void {
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

    // Setup: Visit the search page.
    $this->drupalGet(Url::fromRoute('view.test_elasticsearch_index_search.page_1'));

    // SUT: Search for 'supercalifragilisticexpialidocious', all lower-case.
    // This won't match the conditions for us altering the query parameters in
    // SearchQueryEventSubscriber. Note this assumes the term
    // 'supercalifragilisticexpialidocious' will never exist in the test data.
    $this->submitForm([
      'search_api_fulltext' => 'supercalifragilisticexpialidocious',
    ], 'Apply');

    // Assert: There should be no results.
    $this->assertTestViewNotShowsEntity('1');
    $this->assertTestViewNotShowsEntity('2');
    $this->assertTestViewNotShowsEntity('3');
    $this->assertTestViewNotShowsEntity('4');
    $this->assertTestViewNotShowsEntity('5');

    // Setup: Visit the search page.
    $this->drupalGet(Url::fromRoute('view.test_elasticsearch_index_search.page_1'));

    // SUT: Search for 'Supercalifragilisticexpialidocious', capitalized.
    // This will match the conditions for us altering the search query
    // parameters in SearchQueryEventSubscriber, so we should get results that
    // match the term 'smileὠ1'.
    $this->submitForm([
      'search_api_fulltext' => 'Supercalifragilisticexpialidocious',
    ], 'Apply');

    // Assert: We should see the search result with ID 1.
    $this->assertTestViewShowsEntity('1');
    $this->assertTestViewNotShowsEntity('2');
    $this->assertTestViewNotShowsEntity('3');
    $this->assertTestViewNotShowsEntity('4');
    $this->assertTestViewNotShowsEntity('5');
  }

}
