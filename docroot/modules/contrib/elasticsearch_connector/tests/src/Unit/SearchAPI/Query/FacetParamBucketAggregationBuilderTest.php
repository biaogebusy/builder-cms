<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Unit\SearchAPI\Query;

use Drupal\elasticsearch_connector\SearchAPI\Query\FacetParamBuilder;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

/**
 * Tests the facet parameter builder's buildTermBucketAgg() function.
 *
 * Note that tests for buildTermBucketAgg() could be expanded further!
 *
 * @covers \Drupal\elasticsearch_connector\SearchAPI\Query\FacetParamBuilder::buildTermBucketAgg
 *
 * @group elasticsearch_connector
 */
class FacetParamBucketAggregationBuilderTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Test the buildTermBucketAgg() function's limit (size) options.
   */
  public function testLimitOptions(): void {
    // Mock index with no fulltext fields.
    $index = $this->prophesize(IndexInterface::class);
    $index->getFulltextFields()->willReturn([]);

    // Mock query.
    $query = $this->prophesize(QueryInterface::class);
    $query->getIndex()->willReturn($index->reveal());

    $logger = $this->prophesize(LoggerInterface::class);

    $conditionGroup = $this->prophesize(ConditionGroupInterface::class);
    $conditionGroup->getConjunction()->willReturn('AND');

    $index = $this->prophesize(IndexInterface::class);
    $index->getFulltextFields()->willReturn([]);

    $query = $this->prophesize(QueryInterface::class);
    $query->getConditionGroup()->willReturn($conditionGroup->reveal());
    $query->getIndex()->willReturn($index);

    $builder = new FacetParamBuilder($logger->reveal());
    $reflection = new \ReflectionClass($builder);
    $method = $reflection->getMethod('buildTermBucketAgg');

    // Limit is set to 5, size = 5.
    $facet_with_limit = [
      'field' => 'field_tags',
      'limit' => 5,
      'operator' => 'or',
    ];
    $agg_with_limit = $method->invokeArgs($builder, [$query->reveal(), 'facet_limited', $facet_with_limit, []]);
    $this->assertEquals(5, $agg_with_limit['facet_limited']['terms']['size']);

    // Limit is set to 0 (No limit), size = 10000.
    $facet_with_zero_limit = [
      'field' => 'field_tags',
      'limit' => 0,
      'operator' => 'or',
    ];
    $agg_with_zero = $method->invokeArgs($builder, [$query->reveal(), 'facet_unlimited', $facet_with_zero_limit, []]);
    $this->assertEquals(10000, $agg_with_zero['facet_unlimited']['terms']['size']);

    // Limit not set at all, default size (10) should be used.
    $facet_without_limit = [
      'field' => 'field_tags',
      'operator' => 'or',
    ];
    $agg_without_limit = $method->invokeArgs($builder, [$query->reveal(), 'facet_default', $facet_without_limit, []]);
    $this->assertEquals(10, $agg_without_limit['facet_default']['terms']['size']);
  }

}
