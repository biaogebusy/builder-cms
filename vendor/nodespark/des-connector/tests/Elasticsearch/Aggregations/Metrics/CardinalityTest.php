<?php

namespace nodespark\DESConnector\Test\Elasticsearch\Aggregations;

use nodespark\DESConnector\Elasticsearch\Aggregations\Metrics\Cardinality;
use PHPUnit\Framework\TestCase;

/**
 * Class CardinalityTest
 *
 * @coversDefaultClass \nodespark\DESConnector\Elasticsearch\Aggregations\Metrics\Cardinality
 */
class CardinalityTest extends TestCase {

  /**
   * An instance of the Cardinality class.
   *
   * @var \nodespark\DESConnector\Elasticsearch\Aggregations\Metrics\Cardinality
   */
  protected $cardinality;

  /**
   * @inheritDoc
   */
  protected function setUp() : void {
    parent::setUp();

    $this->cardinality = new Cardinality('foo', 'bar');
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $this->assertInstanceOf('\nodespark\DESConnector\Elasticsearch\Aggregations\Metrics\Cardinality',
      $this->cardinality);
  }

  /**
   * @covers ::setPrecisionThreshold
   * @covers ::constructAggregation
   */
  public function testConstructAggregation() {
    $this->cardinality->setPrecisionThreshold(1);
    $expected_aggregation = [
      'foo' =>
        [
          'cardinality' =>
            [
              'field' => 'bar',
              'precision_threshold' => 1,
            ],
        ],
    ];

    $aggregation = $this->cardinality->constructAggregation();
    $this->assertEquals($expected_aggregation, $aggregation);
  }

}
