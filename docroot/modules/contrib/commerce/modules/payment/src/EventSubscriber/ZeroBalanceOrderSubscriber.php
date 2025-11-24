<?php

namespace Drupal\commerce_payment\EventSubscriber;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Event\FilterPaymentGatewaysEvent;
use Drupal\commerce_payment\Event\PaymentEvents;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsZeroBalanceOrderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Limits list of available payment gateways for free orders.
 *
 * This event engages only if order balance is zero.
 */
class ZeroBalanceOrderSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[PaymentEvents::FILTER_PAYMENT_GATEWAYS] = 'filterPaymentGateways';
    return $events;
  }

  /**
   * Removes unsupported payment gateways.
   *
   * @param \Drupal\commerce_payment\Event\FilterPaymentGatewaysEvent $event
   *   The filter payment gateways event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function filterPaymentGateways(FilterPaymentGatewaysEvent $event) {
    $order = $event->getOrder();
    // Return early if order balance is not zero.
    if (!$order->getBalance()?->isZero()) {
      return;
    }

    $gateways = $event->getPaymentGateways();
    $gateways = array_filter($gateways, function (PaymentGatewayInterface $gateway) {
      return $gateway->getPlugin() instanceof SupportsZeroBalanceOrderInterface;
    });
    $event->setPaymentGateways($gateways);
  }

}
