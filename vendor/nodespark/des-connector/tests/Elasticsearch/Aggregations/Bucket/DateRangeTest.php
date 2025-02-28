<?php

namespace nodespark\DESConnector\Test\Elasticsearch\Aggregations;

use nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\DateRange;
use PHPUnit\Framework\TestCase;

/**
 * Class DateRangeTest
 *
 * @coversDefaultClass \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\DateRange
 */
class DateRangeTest extends TestCase {

  /**
   * An instance of the DateRange class.
   *
   * @var \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\DateRange
   */
  protected $dateRange;

  /**
   * @inheritDoc
   */
  protected function setUp() : void {
    parent::setUp();

    $this->dateRange = new DateRange('foo', 'bar');
  }

  /**
   * @covers ::setInterval
   * @covers ::setKeyed
   * @covers ::setScript
   * @covers ::setFormat
   * @covers ::setTimeZone
   */
  public function testSetters() {
    $this->dateRange->setInterval([1, 2]);
    $this->dateRange->setKeyed(TRUE);
    $this->dateRange->setScript(['bravo']);
    $this->dateRange->setFormat('charlie');
    $this->dateRange->setTimeZone('delta');

    $expected_aggregation = [
      'foo' =>
        [
          'date_range' =>
            [
              'field' => 'bar',
              'ranges' => [1, 2],
              'keyed' => TRUE,
              'script' => ['bravo'],
              'format' => 'charlie',
              'time_zone' => 'delta',
            ],
        ],
    ];

    $this->assertEquals($expected_aggregation,
      $this->dateRange->constructAggregation());
  }

}
