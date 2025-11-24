<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend;
use Drupal\search_api\Entity\Server;

/**
 * Test the enable_debug_logging configuration variables.
 *
 * @group elasticsearch_connector
 */
class DebugLoggingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'search_api',
    'elasticsearch_connector',
    'dblog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('dblog', ['watchdog']);
    $this->installSchema('search_api', ['search_api_item']);
    $this->installConfig([
      'field',
      'search_api',
      'elasticsearch_connector',
      'dblog',
    ]);
  }

  /**
   * Test that logs are written when debug mode is on.
   */
  public function testLogsWhenDebugModeOn(): void {
    $this->clearLogMessages();
    $server = $this->setEnableDebugLogging(TRUE);

    $this->makeQueryToElasticSearchBackend($server);

    $this->assertLogMessageLike('Request: GET http://%/_health_report');
    $this->assertLogMessageLike('Headers: @"Host":["%"]%');
    $this->assertLogMessageLike('Response (retry 0): 200');
    $this->assertLogMessageLike('Headers: @"x-elastic-product":["Elasticsearch"]%');
    $this->assertLogMessageLike('Response time in % sec');
  }

  /**
   * Test that no logs are written when debug mode is off.
   */
  public function testNoLogsWhenDebugModeOff(): void {
    $this->clearLogMessages();
    $server = $this->setEnableDebugLogging(FALSE);

    $this->makeQueryToElasticSearchBackend($server);

    $this->assertNoLogMessageLike('Request: GET http://%/_health_report');
    $this->assertNoLogMessageLike('Headers: @"Host":["%"]%');
    $this->assertNoLogMessageLike('Response (retry 0): 200');
    $this->assertNoLogMessageLike('Headers: @"x-elastic-product":["Elasticsearch"]%');
    $this->assertNoLogMessageLike('Response time in % sec');
  }

  /**
   * Assert that a message was logged.
   *
   * @param string $messageLike
   *   Part of a message to check for.
   *
   * @throws \Exception
   *   Throws an Exception if something does not work, or the assertion fails.
   */
  protected function assertLogMessageLike(string $messageLike): void {
    $this->assertNotEquals(0, $this->numberLogMessagesLike($messageLike));
  }

  /**
   * Assert that a message was not logged.
   *
   * @param string $messageLike
   *   Part of a message to check for.
   *
   * @throws \Exception
   *   Throws an Exception if something does not work, or the assertion fails.
   */
  protected function assertNoLogMessageLike(string $messageLike): void {
    $this->assertEquals(0, $this->numberLogMessagesLike($messageLike));
  }

  /**
   * Clear log messages to prepare for a test.
   */
  protected function clearLogMessages(): void {
    // Should do what \Drupal\dblog\Form\DblogClearLogConfirmForm::submitForm()
    // does.
    $this->container->get('database')->truncate('watchdog')->execute();
  }

  /**
   * Make a query to the ElasticSearch Backend.
   *
   * @param \Drupal\search_api\Entity\Server $server
   *   The server to make a query to.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Throws a Search API exception if anything goes wrong.
   * @throws \Elastic\Transport\Exception\NoNodeAvailableException
   *   Throws a No Node Available exception if all the ElasticSearch hosts are
   *   offline.
   * @throws \Elastic\Elasticsearch\Exception\ClientResponseException
   *   Throws a Client Response exception if the status code of response is 4xx.
   * @throws \Elastic\Elasticsearch\Exception\ServerResponseException
   *   Throws a Server Response exception if the status code of response is 5xx.
   * @throws \RuntimeException
   *    Throws a Run-time exception if the Search API Server does not have an
   *    ElasticSearchBackend.
   */
  protected function makeQueryToElasticSearchBackend(Server $server): void {
    // We run a health check here, but it could be anything that causes network
    // traffic to go to the backend. Just make sure to update the
    // assertLogMessageLike() and assertNoLogMessageLike() lines above if you do
    // something else.
    $serverBackend = $server->getBackend();
    if (!$serverBackend instanceof ElasticSearchBackend) {
      throw new \RuntimeException('Cannot test a Search API server that is not an ElasticSearchBackend.');
    }
    $serverBackend->getClient()->healthReport();
  }

  /**
   * Query the database to see if there is a log message LIKE the given string.
   *
   * @param string $messageLike
   *   The string to match, as in an SQL LIKE query, i.e.: use '%' to match any
   *   number of characters, and '_' to match a single character.
   *
   * @return int
   *   The number of log messages of type 'elasticsearch_connector_client' whose
   *   'message' is LIKE the given $messageLike.
   *
   * @throws \Exception
   *   Throws an Exception if there is an error retrieving the number of log
   *   messages that match the given query.
   */
  protected function numberLogMessagesLike(string $messageLike): int {
    return (int) $this->container->get('database')
      ->select('watchdog', 'w')
      ->condition('type', 'elasticsearch_connector_client')
      ->condition('message', $messageLike, 'LIKE')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Set the debug logging option on the test ElasticSearch server.
   *
   * @param bool $enableDebugLogging
   *   TRUE if debug logging should be enabled; FALSE if it should be disabled.
   *
   * @return \Drupal\search_api\Entity\Server
   *   A test ElasticSearch server connection.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Throws an Entity Storage exception if the test ElasticSearch server's
   *   configuration cannot be saved.
   */
  protected function setEnableDebugLogging(bool $enableDebugLogging): Server {
    // Note an ElasticSearch cluster should should be accessible at the 'url'
    // below, during the test. It's currently set to the URL of the test server
    // set up in the '.with-elasticsearch' section of '/.gitlab-ci.yml'.
    $newServer = Server::create([
      'id' => $this->randomMachineName(),
      'backend' => 'elasticsearch',
      'backend_config' => [
        'connector' => 'standard',
        'connector_config' => [
          'url' => 'http://elasticsearch:9200',
          'enable_debug_logging' => $enableDebugLogging,
        ],
      ],
    ]);
    $newServer->save();

    return $newServer;
  }

}
