<?php

namespace nodespark\DESConnector\Test\Elasticsearch\Aggregations;

use nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Histogram;
use PHPUnit\Framework\TestCase;

/**
 * Class HistogramTest
 *
 * @coversDefaultClass \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Histogram
 */
class HistogramTest extends TestCase {

  /**
   * An instance of the Histogram class.
   *
   * @var \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Histogram
   */
  protected $histogram;

  /**
   * @inheritDoc
   */
  protected function setUp() : void {
    parent::setUp();

    $this->histogram = new Histogram('foo', 'bar');
  }

  /**
   * @covers ::setInterval
   * @covers ::setMinDocCount
   * @covers ::setExtendedBounds
   * @covers ::setOrder
   * @covers ::setOffset
   * @covers ::setKeyed
   * @covers ::setMissing
   */
  public function testSetters() {
    $this->histogram->setInterval('alpha');
    $this->histogram->setMinDocCount('beta');
    $this->histogram->setExtendedBounds(1, 2);
    $this->histogram->setOrder('charlie', 'asc');
    $this->histogram->setOffset('delta');
    $this->histogram->setKeyed(TRUE);
    $this->histogram->setMissing('echo');

    $expected_aggregation = [
      'foo' =>
        [
          'histogram' =>
            [
              'field' => 'bar',
              'interval' => 'alpha',
              'min_doc_count' => 'beta',
              'extended_bounds' => [
                'min' => 1,
                'max' => 2,
              ],
              'order' => [
                'charlie' => 'asc',
              ],
              'offset' => 'delta',
              'keyed' => TRUE,
              'missing' => 'echo',
            ],
        ],
    ];

    $this->assertEquals($expected_aggregation,
      $this->histogram->constructAggregation());
  }

}
