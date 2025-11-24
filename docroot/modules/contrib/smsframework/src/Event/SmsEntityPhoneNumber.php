<?php

declare(strict_types = 1);

namespace Drupal\sms\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event fired when resolving phone numbers for an entity.
 *
 * @see \Drupal\sms\Event\SmsEvents
 */
class SmsEntityPhoneNumber extends Event {

  /**
   * An array of phone numbers.
   *
   * @var string[]
   */
  protected array $phoneNumbers = [];

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to find phone numbers.
   * @param bool|null $verified
   *   Whether the returned phone numbers must be verified, or NULL to get all
   *   phone numbers regardless of status.
   */
  public function __construct(
    protected EntityInterface $entity,
    protected ?bool $verified = TRUE,
  ) {
  }

  /**
   * Get entity to find phone numbers.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity to find phone numbers.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Get phone numbers with this verification state.
   *
   * @return bool|null
   *   Whether the returned phone numbers must be verified, or NULL to get all
   *   phone numbers regardless of status.
   */
  public function getRequiresVerification() {
    return $this->verified;
  }

  /**
   * Get phone number on this event.
   *
   * @return string[]
   *   The phone number on this event.
   */
  public function getPhoneNumbers() {
    return $this->phoneNumbers;
  }

  /**
   * Add phone number to this event.
   *
   * @param string $phone_number
   *   A phone number to add to this event.
   *
   * @return $this
   *   Return this event for chaining.
   */
  public function addPhoneNumber($phone_number) {
    if (!in_array($phone_number, $this->phoneNumbers)) {
      $this->phoneNumbers[] = $phone_number;
    }
    return $this;
  }

}
