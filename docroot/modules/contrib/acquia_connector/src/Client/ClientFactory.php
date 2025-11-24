<?php

namespace Drupal\acquia_connector\Client;

use Drupal\acquia_connector\AuthService;
use Drupal\acquia_connector\ConnectorException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Http\ClientFactory as HttpClientFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings as CoreSettings;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;

use Psr\Http\Message\ResponseInterface;

/**
 * Instantiates an Acquia Connector Client object.
 */
class ClientFactory {

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Acquia Connector Settings Config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Guzzle Client.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $httpClientFactory;

  /**
   * Drupal Time Service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The auth service.
   *
   * @var \Drupal\acquia_connector\AuthService
   */
  protected $authService;

  /**
   * The handler stack.
   *
   * @var \GuzzleHttp\HandlerStack
   */
  protected $stack;

  /**
   * ClientManagerFactory constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list.
   * @param \Drupal\Core\Http\ClientFactory $client_factory
   *   The date time service.
   * @param \Drupal\Component\Datetime\TimeInterface $date_time
   *   The date time service.
   * @param \Drupal\acquia_connector\AuthService $auth_service
   *   The auth service.
   * @param \GuzzleHttp\HandlerStack $stack
   *   The handler stack.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, ModuleExtensionList $module_list, HttpClientFactory $client_factory, TimeInterface $date_time, AuthService $auth_service, HandlerStack $stack) {
    $this->loggerFactory = $logger_factory;
    $this->moduleList = $module_list;
    $this->time = $date_time;
    $this->httpClientFactory = $client_factory;
    $this->authService = $auth_service;
    $this->stack = $stack;
  }

  /**
   * Get a client for Cloud API.
   *
   * @return \GuzzleHttp\Client
   *   The client.
   */
  public function getCloudApiClient(): Client {
    if (!$this->authService->getAccessToken()) {
      throw new ConnectorException("Missing access token.", 403);
    }

    // Do not influence global handler stack.
    $stack = clone $this->stack;
    $stack->after('prepare_body', Middleware::mapRequest(function (RequestInterface $request) {
      $access_data = $this->authService->getAccessToken();
      if (isset($access_data['access_token'])) {
        return $request->withHeader('Authorization', 'Bearer ' . $access_data['access_token']);
      }
      return $request;
    }));
    $stack->after('prepare_body', function (callable $next) {
      return function (RequestInterface $request, array $options = []) use ($next) {
        $access_data = $this->authService->getAccessToken();
        if (!isset($access_data['access_token'])) {
          return $next($request, $options);
        }

        if (!isset($options['retries'])) {
          $options['retries'] = 0;
        }
        return $next($request, $options)->then(
          function ($value) use ($next, $request, $options) {
            if ($options['retries'] > 0) {
              return $value;
            }
            if (!$value instanceof ResponseInterface) {
              return $value;
            }
            // The status should be 401 for an expired access token, but
            // the Cloud API returns 403. We handle both status codes.
            // @see https://www.rfc-editor.org/rfc/rfc6750#section-3.1.
            if (!in_array($value->getStatusCode(), [401, 403], TRUE)) {
              return $value;
            }
            return $next($request, $options);
          },
        );
      };
    });
    return $this->httpClientFactory->fromOptions([
      'base_uri' => (new Uri())
        ->withScheme('https')
        ->withHost(CoreSettings::get('acquia_connector.cloud_api_host', 'cloud.acquia.com')),
      'headers' => [
        'User-Agent' => $this->getClientUserAgent(),
        'Accept' => 'application/json, version=2',
      ],
      'handler' => $stack,
    ]);
  }

  /**
   * Returns Client's user agent.
   *
   * @return string
   *   User Agent.
   */
  protected function getClientUserAgent() {
    static $agent;
    if ($agent === NULL) {
      // Find out the module version in use.
      $module_info = $this->moduleList->getExtensionInfo('acquia_connector');
      $module_version = $module_info['version'] ?? '0.0.0';
      $drupal_version = $module_info['core'] ?? '0.0.0';

      $agent = 'AcquiaConnector/' . $drupal_version . '-' . $module_version;
    }
    return $agent;
  }

}
