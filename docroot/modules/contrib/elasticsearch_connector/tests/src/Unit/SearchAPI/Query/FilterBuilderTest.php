<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Unit\SearchAPI\Query;

use Drupal\search_api\SearchApiException;
use Drupal\Tests\UnitTestCase;
use Drupal\elasticsearch_connector\SearchAPI\Query\FilterBuilder;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Query\Condition;
use Drupal\search_api\Query\ConditionGroup;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

/**
 * Tests the filter builder.
 *
 * @coversDefaultClass \Drupal\elasticsearch_connector\SearchAPI\Query\FilterBuilder
 * @group elasticsearch_connector
 */
class FilterBuilderTest extends UnitTestCase {
  use ProphecyTrait;

  /**
   * A mock index, which we need for constructing filters.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected IndexInterface $index;

  /**
   * A mock logger, which we need for constructing the system under test.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup: Create a mock logger.
    $this->logger = $this->prophesize(LoggerInterface::class)->reveal();

    // Setup: Create a mock index.
    $index = $this->prophesize(IndexInterface::class);
    $indexId = 'index_' . $this->randomMachineName();
    $index->id()->willReturn($indexId);
    $this->index = $index->reveal();
  }

  /**
   * @covers ::buildFilters
   */
  public function testBuildFilters() {
    // Setup: Create a condition group to build a filter for.
    $conditionGroup = (new ConditionGroup())
      ->addCondition('foo', 'bar')
      ->addCondition('whiz', 'bang');

    // Setup: Create two fields to build filters with. Note the second field has
    // a different field identifier and property path.
    $fooField = new Field($this->index, 'foo');
    $fooField->setPropertyPath('foo');
    $whizField = new Field($this->index, 'whiz');
    $whizField->setPropertyPath('whoa');
    $fields = [
      'foo' => $fooField,
      'whiz' => $whizField,
    ];

    // SUT: Ask the SUT to build a filter clause.
    $filters = (new FilterBuilder($this->logger))
      ->buildFilters($conditionGroup, $fields);

    // Assert: We should get a clause defining three named buckets: 'filters',
    // 'post_filters', and 'facets_post_filters'. The 'post_filters' and
    // 'facets_post_filters' buckets should be empty because we're not using
    // facets in this test. The 'filters' bucket should define a conjunction
    // boolean query with a 'must' occurrence type, containing term queries for
    // each of the conditions in the condition group earlier.
    $this->assertEquals([
      'filters' => [
        'bool' => [
          'must' => [
            ['term' => ['foo' => 'bar']],
            ['term' => ['whiz' => 'bang']],
          ],
        ],
      ],
      'post_filters' => NULL,
      'facets_post_filters' => [],
    ], $filters);
  }

  /**
   * Test building filters when a condition is missing a field ID.
   */
  public function testBuildFiltersInvalidConditionMissingFieldId(): void {
    // Setup: Prepare a condition group with a condition missing a field ID.
    $cg0 = new ConditionGroup('AND');
    $cg0->addCondition('', 'foo', '=');

    // Setup: Prepare a field to build filters with.
    $fooField = new Field($this->index, 'foo');
    $fooField->setPropertyPath('foo');
    $fields = ['foo' => $fooField];

    // Assert: We should get a SearchApiException.
    $this->expectException(SearchApiException::class);

    // SUT: Try to build filters.
    (new FilterBuilder($this->logger))
      ->buildFilters($cg0, $fields);
  }

  /**
   * Test building filters when a condition is missing an operator.
   */
  public function testBuildFiltersInvalidConditionMissingOperator(): void {
    // Setup: Prepare a condition group with a condition missing an operator.
    $cg0 = new ConditionGroup('AND');
    $cg0->addCondition('bar', 'baz', '');

    // Setup: Prepare a field to build filters with.
    $fooField = new Field($this->index, 'foo');
    $fooField->setPropertyPath('foo');
    $fields = ['foo' => $fooField];

    // Assert: We should get a SearchApiException.
    $this->expectException(SearchApiException::class);

    // SUT: Try to build filters.
    (new FilterBuilder($this->logger))
      ->buildFilters($cg0, $fields);
  }

  /**
   * Test building filters where the value is NULL.
   */
  public function testBuildFiltersNullValue(): void {
    // Setup: Prepare a condition group with a condition missing an operator.
    $cg0 = new ConditionGroup('AND');
    $cg0->addCondition('foo', NULL, '=');

    // Setup: Prepare a field to build filters with.
    $fooField = new Field($this->index, 'foo');
    $fooField->setPropertyPath('foo');
    $fields = ['foo' => $fooField];

    // SUT: Try to build filters.
    $answer0 = (new FilterBuilder($this->logger))
      ->buildFilters($cg0, $fields);

    // Assert: The filter should check for the existence of the field, not
    // equality with a value.
    $this->assertEquals([
      'filters' => ['bool' => ['must_not' => ['exists' => ['field' => 'foo']]]],
      'post_filters' => NULL,
      'facets_post_filters' => [],
    ], $answer0);
  }

  /**
   * Test building filters with one facet.
   */
  public function testBuildFiltersWithOneFacet() {
    // Setup: Create a condition group to build a filter for. Note this has the
    // non-default conjunction OR and a facets tag.
    $conditionGroup = (new ConditionGroup("OR", ["facet:foo"]))
      ->addCondition('foo', 'bar')
      ->addCondition('whiz', 'bang');

    // Setup: Create two fields to build filters with. Note the second field has
    // a different field identifier and property path.
    $fooField = new Field($this->index, 'foo');
    $fooField->setPropertyPath('foo');
    $whizField = new Field($this->index, 'whiz');
    $whizField->setPropertyPath('whoa');
    $fields = [
      'foo' => $fooField,
      'whiz' => $whizField,
    ];

    // SUT: Ask the SUT to build a filter clause.
    $filters = (new FilterBuilder($this->logger))
      ->buildFilters($conditionGroup, $fields);

    // Assert: We should get a clause defining three named buckets: 'filters',
    // 'post_filters', and 'facets_post_filters'. The 'filters' bucket should
    // contain a term query for the field that doesn't have a facet. The
    // 'post_filters' bucket should have a term query for the field that does
    // have a facet. The 'facets_post_filters' filter should define the facet
    // to Elasticsearch as a terms query.
    $this->assertEquals([
      'filters' => [
        'term' => ['whiz' => 'bang'],
      ],
      'post_filters' => [
        'term' => ['foo' => 'bar'],
      ],
      'facets_post_filters' => [
        "foo" => [
          'terms' => ['foo' => ['bar']],
        ],
      ],
    ], $filters);
  }

  /**
   * Test building filters with two facets.
   */
  public function testBuildFiltersWithTwoFacets() {
    // Setup: Create a condition group to build a filter for. Note this has the
    // non-default conjunction OR and a two facets tags.
    $conditionGroup = (new ConditionGroup('OR', ['facet:foo', 'facet:whiz']))
      ->addCondition('foo', 'bar')
      ->addCondition('whiz', 'bang');

    // Setup: Create two fields to build filters with. Note the second field has
    // a different field identifier and property path.
    $fooField = new Field($this->index, 'foo');
    $fooField->setPropertyPath('foo');
    $whizField = new Field($this->index, 'whiz');
    $whizField->setPropertyPath('whoa');
    $fields = [
      'foo' => $fooField,
      'whiz' => $whizField,
    ];

    // SUT: Ask the SUT to build a filter clause.
    $filters = (new FilterBuilder($this->logger))
      ->buildFilters($conditionGroup, $fields);

    // Assert: We should get a clause defining three named buckets: 'filters',
    // 'post_filters', and 'facets_post_filters'. The 'filters' bucket should
    // be empty because all fields have facets. The 'post_filters' bucket should
    // define a conjunction boolean query with a 'should' occurrence type,
    // containing term queries for each of the conditions with facets. The
    // 'facets_post_filters' filter should define both facets to Elasticsearch
    // as terms queries.
    $this->assertEquals([
      'filters' => NULL,
      'post_filters' => [
        'bool' => [
          'should' => [
            ['term' => ['foo' => 'bar']],
            ['term' => ['whiz' => 'bang']],
          ],
        ],
      ],
      'facets_post_filters' => [
        'foo' => [
          'terms' => ['foo' => ['bar']],
        ],
        'whiz' => [
          'terms' => ['whiz' => ['bang']],
        ],
      ],
    ], $filters);
  }

  /**
   * Test that text-type fields with facets use .keyword for exact matching.
   *
   * When filtering by a facet value on a text field, the filter should use
   * the .keyword subfield for exact matching instead of the analyzed text
   * field. This ensures facet selections work correctly.
   */
  public function testTextFieldFacetUsesKeyword(): void {
    // Setup: Create a text-type field. Note the field has a different field
    // identifier and property path.
    $nameField = new Field($this->index, 'title');
    $nameField->setPropertyPath('name');
    $nameField->setType('text');

    $fields = ['title' => $nameField];

    // Setup: Create a condition group with a facet tag on the text field.
    $conditionGroup = (new ConditionGroup('OR', ['facet:title']))
      ->addCondition('title', 'foo bar baz');

    // SUT: Ask the SUT to build a filter clause.
    $filters = (new FilterBuilder($this->logger))
      ->buildFilters($conditionGroup, $fields);

    // Assert: The post_filters should use name.keyword for exact matching.
    // The facets_post_filters should also use name.keyword.
    $this->assertEquals([
      'filters' => NULL,
      'post_filters' => [
        'term' => ['title.keyword' => 'foo bar baz'],
      ],
      'facets_post_filters' => [
        'title' => [
          'terms' => ['title.keyword' => ['foo bar baz']],
        ],
      ],
    ], $filters);
  }

  /**
   * @covers ::buildFilterTerm
   * @dataProvider filterTermProvider
   */
  public function testBuildFilterTerm($value, $operator, $expected) {
    $filterBuilder = new FilterBuilder($this->logger);
    $condition = new Condition('foo', $value, $operator);
    $filterTerm = $filterBuilder->buildFilterTerm($condition);
    $this->assertEquals($expected, $filterTerm);
  }

  /**
   * Provides test data for term provider.
   */
  public static function filterTermProvider(): array {
    return [
      'not equals with null value' => [
        'value' => NULL,
        'operator' => '<>',
        'expected' => ['exists' => ['field' => 'foo']],
      ],
      'equals with null value' => [
        'value' => NULL,
        'operator' => '=',
        'expected' => ['bool' => ['must_not' => ['exists' => ['field' => 'foo']]]],
      ],
      'equals with string value' => [
        'value' => 'bar',
        'operator' => '=',
        'expected' => ['term' => ['foo' => 'bar']],
      ],
      'equals with integer value' => [
        'value' => 1775741400,
        'operator' => '=',
        'expected' => ['term' => ['foo' => '1775741400']],
      ],
      'equals with float value' => [
        'value' => 1.23,
        'operator' => '=',
        'expected' => ['term' => ['foo' => '1.23']],
      ],
      'equals with boolean value' => [
        'value' => TRUE,
        'operator' => '=',
        'expected' => ['term' => ['foo' => 'true']],
      ],
      'in array' => [
        'value' => ['bar', 'whiz'],
        'operator' => 'IN',
        'expected' => [
          'terms' => ['foo' => ['bar', 'whiz']],
        ],
      ],
      'not in array' => [
        'value' => ['bar', 'whiz'],
        'operator' => 'NOT IN',
        'expected' => [
          'bool' => [
            'must_not' => ['terms' => ['foo' => ['bar', 'whiz']]],
          ],
        ],
      ],
      'not equals with string value' => [
        'value' => 'bar',
        'operator' => '<>',
        'expected' => [
          'bool' => [
            'must_not' => ['term' => ['foo' => 'bar']],
          ],
        ],
      ],
      'not equals with integer value' => [
        'value' => 1775938500,
        'operator' => '<>',
        'expected' => [
          'bool' => [
            'must_not' => ['term' => ['foo' => '1775938500']],
          ],
        ],
      ],
      'not equals with float value' => [
        'value' => 3.14,
        'operator' => '<>',
        'expected' => [
          'bool' => [
            'must_not' => ['term' => ['foo' => '3.14']],
          ],
        ],
      ],
      'not equals with boolean value' => [
        'value' => FALSE,
        'operator' => '<>',
        'expected' => [
          'bool' => [
            'must_not' => ['term' => ['foo' => 'false']],
          ],
        ],
      ],
      'like' => [
        'value' => 'bar',
        'operator' => 'LIKE',
        'expected' => [
          'match' => [
            'foo' => [
              'query' => 'bar',
              'fuzziness' => 'auto',
            ],
          ],
        ],
      ],
      'not like' => [
        'value' => 'bar',
        'operator' => 'NOT LIKE',
        'expected' => [
          'bool' => [
            'must_not' => [
              'match' => [
                'foo' => [
                  'query' => 'bar',
                  'fuzziness' => 'auto',
                ],
              ],
            ],
          ],
        ],
      ],
      'exact' => [
        'value' => 'bar',
        'operator' => 'EXACT',
        'expected' => [
          'match_phrase' => [
            'foo' => ['query' => 'bar'],
          ],
        ],
      ],
      'greater than' => [
        'value' => 'bar',
        'operator' => '>',
        'expected' => [
          'range' => [
            'foo' => [
              'gt' => 'bar',
            ],
          ],
        ],
      ],
      'greater than or equal' => [
        'value' => 'bar',
        'operator' => '>=',
        'expected' => [
          'range' => [
            'foo' => [
              'gte' => 'bar',
            ],
          ],
        ],
      ],
      'less than' => [
        'value' => 'bar',
        'operator' => '<',
        'expected' => [
          'range' => [
            'foo' => [
              'lt' => 'bar',
            ],
          ],
        ],
      ],
      'less than or equal' => [
        'value' => 'bar',
        'operator' => '<=',
        'expected' => [
          'range' => [
            'foo' => [
              'lte' => 'bar',
            ],
          ],
        ],
      ],
      'between' => [
        'value' => [1, 10],
        'operator' => 'BETWEEN',
        'expected' => [
          'range' =>
            [
              'foo' =>
                [
                  'gte' => 1,
                  'lte' => 10,
                ],
            ],
        ],
      ],
      'not between' => [
        'value' => [1, 10],
        'operator' => 'NOT BETWEEN',
        'expected' => [
          'bool' => [
            'must_not' => [
              'range' =>
                [
                  'foo' =>
                    [
                      'gte' => 1,
                      'lte' => 10,
                    ],
                ],
            ],
          ],
        ],
      ],
    ];
  }

}
