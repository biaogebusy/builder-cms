<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_import_word\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * {@inheritDoc}
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritDoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $cke5UploadImageRoute = $collection->get('ckeditor5.upload_image');
    $cke5UploadImageRoute->setDefaults([
      '_controller' => 'Drupal\ckeditor5_premium_features_import_word\Controller\CKEditor5ImportWordImageUploadController::upload',
    ]);
  }

}
