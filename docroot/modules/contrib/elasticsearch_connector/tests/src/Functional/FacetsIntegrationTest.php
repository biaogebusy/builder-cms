<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\Core\Url;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;
use Drupal\Tests\search_api\Functional\SearchApiBrowserTestBase;

/**
 * Test the facets module integration.
 *
 * @group elasticsearch_connector
 */
class FacetsIntegrationTest extends SearchApiBrowserTestBase {
  use ElasticsearchTestViewTrait;
  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'elasticsearch_connector',
    'elasticsearch_connector_test',
    'facets',
    'node',
    'search_api',
    'search_api_test',
    'views',
  ];

  /**
   * Test the facets module integration.
   */
  public function testFacets(): void {
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

    // System under test: Load the facets test view.
    $this->drupalGet(Url::fromRoute('view.test_elasticsearch_index_search_facets.page_1'));

    // Assert: The search view is displayed.
    $this->assertSession()->pageTextContains('test_elasticsearch_index_search_facets');

    // Assert: We see links to grape, orange, apple, strawberry, banana, Orange,
    // örange, and ὠ1 (i.e.: in the facets block).
    $this->assertSession()->linkExists('grape');
    $this->assertSession()->linkExists('orange');
    $this->assertSession()->linkExists('apple');
    $this->assertSession()->linkExists('strawberry');
    $this->assertSession()->linkExists('banana');
    $this->assertSession()->linkExists('Orange');
    $this->assertSession()->linkExists('örange');
    $this->assertSession()->linkExists('ὠ1');

    // Assert: We see IDs 1, 2, 3, 4, and 5 in the results.
    $this->assertTestViewShowsEntity('1');
    $this->assertTestViewShowsEntity('2');
    $this->assertTestViewShowsEntity('3');
    $this->assertTestViewShowsEntity('4');
    $this->assertTestViewShowsEntity('5');

    // System under test: Click on the "grape" facet link.
    $this->clickLink('grape');

    // Assert: We now see links for "Show all", apple, strawberry, banana,
    // Orange, örange, ὠ1, and "(-) grape" (and not "orange").
    $this->assertSession()->linkExists('Show all');
    $this->assertSession()->linkExists('apple');
    $this->assertSession()->linkExists('strawberry');
    $this->assertSession()->linkExists('banana');
    $this->assertSession()->linkExists('Orange');
    $this->assertSession()->linkExists('örange');
    $this->assertSession()->linkExists('ὠ1');
    $this->assertSession()->linkExists('(-) grape');

    // Assert: We see IDs 2, 4, and 5 in the results (and not 1, or 3), showing
    // the facets have successfully filtered the list.
    $this->assertTestViewNotShowsEntity('1');
    $this->assertTestViewShowsEntity('2');
    $this->assertTestViewNotShowsEntity('3');
    $this->assertTestViewShowsEntity('4');
    $this->assertTestViewShowsEntity('5');

    // System under test: Click on the "Show all" facet link.
    $this->clickLink('Show all');

    // Assert: We no longer see the links for "Show all" and "(-) grape", but we
    // do see links for grape, orange, apple, strawberry, banana, Orange,
    // örange, and ὠ1.
    $this->assertSession()->linkNotExists('Show all');
    $this->assertSession()->linkNotExists('(-) grape');
    $this->assertSession()->linkExists('grape');
    $this->assertSession()->linkExists('orange');
    $this->assertSession()->linkExists('apple');
    $this->assertSession()->linkExists('strawberry');
    $this->assertSession()->linkExists('banana');
    $this->assertSession()->linkExists('Orange');
    $this->assertSession()->linkExists('örange');
    $this->assertSession()->linkExists('ὠ1');

    // Assert: We see IDs 1, 2, 3, 4, and 5 in the results.
    $this->assertTestViewShowsEntity('1');
    $this->assertTestViewShowsEntity('2');
    $this->assertTestViewShowsEntity('3');
    $this->assertTestViewShowsEntity('4');
    $this->assertTestViewShowsEntity('5');
  }

}
