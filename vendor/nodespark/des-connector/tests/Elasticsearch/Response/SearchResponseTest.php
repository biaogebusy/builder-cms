<?php

namespace nodespark\DESConnector\Test\Elasticsearch\Response;

use nodespark\DESConnector\Elasticsearch\Aggregations\Aggregations;
use nodespark\DESConnector\Elasticsearch\Query\Query;
use nodespark\DESConnector\Elasticsearch\Response\SearchResponse;
use PHPUnit\Framework\TestCase;

/**
 * Class SearchResponseTest
 *
 * @coversDefaultClass \nodespark\DESConnector\Elasticsearch\Response\SearchResponse
 */
class SearchResponseTest extends TestCase {

  /**
   * An instance of the SearchResponse class.
   *
   * @var \nodespark\DESConnector\Elasticsearch\Response\SearchResponseInterface
   */
  protected $searchResponse;

  /**
   * The search response.
   *
   * @var array
   */
  protected $response;

  /**
   * An instance of the Aggregations class.
   *
   * \nodespark\DESConnector\Elasticsearch\Aggregations\Aggregations
   */
  protected $aggregations;

  /**
   * An instance of the Query class.
   *
   * \nodespark\DESConnector\Elasticsearch\Query\Query
   */
  protected $query;

  /**
   * @inheritDoc
   */
  protected function setUp() : void {
    parent::setUp();

    $this->response = [
      'took' => 200,
      'timed_out' => 0,
      '_shards' => [
        'total' => 1,
        'failed' => 0,
        'successful' => 5,
      ],
      'hits' => [
        'max_score' => 2,
        'total' => ['value' => 100],
        'hits' => [
          'alpha' => 'bravo',
        ],
        'parsed_hits' => [
          'charlie' => 'delta',
        ],
      ],
    ];
    $this->aggregations = Aggregations::getInstance('foo');
    $this->query = new Query();

    $this->searchResponse = new SearchResponse($this->response, $this->aggregations, $this->query);
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $this->assertInstanceOf('\nodespark\DESConnector\Elasticsearch\Response\SearchResponse', $this->searchResponse);
  }

  /**
   * @covers ::getSearchTimeTook
   */
  public function testParseResponse() {
    $this->assertEquals(200, $this->searchResponse->getSearchTimeTook());
  }

  /**
   * @covers ::hasTimeout
   */
  public function testHastimeout() {
    $this->assertFalse($this->searchResponse->hasTimeout());
  }

  /**
   * @covers ::getTotalShards
   */
  public function testGetTotalShards() {
    $this->assertEquals(1, $this->searchResponse->getTotalShards());
  }

  /**
   * @covers ::getFailedShards
   */
  public function testGetFailedShards() {
    $this->assertEquals(0, $this->searchResponse->getFailedShards());
  }

  /**
   * @covers ::getSuccessfulShards
   */
  public function testSuccessfulShards() {
    $this->assertEquals(5, $this->searchResponse->getSuccessfulShards());
  }

  /**
   * @covers ::getSearchMaxScore
   */
  public function testGetSearchMaxScore() {
    $this->assertEquals(2, $this->searchResponse->getSearchMaxScore());
  }

  /**
   * @covers ::getTotalResultsNumber
   */
  public function testTotalResultsNumber() {
    $this->assertEquals(100, $this->searchResponse->getTotalResultsNumber());
  }

  /**
   * @covers ::getRawResponse
   */
  public function testGetRawResponse() {
    $this->assertEquals($this->response, $this->searchResponse->getRawResponse());
  }

  /**
   * @covers ::getRawResults
   */
  public function testGetRawResults() {
    $this->assertEquals($this->response['hits']['hits'], $this->searchResponse->getRawResults());

    // Now try with an empty response.
    $empty_response = new SearchResponse([], $this->aggregations, $this->query);
    $this->assertEmpty($empty_response->getRawResults());
  }

  /**
   * @covers ::getResults
   */
  public function testGetResults() {
    $this->assertEquals($this->response['hits']['parsed_hits'], $this->searchResponse->getResults());

    // Now try with an empty response.
    $empty_response = new SearchResponse([], $this->aggregations, $this->query);
    $this->assertEmpty($empty_response->getResults());
  }

}
