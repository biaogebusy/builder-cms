<?php

declare(strict_types = 1);

namespace Drupal\sms\Event;

use Drupal\sms\Entity\SmsGatewayInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event fired to determine valid gateways for a recipient.
 */
class RecipientGatewayEvent extends Event {

  /**
   * The recipient phone number.
   *
   * @var string
   */
  protected string $recipient;

  /**
   * An array of gateway doubles.
   *
   * @var array
   *   The array of gateway/priority doubles where:
   *     - Key 0: SmsGatewayInterface $gateway
   *     - Key 1: int $priority
   */
  protected array $gateways = [];

  /**
   * Constructs the object.
   *
   * @param string $recipient
   *   The recipient phone number.
   */
  public function __construct($recipient) {
    $this->setRecipient($recipient);
  }

  /**
   * Get the phone number for this event.
   *
   * @return string
   *   The phone number for this event.
   */
  public function getRecipient() {
    return $this->recipient;
  }

  /**
   * Set the phone number for this event.
   *
   * @param string $recipient
   *   The phone number for this event.
   *
   * @return $this
   *   Return this event for chaining.
   */
  public function setRecipient($recipient) {
    $this->recipient = $recipient;
    return $this;
  }

  /**
   * Get the gateways for this event.
   *
   * @return array
   *   An array of doubles gateway/priority doubles.
   */
  public function getGateways() {
    return $this->gateways;
  }

  /**
   * Return gateways ordered by priority from highest to lowest.
   *
   * @return \Drupal\sms\Entity\SmsGatewayInterface[]
   *   An array of gateways ordered by priority from highest to lowest.
   */
  public function getGatewaysSorted() {
    $sorted = $this->gateways;
    uasort($sorted, static function ($a, $b) {
      [, $priority_a] = $a;
      [, $priority_b] = $b;
      if ($priority_a == $priority_b) {
        return 0;
      }
      return ($priority_a > $priority_b) ? -1 : 1;
    });

    // Return the gateway object instead of tuples.
    $gateways = [];
    foreach ($sorted as [$gateway]) {
      $gateways[] = $gateway;
    }

    return $gateways;
  }

  /**
   * Add a gateway for the recipient on this event.
   *
   * @param \Drupal\sms\Entity\SmsGatewayInterface $gateway
   *   The gateway for the recipient.
   * @param int $priority
   *   The priority for this gateway.
   *
   * @return $this
   *   Return this event for chaining.
   */
  public function addGateway(SmsGatewayInterface $gateway, $priority = 0) {
    $this->gateways[] = [$gateway, $priority];
    return $this;
  }

  /**
   * Remove a gateway from this event.
   *
   * @param string $gateway_id
   *   A gateway plugin ID.
   * @param int|null $priority
   *   The priority of the gateway to remove, or NULL to remove all gateways
   *   with the identifier.
   *
   * @return $this
   *   Return this event for chaining.
   */
  public function removeGateway($gateway_id, $priority = NULL) {
    foreach ($this->gateways as $k => [$gateway, $gateway_priority]) {
      if ($gateway_id == $gateway->id()) {
        if (!isset($priority) || ($priority == $gateway_priority)) {
          unset($this->gateways[$k]);
        }
      }
    }
    return $this;
  }

}
