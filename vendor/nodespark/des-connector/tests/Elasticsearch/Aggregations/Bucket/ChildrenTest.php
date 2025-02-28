<?php

namespace nodespark\DESConnector\Test\Elasticsearch\Aggregations;

use nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Children;
use PHPUnit\Framework\TestCase;

/**
 * Class ChildrenTest
 *
 * This test class covers both Children and the abstract class Bucket.
 *
 * @coversDefaultClass \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Children
 */
class ChildrenTest extends TestCase {

  /**
   * An instance of the Children class.
   *
   * @var \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Children
   */
  protected $children;

  /**
   * @inheritDoc
   */
  protected function setUp() : void {
    parent::setUp();

    $this->children = new Children('foo', 'bar');
  }

  /**
   * @covers ::__construct
   * @covers \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Bucket::__construct
   */
  public function testConstruct() {
    $this->assertInstanceOf('\nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Children',
      $this->children);
  }

  /**
   * @covers \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Bucket::constructAggregation
   * @covers \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Bucket::setSubAggregation
   */
  public function testConstructAggregation() {
    $expected_aggregation = [
      'foo' =>
        [
          'children' =>
            [
              'type' => 'bar',
            ],
          'aggs' =>
            [
              'foo' =>
                [
                  'baz' =>
                    [
                      'field' => 'bar',
                    ],
                ],
            ],
        ],
    ];

    $aggregation = $this->getMockForAbstractClass('\nodespark\DESConnector\Elasticsearch\Aggregations\Aggregation',
      ['foo', 'bar', 'baz']);
    $this->children->setSubAggregation($aggregation);

    $aggregation = $this->children->constructAggregation();
    $this->assertEquals($expected_aggregation, $aggregation);
  }

}
