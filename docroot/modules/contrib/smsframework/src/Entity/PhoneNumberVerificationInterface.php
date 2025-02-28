<?php

declare(strict_types = 1);

namespace Drupal\sms\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for phone number verification entity.
 */
interface PhoneNumberVerificationInterface extends ContentEntityInterface {

  /**
   * Gets the phone number verification creation timestamp.
   *
   * @return int
   *   Creation timestamp of the phone number verification.
   */
  public function getCreatedTime();

  /**
   * Gets the entity for the phone number verification.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity for the phone number verification, or NULL if it is missing.
   */
  public function getEntity();

  /**
   * Sets the entity for the phone number verification.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for the phone number verification.
   *
   * @return $this
   *   Return phone number verification for chaining.
   */
  public function setEntity(EntityInterface $entity);

  /**
   * Gets the phone number for the phone number verification.
   *
   * @return string
   *   The phone number for the phone number verification.
   */
  public function getPhoneNumber();

  /**
   * Sets the phone number for the phone number verification.
   *
   * @param string $phone_number
   *   The phone number for the phone number verification.
   *
   * @return $this
   *   Return phone number verification for chaining.
   */
  public function setPhoneNumber($phone_number);

  /**
   * Gets the code for the phone number verification.
   *
   * @return string
   *   The code for the phone number verification.
   */
  public function getCode();

  /**
   * Sets the code for the phone number verification.
   *
   * @param string $code
   *   The code for the phone number verification.
   *
   * @return $this
   *   Return phone number verification for chaining.
   */
  public function setCode($code);

  /**
   * Gets the status for the phone number verification.
   *
   * Default value is FALSE (not verified).
   *
   * @return bool
   *   Whether the phone number is verified.
   */
  public function getStatus();

  /**
   * Sets the status for the phone number verification.
   *
   * @param bool $status
   *   Whether the phone number is verified.
   *
   * @return $this
   *   Return phone number verification for chaining.
   */
  public function setStatus($status);

}
