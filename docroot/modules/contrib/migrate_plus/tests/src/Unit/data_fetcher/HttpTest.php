<?php

namespace Drupal\Tests\migrate_plus\Unit\data_fetcher;

use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\DataFetcherPluginBase;
use Drupal\migrate_plus\Plugin\migrate_plus\authentication\Basic;
use Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher\Http;
use Drupal\Tests\migrate\Unit\MigrateTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * @file
 * PHPUnit tests for the Migrate Plus Http 'data fetcher' plugin.
 */

/**
 * @coversDefaultClass \Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher\Http
 *
 * @group migrate_plus
 */
class HttpTest extends MigrateTestCase {

  /**
   * Minimal migration configuration data.
   *
   * @var array
   */
  private $specificMigrationConfig = [
    'source' => 'url',
    'urls' => ['http://example.org/http_fetcher_test'],
    'data_fetcher_plugin' => 'http',
    'data_parser_plugin' => 'json',
    'item_selector' => 0,
    'authentication' => [
      'plugin' => 'basic',
      'username' => 'testing',
      'password' => 'password',
    ],
    'fields' => [],
    'ids' => [
      'id' => [
        'type' => 'integer',
      ],
    ],
  ];

  /**
   * The data fetcher plugin ID being tested.
   *
   * @var string
   */
  private $dataFetcherPluginId = 'http';

  /**
   * The data fetcher plugin definition.
   *
   * @var array
   */
  private $pluginDefinition = [
    'id' => 'http',
    'title' => 'HTTP',
  ];

  /**
   * Test data to validate an HTTP response against.
   *
   * @var string
   */
  private $testData = '
    {
      "id": 1,
      "name": "Joe Bloggs"
    }
  ';

  /**
   * Mocked up Basic authentication plugin.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  private $basicAuthenticator = NULL;

  /**
   * Set up test environment.
   */
  public function setUp(): void {
    // Mock up a Basic authentication plugin that will be used in requests.
    $basic_authenticator = $this->getMockBuilder(Basic::class)
      ->disableOriginalConstructor()
      ->getMock();

    $basic_authenticator->method('getAuthenticationOptions')
      ->will($this->returnValue([
        'auth' => [
          'username',
          'password',
        ],
      ]));

    $this->basicAuthenticator = $basic_authenticator;
  }

  /**
   * Test 'http' data fetcher (with auth) returns an expected response.
   */
  public function testFetchHttpWithAuth(): void {
    $migration_config = $this->migrationConfiguration + $this->specificMigrationConfig;

    $plugin = new TestHttp($migration_config, $this->dataFetcherPluginId, $this->pluginDefinition);
    $plugin->mockHttpClient([[200, 'application/json', $this->testData]], $this->basicAuthenticator);

    // The Guzzle mock returns an instance of StreamInterface.
    // http://docs.guzzlephp.org/en/latest/psr7.html
    $stream = $plugin->getResponseContent($migration_config['urls'][0]);

    $body = json_decode((string) $stream, TRUE);

    // Compare what we got back from the parser to what we expected to get.
    $expected = json_decode($this->testData, TRUE);
    $this->assertSame($expected, $body);
  }

  /**
   * Test 'http' data fetcher (without auth) returns an expected response.
   */
  public function testFetchHttpNoAuth(): void {
    $migration_config = $this->migrationConfiguration + $this->specificMigrationConfig;
    unset($migration_config['authentication']);

    $plugin = new TestHttp($migration_config, $this->dataFetcherPluginId, $this->pluginDefinition);
    $plugin->mockHttpClient([[200, 'application/json', $this->testData]], NULL);

    $stream = $plugin->getResponseContent($migration_config['urls'][0]);

    $body = json_decode((string) $stream, TRUE);

    $expected = json_decode($this->testData, TRUE);
    $this->assertSame($expected, $body);
  }

  /**
   * Test 'http' data fetcher (with auth) dies as expected when auth fails.
   */
  public function testFetchHttpAuthFailure(): void {
    $migration_config = $this->migrationConfiguration + $this->specificMigrationConfig;

    $plugin = new TestHttp($migration_config, $this->dataFetcherPluginId, $this->pluginDefinition);
    $plugin->mockHttpClient([[403, 'text/html', 'Forbidden']], $this->basicAuthenticator);

    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage('Error message: Client error: `GET http://example.org/http_fetcher_test` resulted in a `403 Forbidden');
    $plugin->getResponseContent($migration_config['urls'][0]);
  }

  /**
   * Test 'http' data fetcher (with auth) dies as expected when server down.
   */
  public function testFetchHttp500Error(): void {
    $migration_config = $this->migrationConfiguration + $this->specificMigrationConfig;

    $plugin = new TestHttp($migration_config, $this->dataFetcherPluginId, $this->pluginDefinition);
    $plugin->mockHttpClient([[500, 'text/html', 'Internal Server Error']], $this->basicAuthenticator);

    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage('GET http://example.org/http_fetcher_test` resulted in a `500 Internal Server Error');
    $plugin->getResponseContent($migration_config['urls'][0]);
  }

}

/**
 * Test class to mock an HTTP request.
 */
class TestHttp extends Http {

  /**
   * Mocked authenticator plugin.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  public $authenticator = NULL;

  /**
   * Mock the HttpClient, so we can control the request/response(s) etc.
   *
   * @param array $responses
   *   An array of responses (arrays), with each consisting of properties,
   *   ordered: response code, content-type and  response body.
   * @param object $authenticator
   *   Mocked authenticator plugin.
   */
  public function mockHttpClient(array $responses, object $authenticator = NULL) {
    // Set mocked authentication plugin to be used for the request auth plugin.
    $this->authenticator = $authenticator;

    $handler_responses = [];
    foreach ($responses as $response) {
      $handler_responses[] = new Response(
        $response[0],
        ['Content-Type' => $response[1]],
        $response[2]
      );
    }

    $mock = new MockHandler($handler_responses);
    $handler = HandlerStack::create($mock);

    $this->httpClient = new Client(['handler' => $handler]);
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    // Skip calling the Http() constructor (that sets the httpClient instance
    // variable via \Drupal which we don't want to do), but keep the call to its
    // parent class constructor. @see https://bugs.php.net/bug.php?id=42016
    DataFetcherPluginBase::__construct($configuration, $plugin_id, $plugin_definition);

    // This is what the parent class is doing, that we need to override.
    $this->httpClient = NULL;
  }

  /**
   * Override the parent::getAuthenticationPlugin()
   *
   * So we can mock the authentication plugin.
   *
   * @return \PHPUnit_Framework_MockObject_MockObject
   *   A mocked authentication plugin.
   */
  public function getAuthenticationPlugin() {
    if (!isset($this->authenticationPlugin)) {
      $this->authenticationPlugin = $this->authenticator;
    }

    return $this->authenticationPlugin;
  }

}
