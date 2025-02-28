<?php

namespace Drupal\acquia_connector;

use Drupal\acquia_connector\Helper\Storage;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Exception\RequestException;

/**
 * Acquia connector client.
 *
 * @package Drupal\acquia_connector
 */
class Client {

  use LoggerChannelTrait;
  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * Request headers.
   *
   * @var array
   */
  protected $headers;

  /**
   * Acquia SPI server.
   *
   * @var string
   */
  protected $server;

  /**
   * Config Factory Interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Client constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config Factory Interface.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   */
  public function __construct(ConfigFactoryInterface $configFactory, StateInterface $state, TimeInterface $time) {
    $this->configFactory = $configFactory;
    $this->config = $configFactory->get('acquia_connector.settings');
    $this->server = $this->config->get('spi.server');
    $this->state = $state;
    $this->time = $time;

    $this->headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
    ];

    $this->client = \Drupal::service('http_client_factory')->fromOptions(
      [
        'verify' => (boolean) $this->config->get('spi.ssl_verify'),
        'http_errors' => FALSE,
      ]
    );
  }

  /**
   * Get account settings to use for creating request authorizations.
   *
   * @param string $email
   *   Acquia account email.
   * @param string $password
   *   Plain-text password for Acquia account. Will be hashed for communication.
   *
   * @return array|false
   *   Credentials array or FALSE.
   *
   * @throws \Drupal\acquia_connector\ConnectorException
   */
  public function getSubscriptionCredentials($email, $password) {
    $body = ['email' => $email];
    $authenticator = $this->buildAuthenticator($email, \Drupal::time()->getRequestTime(), ['rpc_version' => ACQUIA_CONNECTOR_ACQUIA_SPI_DATA_VERSION]);
    $data = [
      'body' => $body,
      'authenticator' => $authenticator,
    ];

    // Don't use nspiCall() - key is not defined yet.
    $communication_setting = $this->request('POST', '/agent-api/subscription/communication', $data);
    if ($communication_setting) {
      $crypt_pass = new CryptConnector($communication_setting['algorithm'], $password, $communication_setting['hash_setting'], $communication_setting['extra_md5']);
      $pass = $crypt_pass->cryptPass();

      $body = [
        'email' => $email,
        'pass' => $pass,
        'rpc_version' => ACQUIA_CONNECTOR_ACQUIA_SPI_DATA_VERSION,
      ];
      $authenticator = $this->buildAuthenticator($pass, \Drupal::time()->getRequestTime(), ['rpc_version' => ACQUIA_CONNECTOR_ACQUIA_SPI_DATA_VERSION]);
      $data = [
        'body' => $body,
        'authenticator' => $authenticator,
      ];

      // Don't use nspiCall() - key is not defined yet.
      $response = $this->request('POST', '/agent-api/subscription/credentials', $data);
      if ($response['body']) {
        return $response['body'];
      }
    }
    return FALSE;
  }

  /**
   * Get Acquia subscription from Acquia.
   *
   * @param string $id
   *   Acquia Subscription ID.
   * @param string $key
   *   Acquia Subscription key.
   * @param array $body
   *   Optional.
   *
   * @return array|false
   *   Acquia Subscription array or FALSE.
   *
   * @throws \Exception
   */
  public function getSubscription($id, $key, array $body = []) {
    $body['identifier'] = $id;
    // There is an identifier and key, so attempt communication.
    $subscription = [];
    $this->state->set('acquia_subscription_data.timestamp', \Drupal::time()->getRequestTime());

    // Include version number information.
    acquia_connector_load_versions();
    if (IS_ACQUIA_DRUPAL) {
      $body['version']  = ACQUIA_DRUPAL_VERSION;
      $body['series']   = ACQUIA_DRUPAL_SERIES;
      $body['branch']   = ACQUIA_DRUPAL_BRANCH;
      $body['revision'] = ACQUIA_DRUPAL_REVISION;
    }

    if ($search_info = $this->getSearchModulesData()) {
      $body['search_version'] = $search_info;
    }

    try {
      $response = $this->nspiCall('/agent-api/subscription', $body);
      if (!empty($response['result']['authenticator']) && $this->validateResponse($key, $response['result'], $response['authenticator'])) {
        $subscription += $response['result']['body'];
        // Subscription activated.
        if (is_numeric($this->state->get('acquia_subscription_data')) && is_array($response['result']['body'])) {
          $this->state->set('acquia_subscription_data', $subscription);
        }
        return $subscription;
      }
    }
    catch (ConnectorException $e) {
      $this->messenger()->addError($this->t('Error occurred while retrieving Acquia subscription information. See logs for details.'));
      if ($e->isCustomized()) {
        $this->getLogger('acquia connector')
          ->error($e->getCustomMessage() . '. Response data: @data', ['@data' => json_encode($e->getAllCustomMessages())]);
      }
      else {
        $this->getLogger('acquia connector')->error($e->getMessage());
      }
      throw $e;
    }

    return FALSE;
  }

  /**
   * Get information on Acquia Search modules.
   *
   * @return array|null
   *   Versions for enabled search modules, NULL otherwise.
   */
  protected function getSearchModulesData(): ?array {

    // This is the only search module compatible with this version of Acquia
    // Connector for now.
    if (!\Drupal::moduleHandler()->moduleExists('acquia_search')) {
      return NULL;
    }

    // Include Acquia Search Solr for Search API module version number.
    $modules = ['acquia_search', 'search_api_solr'];
    $result = [];

    foreach ($modules as $name) {
      $extension_list = \Drupal::service('extension.list.module');
      $info = $extension_list->getExtensionInfo($name);
      // Send the version, or at least the core compatibility as a fallback.
      $result[$name] = isset($info['version']) ? (string) $info['version'] : (string) $info['core_version_requirement'];
    }

    return $result;

  }

  /**
   * Get Acquia subscription from Acquia.
   *
   * @param string $id
   *   Acquia Subscription ID.
   * @param string $key
   *   Acquia Subscription key.
   * @param array $body
   *   Optional.
   *
   * @return array|false
   *   Response result or FALSE.
   */
  public function sendNspi($id, $key, array $body = []) {
    $this->getLogger('acquia connector')->error("Acquia Insight is EOL. Please remove sendNspi from your modules/scripts.");
    return FALSE;
  }

  /**
   * Get SPI definition.
   *
   * @param string $apiEndpoint
   *   API endpoint.
   *
   * @return array|bool
   *   Definition array or FALSE.
   */
  public function getDefinition($apiEndpoint) {
    try {
      return $this->request('GET', $apiEndpoint, []);
    }
    catch (ConnectorException $e) {
      $this->getLogger('acquia connector')->error($e->getCustomMessage());
    }
    return FALSE;
  }

  /**
   * Validate the response authenticator.
   *
   * @param string $key
   *   Acquia Subscription key.
   * @param array $response
   *   Response.
   * @param array $requestAuthenticator
   *   Authenticator array.
   *
   * @return bool
   *   TRUE if valid response, FALSE otherwise.
   */
  protected function validateResponse($key, array $response, array $requestAuthenticator) {
    $responseAuthenticator = $response['authenticator'];
    if (!($requestAuthenticator['nonce'] === $responseAuthenticator['nonce'] && $requestAuthenticator['time'] < $responseAuthenticator['time'])) {
      return FALSE;
    }
    $hash = $this->hash($key, $responseAuthenticator['time'], $responseAuthenticator['nonce']);
    return ($hash === $responseAuthenticator['hash']);
  }

  /**
   * Create and send a request.
   *
   * @param string $method
   *   Method to call.
   * @param string $path
   *   Path to call.
   * @param array $data
   *   Data to send.
   *
   * @return array|false
   *   Response array or FALSE.
   *
   * @throws ConnectorException
   */
  protected function request($method, $path, array $data) {
    $uri = $this->server . $path;
    $options = [
      'headers' => $this->headers,
      'body' => Json::encode($data),
    ];

    try {
      switch ($method) {
        case 'GET':
          $response = $this->client->get($uri);
          $status_code = $response->getStatusCode();
          $stream_size = $response->getBody()->getSize();
          $data = Json::decode($response->getBody()->read($stream_size));

          if ($status_code < 200 || $status_code > 299) {
            if (!$data) {
              throw new ConnectorException("Invalid response", $status_code);
            }

            throw new ConnectorException($data['message'], $data['code'], $data);
          }

          return $data;

        case 'POST':
          $response = $this->client->post($uri, $options);
          $status_code = $response->getStatusCode();
          $stream_size = $response->getBody()->getSize();
          $data = Json::decode($response->getBody()->read($stream_size));

          if ($status_code < 200 || $status_code > 299) {
            if (!$data) {
              throw new ConnectorException("Invalid response", $status_code);
            }

            throw new ConnectorException($data['message'], $data['code'], $data);
          }

          return $data;

      }
    }
    catch (RequestException $e) {
      throw new ConnectorException($e->getMessage(), $e->getCode());
    }

    return FALSE;
  }

  /**
   * Build authenticator to sign requests to the Acquia.
   *
   * @param string $key
   *   Secret key to use for signing the request.
   * @param int $request_time
   *   Such as from \Drupal::time()->getRequestTime().
   * @param array $params
   *   Optional parameters to include.
   *   'identifier' - Network Identifier.
   *
   * @return array
   *   Authenticator array.
   */
  protected function buildAuthenticator($key, int $request_time, array $params = []) {
    $authenticator = [];
    if (isset($params['identifier'])) {
      // Put Subscription ID in authenticator but do not use in hash.
      $authenticator['identifier'] = $params['identifier'];
      unset($params['identifier']);
    }
    $nonce = $this->getNonce();
    $authenticator['time'] = $request_time;
    $authenticator['hash'] = $this->hash($key, $request_time, $nonce);
    $authenticator['nonce'] = $nonce;

    return $authenticator;
  }

  /**
   * Calculates a HMAC-SHA1 according to RFC2104.
   *
   * @param string $key
   *   Key.
   * @param int $time
   *   Timestamp.
   * @param string $nonce
   *   Nonce.
   *
   * @return string
   *   HMAC-SHA1 hash.
   *
   * @see http://www.ietf.org/rfc/rfc2104.txt
   */
  protected function hash($key, $time, $nonce) {
    $string = $time . ':' . $nonce;
    return CryptConnector::acquiaHash($key, $string);
  }

  /**
   * Get a random base 64 encoded string.
   *
   * @return string
   *   Random base 64 encoded string.
   */
  protected function getNonce() {
    return Crypt::hashBase64(uniqid(mt_rand(), TRUE) . random_bytes(55));
  }

  /**
   * Prepare and send a REST request to Acquia with an authenticator.
   *
   * @param string $method
   *   HTTP method.
   * @param array $params
   *   Parameters to pass to the NSPI.
   * @param string $key
   *   Acquia Key or NULL.
   *
   * @return array
   *   NSPI response.
   *
   * @throws ConnectorException
   */
  public function nspiCall($method, array $params, $key = NULL) {
    // This has reached EOL after Wed Mar 01 2023.
    $eol_date = new DrupalDateTime('Wed Mar 01 2023');
    $current_date = DrupalDateTime::createFromTimestamp($this->time->getRequestTime());
    if ($current_date >= $eol_date) {
      throw new ConnectorException(
        'Acquia Connector 3.x has reached end of life, please upgrade to 4.x to continue using it.',
        500
      );
    }

    if (empty($key)) {
      $storage = new Storage();
      $key = $storage->getKey();
    }
    // Used in HMAC validation.
    $params['rpc_version'] = ACQUIA_CONNECTOR_ACQUIA_SPI_DATA_VERSION;
    $ip = \Drupal::request()->server->get('SERVER_ADDR', '');
    $host = \Drupal::request()->server->get('HTTP_HOST', '');
    $ssl = \Drupal::request()->isSecure();
    $data = [
      'authenticator' => $this->buildAuthenticator($key, $this->time->getRequestTime(), $params),
      'ip' => $ip,
      'host' => $host,
      'ssl' => $ssl,
      'body' => $params,
    ];

    $data['result'] = $this->request('POST', $method, $data);
    return $data;
  }

}
