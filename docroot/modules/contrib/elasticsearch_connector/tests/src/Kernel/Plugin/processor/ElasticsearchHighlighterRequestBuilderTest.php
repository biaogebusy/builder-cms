<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Kernel\Plugin\processor;

use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\elasticsearch_connector\Plugin\search_api\processor\ElasticsearchHighlighter;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\Query;

/**
 * Tests how the Elasticsearch Highlight processor builds highlight queries.
 *
 * @coversDefaultClass \Drupal\elasticsearch_connector\Plugin\search_api\processor\ElasticsearchHighlighter
 *
 * @group elasticsearch_connector
 */
class ElasticsearchHighlighterRequestBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'locale',
    'search_api',
    'elasticsearch_connector',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('locale', ['locales_source', 'locales_target', 'locales_location']);

    // Install the Lolspeak language, and make it the default language. The
    // author chose this particular language because it is a valid language from
    // \Drupal\Core\Language\LanguageManager::getStandardLanguageList(); it has
    // an easy-to-notice, extra-long langcode, and it is very unlikely that a
    // real site would have it installed because it is a joke language.
    $defaultLanguage = ConfigurableLanguage::createFromLangcode('xx-lolspeak');
    $defaultLanguage->save();
    $this->container->get('language.default')->set($defaultLanguage);

    // Install the French language.
    ConfigurableLanguage::createFromLangcode('fr')->save();

    locale_system_set_config_langcodes();
  }

  /**
   * Data provider for testPreprocessSearchQuery().
   *
   * @return array
   *   An associative array where the keys are data set names, and the values
   *   are arrays of arguments to pass to testPreprocessSearchQuery().
   */
  public static function preprocessSearchResultsDataProvider(): array {
    $testCases = [];

    $testCases['Default configuration values'] = [
      [],
      [
        'type' => 'unified',
        'encoder' => 'default',
        'fragment_size' => 60,
        'no_match_size' => 0,
        'number_of_fragments' => 5,
        'order' => 'none',
        'require_field_match' => TRUE,
        'fields' => [],
        'pre_tags' => ['<em class="placeholder">'],
        'post_tags' => ['</em>'],
        'boundary_scanner' => 'sentence',
        'boundary_scanner_locale' => 'xx-lolspeak',
      ],
    ];

    $testCases['Plain highlighter, unspecified fragmenter'] = [
      ['type' => 'plain'],
      [
        'type' => 'plain',
        'encoder' => 'default',
        'fragment_size' => 60,
        'no_match_size' => 0,
        'number_of_fragments' => 5,
        'order' => 'none',
        'require_field_match' => TRUE,
        'fields' => [],
        'pre_tags' => ['<em class="placeholder">'],
        'post_tags' => ['</em>'],
        'fragmenter' => 'span',
      ],
    ];

    $testCases['Plain highlighter, simple fragmenter'] = [
      [
        'type' => 'plain',
        'fragmenter' => 'simple',
      ],
      [
        'type' => 'plain',
        'encoder' => 'default',
        'fragment_size' => 60,
        'no_match_size' => 0,
        'number_of_fragments' => 5,
        'order' => 'none',
        'require_field_match' => TRUE,
        'fields' => [],
        'pre_tags' => ['<em class="placeholder">'],
        'post_tags' => ['</em>'],
        'fragmenter' => 'simple',
      ],
    ];

    $testCases['Plain highlighter, span fragmenter'] = [
      [
        'type' => 'plain',
        'fragmenter' => 'span',
      ],
      [
        'type' => 'plain',
        'encoder' => 'default',
        'fragment_size' => 60,
        'no_match_size' => 0,
        'number_of_fragments' => 5,
        'order' => 'none',
        'require_field_match' => TRUE,
        'fields' => [],
        'pre_tags' => ['<em class="placeholder">'],
        'post_tags' => ['</em>'],
        'fragmenter' => 'span',
      ],
    ];

    $testCases['Unified highlighter, word boundary scanner'] = [
      [
        'type' => 'unified',
        'boundary_scanner' => 'word',
      ],
      [
        'type' => 'unified',
        'encoder' => 'default',
        'fragment_size' => 60,
        'no_match_size' => 0,
        'number_of_fragments' => 5,
        'order' => 'none',
        'require_field_match' => TRUE,
        'fields' => [],
        'pre_tags' => ['<em class="placeholder">'],
        'post_tags' => ['</em>'],
        'boundary_scanner' => 'word',
      ],
    ];

    $testCases['Unified highlighter, sentence boundary scanner, unspecified locale'] = [
      [
        'type' => 'unified',
        'boundary_scanner' => 'sentence',
      ],
      [
        'type' => 'unified',
        'encoder' => 'default',
        'fragment_size' => 60,
        'no_match_size' => 0,
        'number_of_fragments' => 5,
        'order' => 'none',
        'require_field_match' => TRUE,
        'fields' => [],
        'pre_tags' => ['<em class="placeholder">'],
        'post_tags' => ['</em>'],
        'boundary_scanner' => 'sentence',
        'boundary_scanner_locale' => 'xx-lolspeak',
      ],
    ];

    $testCases['Unified highlighter, sentence boundary scanner, LANGCODE_SYSTEM locale'] = [
      [
        'type' => 'unified',
        'boundary_scanner' => 'sentence',
        'boundary_scanner_locale' => LanguageInterface::LANGCODE_SYSTEM,
      ],
      [
        'type' => 'unified',
        'encoder' => 'default',
        'fragment_size' => 60,
        'no_match_size' => 0,
        'number_of_fragments' => 5,
        'order' => 'none',
        'require_field_match' => TRUE,
        'fields' => [],
        'pre_tags' => ['<em class="placeholder">'],
        'post_tags' => ['</em>'],
        'boundary_scanner' => 'sentence',
        'boundary_scanner_locale' => 'xx-lolspeak',
      ],
    ];

    $testCases['Unified highlighter, sentence boundary scanner, French-language locale'] = [
      [
        'type' => 'unified',
        'boundary_scanner' => 'sentence',
        'boundary_scanner_locale' => 'fr',
      ],
      [
        'type' => 'unified',
        'encoder' => 'default',
        'fragment_size' => 60,
        'no_match_size' => 0,
        'number_of_fragments' => 5,
        'order' => 'none',
        'require_field_match' => TRUE,
        'fields' => [],
        'pre_tags' => ['<em class="placeholder">'],
        'post_tags' => ['</em>'],
        'boundary_scanner' => 'sentence',
        'boundary_scanner_locale' => 'fr',
      ],
    ];

    $testCases['Fields test'] = [
      [
        'fields' => [
          'body',
          'title',
          'field_testing',
        ],
      ],
      [
        'type' => 'unified',
        'encoder' => 'default',
        'fragment_size' => 60,
        'no_match_size' => 0,
        'number_of_fragments' => 5,
        'order' => 'none',
        'require_field_match' => TRUE,
        'fields' => [
          'body' => new \stdClass(),
          'title' => new \stdClass(),
          'field_testing' => new \stdClass(),
        ],
        'pre_tags' => ['<em class="placeholder">'],
        'post_tags' => ['</em>'],
        'boundary_scanner' => 'sentence',
        'boundary_scanner_locale' => 'xx-lolspeak',
      ],
    ];

    $testCases['Simple custom pre-tag'] = [
      [
        'pre_tag' => '<mark>',
      ],
      [
        'type' => 'unified',
        'encoder' => 'default',
        'fragment_size' => 60,
        'no_match_size' => 0,
        'number_of_fragments' => 5,
        'order' => 'none',
        'require_field_match' => TRUE,
        'fields' => [],
        'pre_tags' => ['<mark>'],
        'post_tags' => ['</mark>'],
        'boundary_scanner' => 'sentence',
        'boundary_scanner_locale' => 'xx-lolspeak',
      ],
    ];

    $testCases['Complex custom pre-tag'] = [
      [
        'pre_tag' => '<span class="foo bar" role="mark" aria-brailleroledescription="Your search term">',
      ],
      [
        'type' => 'unified',
        'encoder' => 'default',
        'fragment_size' => 60,
        'no_match_size' => 0,
        'number_of_fragments' => 5,
        'order' => 'none',
        'require_field_match' => TRUE,
        'fields' => [],
        'pre_tags' => ['<span class="foo bar" role="mark" aria-brailleroledescription="Your search term">'],
        'post_tags' => ['</span>'],
        'boundary_scanner' => 'sentence',
        'boundary_scanner_locale' => 'xx-lolspeak',
      ],
    ];

    $testCases['Other fields at non-default values'] = [
      [
        'encoder' => 'html',
        'fragment_size' => 432,
        'no_match_size' => 234,
        'number_of_fragments' => 11,
        'order' => 'none',
        'require_field_match' => FALSE,

      ],
      [
        'type' => 'unified',
        'encoder' => 'html',
        'fragment_size' => 432,
        'no_match_size' => 234,
        'number_of_fragments' => 11,
        'order' => 'none',
        'require_field_match' => FALSE,
        'fields' => [],
        'pre_tags' => ['<em class="placeholder">'],
        'post_tags' => ['</em>'],
        'boundary_scanner' => 'sentence',
        'boundary_scanner_locale' => 'xx-lolspeak',
      ],
    ];

    return $testCases;
  }

  /**
   * Test that we can build a highlight query fragment from processor config.
   *
   * @covers ::preprocessSearchQuery
   *
   * @dataProvider preprocessSearchResultsDataProvider
   */
  public function testPreprocessSearchQuery(array $processorConfig, array $expectedHighlightFragment): void {
    // Setup: Instantiate an ElasticsearchHighlighter plugin with the
    // configuration we are being passed for this test case.
    $processor = new ElasticsearchHighlighter($processorConfig, 'elasticsearch_highlight', []);

    // Setup: Create a mock index.
    $index = $this->prophesize(IndexInterface::class);
    $index->status()->willReturn(TRUE);

    // Setup: Create a query.
    $query = Query::create($index->reveal(), []);

    // System Under Test: Preprocess the query.
    $processor->preprocessSearchQuery($query);

    // Assertions: Ensure that the highlight query fragment matches what we
    // expect.
    $actualHighlightFragment = $query->getOption('highlight');
    $this->assertEquals($expectedHighlightFragment, $actualHighlightFragment);
  }

}
