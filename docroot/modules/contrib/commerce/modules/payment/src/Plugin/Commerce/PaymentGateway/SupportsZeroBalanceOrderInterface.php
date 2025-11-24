<?php

namespace Drupal\commerce_payment\Plugin\Commerce\PaymentGateway;

/**
 * Defines if the gateway is able to store payment method on zero balance order.
 *
 * Payment gateways implementing this interface can be configured to allow the
 * collection and storage of payment methods even when the order total is zero.
 * This is particularly useful in scenarios where payment methods are required
 * for future transactions or for customer verification, despite the current
 * order having no monetary value.
 *
 * Implementing this interface allows the checkout process to present relevant
 * payment options and manage them accordingly, ensuring that even free orders
 * can follow the same payment method workflows as regular orders.
 *
 * @see \Drupal\commerce_payment\EventSubscriber\ZeroBalanceOrderSubscriber
 */
interface SupportsZeroBalanceOrderInterface extends SupportsStoredPaymentMethodsInterface {}
