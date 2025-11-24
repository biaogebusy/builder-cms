<?php

namespace Drupal\commerce_checkout;

use Drupal\commerce_checkout\Resolver\ChainCheckoutFlowResolverInterface;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Manages checkout flows for orders.
 */
class CheckoutOrderManager implements CheckoutOrderManagerInterface {

  /**
   * Constructs a new CheckoutOrderManager object.
   *
   * @param \Drupal\commerce_checkout\Resolver\ChainCheckoutFlowResolverInterface $chainCheckoutFlowResolver
   *   The chain checkout flow resolver.
   */
  public function __construct(protected ChainCheckoutFlowResolverInterface $chainCheckoutFlowResolver) {}

  /**
   * {@inheritdoc}
   */
  public function getCheckoutFlow(OrderInterface $order) {
    if (!$order->get('checkout_flow')->entity) {
      $checkout_flow = $this->chainCheckoutFlowResolver->resolve($order);
      $order->set('checkout_flow', $checkout_flow);
      $order->save();
    }

    // Set the order on the checkout flow's checkout flow plugin.
    $order->get('checkout_flow')->entity->getPlugin()->setOrder($order);

    return $order->get('checkout_flow')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getCheckoutStepId(OrderInterface $order, $requested_step_id = NULL) {
    // Customers can't edit orders that have already been placed.
    $checkout_flow_plugin = $this->getCheckoutFlow($order)->getPlugin();
    // Allow checkout flow plugins to control the step.
    if ($plugin_step_id = $checkout_flow_plugin->getStepId($requested_step_id)) {
      return $plugin_step_id;
    }

    $available_step_ids = array_keys($checkout_flow_plugin->getVisibleSteps());
    $selected_step_id = $order->get('checkout_step')->value;
    $selected_step_id = $selected_step_id ?: reset($available_step_ids);
    if (empty($requested_step_id) || $requested_step_id == $selected_step_id) {
      return $selected_step_id;
    }

    if (in_array($requested_step_id, $available_step_ids)) {
      // Allow access to a previously completed step.
      $requested_step_index = array_search($requested_step_id, $available_step_ids);
      $selected_step_index = array_search($selected_step_id, $available_step_ids);
      if ($requested_step_index <= $selected_step_index) {
        $selected_step_id = $requested_step_id;
      }
    }

    return $selected_step_id;
  }

}
