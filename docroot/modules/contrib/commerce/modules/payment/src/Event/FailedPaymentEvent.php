<?php

namespace Drupal\commerce_payment\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;

/**
 * Defines the event for failed payment.
 *
 * @see \Drupal\commerce_payment\Event\PaymentEvents
 */
class FailedPaymentEvent extends EventBase {

  /**
   * @var \Drupal\commerce_payment\Entity\PaymentMethodInterface|null
   */
  protected ?PaymentMethodInterface $paymentMethod = NULL;

  /**
   * Constructs a new FailedPaymentEvent.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $paymentGateway
   *   The payment gateway.
   * @param \Drupal\commerce_payment\Exception\PaymentGatewayException $gatewayException
   *   The payment gateway exception.
   * @param \Drupal\commerce_payment\Entity\PaymentInterface|null $payment
   *   The payment.
   */
  public function __construct(
    protected OrderInterface $order,
    protected PaymentGatewayInterface $paymentGateway,
    protected PaymentGatewayException $gatewayException,
    protected ?PaymentInterface $payment = NULL,
  ) {

  }

  /**
   * Gets the order.
   */
  public function getOrder(): OrderInterface {
    return $this->order;
  }

  /**
   * Gets the payment gateway.
   */
  public function getPaymentGateway(): PaymentGatewayInterface {
    return $this->paymentGateway;
  }

  /**
   * Gets the payment gateway exception.
   */
  public function getGatewayException(): PaymentGatewayException {
    return $this->gatewayException;
  }

  /**
   * Gets the payment.
   */
  public function getPayment(): ?PaymentInterface {
    return $this->payment ?? $this->gatewayException->getPayment();
  }

  /**
   * Gets the payment method.
   */
  public function getPaymentMethod(): ?PaymentMethodInterface {
    return $this->paymentMethod ?? $this->payment?->getPaymentMethod() ?? $this->gatewayException->getPaymentMethod();
  }

  /**
   * Sets the payment method.
   */
  public function setPaymentMethod(PaymentMethodInterface $payment_method): self {
    $this->paymentMethod = $payment_method;
    return $this;
  }

}
