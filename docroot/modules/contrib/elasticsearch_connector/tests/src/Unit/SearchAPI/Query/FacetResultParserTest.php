<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Unit\SearchAPI\Query;

use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\SearchAPI\Query\FacetResultParser;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

/**
 * Tests the facets result parser.
 *
 * @coversDefaultClass \Drupal\elasticsearch_connector\SearchAPI\Query\FacetResultParser
 * @group elasticsearch_connector
 */
class FacetResultParserTest extends UnitTestCase {
  use ProphecyTrait;

  /**
   * A mock logger used to construct the system under test.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup: Mock a logger.
    $this->logger = $this->prophesize(LoggerInterface::class)->reveal();
  }

  /**
   * Test parsing facets with empty keys, boolean false, and number 0.
   */
  public function testFacetWithEmptyKey() {
    // Setup: Mock an index with an empty fields array.
    $index = $this->prophesize(IndexInterface::class);
    $index->getFields()->willReturn([]);

    // Setup: Mock a query with one facet.
    $query = $this->prophesize(QueryInterface::class);
    $query->getIndex()->willReturn($index->reveal());
    $query->getOption('search_api_facets', [])
      ->willReturn([
        'facet1' => ['field' => 'field1', 'operator' => 'and'],
      ]);

    // Setup: Prepare a test response.
    $response = [
      'aggregations' => [
        'facet1' => [
          'doc_count_error_upper_bound' => 0,
          'sum_other_doc_count' => 0,
          'buckets' => [
            // Missing key.
            ['doc_count' => 100],

            // Key is an empty string.
            ['key' => '', 'doc_count' => 200],

            // Key is a string containing a 0, i.e.: a boolean FALSE.
            ['key' => '0', 'doc_count' => 300],
          ],
        ],
      ],
    ];

    // SUT: Run the system under test on the test response.
    $facetData = (new FacetResultParser($this->logger))
      ->parseFacetResult($query->reveal(), $response);

    // Assert: The SUT should have parsed the response as expected.
    $this->assertNotEmpty($facetData);
    $this->assertEquals([
      'facet1' => [
        // Missing key should come back as an exclamation mark.
        ['count' => 100, 'filter' => '!'],

        // Empty-string key should come back as an exclamation mark.
        ['count' => 200, 'filter' => '!'],

        // String containing a 0 should come back as a 0 for boolean facets.
        ['count' => 300, 'filter' => '"0"'],
      ],
    ], $facetData);
  }

  /**
   * @covers ::parseFacetResult
   */
  public function testParseFacetResult() {
    // Setup: Mock an index with an empty fields array.
    $index = $this->prophesize(IndexInterface::class);
    $index->getFields()->willReturn([]);

    // Setup: Mock a query with two facets.
    $query = $this->prophesize(QueryInterface::class);
    $query->getIndex()->willReturn($index->reveal());
    $query->getOption('search_api_facets', [])
      ->willReturn([
        'facet1' => [
          'field' => 'field1',
          'operator' => 'and',
        ],
        'facet2' => [
          'field' => 'field1',
          'operator' => 'or',
        ],
      ]);

    // Setup: Prepare a test response.
    $response = [
      'aggregations' => [
        'facet1' => [
          'doc_count_error_upper_bound' => 0,
          'sum_other_doc_count' => 0,
          'buckets' => [
            [
              'key' => 'foo',
              'doc_count' => 100,
            ],
            [
              'key' => 'bar',
              'doc_count' => 200,
            ],
          ],
        ],
        'facet2_filtered' => [
          'facet2' => [
            'buckets' => [
              [
                'key' => 'whizz',
                'doc_count' => 400,
              ],
            ],
          ],
        ],
      ],
    ];

    // SUT: Run the system under test on the test response.
    $facetData = (new FacetResultParser($this->logger))
      ->parseFacetResult($query->reveal(), $response);

    // Assert: The SUT should have parsed the response as expected.
    $this->assertNotEmpty($facetData);
    $this->assertEquals([
      'facet1' => [
        [
          'count' => 100,
          'filter' => '"foo"',
        ],
        [
          'count' => 200,
          'filter' => '"bar"',
        ],
      ],
      'facet2' => [
        [
          'count' => 400,
          'filter' => '"whizz"',
        ],
      ],
    ], $facetData);
  }

}
