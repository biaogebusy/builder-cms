<?php

namespace Drupal\commerce_payment\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_order\Entity\OrderInterface;

class FilterPaymentOptionsEvent extends EventBase {

  /**
   * Constructs a new FilterPaymentOptionsEvent object.
   */
  public function __construct(protected array $paymentOptions, protected OrderInterface $order) {}

  /**
   * Gets the payment options.
   *
   * @return \Drupal\commerce_payment\PaymentOption[]
   *   The payment options.
   */
  public function getPaymentOptions(): array {
    return $this->paymentOptions;
  }

  /**
   * Sets the payment options.
   *
   * @param \Drupal\commerce_payment\PaymentOption[] $payment_options
   *   The payment options.
   *
   * @return $this
   */
  public function setPaymentOptions(array $payment_options): static {
    $this->paymentOptions = $payment_options;
    return $this;
  }

  /**
   * Gets the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  public function getOrder(): OrderInterface {
    return $this->order;
  }

}
