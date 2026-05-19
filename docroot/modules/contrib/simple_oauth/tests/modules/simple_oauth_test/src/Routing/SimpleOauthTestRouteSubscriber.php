<?php

namespace Drupal\simple_oauth_test\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\simple_oauth_test\Controller\TestOauth2Token;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides a route subscriber for Simple OAuth Test.
 */
class SimpleOauthTestRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $token_route = $collection->get('oauth2_token.token');
    if ($token_route) {
      $token_route->setDefault('_controller', TestOauth2Token::class . '::token');
    }
  }

}
