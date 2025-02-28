<?php

namespace nodespark\DESConnector\Test\Elasticsearch\Aggregations;

use nodespark\DESConnector\Elasticsearch\Aggregations\Metrics\Avg;
use PHPUnit\Framework\TestCase;

/**
 * Class AvgTest
 *
 * @coversDefaultClass \nodespark\DESConnector\Elasticsearch\Aggregations\Metrics\Avg
 */
class AvgTest extends TestCase {

  /**
   * An instance of the Avg class.
   *
   * @var \nodespark\DESConnector\Elasticsearch\Aggregations\Metrics\Avg
   */
  protected $avg;

  /**
   * @inheritDoc
   */
  protected function setUp() : void {
    parent::setUp();

    $this->avg = new Avg('foo', 'bar');
  }

  /**
   * @covers ::__construct
   * @covers \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Bucket::__construct
   */
  public function testConstruct() {
    $this->assertInstanceOf('\nodespark\DESConnector\Elasticsearch\Aggregations\Metrics\Avg',
      $this->avg);
  }

  /**
   * @covers \nodespark\DESConnector\Elasticsearch\Aggregations\Metrics\Avg::constructAggregation
   * @covers \nodespark\DESConnector\Elasticsearch\Aggregations\Metrics\Avg::setMissing
   */
  public function testConstructAggregation() {
    $this->avg->setMissing(1);
    $expected_aggregation = [
      'foo' =>
        [
          'avg' =>
            [
              'field' => 'bar',
              'missing' => 1,
            ],
        ],
    ];

    $aggregation = $this->avg->constructAggregation();
    $this->assertEquals($expected_aggregation, $aggregation);
  }

}
