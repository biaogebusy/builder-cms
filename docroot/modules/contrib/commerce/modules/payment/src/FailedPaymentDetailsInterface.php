<?php

namespace Drupal\commerce_payment;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeInterface;

/**
 * Defines an interface to report additional details for failed payments.
 *
 * Failed payments are not saved therefore the best we can do is to add data
 * to the commerce log entry for a failed payment.
 */
interface FailedPaymentDetailsInterface extends PaymentMethodTypeInterface {

  /**
   * Gets additional data to log for failed payments.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method to get failed payment details for.
   *
   * @return array
   *   Parameters to add to the commerce log entry for the failed payment. Keys
   *   should be strings and the values can be anything that is useful for
   *   reporting on. Note: the keys should not match the default keys set in
   *   PaymentEventSubscriber::onPaymentFailure() as the values assigned there
   *   will override values set in this array.
   *
   * @see \Drupal\commerce_log\EventSubscriber\PaymentEventSubscriber::onPaymentFailure
   */
  public function failedPaymentDetails(PaymentMethodInterface $payment_method): array;

}
