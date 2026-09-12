<?php

namespace Drupal\xls_serialization;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Adds 'xls' and 'xlsx' as known formats.
 */
class XlsSerializationServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->has('http_middleware.negotiation') && is_a($container->getDefinition('http_middleware.negotiation')->getClass(), '\Drupal\Core\StackMiddleware\NegotiationMiddleware', TRUE)) {
      $container->getDefinition('http_middleware.negotiation')
        ->addMethodCall('registerFormat', ['xls', ['application/vnd.ms-excel']])
        ->addMethodCall('registerFormat', [
          'xlsx',
          ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ]);
    }
  }

}
