<?php

declare(strict_types = 1);

namespace Drupal\sms\Routing\Enhancer;

use Drupal\Core\Routing\EnhancerInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\sms\Entity\SmsGateway;
use Symfony\Component\HttpFoundation\Request;

/**
 * Route enhancer for SMS Framework.
 */
class SmsRouteEnhancer implements EnhancerInterface {

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    $route = $defaults[RouteObjectInterface::ROUTE_OBJECT];
    if (!$route->hasDefault('_sms_gateway_push_endpoint')) {
      return $defaults;
    }

    $gateway_id = $defaults['_sms_gateway_push_endpoint'];
    $defaults['sms_gateway'] = SmsGateway::load($gateway_id);
    return $defaults;
  }

}
