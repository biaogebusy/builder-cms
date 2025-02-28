<?php

namespace nodespark\DESConnector\Test\Elasticsearch\Aggregations;

use nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Terms;
use PHPUnit\Framework\TestCase;

/**
 * Class TermsTest
 *
 * @coversDefaultClass \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Terms
 */
class TermsTest extends TestCase {

  /**
   * An instance of the Terms class.
   *
   * @var \nodespark\DESConnector\Elasticsearch\Aggregations\Bucket\Terms
   */
  protected $terms;

  /**
   * @inheritDoc
   */
  protected function setUp() : void {
    parent::setUp();

    $this->terms = new Terms('foo', 'bar');
  }

  /**
   * @covers ::setSize
   * @covers ::setOrder
   * @covers ::constructAggregation
   */
  public function testConstructAggregation() {
    $this->terms->setSize(1);
    $this->terms->setOrder('alpha');

    $expected_aggregation = [
      'foo' =>
        [
          'terms' =>
            [
              'field' => 'bar',
              'size' => 1,
              'order' => 'alpha',
            ],
        ],
    ];

    $this->assertEquals($expected_aggregation,
      $this->terms->constructAggregation());
  }

}
