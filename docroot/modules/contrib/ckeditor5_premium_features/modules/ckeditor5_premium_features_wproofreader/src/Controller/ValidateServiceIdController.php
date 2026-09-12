<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_wproofreader\Controller;

use Drupal\ckeditor5_premium_features_wproofreader\Form\SettingsForm;
use Drupal\ckeditor5_premium_features_wproofreader\Utility\WebSpellCheckerHandler;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint for validating WProofreader service id.
 */
final class ValidateServiceIdController extends ControllerBase {

  /**
   * Constructs the object.
   *
   * @param \Drupal\ckeditor5_premium_features_wproofreader\Utility\WebSpellCheckerHandler $webSpellCheckerHandler
   *   WebSpellChecker handler service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(private WebSpellCheckerHandler $webSpellCheckerHandler, ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ckeditor5_premium_features_wproofreader.wsc_handler'),
      $container->get('config.factory'),
    );
  }

  /**
   * Sends request to WebSpellCheckerApi to validate service id.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Json response with information if the service id is valid.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function __invoke(Request $request): Response {
    $config = $this->configFactory->get(SettingsForm::WPROOFREADER_SETTINGS_ID);
    $serviceId = $config->get('service_id');
    return $this->webSpellCheckerHandler->validateServiceId($serviceId);
  }

}
