<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai\Utility;

use Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;
use Drupal\ckeditor5_premium_features_ai\Service\AiConfigHandler;
use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * Provides the CKEditor API connection.
 */
class ApiAdapter {

  use LoggerChannelTrait;
  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * Creates the Track Changes plugin instance.
   *
   * @param \Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface $settingsConfigHandler
   *   The settings configuration handler.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(protected SettingsConfigHandlerInterface $settingsConfigHandler,
                              protected ClientInterface $http_client,
                              protected AccountProxyInterface $account,
                              protected ConfigFactoryInterface $configFactory,
                              protected CacheBackendInterface $cache,
                              protected AiConfigHandler $aiConfigHandler) {
  }

  /**
   * Call to get available models.
   *
   * @param string $version
   *   Compatibility version.
   *
   * @return array
   *   Response of the request.
   */
  public function getModels(string $version): array {
    $cid = "ckeditor5_premium_features_ai:models:$version";

    if ($cache = $this->cache->get($cid)) {
      $data = $cache->data;
      return is_array($data) ? $data : (array) $data;
    }

    $result = $this->sendRequest('GET', 'models/' . $version);
    // The successful response doesn't have code, only a decoded response body.
    if (isset($result['code'])) {
      return [];
    }

    // For the clarity in communication, change the "Agent" model to "Auto"
    foreach ($result['items'] as &$item) {
      if ($item['name'] === 'Agent') {
        $item['name'] = 'Auto';
        $item['provider'] = 'Auto';
        $item['description'] = $this->t('Uses a pre-defined set of AI models selected by the CKEditor team for optimizing speed, quality, and cost.');
        break;
      }
    }

    // Cache for 24 hours.
    $this->cache->set($cid, $result, time() + 24 * 60 * 60, [
      'ckeditor5_premium_features_ai:models',
    ]);

    return $result;
  }

  /**
   * Base URL of API.
   *
   * @return string
   *   Base URL.
   */
  private function getBaseUrl(): string {
    $base = rtrim($this->aiConfigHandler->getServiceUrl(), '/');
    return $base . '/';
  }

  /**
   * Send request to API.
   *
   * @param string $method
   *   Request method.
   * @param string $path
   *   Request path.
   *
   * @return array
   *   Result of sent request.
   */
  private function sendRequest(string $method, string $path, array $options = []): array {
    $url = $this->getBaseUrl() . $path;

    $options['headers']['Authorization'] = 'Bearer ' . $this->generateToken();

    try {
      $request = $this->http_client->request($method, $url, $options);
    } catch (ConnectException $e) {
      Error::logException($this->getLogger('ckeditor5_premium_features'), $e, $e->getMessage());
      return ['code' => $e->getCode(), 'message' => $e->getMessage()];
    } catch (RequestException $e) {
      $msg = $e->getResponse()?->getBody()?->getContents() ?? '';
      Error::logException($this->getLogger('ckeditor5_premium_features'), $e, $msg);
      return ['code' => $e->getCode(), 'message' => $msg];
    } catch (GuzzleException $e) {
      Error::logException($this->getLogger('ckeditor5_premium_features'), $e, $e->getMessage());
      return ['code' => $e->getCode(), 'message' => $e->getMessage()];
    }

    $response = $request->getBody()->getContents();

    if (empty($response)) {
      return ['code' => $request->getStatusCode()];
    }
    $decodedJson = Json::decode($response);
    if (!empty($decodedJson)) {
      return (array) $decodedJson;
    }
    return [$response];
  }

  private function generateToken(): string {
    $payload = [
      'aud' => $this->settingsConfigHandler->getEnvironmentId(),
      'iat' => time(),
      'sub' => $this->account->id(),
      'auth' => [
        'ai' => [
          'permissions' => ['ai:models:*']
        ]
      ],
    ];

    return JWT::encode($payload, $this->settingsConfigHandler->getAccessKey(), 'HS512');
  }

}
