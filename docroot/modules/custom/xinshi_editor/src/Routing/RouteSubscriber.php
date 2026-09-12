<?php

namespace Drupal\xinshi_editor\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('ckeditor_uploadimage.save')) {
      $route->setDefaults([
        '_controller' => '\Drupal\xinshi_editor\Controller\CKEditorUploadImageController::saveFile',
      ]);
    }
  }

}
