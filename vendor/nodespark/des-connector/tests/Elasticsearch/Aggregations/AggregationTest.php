<?php

namespace nodespark\DESConnector\Test\Elasticsearch\Aggregations;

use nodespark\DESConnector\Elasticsearch\Aggregations\Aggregation;
use PHPUnit\Framework\TestCase;

/**
 * Class AggregationTest
 *
 * @coversDefaultClass \nodespark\DESConnector\Elasticsearch\Aggregations\Aggregation
 */
class AggregationTest extends TestCase {

  /**
   * An instance that extends from the Aggregation class.
   *
   * @var \nodespark\DESConnector\Elasticsearch\Aggregations\AggregationInterface
   */
  protected $aggregation;

  /**
   * @inheritDoc
   */
  protected function setUp() : void {
    parent::setUp();

    $this->aggregation = $this->getMockForAbstractClass('\nodespark\DESConnector\Elasticsearch\Aggregations\Aggregation',
      ['foo', 'bar', 'baz']);
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $this->assertInstanceOf('\nodespark\DESConnector\Elasticsearch\Aggregations\Aggregation',
      $this->aggregation);
  }

  /**
   * @covers ::getGlobalName
   */
  public function testGetGlobalName() {
    $this->assertEquals('foo_global', $this->aggregation->getGlobalName());
  }

  /**
   * @covers ::getFieldName
   */
  public function testGetFieldName() {
    $this->assertEquals('bar', $this->aggregation->getFieldName());
  }

  /**
   * @covers ::constructAggregation
   * @covers ::getParameters
   * @covers ::setGlobalScope
   */
  public function testConsructAggregation() {
    $expected_aggregation = [
      'foo' =>
        [
          'baz' =>
            [
              'field' => 'bar',
            ],
        ],
    ];

    $this->assertEquals($expected_aggregation,
      $this->aggregation->constructAggregation());

    $this->aggregation->setGlobalScope(TRUE);
    $expected_aggregation = [
      'foo' =>
        [
          'baz' =>
            [
              'field' => 'bar',
            ],
        ],
      'foo_global' =>
        [
          'global' => new \stdClass(),
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
    $this->assertEquals($expected_aggregation,
      $this->aggregation->constructAggregation());
  }

  /**
   * @covers ::getName
   */
  public function testGetName() {
    $this->assertEquals('foo', $this->aggregation->getName());
  }

}
