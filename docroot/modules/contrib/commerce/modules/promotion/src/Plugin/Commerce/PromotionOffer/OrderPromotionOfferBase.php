<?php

namespace Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the base class for order offers.
 */
abstract class OrderPromotionOfferBase extends PromotionOfferBase implements OrderPromotionOfferInterface {

  /**
   * The price splitter.
   *
   * @var \Drupal\commerce_order\PriceSplitterInterface
   */
  protected $splitter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->splitter = $container->get('commerce_order.price_splitter');

    return $instance;
  }

}
