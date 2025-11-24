<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Unit\SearchAPI\Query;

use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\SearchAPI\Query\FacetParamBuilder;
use Drupal\search_api\Query\QueryInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;

/**
 * Tests the facet param builder.
 *
 * @coversDefaultClass \Drupal\elasticsearch_connector\SearchAPI\Query\FacetParamBuilder
 *
 * @group elasticsearch_connector
 */
class FacetParamBuilderTest extends UnitTestCase {
  use ProphecyTrait;

  /**
   * A set of facet filters for the system under test.
   *
   * @var array
   */
  private array $facetFilters;

  /**
   * A set of index fields for the system under test.
   *
   * @var array
   */
  private array $indexFields;

  /**
   * A mock logger to use for the builder.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  private ObjectProphecy $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup: Create a mock logger.
    $this->logger = $this->prophesize(LoggerInterface::class);

    // Setup: Prepare a set of index fields for the system under test.
    $this->indexFields = ['field1' => [], 'field2' => []];

    // Setup: Prepare a set of facet filters for the system under test.
    $this->facetFilters = ['facet2' => 'filter for facet2'];
  }

  /**
   * Test a query with 2 facets, with mixed operators.
   */
  public function getTwoFacetsQuery(): void {
    // Setup: Prepare a mock query that has two facets on field1.
    $query = $this->mockQuery([
      'facet1' => ['field' => 'field1', 'operator' => 'and'],
      'facet2' => ['field' => 'field1', 'operator' => 'or'],
    ]);

    // SUT: Ask the SUT to build a faceting clause.
    $result = (new FacetParamBuilder($this->logger->reveal()))
      ->buildFacetParams($query, $this->indexFields, $this->facetFilters);

    // Assert: We should get a 'facet1_filtered' clause with a 'should' boolean
    // filter (because facet1 has the 'and' operator), and a 'terms' aggregation
    // on the facet's field. Separately, we should get an unfiltered 'terms'
    // aggregation on facet2's field.
    $this->assertEquals([
      'facet1_filtered' => [
        'filter' => [
          'bool' => [
            'must' => 'filter for facet2',
          ],
        ],
        'aggs' => [
          'facet1' => [
            'terms' => [
              'field' => 'field1',
              'size' => 10,
            ],
          ],
        ],
      ],
      'facet2' => [
        'terms' => [
          'field' => 'field1',
          'size' => 10,
        ],
      ],
    ], $result);
  }

  /**
   * If no facets are defined, this builds an empty clause.
   */
  public function testBuildsNothingWithNoFacets(): void {
    // Setup: Prepare a mock query with no facets.
    $query = $this->mockQuery();

    // SUT: Ask the SUT to build a faceting clause.
    $result = (new FacetParamBuilder($this->logger->reveal()))
      ->buildFacetParams($query, $this->indexFields, $this->facetFilters);

    // Assert: We should get an empty facet parameters clause.
    $this->assertEmpty($result);
  }

  /**
   * Test a query with 1 facet whose operator is 'and'.
   */
  public function testOneAndFacetQuery(): void {
    // Setup: Prepare a mock query with one facet on field1.
    $query = $this->mockQuery([
      'facet1' => ['field' => 'field1', 'operator' => 'and'],
    ]);

    // SUT: Ask the SUT to build a faceting clause.
    $result = (new FacetParamBuilder($this->logger->reveal()))
      ->buildFacetParams($query, $this->indexFields, $this->facetFilters);

    // Assert: We should get a 'facet1_filtered' clause with a 'must' boolean
    // filter, and a 'terms' aggregation on the facet's field.
    $this->assertEquals([
      'facet1_filtered' => [
        'filter' => ['bool' => ['must' => 'filter for facet2']],
        'aggs' => ['facet1' => ['terms' => ['field' => 'field1', 'size' => 10]]],
      ],
    ], $result);
  }

  /**
   * Test a query with 1 facet whose operator is 'or'.
   */
  public function testOneOrFacetQuery(): void {
    // Setup: Prepare a mock query with one facet on field1.
    $query = $this->mockQuery([
      'facet1' => ['field' => 'field1', 'operator' => 'or'],
    ]);

    // SUT: Ask the SUT to build a faceting clause.
    $result = (new FacetParamBuilder($this->logger->reveal()))
      ->buildFacetParams($query, $this->indexFields, $this->facetFilters);

    // Assert: We should get a 'facet1_filtered' clause with a 'should' boolean
    // filter, and a 'terms' aggregation on the facet's field.
    $this->assertEquals([
      'facet1_filtered' => [
        'filter' => ['bool' => ['should' => 'filter for facet2']],
        'aggs' => ['facet1' => ['terms' => ['field' => 'field1', 'size' => 10]]],
      ],
    ], $result);
  }

  /**
   * Log a warning if we try to build a facet clause for an undefined field.
   */
  public function testUnknownFacetFieldLogsWarning(): void {
    // Setup: Tell the logger to expect a warning.
    $this->logger
      ->warning('Unknown facet field: %field', ['%field' => 'field1'])
      ->shouldBeCalledOnce();

    // Setup: Prepare a mock query with any one facet.
    $query = $this->mockQuery([
      'facet1' => ['field' => 'field1', 'operator' => 'and'],
    ]);

    // SUT: Ask the SUT to build a faceting clause. Note indexFields is empty.
    $result = (new FacetParamBuilder($this->logger->reveal()))
      ->buildFacetParams($query, [], $this->facetFilters);

    // Assert: We should get an empty facet parameters clause.
    $this->assertEmpty($result);
  }

  /**
   * Helper function to build a mock Search API Query.
   *
   * @param array $facetOptions
   *   A specification for search_api_facets options for this query.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   A mock Search API Query to use in the test.
   */
  protected function mockQuery(array $facetOptions = []): QueryInterface {
    $query = $this->prophesize(QueryInterface::class);
    $query->getOption('search_api_facets', [])
      ->willReturn($facetOptions);
    return $query->reveal();
  }

}
