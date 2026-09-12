<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_wproofreader\Controller;

use Drupal\ckeditor5_premium_features_wproofreader\Form\SettingsForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint for handling requests to the webspellchecker api.
 */
final class WebSpellCheckerApiProxyController extends ControllerBase {

  const WEBSPELLCHECKER_ENDPOINT = 'https://svc.webspellchecker.net/spellcheck31/api';

  /**
   * Constructs the object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The http client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(private ClientInterface $httpClient, ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('config.factory'),
    );
  }

  /**
   * Builds the response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request from wproofreader plugin.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response from webspellchecker api.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function __invoke(Request $request): Response {
    $config = $this->configFactory->get(SettingsForm::WPROOFREADER_SETTINGS_ID);
    $customerId = ['customerid' => $config->get('service_id')];
    $requestBody = http_build_query($customerId) . '&' . $request->getContent();
    $headers = $request->headers;
    $origin = $headers->get('origin');
    $options = [
      'body' => $requestBody,
      'headers' => [
        'Content-Type' => 'text/plain',
        'Origin' => $origin,
      ],
    ];
    try {
      $response = $this->httpClient->request('POST', self::WEBSPELLCHECKER_ENDPOINT, $options);
      $body = $response->getBody()->getContents() ?? '';
      return new JsonResponse(json_decode($body), $response->getStatusCode());
    }
    catch (RequestException $exception) {
      $body = $exception->getResponse()->getBody()->getContents() ?? '';
      return new JsonResponse(json_decode($body), $exception->getCode());
    }
  }

}
