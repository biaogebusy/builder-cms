<?php

declare(strict_types=1);

namespace Drupal\private_message\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * The Private Message Ban entity interface.
 *
 * @ingroup private_message
 */
interface PrivateMessageBanInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Gets the Private Message Ban creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Private Message Ban.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the Private Message Ban creation timestamp.
   *
   * @param int $timestamp
   *   The Private Message Ban creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime(int $timestamp): self;

  /**
   * Gets banned user.
   */
  public function getTarget(): UserInterface;

  /**
   * Gets target id.
   */
  public function getTargetId(): int;

  /**
   * Sets banned user.
   */
  public function setTarget(UserInterface $user): self;

}
