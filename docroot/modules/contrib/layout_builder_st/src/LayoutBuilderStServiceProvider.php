<?php

namespace Drupal\layout_builder_st;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Overrides core services.
 */
class LayoutBuilderStServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');

    if (isset($modules['jsonapi'])) {
      $container
        ->getDefinition('jsonapi.resource_type.repository')
        ->setClass(ResourceTypeRepository::class);
    }
  }

}
