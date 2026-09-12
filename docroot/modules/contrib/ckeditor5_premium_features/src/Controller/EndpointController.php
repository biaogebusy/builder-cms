<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Controller;

use Drupal\ckeditor5_premium_features\Generator\TokenGeneratorInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the controller for endpoints required by the premium features.
 */
class EndpointController extends ControllerBase {

  /**
   * Constructs the endpoint controller instance.
   *
   * @param \Drupal\ckeditor5_premium_features\Generator\TokenGeneratorInterface $tokenGenerator
   *   The token generator service.
   */
  public function __construct(protected TokenGeneratorInterface $tokenGenerator) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ckeditor5_premium_features.token_generator'),
    );
  }

  /**
   * Handle the JWT token endpoint request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response containing JWT token.
   */
  public function jwtToken(): AjaxResponse {
    $filterFormatId = \Drupal::request()->query->get('format');

    $response = new AjaxResponse();

    $token = $this->tokenGenerator->generate($filterFormatId);
    $response->setContent($token);

    return $response;
  }

}
