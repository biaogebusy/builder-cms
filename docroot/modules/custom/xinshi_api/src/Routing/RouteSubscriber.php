<?php

namespace Drupal\xinshi_api\Routing;

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
    if ($route = $collection->get('user.login.http')) {
      $route->setDefaults([
        '_controller' => '\Drupal\xinshi_api\Controller\UserAuthenticationController::login',
      ]);
    }

    // Add oauth2 authentication provider to all REST resource routes.
    foreach ($collection->all() as $name => $route) {
      if (strpos($name, 'rest.') === 0) {
        $auth = $route->getOption('_auth') ?: [];
        if (!in_array('oauth2', $auth)) {
          $auth[] = 'oauth2';
          $route->setOption('_auth', $auth);
        }
      }
    }
  }

}
