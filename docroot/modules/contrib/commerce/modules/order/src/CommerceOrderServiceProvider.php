<?php

namespace Drupal\commerce_order;

use Drupal\commerce_order\Normalizer\AdjustmentItemNormalizer;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\commerce_order\DependencyInjection\Compiler\PriceCalculatorPass;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the PriceCalculator compiler pass.
 */
class CommerceOrderServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $container->addCompilerPass(new PriceCalculatorPass());
    $modules = $container->getParameter('container.modules');
    if (isset($modules['serialization'])) {
      $container->register('commerce_order.normalizer.adjustment_item', AdjustmentItemNormalizer::class)
        ->addArgument(new Reference('commerce_price.currency_formatter'))
        ->addTag('normalizer', ['priority' => 20]);
    }
  }

}
