<?php

namespace Drupal\commerce_payment\Exception;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;

/**
 * Base exception for all payment gateway errors.
 */
class PaymentGatewayException extends \RuntimeException {

  /**
   * @var \Drupal\commerce_payment\Entity\PaymentInterface
   */
  private ?PaymentInterface $payment = NULL;

  /**
   * @var \Drupal\commerce_payment\Entity\PaymentMethodInterface|null
   */
  private ?PaymentMethodInterface $paymentMethod = NULL;

  /**
   * @param \Drupal\commerce_payment\Entity\PaymentInterface|\Drupal\commerce_payment\Entity\PaymentMethodInterface|null $payment
   *   The payment or payment method entity.
   * @param string $message
   *   The exception message.
   * @param int $code
   *   The error code.
   * @param \Throwable $previous
   *   The previous exception.
   *
   * @return $this
   */
  public static function createForPayment(PaymentInterface|PaymentMethodInterface|null $payment, string $message, int $code = 0, ?\Throwable $previous = NULL): static {
    $e = new static($message, $code, $previous);
    if ($payment instanceof PaymentInterface) {
      $e->payment = $payment;
    }
    elseif ($payment instanceof PaymentMethodInterface) {
      $e->paymentMethod = $payment;
    }
    return $e;
  }

  /**
   * Gets the payment, if available.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface|null
   *   The payment, if available.
   */
  public function getPayment(): ?PaymentInterface {
    return $this->payment;
  }

  /**
   * Gets the payment method, if available.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentMethodInterface|null
   *   The payment method, if available.
   */
  public function getPaymentMethod(): ?PaymentMethodInterface {
    return $this->paymentMethod ?? $this->payment?->getPaymentMethod();
  }

}
