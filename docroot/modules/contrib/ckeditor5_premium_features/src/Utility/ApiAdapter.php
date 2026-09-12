<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Utility;

use Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;
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
                              protected ConfigFactoryInterface $configFactory) {
  }

  /**
   * Call flush all collaborative sessions endpoint.
   */
  public function flushAllCollaborativeSessions(): void {
    $this->sendRequest('DELETE', 'collaborations');
  }

  /**
   * Call flush collaborative session endpoint.
   */
  public function flushCollaborativeSession(string $documentId): void {
    $this->sendRequest('DELETE', 'collaborations/' . $documentId . '?force=true');
  }

  /**
   * Call to get document details of collaborative session.
   *
   * @param string $documentId
   *   The document id.
   *
   * @return array
   *   Response of the request.
   */
  public function getCollaborativeSessionDetails(string $documentId): array {
    return $this->sendRequest('GET', 'collaborations/' . $documentId . '/details');
  }

  /**
   * Call to get the HTML contents of the document.
   *
   * @param string $documentId
   *   The document id.
   *
   * @return array
   *   Response of the request.
   */
  public function exportDocument(string $documentId): array {
    return $this->sendRequest('GET', 'collaborations/' . $documentId);
  }


  /**
   * Post editor bundle to the cloud server.
   *
   * @param array $config
   *   Editor config.
   * @param string $code
   *   The complete code of posted bundle.
   *
   * @return array
   *   Response of the request.
   */
  public function postEditor(array $config, string $code): array {
    $body = [
      'bundle' => $code,
      'config' => $config,
    ];
    $options = [
      'body' => json_encode($body),
    ];
    return $this->sendRequest('POST', 'editors', $options);
  }

  /**
   * Gets document suggestions.
   *
   * @param string $documentId
   *   The document id.
   * @param array $parameters
   *   Optional parameters.
   *
   * @return array
   *   Array of suggestions.
   */
  public function getDocumentSuggestions(string $documentId, array $parameters = []): array {
    $path = 'suggestions?document_id=' . $documentId;
    foreach ($parameters as $key => $parameter) {
      $path .= '&' . $key . '=' . $parameter;
    }
    $response = $this->sendRequest('GET', $path);
    return $response['data'] ?? [];
  }

  /**
   * Gets document suggestions.
   *
   * @param string $suggestionId
   *   The suggestion id.
   * @param string $documentId
   *   The document id.
   * @param array $parameters
   *   Optional request parameters.
   *
   * @return array
   *   Array of suggestions.
   */
  public function getSingleSuggestion(string $suggestionId, string $documentId, array $parameters = []): array {
    $path = 'suggestions/' . $suggestionId . '?document_id=' . $documentId;
    foreach ($parameters as $key => $parameter) {
      $path .= '&' . $key . '=' . $parameter;
    }
    $response = $this->sendRequest('GET', $path);
    return $response ?? [];
  }

  /**
   * Gets document comments.
   *
   * @param string $documentId
   *   The document id.
   * @param array $parameters
   *   Optional request parameters.
   *
   * @return array
   *   Array of comments.
   */
  public function getDocumentComments(string $documentId, array $parameters = []): array {
    $path = 'comments?document_id=' . $documentId;
    foreach ($parameters as $key => $parameter) {
      $path .= '&' . $key . '=' . $parameter;
    }
    $response = $this->sendRequest('GET', $path);
    return $response['data'] ?? [];
  }

  /**
   * Get single comment.
   *
   * @param string $commentId
   *   The comment id.
   * @param string $documentId
   *   The document id.
   * @param array $parameters
   *   Optional request parameters.
   *
   * @return array
   *   Array of comments.
   */
  public function getSingleComment(string $commentId, string $documentId, array $parameters = []): array {
    $path = 'comments/' . $commentId . '?document_id=' . $documentId;
    foreach ($parameters as $key => $parameter) {
      $path .= '&' . $key . '=' . $parameter;
    }
    $response = $this->sendRequest('GET', $path);
    return $response ?? [];
  }

  /**
   * Check the library version used in last session.
   *
   * @param string $documentId
   *   The document id.
   *
   * @return string|null
   *   Library version
   */
  public function getBundleVersion(string $documentId): ?string {
    $details = $this->getCollaborativeSessionDetails($documentId);
    if (!empty($details['current_session'])) {
      return $details['current_session']['bundle_version'];
    }
    return NULL;
  }

  /**
   * Validate session library version with used in Drupal.
   *
   * @param string $documentId
   *   The document id.
   * @param string $textFormat
   *   Text format used for the document.
   */
  public function validateBundleVersion(string $documentId, string $textFormat): void {
    $sessionVersion = $this->getBundleVersion($documentId);
    $config = $this->configFactory->get('ckeditor5_premium_features_realtime_collaboration.settings');

    if ($config->get('realtime_permissions')) {
      $bundles = $config->get('editor_bundles') ?? [];
      $bundleVersion = $bundles[$textFormat] ?? '';
    }
    else {
      $bundleVersion = $textFormat;
    }

    if (is_null($sessionVersion) || $sessionVersion === $bundleVersion) {
      return;
    }
    $this->flushCollaborativeSession($documentId);
  }

  /**
   * Base URL of API.
   *
   * @return string
   *   Base URL.
   */
  private function getBaseUrl(): String {
    return $this->settingsConfigHandler->getApiUrl();
  }

  /**
   * Generate signature for request.
   *
   * @param string $method
   *   Request method.
   * @param string $url
   *   Request url.
   * @param int $timestamp
   *   Timestamp.
   * @param string|array|NULL $body
   *   Request body.
   *
   * @return string
   *   Generated signature.
   */
  private function generateSignature(string $method, string $url, int $timestamp, string|array|NULL $body): String {
    $parsedUrl = parse_url($url);
    $uri = $parsedUrl['path'] ?? '';

    if (isset($parsedUrl['query'])) {
      $uri .= '?' . $parsedUrl['query'];
    }

    $data = $method . $uri . $timestamp;

    if ($body) {
      if (is_array($body)) {
        $data .= array_shift($body);
      }
      else {
        $data .= $body;
      }
    }
    $key = $this->settingsConfigHandler->getApiKey();

    if (!$key) {
      throw new ConfigException('Missing API Key');
    }
    return hash_hmac('sha256', $data, $key);
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
    $time = new \DateTime();
    $timestamp = (int) $time->format('Uv');
    $requestBody = $options['body'] ?? NULL;

    try {
      $signature = $this->generateSignature($method, $url, $timestamp, $requestBody);
    }
    catch (ConfigException $e) {
      if ($this->account->hasPermission('use ckeditor5 access token')) {
        Error::logException($this->getLogger('ckeditor5_premium_features'), $e, $e->getMessage());
      }
      return [];
    }

    $options['headers']['X-CS-Signature'] = $signature;
    $options['headers']['X-CS-Timestamp'] = $timestamp;

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

}
