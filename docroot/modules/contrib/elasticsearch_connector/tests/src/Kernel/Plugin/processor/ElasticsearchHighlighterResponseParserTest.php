<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Kernel\Plugin\processor;

use Drupal\KernelTests\KernelTestBase;
use Drupal\TestTools\Random;
use Drupal\elasticsearch_connector\Plugin\search_api\processor\ElasticsearchHighlighter;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Query\Query;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests how the Elasticsearch Highlight processor handles search responses.
 *
 * @coversDefaultClass \Drupal\elasticsearch_connector\Plugin\search_api\processor\ElasticsearchHighlighter
 *
 * @group elasticsearch_connector
 */
class ElasticsearchHighlighterResponseParserTest extends KernelTestBase {
  use ProphecyTrait;

  /**
   * The name of the fictional index that we will use during this test.
   *
   * @var string
   */
  public const INDEX_NAME = 'content_index';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'elasticsearch_connector',
  ];

  /**
   * Data provider for testPostprocessSearchResults().
   *
   * @return array
   *   An associative array where the keys are data set names, and the values
   *   are arrays of arguments to pass to testPostprocessSearchResults().
   */
  public static function postprocessSearchResultsDataProvider(): array {
    $testCases = [];

    // Test the case where there is no highlight data in the result, resulting
    // in empty excerpts.
    $testCases['No Highlight Data'] = [
      [
        self::responseHitJson('en', []),
        self::responseHitJson('en', []),
      ],
      [
        '',
        '',
      ],
    ];

    // Test the case where there is field and one snippet in it. Add some HTML:
    // we expect only the emphasis tag (em) to successfully pass through the
    // Xss::filter() command, because it's the tag configured as the highlight.
    $testCases['One field one snippet'] = [
      [
        self::responseHitJson('en', [
          'body' => ['lorem <em>ipsum</em> dolor'],
        ]),
        self::responseHitJson('en', [
          'body' => ['<strong>dolor</strong> ipsum sit'],
        ]),
      ],
      [
        'lorem <em>ipsum</em> dolor',
        'dolor ipsum sit',
      ],
    ];

    // Test the case where there is one field and two snippets in it. Add some
    // HTML: we expect only the emphasis tag (em) to successfully pass through
    // the Xss::filter() command, because it's the tag configured as the
    // highlight.
    $testCases['Highlight one field with two snippets in it'] = [
      [
        self::responseHitJson('en', [
          'body' => [
            'fizz <em>bizz</em> buzz',
            'bazz bizz <mark>bozz</mark>',
          ],
        ]),
        self::responseHitJson('en', [
          'body' => [
            '<strong>aliquam</strong> bizz lacinia',
            'nulla <em>bizz</em> mi',
          ],
        ]),
      ],
      [
        'fizz <em>bizz</em> buzz … bazz bizz bozz',
        'aliquam bizz lacinia … nulla <em>bizz</em> mi',
      ],
    ];

    // Test the case where there is two fields and one snippet in each. Let us
    // try some nested HTML, to make sure that Xss::filter() continues to only
    // let through the highlighting tag.
    $testCases['Highlight two fields with one snippet in each'] = [
      [
        self::responseHitJson('en', [
          'body' => ['gravida <span>vel</spam> <em>nisl</em> <del>aliquam</del> sed'],
          'title' => ['imperdiet <code>aliquam</code> <div>rhoncus</div> nisi'],
        ]),
        self::responseHitJson('en', [
          'body' => ['<ul><li>aliquam</li> <li><em>bizz</em></li> <li>lacinia</li></ul>'],
          'title' => ['<div><ol><li><em>praesent</em></li> <li><strong>aliquam</strong></li> <li>ipsum</li></ol></div>'],
        ]),
      ],
      [
        'gravida vel <em>nisl</em> aliquam sed … imperdiet aliquam rhoncus nisi',
        'aliquam <em>bizz</em> lacinia … <em>praesent</em> aliquam ipsum',
      ],
    ];

    // Test the case where there is are two fields, each with two snippets. Try
    // with some invalid HTML to make sure we are properly filtering.
    $testCases['Highlight two fields with two snippets in each'] = [
      [
        self::responseHitJson('en', [
          'body' => [
            '</ol><h1>quis sagittis <em>lorem</em> tincidunt',
            'interdum lorem malesuada fames',
          ],
          'title' => [
            'ante lorem primis faucibus',
            'quisque sit lorem enim',
          ],
        ]),
        self::responseHitJson('en', [
          'body' => [
            'aenean lorem arcu bibendum',
            'vestibulum venenatis lorem hendrerit',
          ],
          'title' => [
            'cras faucibus lorem sed',
            'quisque lorem libero',
          ],
        ]),
      ],
      [
        'quis sagittis <em>lorem</em> tincidunt … interdum lorem malesuada fames … ante lorem primis faucibus … quisque sit lorem enim',
        'aenean lorem arcu bibendum … vestibulum venenatis lorem hendrerit … cras faucibus lorem sed … quisque lorem libero',
      ],
    ];

    return $testCases;
  }

  /**
   * Return an array that looks like a decoded JSON response for a search hit.
   *
   * Each time this function is called, we get a new Search API ID in the return
   * value, so that we don't get search result item ID collisions.
   *
   * This function randomly generates a title and body field, because
   * \Drupal\elasticsearch_connector\Plugin\search_api\processor\ElasticsearchHighlighter::postprocessSearchResults()
   * only cares about the 'highlights' part of the response.
   *
   * For simplicity, this function:
   * - Assigns a score of '5.15' to each result.
   * - Sets every Search Api DataSource to 'entity:node' (which is also used in
   *   the Search API ID).
   * - Sets the index name to the value of the constant self::INDEX_NAME.
   *
   * @param string $langcode
   *   The language code that we should expect in the response.
   * @param array $highlights
   *   An array of highlight data for the given search hit.
   *
   * @return array
   *   An array that looks like a decoded JSON response for a search query hit.
   */
  protected static function responseHitJson(string $langcode, array $highlights = []): array {
    $nodeId = self::yieldNewNodeId();
    $searchApiDataSource = 'entity:node';
    $searchApiId = "{$searchApiDataSource}/{$nodeId}:{$langcode}";
    $title = Random::string(5);
    $body = Random::string(10);

    return [
      '_index' => self::INDEX_NAME,
      '_id' => $searchApiId,
      '_score' => 5.15,
      '_ignored' => [],
      '_source' => [
        'body' => [$body],
        'title' => [$title],
        'search_api_id' => [$searchApiId],
        'search_api_datasource' => [$searchApiDataSource],
        'search_api_language' => [$langcode],
      ],
      'highlight' => $highlights,
    ];
  }

  /**
   * Return an array that looks like a decoded JSON response from a search.
   *
   * For simplicity, this function assumes:
   * - the search took 5ms;
   * - the search did not time out;
   * - the search was performed successfully on 1 of 1 shards, and there were no
   *   skipped or failed shards;
   * - there were exactly \count($hits) search result hits; and;
   * - the maximum score of all the hits was '5.15'.
   *
   * @param array $hits
   *   An array of arrays that look like decoded JSON responses for search hits.
   *
   * @return array
   *   An array that looks like a decoded JSON response for a search query.
   */
  protected static function responseStructure(array $hits = []): array {
    return [
      'took' => 5,
      'timed_out' => FALSE,
      '_shards' => [
        'total' => 1,
        'successful' => 1,
        'skipped' => 0,
        'failed' => 0,
      ],
      'hits' => [
        'total' => [
          'value' => \count($hits),
          'relation' => 'eq',
        ],
        'max_score' => 5.15,
        'hits' => $hits,
      ],
    ];
  }

  /**
   * Get a new node ID every time this function is called.
   *
   * Note this uses a static variable.
   *
   * @return int
   *   A monotonically increasing integer beginning with 1. Presumably, if you
   *   call this function more than PHP_INT_MAX times in one test, you will get
   *   undefined behavior.
   */
  protected static function yieldNewNodeId(): int {
    static $nid = 0;
    return ++$nid;
  }

  /**
   * Test that we can interpret the Highlight data returned by ElasticSearch.
   *
   * @covers ::postprocessSearchResults
   *
   * @dataProvider postprocessSearchResultsDataProvider
   */
  public function testPostprocessSearchResults(array $responseHitStructures, array $expectedExcerpts): void {
    // Setup: Create a mock index.
    $index = $this->prophesize(IndexInterface::class);
    $index->status()->willReturn(TRUE);
    $index->id()->willReturn(self::INDEX_NAME);

    // Setup: Create a query.
    $query = Query::create($index->reveal(), []);

    // Setup: Get a ResultSet by parsing a response containing the given hits.
    /** @var \Drupal\elasticsearch_connector\SearchAPI\Query\QueryResultParser $resultParser */
    $resultParser = $this->container->get('elasticsearch_connector.query_result_parser');
    $resultSet = $resultParser->parseResult($query, self::responseStructure($responseHitStructures));

    // Setup: Instantiate an ElasticsearchHighlighter plugin.
    $processor = new ElasticsearchHighlighter([
      'fields' => ['title' => 'title', 'body' => 'body'],
      'pre_tag' => '<em>',
      'snippet_joiner' => ' … ',
    ], 'elasticsearch_highlight', []);

    // System Under Test: Postprocess the search results.
    $processor->postprocessSearchResults($resultSet);

    // Assertions: loop through each result and make sure that the excerpt
    // corresponds with the expected result.
    $iterator = new \MultipleIterator(\MultipleIterator::MIT_NEED_ANY | \MultipleIterator::MIT_KEYS_ASSOC);
    $iterator->attachIterator(new \ArrayIterator($resultSet->getResultItems()), 'searchResult');
    $iterator->attachIterator(new \ArrayIterator($expectedExcerpts), 'expectedExcerpt');
    foreach ($iterator as $cursor) {
      if ($cursor['searchResult'] instanceof ItemInterface) {
        $this->assertEquals($cursor['expectedExcerpt'], $cursor['searchResult']->getExcerpt());
      }
    }
  }

}
