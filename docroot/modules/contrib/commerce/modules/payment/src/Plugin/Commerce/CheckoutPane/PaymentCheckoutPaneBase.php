<?php

namespace Drupal\commerce_payment\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_payment\Event\PaymentEvents;
use Drupal\commerce_payment\Event\RequirePaymentMethodEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class PaymentCheckoutPaneBase extends CheckoutPaneBase {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * Determines if only the billing profile should be collected.
   */
  protected function collectBillingProfileOnly(): bool {
    if ($this instanceof PaymentInformation) {
      $configuration = $this->getConfiguration();
    }
    else {
      $payment_info_pane = $this->checkoutFlow->getPane('payment_information');
      $configuration = $payment_info_pane->getConfiguration();
    }
    $event = new RequirePaymentMethodEvent($this->order,
      !empty($configuration['require_payment_method']));
    $this->eventDispatcher->dispatch($event, PaymentEvents::REQUIRE_PAYMENT_METHOD);
    return $this->order->getBalance()->isZero() && !$event->isRequired();
  }

}
