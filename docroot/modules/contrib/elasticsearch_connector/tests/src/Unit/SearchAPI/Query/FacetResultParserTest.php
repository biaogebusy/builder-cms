<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Unit\SearchAPI\Query;

use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\SearchAPI\Query\FacetResultParser;
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
   * @covers ::parseFacetResult
   */
  public function testParseFacetResult() {
    $logger = $this->prophesize(LoggerInterface::class);
    $parser = new FacetResultParser($logger->reveal());

    $query = $this->prophesize(QueryInterface::class);
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

    $facetData = $parser->parseFacetResult($query->reveal(), $response);

    $expected = [
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
    ];
    $this->assertNotEmpty($facetData);
    $this->assertEquals($expected, $facetData);

  }

}
