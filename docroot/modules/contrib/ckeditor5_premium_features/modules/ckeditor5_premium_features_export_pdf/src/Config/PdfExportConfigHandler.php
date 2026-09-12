<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features_export_pdf\Config;

use Drupal\ckeditor5_premium_features\Config\ExportFeaturesConfigHandler;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

/**
 * Provides the utility service for handling the stored settings configuration.
 */

class PdfExportConfigHandler extends ExportFeaturesConfigHandler {

  /**
   * Constructs the handler.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(protected ConfigFactoryInterface $configFactory) {
    parent::__construct($configFactory);
    $this->config = $this->configFactory->get('ckeditor5_premium_features_export_pdf.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenUrl(): string {
    if ($this->getAccessKey() && $this->getEnvironmentId()) {
      return Url::fromRoute('ckeditor5_premium_features_export_pdf.endpoint.jwt_token')
        ->toString(TRUE)
        ->getGeneratedUrl();
    }

    return '';
  }
}
