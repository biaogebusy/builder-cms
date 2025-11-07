<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Component\Serialization\Json;
use Drupal\entity_share_client\Entity\RemoteInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;

/**
 * Service to wrap requests logic.
 *
 * @package Drupal\entity_share_client\Service
 */
class RemoteManager implements RemoteManagerInterface {

  /**
   * A constant to document the call for a standard client.
   *
   * @var bool
   */
  const STANDARD_CLIENT = FALSE;

  /**
   * A constant to document the call for a JSON:API client.
   *
   * @var bool
   */
  const JSON_API_CLIENT = TRUE;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * HTTP clients prepared per remote.
   *
   * @var \GuzzleHttp\ClientInterface[]
   */
  protected $httpClients = [];

  /**
   * HTTP clients prepared for JSON:API endpoints per remotes.
   *
   * @var \GuzzleHttp\ClientInterface[]
   */
  protected $jsonApiHttpClients = [];

  /**
   * Data provided by entity_share entry point per remote.
   *
   * @var array
   */
  protected $remoteInfos = [];

  /**
   * RemoteManager constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    LoggerInterface $logger
  ) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function request(RemoteInterface $remote, $method, $url, array $options = []) {
    $client = $this->getHttpClient($remote);
    return $this->doRequest($client, $method, $url, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function jsonApiRequest(RemoteInterface $remote, $method, $url, array $options = []) {
    $client = $this->getJsonApiHttpClient($remote);
    return $this->doRequest($client, $method, $url, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getChannelsInfos(RemoteInterface $remote, array $options = []) {
    $remote_id = $remote->id();
    if (!isset($this->remoteInfos[$remote_id])) {
      $response = $this->jsonApiRequest($remote, 'GET', 'entity_share', $options);
      $json = [
        'data' => [
          'channels' => [],
          'field_mappings' => [],
        ],
      ];
      if (!is_null($response)) {
        $json = Json::decode((string) $response->getBody());
      }
      $this->remoteInfos[$remote_id] = $json['data'];
    }

    return $this->remoteInfos[$remote_id]['channels'];
  }

  /**
   * {@inheritdoc}
   */
  public function getfieldMappings(RemoteInterface $remote) {
    $remote_id = $remote->id();
    if (!isset($this->remoteInfos[$remote_id])) {
      $response = $this->jsonApiRequest($remote, 'GET', 'entity_share');
      $json = Json::decode((string) $response->getBody());
      $this->remoteInfos[$remote_id] = $json['data'];
    }

    return $this->remoteInfos[$remote_id]['field_mappings'];
  }

  /**
   * Prepares a client object from the auth plugin.
   *
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The remote website on which to perform the request.
   *
   * @return \GuzzleHttp\Client
   *   The configured client.
   */
  protected function getHttpClient(RemoteInterface $remote) {
    $remote_id = $remote->id();
    if (!isset($this->httpClients[$remote_id])) {
      $this->httpClients[$remote_id] = $remote->getHttpClient(self::STANDARD_CLIENT);
    }

    return $this->httpClients[$remote_id];
  }

  /**
   * Prepares a client object from the auth plugin.
   *
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The remote website on which to perform the request.
   *
   * @return \GuzzleHttp\Client
   *   The configured client.
   */
  protected function getJsonApiHttpClient(RemoteInterface $remote) {
    $remote_id = $remote->id();
    if (!isset($this->jsonApiHttpClients[$remote_id])) {
      $this->jsonApiHttpClients[$remote_id] = $remote->getHttpClient(self::JSON_API_CLIENT);
    }

    return $this->jsonApiHttpClients[$remote_id];
  }

  /**
   * Performs a HTTP request.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The client which will do the request.
   * @param string $method
   *   HTTP method.
   * @param string $url
   *   URL to request.
   * @param array $options
   *   Some options to alter the behavior.
   *
   * @return \Psr\Http\Message\ResponseInterface|null
   *   The response or NULL if a problem occurred.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  protected function doRequest(ClientInterface $client, $method, $url, array $options = []) {
    $log_variables = [
      '@url' => $url,
      '@method' => $method,
    ];

    try {
      return $client->request($method, $url);
    }
    catch (ClientException $exception) {
      $log_variables['@exception_message'] = $exception->getMessage();
      $this->logger->error('Client exception when requesting the URL: @url with method @method: @exception_message', $log_variables);
    }
    catch (ServerException $exception) {
      $log_variables['@exception_message'] = $exception->getMessage();
      $this->logger->error('Server exception when requesting the URL: @url with method @method: @exception_message', $log_variables);
    }
    catch (GuzzleException $exception) {
      $log_variables['@exception_message'] = $exception->getMessage();
      $this->logger->error('Guzzle exception when requesting the URL: @url with method @method: @exception_message', $log_variables);
    }
    catch (\Exception $exception) {
      $log_variables['@exception_message'] = $exception->getMessage();
      $this->logger->error('Error when requesting the URL: @url with method @method: @exception_message', $log_variables);
    }

    if (isset($options['rethrow']) && $options['rethrow'] && isset($exception)) {
      throw $exception;
    }

    return NULL;
  }

}
