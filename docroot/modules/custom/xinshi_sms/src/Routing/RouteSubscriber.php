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
    // oauth2_token.authorize is deliberately left alone: the decoupled client
    // carries its own SMS login form (see the /api/v3/otp/* resources), so an
    // anonymous authorize request belongs on core's /user/login — the route
    // simple_oauth points at by default, and the only login path other modules
    // (require_login, for one) reliably treat as public.
  }
}
