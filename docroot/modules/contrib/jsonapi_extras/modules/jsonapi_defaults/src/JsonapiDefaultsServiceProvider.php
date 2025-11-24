<?php

namespace Drupal\jsonapi_defaults;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

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
      $definition->setClass('Drupal\jsonapi_defaults\Controller\EntityResource')
        ->addMethodCall('setJsonapiDefaults', [new Reference('jsonapi_defaults_includes')])
        ->addMethodCall('setLogger', [new Reference('logger.factory')]);
    }

    if ($container->has('cache_context.url.query_args')) {
      // Override the cache_context.url.query_args service.
      /** @var \Symfony\Component\DependencyInjection\Definition $definition */
      $definition = $container->getDefinition('cache_context.url.query_args');
      $definition->setClass(QueryArgsCacheContext::class)
        ->addArgument(new Reference('jsonapi_defaults_includes'));
    }
  }

}
