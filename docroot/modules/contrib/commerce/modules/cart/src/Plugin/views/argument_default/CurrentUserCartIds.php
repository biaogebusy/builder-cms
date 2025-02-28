<?php

namespace Drupal\commerce_cart\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default argument plugin for the current user's carts.
 *
 * @ViewsArgumentDefault(
 *   id = "current_user_cart_ids",
 *   title = @Translation("Current user cart IDs")
 * )
 */
class CurrentUserCartIds extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->cartProvider = $container->get('commerce_cart.cart_provider');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['argument'] = [
      '#markup' => '<div class="description">' . $this->t("Provides the ID of the current user's first non-empty cart for the current store by default.") . '<br />' . $this->t("Check <em>Allow multiple values</em> under <em>More</em> to provide all of the matching cart IDs for the current store.") . '</div>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    // Note that we only need the order IDS here, but the cart provider loads
    // the orders even when getCartIds() is called.
    $carts = array_filter($this->cartProvider->getCarts(), function ($cart) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
      return $cart->hasItems();
    });
    return implode('+', array_keys($carts));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['cart'];
  }

}
