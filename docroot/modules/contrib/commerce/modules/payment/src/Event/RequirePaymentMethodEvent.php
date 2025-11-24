<?php

namespace Drupal\commerce_payment\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_order\Entity\OrderInterface;

class RequirePaymentMethodEvent extends EventBase {

  /**
   * Constructs a new RequirePaymentMethodEvent object.
   */
  public function __construct(
    protected OrderInterface $order,
    protected bool $required,
  ) {}

  /**
   * Gets the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  public function getOrder(): OrderInterface {
    return $this->order;
  }

  /**
   * Is payment method required.
   *
   * @return bool
   *   TRUE if payment method is required for the order, FALSE if not.
   */
  public function isRequired(): bool {
    return $this->required;
  }

  /**
   * Overwrite default value for the required flag.
   *
   * @param bool $required
   *   Determines whether payment method collection is required.
   *
   * @return static
   *   This instance.
   */
  public function setRequired(bool $required): static {
    $this->required = $required;
    return $this;
  }

}
