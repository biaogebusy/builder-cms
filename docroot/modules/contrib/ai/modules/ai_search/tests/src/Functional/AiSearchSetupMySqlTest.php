<?php

namespace Drupal\Tests\ai_search\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Contains AI Search UI setup functional tests.
 *
 * @group ai_search_functional
 */
class AiSearchSetupMySqlTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_search',
    'test_ai_provider_mysql',
    'test_ai_vdb_provider_mysql',
    'node',
    'taxonomy',
    'user',
    'system',
    'field_ui',
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to bypass access content.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Nodes for testing the indexing.
   *
   * @var array
   *   An array of nodes for testing.
   */
  protected $nodes = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {

    // Allow skipping the test if FFI is not loaded AND PHP version is not
    // 8.3. We must update gitlab-ci.yml and this with 8.4, etc as versions
    // change. See docs/modules/ai_search/index.md for details.
    if (
      (!in_array('FFI', get_loaded_extensions()) || !class_exists('FFI'))
      && !str_starts_with(phpversion(), '8.3')
      && !str_starts_with(phpversion(), '8.4')
    ) {
      $this->markTestSkipped('FFI extension is not loaded.');
    }
    parent::setUp();

    if ($this->profile != 'standard') {
      $this->drupalCreateContentType([
        'type' => 'article',
        'name' => 'Article',
      ]);
    }

    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer content types',
      'access content overview',
      'administer nodes',
      'administer node fields',
      'bypass node access',
      'administer ai',
      'administer ai providers',
      'administer search_api',
      'administer views',
    ]);

    $this->setupServerAndIndex();
    $this->createSampleContent();
    $this->indexContent();
  }

  /**
   * Set up server and index.
   *
   * This both tests the setup and provides a default setup for other tests.
   */
  public function setupServerAndIndex(): void {
    $this->drupalLogin($this->adminUser);

    // Set the embedding default provider as the test MySQL one.
    $this->drupalGet('admin/config/ai/settings');
    $this->submitForm([
      'operation__embeddings' => 'test_mysql_provider',
    ], 'Choose Model');
    $this->submitForm([
      'operation__embeddings' => 'test_mysql_provider',
      'model__embeddings' => 'mysql',
    ], 'Save configuration');

    // Set up Search API Server.
    $this->drupalGet('admin/config/search/search-api/add-server');
    $this->submitForm([
      'name' => 'Test MySQL AI Vector Database',
      'id' => 'test_mysql_vdb',
      'backend' => 'search_api_ai_search',
      'status' => TRUE,
    ], 'Save');
    $this->submitForm([
      'backend_config[embeddings_engine]' => 'test_mysql_provider__mysql',
      'backend_config[database]' => 'test_mysql',
      'backend_config[embeddings_engine_configuration][dimensions]' => 384,
      'backend_config[embeddings_engine_configuration][set_dimensions]' => TRUE,
    ], 'Save');
    $this->submitForm([
      'backend_config[database_settings][database_name]' => 'test_mysql_database',
      'backend_config[database_settings][collection]' => 'test_mysql_collection',
    ], 'Save');

    // Set up index.
    $this->drupalGet('admin/config/search/search-api/add-index');
    $this->submitForm([
      'name' => 'Test MySQL VDB Index',
      'id' => 'test_mysql_vdb_index',
      'datasources[entity:node]' => TRUE,
      'server' => 'test_mysql_vdb',
      'options[cron_limit]' => 5,
    ], 'Save and add fields');
    $this->submitForm([], 'Save and add fields');

    // Add fields.
    $page = $this->getSession()->getPage();
    // Rendered html.
    $page->pressButton('rendered_item');
    $this->submitForm([
      'view_mode[entity:node][:default]' => 'default',
    ], 'Save');
    // Title.
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields/add/nojs');
    $page->pressButton('entity:node/title');
    // Done.
    $page->clickLink('edit-done');

    // Selecting indexing options on fields page.
    $this->submitForm([
      'fields[rendered_item][indexing_option]' => 'main_content',
      'fields[title][indexing_option]' => 'contextual_content',
    ], 'Save changes');

    // Check indexing options have been configured.
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index');
    $this->assertSession()->pageTextContains('Indexing options have been configured.');
  }

  /**
   * Create sample content to check index.
   */
  public function createSampleContent(): void {
    $this->nodes[] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Chocolate Cake',
      'field_body' => [
        'value' => 'A delicious chocolate dessert made with cocoa powder and dark chocolate.',
        'format' => 'plain_text',
      ],
    ]);
    $this->nodes[] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Strawberry Cheese Cake',
      'field_body' => [
        'value' => 'A sweet cheese based dessert make with strawberries on a pie-like crust.',
        'format' => 'plain_text',
      ],
      'status' => 0,
    ]);
    $this->nodes[] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Vanilla Ice Cream',
      'field_body' => [
        'value' => 'A creamy vanilla dessert made with milk, cream, and vanilla extract.',
        'format' => 'plain_text',
      ],
    ]);
    $this->nodes[] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Tomato Soup',
      'field_body' => [
        'value' => 'A warm starter made with fresh tomatoes, garlic, and basil.',
        'format' => 'plain_text',
      ],
    ]);
    $this->nodes[] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Grilled Chicken Breast',
      'field_body' => [
        'value' => 'A savory main course made with marinated chicken breast, grilled to perfection.',
        'format' => 'plain_text',
      ],
    ]);
  }

  /**
   * Index content.
   */
  public function indexContent(): void {
    $cron_service = \Drupal::service('cron');
    $cron_service->run();
  }

  /**
   * Test the content indexing has completed.
   */
  public function testContentIndexingCompleted(): void {
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index');
    $this->assertSession()->elementTextContains('css', '.progress__percentage', '100%');
  }

  /**
   * Test the field main and contextual indexing options.
   */
  public function testFieldIndexingOptions() {
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields');
    $this->submitForm([
      'checker[entity]' => $this->nodes[0]->label() . ' (' . $this->nodes[0]->id() . ')',
    ], 'Save changes');

    if (class_exists('League\CommonMark\CommonMarkConverter')) {
      $this->assertSession()->pageTextContains('Chocolate Cake');
    }
    else {
      $has_markdown_link = $this->getSession()->getPage()->hasContent('[Chocolate Cake](' . $this->nodes[0]->toUrl()->toString() . ')');
      $has_markdown_title = $this->getSession()->getPage()->hasContent('# Chocolate Cake');
      $this->assertTrue($has_markdown_link || $has_markdown_title);
    }

    $this->assertSession()->pageTextContains('Title: Chocolate Cake');

    // Ignore the title and expect it to no longer show up.
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields');
    $this->submitForm([
      'fields[rendered_item][indexing_option]' => 'main_content',
      'fields[title][indexing_option]' => 'ignore',
    ], 'Save changes');
    $this->submitForm([
      'checker[entity]' => $this->nodes[0]->label() . ' (' . $this->nodes[0]->id() . ')',
    ], 'Save changes');

    if (class_exists('League\CommonMark\CommonMarkConverter')) {
      $this->assertSession()->pageTextContains('Chocolate Cake');
    }
    else {
      $has_markdown_link = $this->getSession()->getPage()->hasContent('[Chocolate Cake](' . $this->nodes[0]->toUrl()->toString() . ')');
      $has_markdown_title = $this->getSession()->getPage()->hasContent('# Chocolate Cake');
      $this->assertTrue($has_markdown_link || $has_markdown_title);
    }

    $this->assertSession()->pageTextNotContains('Title: Chocolate Cake');

    // Reset in case parallel test run.
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields');
    $this->submitForm([
      'fields[rendered_item][indexing_option]' => 'main_content',
      'fields[title][indexing_option]' => 'contextual_content',
    ], 'Save changes');
  }

  /**
   * Test searching via a search view.
   */
  public function testSearchView() {
    // Create the view using our index.
    $this->drupalGet('admin/structure/views/add');
    $this->submitForm([
      'label' => 'Test search view',
      'id' => 'test_search_view',
      'show[wizard_key]' => 'standard:search_api_index_test_mysql_vdb_index',
      'page[title]' => 'Test Search View',
      'page[create]' => 1,
      'page[path]' => 'test-search-view',
    ], 'Save and edit');

    // Add a search exposed filter.
    $this->drupalGet('admin/structure/views/nojs/add-handler/test_search_view/default/filter');
    $this->submitForm([
      'name[search_api_index_test_mysql_vdb_index.search_api_fulltext]' => 'search_api_index_test_mysql_vdb_index.search_api_fulltext',
    ], 'Add and configure filter criteria');

    // Expose the filter then save it.
    $edit = [
      'options[expose_button][checkbox][checkbox]' => 1,
    ];
    $this->submitForm($edit, 'Expose filter');
    $edit = [
      'options[expose_button][checkbox][checkbox]' => 1,
      'options[group_button][radios][radios]' => 0,
    ];
    $this->submitForm($edit, 'Apply');
    $this->submitForm([], 'Save');

    // Sort by relevance.
    $this->drupalGet('admin/structure/views/nojs/add-handler/test_search_view/default/sort');
    $this->submitForm([
      'name[search_api_index_test_mysql_vdb_index.search_api_relevance]' => 'search_api_index_test_mysql_vdb_index.search_api_relevance',
    ], 'Add and configure sort criteria');
    $this->submitForm([
      'options[order]' => 'DESC',
    ], 'Apply');
    $this->submitForm([], 'Save');

    // Remove sort by authored on.
    $this->drupalGet('admin/structure/views/nojs/handler/test_search_view/default/sort/created');
    $this->submitForm([], 'Remove');
    $this->submitForm([], 'Save');

    // Check results when logged in.
    $this->drupalGet('test-search-view');
    $this->submitForm([
      'search_api_fulltext' => 'Strawberry',
    ], 'Apply');
    $rows = $this->cssSelect('.views-row');
    $this->assertStringContainsString('Strawberry Cheese Cake', $rows[0]->getText(), 'Row 1 contains "cake".');

    // Now logged out: Ensure the unpublished item does not exist.
    $this->drupalLogout();
    $this->drupalGet('test-search-view');
    $this->submitForm([
      'search_api_fulltext' => 'Strawberry',
    ], 'Apply');
    $rows = $this->cssSelect('.views-row');
    $this->assertSession()->pageTextNotContains('Strawberry Cheese Cake');
  }

  /**
   * Tests that raw embedding vector is included in results when enabled.
   */
  public function testRawEmbeddingVectorInResults() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/search/search-api/server/test_mysql_vdb/edit');
    $this->submitForm([
      'backend_config[include_raw_embedding_vector]' => TRUE,
    ], 'Save');

    $this->drupalGet('admin/structure/views');
    if (!$this->getSession()->getPage()->hasLink('Test raw vector view')) {
      $this->drupalGet('admin/structure/views/add');
      $this->submitForm([
        'label' => 'Test raw vector view',
        'id' => 'test_raw_vector_view',
        'show[wizard_key]' => 'standard:search_api_index_test_mysql_vdb_index',
        'page[title]' => 'Test Raw Vector View',
        'page[create]' => 1,
        'page[path]' => 'test-raw-vector-view',
      ], 'Save and edit');

      // Add a search exposed filter.
      $this->drupalGet('admin/structure/views/nojs/add-handler/test_raw_vector_view/default/filter');
      $this->submitForm([
        'name[search_api_index_test_mysql_vdb_index.search_api_fulltext]' => 'search_api_index_test_mysql_vdb_index.search_api_fulltext',
      ], 'Add and configure filter criteria');

      // Expose the filter then save it.
      $edit = [
        'options[expose_button][checkbox][checkbox]' => 1,
      ];
      $this->submitForm($edit, 'Expose filter');
      $edit = [
        'options[expose_button][checkbox][checkbox]' => 1,
        'options[group_button][radios][radios]' => 0,
      ];
      $this->submitForm($edit, 'Apply');
      $this->submitForm([], 'Save');
    }

    // Go to the search view page.
    $this->drupalGet('test-raw-vector-view');
    $this->submitForm([
      'search_api_fulltext' => 'chocolate',
    ], 'Apply');

    // Programmatically fetch Search API results for deeper inspection.
    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index = $index_storage->load('test_mysql_vdb_index');
    $query = $index->query();
    $query->keys('chocolate');
    $results = $query->execute();

    foreach ($results->getResultItems() as $item) {
      $extra_data = $item->getExtraData();
      $this->assertArrayHasKey('raw_vector', $extra_data, 'raw_vector is present in extra data');
      $this->assertIsArray($extra_data['raw_vector'], 'raw_vector is an array');
    }
  }

}
