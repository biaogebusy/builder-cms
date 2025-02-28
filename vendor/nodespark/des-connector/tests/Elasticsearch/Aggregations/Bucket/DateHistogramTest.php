<?php

namespace nodespark\DESConnector\Test\Elasticsearch\Aggregations;

use nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\DateHistogram;
use PHPUnit\Framework\TestCase;

/**
 * Class DateHistogramTest
 *
 * @coversDefaultClass \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\DateHistogram
 */
class DateHistogramTest extends TestCase {

  /**
   * An instance of the DateHistogram class.
   *
   * @var \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\DateHistogram
   */
  protected $dateHistogram;

  /**
   * @inheritDoc
   */
  protected function setUp() : void {
    parent::setUp();

    $this->dateHistogram = new DateHistogram('foo', 'bar');
  }

  /**
   * @covers ::setInterval
   * @covers ::setTimeZone
   * @covers ::setOffset
   * @covers ::setMinDocCount
   * @covers ::setExtendedBounds
   * @covers ::setOrder
   * @covers ::setKeyed
   * @covers ::setFormat
   * @covers ::setMissing
   */
  public function testSetters() {
    $this->dateHistogram->setInterval('alpha');
    $this->dateHistogram->setTimeZone('bravo');
    $this->dateHistogram->setOffset('charlie');
    $this->dateHistogram->setMinDocCount('delta');
    $this->dateHistogram->setExtendedBounds(1, 2);
    $this->dateHistogram->setOrder('echo', 'asc');
    $this->dateHistogram->setKeyed(TRUE);
    $this->dateHistogram->setFormat('foxtrot');
    $this->dateHistogram->setMissing('golf');

    $expected_aggregation = [
      'foo' =>
        [
          'date_histogram' =>
            [
              'field' => 'bar',
              'interval' => 'alpha',
              'time_zone' => 'bravo',
              'offset' => 'charlie',
              'min_doc_count' => 'delta',
              'extended_bounds' => [
                'min' => 1,
                'max' => 2,
              ],
              'order' => [
                'echo' => 'asc',
              ],
              'keyed' => TRUE,
              'format' => 'foxtrot',
              'missing' => 'golf',
            ],
        ],
    ];

    $this->assertEquals($expected_aggregation,
      $this->dateHistogram->constructAggregation());
  }

}
