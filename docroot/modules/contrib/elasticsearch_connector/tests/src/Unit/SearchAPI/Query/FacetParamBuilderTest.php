<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Unit\SearchAPI\Query;

use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\SearchAPI\Query\FacetParamBuilder;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
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
    $field1 = $this->prophesize(FieldInterface::class);
    $field1->getPropertyPath()->willReturn('field1');
    $field2 = $this->prophesize(FieldInterface::class);
    $field2->getPropertyPath()->willReturn('field2');
    $this->indexFields = [
      'field1' => $field1->reveal(),
      'field2' => $field2->reveal(),
    ];

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
    // Test an "AND" query, with 1 "AND" facet, and 1 facet value selected.
    //
    // Setup: Prepare a mock AND query with one facet on field1.
    $query0 = $this->mockQuery([
      'facet1' => ['field' => 'field1', 'operator' => 'and'],
    ], [], 'AND');

    // SUT: Ask the SUT to build a faceting clause for 1 facet value.
    $result0 = (new FacetParamBuilder($this->logger->reveal()))
      ->buildFacetParams($query0, $this->indexFields, $this->facetFilters);

    // Assert: We should get a 'field1_filtered' clause with a 'must' boolean
    // filter (because the query-level conjunction is AND), and a 'terms'
    // aggregation on the facet's field.
    // Note: The facet_id is replaced with the property path when FieldInterface
    // is provided, so we expect 'field1' not 'facet1'.
    $this->assertEquals([
      'field1_filtered' => [
        'filter' => ['bool' => ['must' => 'filter for facet2']],
        'aggs' => ['field1' => ['terms' => ['field' => 'field1', 'size' => 10]]],
      ],
    ], $result0);

    // Test an "OR" query, with 1 "AND" facet, and 1 facet value selected.
    //
    // Setup: Prepare a mock OR query with one facet on field1.
    $query1 = $this->mockQuery([
      'facet1' => ['field' => 'field1', 'operator' => 'and'],
    ], [], 'OR');

    // SUT: Ask the SUT to build a faceting clause for 1 facet value.
    $result1 = (new FacetParamBuilder($this->logger->reveal()))
      ->buildFacetParams($query1, $this->indexFields, $this->facetFilters);

    // Assert: We should get a 'field1_filtered' clause with a 'should' boolean
    // filter (because the query-level conjunction is OR), and a 'terms'
    // aggregation on the facet's field.
    // Note: The facet_id is replaced with the property path when FieldInterface
    // is provided, so we expect 'field1' not 'facet1'.
    $this->assertEquals([
      'field1_filtered' => [
        'filter' => ['bool' => ['should' => 'filter for facet2']],
        'aggs' => ['field1' => ['terms' => ['field' => 'field1', 'size' => 10]]],
      ],
    ], $result1);

    // Test an "AND" query, with 1 "AND" facet, and 2 facet values selected
    // (the values should be ANDed together).
    //
    // Setup: Prepare a mock AND query with one facet on field1.
    $query2 = $this->mockQuery([
      'facet1' => ['field' => 'field1', 'operator' => 'and'],
    ], [], 'AND');

    // SUT: Ask the SUT to build a faceting clause for 2 facet values.
    $result2 = (new FacetParamBuilder($this->logger->reveal()))
      ->buildFacetParams($query2, $this->indexFields, [
        'type' => ['terms' => ['type' => ['value1', 'value2']]],
      ]);

    // Assert: We should get a 'field1_filtered' clause with a 'must' boolean
    // filter (because the query-level conjunction is AND), and a 'terms'
    // aggregation on the facet's field.
    // Note: The facet_id is replaced with the property path when FieldInterface
    // is provided, so we expect 'field1' not 'facet1'.
    $this->assertEquals([
      'field1_filtered' => [
        'filter' => [
          'bool' => ['must' => ['terms' => ['type' => ['value1', 'value2']]]],
        ],
        'aggs' => ['field1' => ['terms' => ['field' => 'field1', 'size' => 10]]],
      ],
    ], $result2);

    // Test an "OR" query, with 1 "AND" facet, and 2 facet values selected
    // (the values should be ANDed together).
    //
    // Setup: Prepare a mock OR query with one facet on field1.
    $query3 = $this->mockQuery([
      'facet1' => ['field' => 'field1', 'operator' => 'and'],
    ], [], 'OR');

    // SUT: Ask the SUT to build a faceting clause for 2 facet values.
    $result3 = (new FacetParamBuilder($this->logger->reveal()))
      ->buildFacetParams($query3, $this->indexFields, [
        'type' => ['terms' => ['type' => ['value1', 'value2']]],
      ]);

    // Assert: We should get a 'field1_filtered' clause with a 'should' boolean
    // filter (because the query-level conjunction is OR), and a 'terms'
    // aggregation on the facet's field.
    // Note: The facet_id is replaced with the property path when FieldInterface
    // is provided, so we expect 'field1' not 'facet1'.
    $this->assertEquals([
      'field1_filtered' => [
        'filter' => [
          'bool' => ['should' => ['terms' => ['type' => ['value1', 'value2']]]],
        ],
        'aggs' => ['field1' => ['terms' => ['field' => 'field1', 'size' => 10]]],
      ],
    ], $result3);
  }

  /**
   * Test a query with 1 facet whose operator is 'or'.
   */
  public function testOneOrFacetQuery(): void {
    // Test an "AND" query, with 1 "OR" facet, and 1 facet value selected.
    //
    // Setup: Prepare a mock AND query with one facet on field1.
    $query0 = $this->mockQuery([
      'facet1' => ['field' => 'field1', 'operator' => 'or'],
    ], [], 'AND');

    // SUT: Ask the SUT to build a faceting clause for 1 facet value.
    $result0 = (new FacetParamBuilder($this->logger->reveal()))
      ->buildFacetParams($query0, $this->indexFields, $this->facetFilters);

    // Assert: We should get a 'field1_filtered' clause with a 'must' boolean
    // filter (because the query-level conjunction is AND), and a 'terms'
    // aggregation on the facet's field.
    // Note: The facet_id is replaced with the property path when FieldInterface
    // is provided, so we expect 'field1' not 'facet1'.
    $this->assertEquals([
      'field1_filtered' => [
        'filter' => ['bool' => ['must' => 'filter for facet2']],
        'aggs' => ['field1' => ['terms' => ['field' => 'field1', 'size' => 10]]],
      ],
    ], $result0);

    // Test an "OR" query, with 1 "OR" facet, and 1 facet value selected.
    //
    // Setup: Prepare a mock OR query with one facet on field1.
    $query1 = $this->mockQuery([
      'facet1' => ['field' => 'field1', 'operator' => 'or'],
    ], [], 'OR');

    // SUT: Ask the SUT to build a faceting clause for 1 facet value.
    $result1 = (new FacetParamBuilder($this->logger->reveal()))
      ->buildFacetParams($query1, $this->indexFields, $this->facetFilters);

    // Assert: We should get a 'field1_filtered' clause with a 'should' boolean
    // filter (because the query-level conjunction is OR), and a 'terms'
    // aggregation on the facet's field.
    // Note: The facet_id is replaced with the property path when FieldInterface
    // is provided, so we expect 'field1' not 'facet1'.
    $this->assertEquals([
      'field1_filtered' => [
        'filter' => ['bool' => ['should' => 'filter for facet2']],
        'aggs' => ['field1' => ['terms' => ['field' => 'field1', 'size' => 10]]],
      ],
    ], $result1);

    // Test an "AND" query, with 1 "OR" facet, and 2 facet values selected.
    //
    // Setup: Prepare a mock AND query with one facet on field1.
    $query2 = $this->mockQuery([
      'facet1' => ['field' => 'field1', 'operator' => 'or'],
    ], [], 'AND');

    // SUT: Ask the SUT to build a faceting clause for 2 facet values.
    $result2 = (new FacetParamBuilder($this->logger->reveal()))
      ->buildFacetParams($query2, $this->indexFields, [
        'type' => ['terms' => ['type' => ['value1', 'value2']]],
      ]);

    // Assert: We should get a 'field1_filtered' clause with a 'must' boolean
    // filter (because the query-level conjunction is AND), and a 'terms'
    // aggregation on the facet's field.
    // Note: The facet_id is replaced with the property path when FieldInterface
    // is provided, so we expect 'field1' not 'facet1'.
    $this->assertEquals([
      'field1_filtered' => [
        'filter' => [
          'bool' => ['must' => ['terms' => ['type' => ['value1', 'value2']]]],
        ],
        'aggs' => ['field1' => ['terms' => ['field' => 'field1', 'size' => 10]]],
      ],
    ], $result2);

    // Test an "OR" query, with 1 "OR" facet, and 2 facet values selected.
    //
    // Setup: Prepare a mock OR query with one facet on field1.
    $query3 = $this->mockQuery([
      'facet1' => ['field' => 'field1', 'operator' => 'or'],
    ], [], 'OR');

    // SUT: Ask the SUT to build a faceting clause for 2 facet values.
    $result3 = (new FacetParamBuilder($this->logger->reveal()))
      ->buildFacetParams($query3, $this->indexFields, [
        'type' => ['terms' => ['type' => ['value1', 'value2']]],
      ]);

    // Assert: We should get a 'field1_filtered' clause with a 'should' boolean
    // filter (because the query-level conjunction is OR), and a 'terms'
    // aggregation on the facet's field.
    // Note: The facet_id is replaced with the property path when FieldInterface
    // is provided, so we expect 'field1' not 'facet1'.
    $this->assertEquals([
      'field1_filtered' => [
        'filter' => [
          'bool' => ['should' => ['terms' => ['type' => ['value1', 'value2']]]],
        ],
        'aggs' => ['field1' => ['terms' => ['field' => 'field1', 'size' => 10]]],
      ],
    ], $result3);
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
   * Test that text/fulltext fields use the .keyword subfield for aggregations.
   *
   * This test ensures that faceting on text-type Search API fields (which map
   * to Elasticsearch text fields) correctly appends .keyword to the
   * aggregation
   * field name. This is critical because:
   *
   * - Text fields in Elasticsearch are analyzed/tokenized for full-text search
   * - Aggregations require exact values, not tokenized text
   * - Elasticsearch automatically creates a .keyword subfield (type: keyword)
   *   that stores the non-analyzed value
   * - Without .keyword, aggregations fail with "Fielddata is disabled" errors
   *
   * This mirrors the behavior in QuerySortBuilder where fulltext fields also
   * use .keyword for sorting operations.
   *
   * @see \Drupal\elasticsearch_connector\SearchAPI\Query\QuerySortBuilder::buildSort()
   * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-terms-aggregation.html#search-aggregations-bucket-terms-aggregation-text
   */
  public function testTextFieldUsesKeywordSubfield(): void {
    // Setup: Mock an index that declares 'name' as a fulltext field.
    $index = $this->prophesize(IndexInterface::class);
    $index->getFulltextFields()
      ->willReturn(['name']);

    // Setup: Mock a field with a property path.
    $field = $this->prophesize(FieldInterface::class);
    $field->getPropertyPath()
      ->willReturn('name');

    // Setup: Mock a query with a facet on the fulltext 'name' field.
    $query = $this->prophesize(QueryInterface::class);
    $query->getIndex()
      ->willReturn($index->reveal());
    $query->getOption('search_api_facets', [])
      ->willReturn([
        'name' => ['field' => 'name', 'operator' => 'or'],
      ]);

    // SUT: Ask the SUT to build facet params.
    $result = (new FacetParamBuilder($this->logger->reveal()))
      ->buildFacetParams($query->reveal(), ['name' => $field->reveal()]);

    // Assert: The aggregation should use 'name.keyword' not 'name'.
    $this->assertEquals([
      'name' => [
        'terms' => [
          'field' => 'name.keyword',
          'size' => 10,
        ],
      ],
    ], $result);
  }

  /**
   * Test that non-fulltext fields do not get .keyword appended.
   *
   * Fields that are not in the fulltext fields list (like string/keyword type
   * fields) should be used directly for aggregations without .keyword suffix.
   */
  public function testNonFulltextFieldDoesNotUseKeywordSubfield(): void {
    // Setup: Mock an index with no fulltext fields.
    $index = $this->prophesize(IndexInterface::class);
    $index->getFulltextFields()
      ->willReturn([]);

    // Setup: Mock a field with a property path.
    $field = $this->prophesize(FieldInterface::class);
    $field->getPropertyPath()
      ->willReturn('category');

    // Setup: Mock a query with a facet on a non-fulltext field.
    $query = $this->prophesize(QueryInterface::class);
    $query->getIndex()
      ->willReturn($index->reveal());
    $query->getOption('search_api_facets', [])
      ->willReturn([
        'category' => ['field' => 'category', 'operator' => 'or'],
      ]);

    // SUT: Ask the SUT to build facet params.
    $result = (new FacetParamBuilder($this->logger->reveal()))
      ->buildFacetParams($query->reveal(), ['category' => $field->reveal()]);

    // Assert: The aggregation should use 'category' without .keyword suffix.
    $this->assertEquals([
      'category' => [
        'terms' => [
          'field' => 'category',
          'size' => 10,
        ],
      ],
    ], $result);
  }

  /**
   * Helper function to build a mock Search API Query.
   *
   * @param array $facetOptions
   *   A specification for search_api_facets options for this query.
   * @param array $fulltextFields
   *   List of fulltext field IDs.
   * @param string $conjunction
   *   The conjunction of the query-level condition group.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   A mock Search API Query to use in the test.
   */
  protected function mockQuery(array $facetOptions = [], array $fulltextFields = [], string $conjunction = 'AND'): QueryInterface {
    // Mock the index.
    $index = $this->prophesize(IndexInterface::class);
    $index->getFulltextFields()
      ->willReturn($fulltextFields);

    // Mock a query-level condition group.
    $conditionGroup = $this->prophesize(ConditionGroupInterface::class);
    $conditionGroup->getConjunction()->willReturn($conjunction);

    // Mock the query.
    $query = $this->prophesize(QueryInterface::class);
    $query->getOption('search_api_facets', [])
      ->willReturn($facetOptions);
    $query->getIndex()
      ->willReturn($index->reveal());
    $query->getConditionGroup()
      ->willReturn($conditionGroup->reveal());

    return $query->reveal();
  }

}
