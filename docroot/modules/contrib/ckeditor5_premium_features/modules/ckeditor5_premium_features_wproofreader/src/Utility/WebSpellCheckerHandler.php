<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_wproofreader\Utility;

use Drupal\ckeditor5_premium_features_wproofreader\Controller\WebSpellCheckerApiProxyController;
use Drupal\Core\Routing\RequestContext;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines WebSpellChecker validation handlers.
 */
class WebSpellCheckerHandler implements WebSpellCheckerHandlerInterface {

  /**
   * WebSpellCheckerHandler constructor.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The http client.
   * @param \Drupal\Core\Routing\RequestContext $requestContext
   *   The request context.
   */
  public function __construct(private ClientInterface $httpClient, private RequestContext $requestContext) {
  }

  /**
   * {@inheritdoc}
   */
  public function validateServiceId(string $serviceId): JsonResponse {
    $response = $this->getInfo($serviceId);
    if ($response instanceof RequestExceptionInterface) {
      $responseMessage = ['valid' => FALSE];
      if (str_contains($response->getMessage(), 'Word usage quota')) {
        $responseMessage['usage_limit_error'] = TRUE;
      }
      return new JsonResponse($responseMessage, $response->getCode());
    }
    return new JsonResponse(['valid' => $response->getStatusCode() === Response::HTTP_OK], $response->getStatusCode());
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableLanguages(string $serviceId):array {
    $response = $this->getInfo($serviceId);
    if ($response instanceof ResponseInterface) {
      $responseMessage = (string) $response->getBody();
      $content = json_decode($responseMessage, TRUE);
      if (!empty($content['langList'])) {
        $ltr = $content['langList']['ltr'] ?? [];
        $rtl = $content['langList']['rtl'] ?? [];
        return array_merge($ltr, $rtl);
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isServiceIdValid(string $serviceId): bool {
    $response = $this->getInfo($serviceId);
    if ($response instanceof RequestExceptionInterface) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Send request to WebSpellChecker endpoint.
   *
   * @param string $serviceId
   *   The WSC Service ID.
   *
   * @return \GuzzleHttp\Exception\RequestException|\Exception|ResponseInterface
   *   Response from WSC endpoint.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function getInfo(string $serviceId): RequestException|\Exception|ResponseInterface {
    $customerId = ['customerid' => $serviceId];
    $requestBody = http_build_query($customerId) . '&format=json&app_type=proofreader_ck5&cmd=get_info';
    $options = [
      'body' => $requestBody,
      'headers' => [
        'Content-Type' => 'text/plain',
        'Origin' => $this->requestContext->getCompleteBaseUrl(),
      ],
    ];
    try {
      return $this->httpClient->request('POST', WebSpellCheckerApiProxyController::WEBSPELLCHECKER_ENDPOINT, $options);
    }
    catch (RequestException $exception) {
      return $exception;
    }
  }

}
