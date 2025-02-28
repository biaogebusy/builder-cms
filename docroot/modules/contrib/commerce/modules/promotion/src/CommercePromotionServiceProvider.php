<?php

namespace Drupal\commerce_promotion;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\commerce_promotion\EventSubscriber\CartEventSubscriber;

/**
 * Registers event subscribers for installed Commerce modules.
 */
class CommercePromotionServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // We cannot use the module handler as the container is not yet compiled.
    // @see \Drupal\Core\DrupalKernel::compileContainer()
    $modules = $container->getParameter('container.modules');

    if (isset($modules['commerce_cart'])) {
      $container->register('commerce_promotion.cart_subscriber', CartEventSubscriber::class)
        ->addTag('event_subscriber');
    }
  }

}
