<?php

namespace Drupal\flood_control;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Defines a service provider for the Flood Control module.
 */
class FloodControlServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('flood_control.flood_unblock_manager')) {
      // Repeatedly get 'You have requested a non-existent parameter
      // "flood_control.flood_unblock_manager"' when trying to set a new
      // definition vs altering the existing one.
      $container->getDefinition('flood_control.flood_unblock_manager')
        ->setClass(FloodUnblockManagerDatabase::class)
        ->setArguments([
          new Reference('database'),
          new Reference('flood'),
          new Reference('config.factory'),
          new Reference('entity_type.manager'),
          new Reference('messenger'),
          new Reference('logger.factory'),
        ]
        );
    }
  }

}
