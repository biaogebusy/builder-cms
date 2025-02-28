<?php

namespace nodespark\DESConnector\Test;

use nodespark\DESConnector\Client;
use nodespark\DESConnector\Elasticsearch\Query\QueryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class ClientTest
 *
 * @coversDefaultClass \nodespark\DESConnector\Client
 */
class ClientTest extends TestCase {

  /**
   * An instance of the DESConnector client.
   *
   * @var \nodespark\DESConnector\ClientInterface
   */
  protected $client;

  /**
   * @inheritDoc
   */
  protected function setUp() : void {
    parent::setUp();

    $params = ['hosts' => ['foo', 'bar']];
    $this->client = new Client($params);
  }


  /**
   * @covers ::__construct
   * @covers ::initClient
   */
  public function testConstruct() {
    $this->assertInstanceOf('nodespark\DESConnector\Client', $this->client);
  }

  /**
   * @covers ::handleUrls
   */
  public function testGetHandleUrls() {
    $params = [
      'hosts' => ['foo'=> 'http://example.com'],
      'auth' => [
        'http://example.com' => [
          'username' => 'Tom Waits',
          'password' => 'Clap hands',
        ],
      ],
    ];
    $this->client = new Client($params);
    $this->assertInstanceOf('nodespark\DESConnector\Client', $this->client);
  }

  /**
   * @covers ::CheckResponseAck
   */
  public function testCheckResponseAck() {
    $successful_response = [
      'acknowledged' => TRUE
    ];
    $this->assertTrue($this->client->CheckResponseAck($successful_response));

    $failed_response = [];
    $this->assertFalse($this->client->CheckResponseAck($failed_response));
  }

  /**
   * @covers ::getServerVersion
   */
  public function testGetServerVersion() {
    $client = $this->getMockBuilder(Client::class)
      ->disableOriginalConstructor()
      ->setMethods(['info'])
      ->getMock();

    $client->expects($this->once())
      ->method('info')
      ->willReturn([
        'version' => [
          'number' => 1,
        ]
      ]);

    $this->assertEquals(1, $client->getServerVersion());
  }

  /**
   * @covers ::checkIfPluginExists
   */
  public function testCheckIfPluginExists() {
    $client = $this->getMockBuilder(Client::class)
      ->disableOriginalConstructor()
      ->setMethods(['getInstalledPlugins'])
      ->getMock();

    $client->expects($this->exactly(2))
      ->method('getInstalledPlugins')
      ->willReturn([
        'elastic_node_1' => [
          'plugin_a' => ['plugin_config'],
        ],
      ]);

    $this->assertTrue($client->checkIfPluginExists('plugin_a'));
    $this->assertFalse($client->checkIfPluginExists('plugin_b'));
  }

  /**
   * @covers ::aggregations
   */
  public function testAggregations() {
    $aggregations = $this->client->aggregations();
    $this->assertInstanceOf('nodespark\DESConnector\Elasticsearch\Aggregations\Aggregations', $aggregations);
  }

  /**
   * @covers ::setSearchResponse
   * @covers ::getSearchResponse
   */
  public function testSearchResponse() {
    $searchResponse = [
      'foo' => 'bar',
    ];
    $query = $this->createMock(QueryInterface::class);

    $this->client->setSearchResponse($searchResponse, $query);
    $this->assertInstanceOf('\nodespark\DESConnector\Elasticsearch\Response\SearchResponse', $this->client->getSearchResponse());
  }

}
