<?php

namespace nodespark\DESConnector\Test\Elasticsearch\Aggregations;

use nodespark\DESConnector\Elasticsearch\Aggregations\Aggregations;
use nodespark\DESConnector\Elasticsearch\Aggregations\AggregationsInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class AggregationsTest
 *
 * @coversDefaultClass \nodespark\DESConnector\Elasticsearch\Aggregations\Aggregations
 */
class AggregationsTest extends TestCase {

  /**
   * An instance of the Aggregations class.
   *
   * @var \nodespark\DESConnector\Elasticsearch\Aggregations\AggregationsInterface
   */
  protected $aggregations;

  /**
   * @inheritDoc
   */
  protected function setUp() : void {
    parent::setUp();

    $this->aggregations = Aggregations::getInstance('foo');
  }

  /**
   * @covers ::getInstance
   */
  public function testGetInstance() {
    $this->assertInstanceOf('\nodespark\DESConnector\Elasticsearch\Aggregations\AggregationsInterface',
      $this->aggregations);
  }

  /**
   * @covers ::hasAggregations
   */
  public function testHasAggregations() {
    $this->assertFalse($this->aggregations->hasAggregations());
  }

  /**
   * @covers ::constructAggregation
   * @covers ::setAggregation
   */
  public function testConstructAggregation() {
    $expected_aggregation = [];
    $this->assertEquals([], $this->aggregations->constructAggregation());

    $aggregation = $this->createMock('\nodespark\DESConnector\Elasticsearch\Aggregations\Aggregation');

    $aggregation->expects($this->once())
      ->method('getName')
      ->willReturn('foo');

    $this->aggregations->setAggregation($aggregation);

    $aggregation->expects($this->any())
      ->method('constructAggregation')
      ->willReturn(['foo' => 'bar']);
    $expected_aggregation = [
      'aggs' =>
        [
          'foo' => 'bar',
        ],
    ];
    $this->assertEquals($expected_aggregation,
      $this->aggregations->constructAggregation());

    return $this->aggregations;
  }

  /**
   * @covers ::applyAggregationsToParams
   *
   * @param AggregationsInterface $aggregations
   *
   * @depends testConstructAggregation
   */
  public function testApplyAggregationsToParams(
    AggregationsInterface $aggregations
  ) {
    $params = [];
    $aggregations->applyAggregationsToParams($params);
    $expected_params = [
      'body' =>
        [
          'aggs' =>
            [
              'foo' => 'bar',
            ],
        ],
    ];
    $this->assertEquals($expected_params, $params);
  }

}
