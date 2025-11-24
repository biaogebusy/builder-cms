<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;

/**
 * Tests for situations when backend is down.
 *
 * @group elasticsearch_connector
 */
class ElasticsearchConnectorBackendTest extends BrowserTestBase {
  use ElasticsearchTestViewTrait;
  use ExampleContentTrait;

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
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an admin user.
    $admin_user = $this->drupalCreateUser([
      'access administration pages',
      'access site reports',
      'administer search_api',
      'view test entity',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests that no exception is thrown when visiting the Search API routes.
   */
  public function testSearchApiRoutes() {
    $assert_session = $this->assertSession();

    // Alter the Elasticsearch server configuration to cause failure to connect
    // to Elasticsearch server.
    $config = $this->config('search_api.server.elasticsearch_server');
    $config->set('backend_config.connector_config.url', 'http://elasticsearch:9999');
    $config->save();

    // Assert "search_api.overview" route loads without errors.
    $url = Url::fromRoute('search_api.overview');
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $assert_session->elementTextContains('css', '.search-api-server-elasticsearch-server .search-api-status', 'Unavailable');

    // Assert "entity.search_api_server.canonical" route loads without errors.
    $url = Url::fromRoute('entity.search_api_server.canonical', [
      'search_api_server' => 'elasticsearch_server',
    ]);
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $assert_session->pageTextContains('Local test server');

    // Assert "entity.search_api_index.canonical" route loads without errors.
    $url = Url::fromRoute('entity.search_api_index.canonical', [
      'search_api_index' => self::getIndexId(),
    ]);
    $this->drupalGet($url);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Test Index');
    $assert_session->elementTextContains('css', '.search-api-index-summary--server-index-status', 'Error while checking server index status');

    // Assert error produced on "search_api.overview" route is logged.
    $this->drupalGet('/admin/reports/dblog');
    $assert_session->pageTextContains('Elastic\Transport\Exception\NoNodeAvailableException');
  }

  /**
   * Tests that we can see results retrieved from the backend.
   */
  public function testSearchResultsView(): void {
    // Setup: Set up the example content structure and add some example content.
    $this->setUpExampleStructure();
    $this->insertExampleContent();

    // Setup: Re-index content in the test_elasticsearch_index.
    $numberIndexed = $this->indexItems(self::getIndexId());

    // Assert: The number of items indexed matches the number of items inserted.
    $this->assertEquals(\count($this->entities), $numberIndexed, 'The number of items indexed should match the number of items inserted.');

    // System under test: Load a search view.
    $this->drupalGet(Url::fromRoute('view.test_elasticsearch_index_search.page_1'));

    // Assert: The search view is displayed.
    $this->assertSession()->pageTextContains('test_elasticsearch_index_search');
    $this->assertTestViewExposedForm();
    $this->assertTestViewColumnHeaders();

    // Assert: We initially see entities 1, 2, 3, 4, 5 retrieved from the index.
    $this->assertTestViewShowsEntity('1');
    $this->assertTestViewShowsEntity('2');
    $this->assertTestViewShowsEntity('3');
    $this->assertTestViewShowsEntity('4');
    $this->assertTestViewShowsEntity('5');

    // System under test: Search for the term "foo".
    $this->submitForm(['search_api_fulltext' => 'foo'], 'Apply');

    // Assert: We see IDs 1, 2, 4, 5 returned for the term "foo". We do not see
    // ID 3 returned for the term "foo".
    $this->assertTestViewShowsEntity('1');
    $this->assertTestViewShowsEntity('2');
    $this->assertTestViewShowsEntity('4');
    $this->assertTestViewShowsEntity('5');
    $this->assertTestViewNotShowsEntity('3');
  }

}
