<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_export_pdf\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ckeditor5_premium_features\Controller\EndpointController as MainEndpointController;

/**
 * Provides the controller for endpoints required by the pdf export feature.
 */
class EndpointController extends MainEndpointController {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ckeditor5_premium_features_export_pdf.token_generator'),
    );
  }

}
