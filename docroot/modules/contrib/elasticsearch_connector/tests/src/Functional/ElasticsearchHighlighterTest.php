<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;

// cspell:ignore smileὠ

/**
 * Tests the highlighter.
 *
 * @group elasticsearch_connector
 */
class ElasticsearchHighlighterTest extends BrowserTestBase {
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
   * Test that we can get results from the highlighter.
   */
  public function testHighlighterResults(): void {
    // Setup: Set configuration for the highlighter.
    $indexConfig = $this->container->get('config.factory')->getEditable('search_api.index.test_elasticsearch_index');
    $processorConfig = $indexConfig->get('processor_settings');
    unset($processorConfig['highlight']);
    $processorConfig['elasticsearch_highlight'] = [
      'weights' => [
        'postprocess_query' => 0,
        'preprocess_query' => 0,
      ],
      'boundary_scanner' => 'sentence',
      'boundary_scanner_locale' => 'system',
      'encoder' => 'default',
      'fields' => ['body', 'name'],
      'fragment_size' => 60,
      'fragmenter' => 'span',
      'no_match_size' => 0,
      'number_of_fragments' => 5,
      'order' => 'none',
      'pre_tag' => '<strong>',
      'require_field_match' => TRUE,
      'snippet_joiner' => ' + ',
      'type' => 'unified',
    ];
    $indexConfig->set('processor_settings', $processorConfig);
    $indexConfig->save();

    $newIndexConfig = $this->container->get('config.factory')->getEditable('search_api.index.test_elasticsearch_index');

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

    // System under test: Load a search view.
    $this->drupalGet(Url::fromRoute('view.test_elasticsearch_index_search.page_1'));

    // Assert: The search view is displayed.
    $this->assertSession()->pageTextContains('test_elasticsearch_index_search');

    // System under test: Search for the term "foo".
    $this->submitForm(['search_api_fulltext' => 'foo'], 'Apply');

    // Assert: We see IDs 1, 2, 4, 5 returned for the term "foo". We do not see
    // ID 3 returned for the term "foo".
    $entity1 = $this->assertTestViewHasEntityRow('1');
    $this->assertTableCellWithInnerHtml(self::getColumnExcerpt(), '<strong>foo</strong> bar baz foobaz föö smileὠ1', $entity1);
    $entity2 = $this->assertTestViewHasEntityRow('2');
    $this->assertTableCellWithInnerHtml(self::getColumnExcerpt(), '<strong>foo</strong> test foobuz', $entity2);
    $entity4 = $this->assertTestViewHasEntityRow('4');
    $this->assertTableCellWithInnerHtml(self::getColumnExcerpt(), '<strong>foo</strong> baz', $entity4);
    $entity5 = $this->assertTestViewHasEntityRow('5');
    $this->assertTableCellWithInnerHtml(self::getColumnExcerpt(), '<strong>foo</strong>', $entity5);
    $this->assertTestViewNotShowsEntity('3');
  }

}
