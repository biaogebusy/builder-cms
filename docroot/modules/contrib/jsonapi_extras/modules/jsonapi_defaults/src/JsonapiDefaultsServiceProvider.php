<?php

namespace Drupal\jsonapi_defaults;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the jsonapi normalizer service.
 */
class JsonapiDefaultsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {

    if ($container->hasDefinition('jsonapi.entity_resource')) {
      /** @var \Symfony\Component\DependencyInjection\Definition $definition */
      $definition = $container->getDefinition('jsonapi.entity_resource');
      $definition->setClass('Drupal\jsonapi_defaults\Controller\EntityResource');
    }
  }

}
