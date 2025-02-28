<?php

namespace Drupal\xinshi_sms\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('user.pass')) {
      $config = \Drupal::configFactory()->get('xinshi_sms.settings');
      if ($config->get('override_reset_pass')) {
        $route->setDefault('_form', '\Drupal\xinshi_sms\Form\FindPasswordForm');
      }
    }
  }
}
