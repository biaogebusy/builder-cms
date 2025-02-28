<?php

namespace nodespark\DESConnector\Test\Elasticsearch\Aggregations;

use nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Range;
use PHPUnit\Framework\TestCase;

/**
 * Class RangeTest
 *
 * @coversDefaultClass \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Range
 */
class RangeTest extends TestCase {

  /**
   * An instance of the Range class.
   *
   * @var \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Range
   */
  protected $range;

  /**
   * @inheritDoc
   */
  protected function setUp() : void {
    parent::setUp();

    $this->range = new Range('foo', 'bar');
  }

  /**
   * @covers ::setInterval
   * @covers ::setKeyed
   * @covers ::setScript
   */
  public function testSetters() {
    $this->range->setInterval([1, 2]);
    $this->range->setKeyed(TRUE);
    $this->range->setScript(['alpha']);

    $expected_aggregation = [
      'foo' =>
        [
          'range' =>
            [
              'field' => 'bar',
              'ranges' => [1, 2],
              'keyed' => TRUE,
              'script' => ['alpha'],
            ],
        ],
    ];

    $this->assertEquals($expected_aggregation,
      $this->range->constructAggregation());
  }

}
