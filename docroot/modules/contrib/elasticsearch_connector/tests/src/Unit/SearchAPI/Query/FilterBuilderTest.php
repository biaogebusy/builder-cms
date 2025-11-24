<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Unit\SearchAPI\Query;

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
   * @covers ::buildFilters
   */
  public function testBuildFilters() {
    // Setup: Create a mock index for the test.
    $index = $this->mockIndex();

    // Setup: Create a condition group to build a filter for.
    $conditionGroup = (new ConditionGroup())
      ->addCondition('foo', 'bar')
      ->addCondition('whiz', 'bang');

    // Setup: Create two fields to build filters with.
    $fields = [
      'foo' => new Field($index, 'foo'),
      'whiz' => new Field($index, 'whiz'),
    ];

    // Setup: Create a mock logger.
    $logger = $this->prophesize(LoggerInterface::class)->reveal();

    // SUT: Ask the SUT to build a filter clause.
    $filters = (new FilterBuilder($logger))
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
   * Test building filters with one facet.
   */
  public function testBuildFiltersWithOneFacet() {
    // Setup: Create a mock index for the test.
    $index = $this->mockIndex();

    // Setup: Create a condition group to build a filter for. Note this has the
    // non-default conjunction OR and a facets tag.
    $conditionGroup = (new ConditionGroup("OR", ["facet:foo"]))
      ->addCondition('foo', 'bar')
      ->addCondition('whiz', 'bang');

    // Setup: Create two fields to build filters with.
    $fields = [
      'foo' => new Field($index, 'foo'),
      'whiz' => new Field($index, 'whiz'),
    ];

    // Setup: Create a mock logger.
    $logger = $this->prophesize(LoggerInterface::class)->reveal();

    // SUT: Ask the SUT to build a filter clause.
    $filters = (new FilterBuilder($logger))
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
    // Setup: Create a mock index for the test.
    $index = $this->mockIndex();

    // Setup: Create a condition group to build a filter for. Note this has the
    // non-default conjunction OR and a two facets tags.
    $conditionGroup = (new ConditionGroup('OR', ['facet:foo', 'facet:whiz']))
      ->addCondition('foo', 'bar')
      ->addCondition('whiz', 'bang');

    // Setup: Create two fields to build filters with.
    $fields = [
      'foo' => new Field($index, 'foo'),
      'whiz' => new Field($index, 'whiz'),
    ];

    // Setup: Create a mock logger.
    $logger = $this->prophesize(LoggerInterface::class)->reveal();

    // SUT: Ask the SUT to build a filter clause.
    $filters = (new FilterBuilder($logger))
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
   * @covers ::buildFilterTerm
   * @dataProvider filterTermProvider
   */
  public function testBuildFilterTerm($value, $operator, $expected) {
    $logger = $this->prophesize(LoggerInterface::class);
    $filterBuilder = new FilterBuilder($logger->reveal());
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
      'equals' => [
        'value' => 'bar',
        'operator' => '=',
        'expected' => ['term' => ['foo' => 'bar']],
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
      'not equals' => [
        'value' => 'bar',
        'operator' => '<>',
        'expected' => [
          'bool' => [
            'must_not' => ['term' => ['foo' => 'bar']],
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

  /**
   * Helper function to build a mock Search API Index.
   *
   * @return \Drupal\search_api\IndexInterface
   *   A mock Search API Index with a random machine name.
   */
  protected function mockIndex(): IndexInterface {
    $index = $this->prophesize(IndexInterface::class);
    $indexId = 'index_' . $this->randomMachineName();
    $index->id()->willReturn($indexId);
    return $index->reveal();
  }

}
